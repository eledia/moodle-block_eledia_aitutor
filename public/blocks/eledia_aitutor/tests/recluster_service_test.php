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
use block_eledia_aitutor\local\question_log;
use block_eledia_aitutor\local\rag_client;
use block_eledia_aitutor\local\recluster_service;
use block_eledia_aitutor\local\service_user;
use moodle_url;

/**
 * Unit tests for the batch topic reclustering service.
 *
 * Mostly connector-independent: the maintenance token is injected so these
 * tests run without webservice_elediamcp installed; only the end-to-end token
 * minting test requires the connector (and skips without it).
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_eledia_aitutor\local\recluster_service::class)]
#[CoversClass(\block_eledia_aitutor\local\service_user::class)]
#[CoversClass(\block_eledia_aitutor\task\recluster_questions::class)]
final class recluster_service_test extends \advanced_testcase {
    /**
     * Load the fake transport helper.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/eledia_aitutor/tests/fake_transport.php');
        parent::setUpBeforeClass();
    }

    /**
     * Without configuration the run is a strict no-op.
     */
    public function test_unconfigured_is_noop(): void {
        $this->resetAfterTest();
        $this->assertFalse(recluster_service::is_configured());

        $stats = recluster_service::run();
        $this->assertSame(['courses' => 0, 'batches' => 0, 'updated' => 0, 'failed' => 0], $stats);
    }

    /**
     * The maintenance account is auto-created once and reused, with
     * webservice-only auth and no interactive login.
     */
    public function test_service_user_autocreated_and_reused(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertFalse($DB->record_exists('user', ['username' => service_user::USERNAME]));

        $first = service_user::get_or_create();
        $second = service_user::get_or_create();

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame('webservice', $first->auth);
        $this->assertEquals(1, $first->confirmed);
        $this->assertEquals(0, $first->suspended);
        $this->assertSame(1, $DB->count_records('user', ['username' => service_user::USERNAME]));
    }

    /**
     * A configured run sends the batch (with the existing label registry and
     * the maintenance token) and applies the returned labels.
     */
    public function test_run_converges_labels(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_eledia_aitutor');
        set_config('reclustertoolname', 'tutor_recluster_questions', 'block_eledia_aitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');

        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;
        // Two phrasings without a topic, one with a drifted label.
        question_log::log($uid, 7, 'When is the essay due?', true, null, null, 'Essay 2', null);
        question_log::log($uid, 7, 'essay deadline?', true, null, null, null, null);
        question_log::log($uid, 7, 'wann ist der essay fällig', true, null, 'Essay deadline (old)', null, null);
        $ids = array_map('intval', array_keys($DB->get_records(question_log::TABLE, [], 'id ASC', 'id')));

        $transport = fake_transport::json_result([
            'structuredContent' => ['topics' => [
                ['id' => $ids[0], 'topic' => 'Assignments & deadlines'],
                ['id' => $ids[1], 'topic' => 'Assignments & deadlines'],
                ['id' => $ids[2], 'topic' => 'Assignments & deadlines'],
            ]],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $this->assertTrue(recluster_service::is_configured());
        $stats = recluster_service::run($client, 'MAINT-TOKEN');

        $this->assertSame(1, $stats['courses']);
        $this->assertSame(1, $stats['batches']);
        $this->assertSame(3, $stats['updated']);
        $this->assertSame(0, $stats['failed']);

        // The request carried the registry and the maintenance token.
        $args = $transport->last_payload()['params']['arguments'];
        $this->assertSame('MAINT-TOKEN', $args['moodle_token']);
        $this->assertSame(['Essay deadline (old)'], $args['existing_labels']);
        $this->assertCount(3, $args['questions']);

        // All three rows converged to one label → a single hotspot.
        $hotspots = question_log::hotspots(7);
        $this->assertCount(1, $hotspots);
        $this->assertSame('Assignments & deadlines', $hotspots[0]->label);
        $this->assertSame(3, $hotspots[0]->count);
    }

    /**
     * Without an injected token, run() auto-provisions the maintenance account
     * and mints a real component token for it (requires the connector).
     */
    public function test_run_mints_maintenance_token(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }
        require_once($CFG->dirroot . '/webservice/lib.php');
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_eledia_aitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        set_config('enableanalytics', 1, 'block_eledia_aitutor');
        set_config('reclustertoolname', 'tutor_recluster_questions', 'block_eledia_aitutor');

        $user = $this->getDataGenerator()->create_user();
        question_log::log((int) $user->id, 7, 'a question', true, null);
        $ids = array_keys($DB->get_records(question_log::TABLE, [], 'id ASC', 'id'));

        $transport = fake_transport::json_result([
            'structuredContent' => ['topics' => [['id' => (int) $ids[0], 'topic' => 'General']]],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $stats = recluster_service::run($client);

        $this->assertSame(1, $stats['updated']);
        // The payload carried a real token belonging to the maintenance account.
        $sent = $transport->last_payload()['params']['arguments']['moodle_token'];
        $this->assertNotEmpty($sent);
        $serviceuser = service_user::get_or_create();
        $this->assertTrue($DB->record_exists('webservice_elediamcp_token', [
            'userid' => $serviceuser->id,
            'component' => 'block_eledia_aitutor',
        ]));
    }

    /**
     * A failing batch is counted and skips the course without aborting the run.
     */
    public function test_failed_batch_is_contained(): void {
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_eledia_aitutor');
        set_config('reclustertoolname', 'tutor_recluster_questions', 'block_eledia_aitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');

        $user = $this->getDataGenerator()->create_user();
        question_log::log((int) $user->id, 7, 'a question', true, null);

        $transport = new fake_transport([
            'status' => 502, 'headers' => ['content-type' => 'text/plain'], 'body' => 'nope', 'error' => '',
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $stats = recluster_service::run($client, 'MAINT-TOKEN');
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);

        $this->assertSame(1, $stats['courses']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame(1, $stats['failed']);
    }

    /**
     * The scheduled task wires through and reports.
     */
    public function test_task_runs(): void {
        $this->resetAfterTest();
        $task = new \block_eledia_aitutor\task\recluster_questions();
        ob_start();
        $task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('not configured', $output);
    }
}
