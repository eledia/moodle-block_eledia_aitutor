<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace block_eledia_aitutor;

use PHPUnit\Framework\Attributes\CoversClass;
use block_eledia_aitutor\local\consent;
use block_eledia_aitutor\local\copilot_service;
use block_eledia_aitutor\local\question_log;
use block_eledia_aitutor\local\rag_client;
use moodle_url;

/**
 * Tests for the Teacher-Copilot analysis service.
 *
 * @package     block_eledia_aitutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_eledia_aitutor\local\copilot_service::class)]
final class copilot_service_test extends \advanced_testcase {
    /**
     * Load the fake transport.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/eledia_aitutor/tests/fake_transport.php');
        parent::setUpBeforeClass();
    }

    /**
     * Configure an MCP service and point the block at it (token provisioning).
     *
     * @return void
     */
    private function configure_mcp_service(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/webservice/lib.php');

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        $service = (object) [
            'name' => 'MCP test service',
            'shortname' => 'mcptest',
            'enabled' => 1,
            'restrictedusers' => 0,
            'downloadfiles' => 0,
            'uploadfiles' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);

        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_eledia_aitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        set_config('chattoolname', 'tutor_chat', 'block_eledia_aitutor');
        set_config('enableanalytics', 1, 'block_eledia_aitutor');
    }

    /**
     * Course + consented teacher + a few logged questions.
     *
     * @return array{course: \stdClass, teacher: \stdClass, context: \core\context\course}
     */
    private function seed_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $context = \core\context\course::instance($course->id);
        consent::give((int) $teacher->id, $context);

        $student = $this->getDataGenerator()->create_user();
        question_log::log(
            (int) $student->id,
            (int) $course->id,
            'What is a normal distribution?',
            true,
            'explain',
            'Statistics basics'
        );
        question_log::log(
            (int) $student->id,
            (int) $course->id,
            'How do I compute the variance?',
            true,
            'explain',
            'Statistics basics'
        );
        question_log::log((int) $student->id, (int) $course->id, 'When is the essay due?', false);

        return ['course' => $course, 'teacher' => $teacher, 'context' => $context];
    }

    /**
     * The analysis prompt carries the hotspots and sampled questions, the
     * answer comes back rendered, and nothing new is persisted in Moodle.
     */
    public function test_analyse_builds_prompt_and_persists_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_mcp_service();
        ['course' => $course, 'teacher' => $teacher, 'context' => $context] = $this->seed_course();

        $qlogbefore = $DB->count_records('block_eledia_aitutor_qlog');

        $transport = fake_transport::json_result([
            'structuredContent' => [
                'answer' => "## Analyse\n\nDie Lernenden verwechseln Varianz und Standardabweichung.",
                'conversation_id' => 'server-conv-999',
            ],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $result = copilot_service::analyse((int) $course->id, (int) $teacher->id, $context, $client);

        // The prompt contains the hotspot label and a sampled question.
        $arguments = $transport->last_payload()['params']['arguments'];
        $this->assertStringContainsString('Statistics basics', $arguments['user_message']);
        $this->assertStringContainsString('What is a normal distribution?', $arguments['user_message']);
        // Grounded call in the course context with a user token.
        $this->assertTrue($arguments['rag_enabled']);
        $this->assertSame((string) $course->id, $arguments['course_id']);
        $this->assertNotEmpty($arguments['moodle_token']);

        // Markdown was rendered to HTML.
        $this->assertFalse($result['iserror']);
        $this->assertStringContainsString('<h2', $result['analysishtml']);
        $this->assertStringContainsString('Standardabweichung', $result['analysishtml']);

        // No new question-log rows, no conversation pointer stored.
        $this->assertSame($qlogbefore, $DB->count_records('block_eledia_aitutor_qlog'));
        $this->assertSame(0, $DB->count_records('block_eledia_aitutor_conv'));
    }

    /**
     * Without a consent record the analysis never leaves Moodle.
     */
    public function test_analyse_requires_consent(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $context = \core\context\course::instance($course->id);

        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'never sent']]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        try {
            copilot_service::analyse((int) $course->id, (int) $teacher->id, $context, $client);
            $this->fail('Expected the consent gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_consentrequired', $e->errorcode);
        }
        $this->assertNull($transport->lastbody);
    }

    /**
     * A course without logged questions yields the no-data error before any
     * RAG contact.
     */
    public function test_analyse_requires_data(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $context = \core\context\course::instance($course->id);
        consent::give((int) $teacher->id, $context);

        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'never sent']]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        try {
            copilot_service::analyse((int) $course->id, (int) $teacher->id, $context, $client);
            $this->fail('Expected the no-data gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('copilot_nodata', $e->errorcode);
        }
        $this->assertNull($transport->lastbody);
    }
}
