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

use block_eledia_aitutor\local\chat_service;
use block_eledia_aitutor\local\consent;
use block_eledia_aitutor\local\conversation_repository;
use block_eledia_aitutor\local\rag_client;
use moodle_url;

/**
 * Integration tests for the chat orchestration, including real token
 * provisioning through webservice_elediamcp.
 *
 * @package     block_eledia_aitutor
 * @covers      \block_eledia_aitutor\local\chat_service
 * @covers      \block_eledia_aitutor\local\token_provider
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chat_service_test extends \advanced_testcase {
    /**
     * Load the fake transport.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/eledia_aitutor/tests/fake_transport.php');
        parent::setUpBeforeClass();
    }

    /**
     * Configure an MCP service and point the block at it.
     *
     * @return int The external service id.
     */
    private function configure_mcp_service(): int {
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

        return (int) $service->id;
    }

    /**
     * Create a user who has already passed the first-use consent gate.
     *
     * @return \stdClass The user record.
     */
    private function create_consented_user(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        consent::give((int) $user->id, \core\context\system::instance());
        return $user;
    }

    /**
     * Without a documented consent record no message leaves Moodle.
     */
    public function test_send_requires_consent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'never sent']]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        try {
            chat_service::send((int) $user->id, 'Hi tutor', null, null, \core\context\system::instance(), $client);
            $this->fail('Expected the consent gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_consentrequired', $e->errorcode);
        }
        // The RAG server was never contacted.
        $this->assertNull($transport->lastbody);
    }

    /**
     * In LLM-only mode, sources returned by a non-compliant server are
     * stripped: no grounded badge, analytics record grounded=false.
     */
    public function test_send_llmonly_strips_sources(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_mcp_service();
        set_config('enableanalytics', 1, 'block_eledia_aitutor');
        $user = $this->create_consented_user();

        // A server that (wrongly) returns sources despite rag_enabled=false.
        $transport = fake_transport::json_result([
            'structuredContent' => [
                'answer' => 'general answer',
                'sources' => [['title' => 'Should not appear', 'url' => '', 'snippet' => '']],
            ],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $result = chat_service::send(
            (int) $user->id,
            'Q?',
            5,
            null,
            \core\context\system::instance(),
            $client,
            null,
            null,
            false
        );

        // The flag was sent, and the sources were dropped.
        $this->assertFalse($transport->last_payload()['params']['arguments']['rag_enabled']);
        $this->assertSame([], $result['sources']);

        // Analytics records the turn as ungrounded.
        $rows = array_values($DB->get_records('block_eledia_aitutor_qlog'));
        $this->assertCount(1, $rows);
        $this->assertEquals(0, $rows[0]->grounded);
    }

    /**
     * The daily quota blocks the turn at the limit; only successful turns
     * consume quota.
     */
    public function test_send_enforces_daily_quota(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->create_consented_user();
        $uid = (int) $user->id;

        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'ok']]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        // Limit 2: two turns pass and are counted, the third is refused.
        chat_service::send($uid, 'one', null, null, \core\context\system::instance(), $client, null, 2);
        chat_service::send($uid, 'two', null, null, \core\context\system::instance(), $client, null, 2);
        $this->assertSame(2, \block_eledia_aitutor\local\usage::count_today($uid));

        try {
            chat_service::send($uid, 'three', null, null, \core\context\system::instance(), $client, null, 2);
            $this->fail('Expected the quota gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_quota_exceeded', $e->errorcode);
        }

        // Limit 0 = unlimited.
        chat_service::send($uid, 'four', null, null, \core\context\system::instance(), $client, null, 0);
        $this->assertSame(3, \block_eledia_aitutor\local\usage::count_today($uid));
    }

    /**
     * A full chat turn provisions a token, calls the RAG server and persists
     * the conversation pointer.
     */
    public function test_send_provisions_token_and_persists_conversation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_mcp_service();

        $user = $this->create_consented_user();

        $transport = fake_transport::json_result([
            'structuredContent' => ['answer' => 'Hello **world**', 'conversation_id' => 'conv-77'],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $result = chat_service::send((int) $user->id, 'Hi tutor', null, null, \core\context\system::instance(), $client);

        // Answer is rendered to safe HTML.
        $this->assertStringContainsString('<strong>world</strong>', $result['answerhtml']);
        $this->assertSame('conv-77', $result['conversationid']);

        // A user-scoped MCP token was provisioned by the connector.
        $token = $transport->last_payload()['params']['arguments']['moodle_token'];
        $this->assertNotEmpty($token);
        $this->assertTrue($DB->record_exists('webservice_elediamcp_token', [
            'userid' => $user->id,
            'component' => 'block_eledia_aitutor',
        ]));

        // The conversation pointer was stored for the owner.
        $rows = conversation_repository::list_for_user((int) $user->id);
        $this->assertCount(1, $rows);
        $this->assertSame('conv-77', $rows[0]->conversationid);
    }

    /**
     * With analytics enabled, a chat turn logs the question (grounded + style),
     * and the chat payload carries the style and the user language.
     */
    public function test_send_logs_question_analytics(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_mcp_service();
        set_config('enableanalytics', 1, 'block_eledia_aitutor');

        $user = $this->create_consented_user();

        global $CFG;
        $transport = fake_transport::json_result([
            'structuredContent' => [
                'answer' => 'cited answer',
                'topic' => 'Assignments & deadlines',
                'sources' => [[
                    'title' => 'Essay 2',
                    'url' => $CFG->wwwroot . '/mod/assign/view.php?id=99',
                    'snippet' => 'Due Friday',
                ]],
            ],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        chat_service::send(
            (int) $user->id,
            'What is due?',
            42,
            null,
            \core\context\system::instance(),
            $client,
            'hint'
        );

        $args = $transport->last_payload()['params']['arguments'];
        $this->assertSame('hint', $args['answer_style']);
        $this->assertNotEmpty($args['user_lang']);

        $rows = array_values($DB->get_records('block_eledia_aitutor_qlog'));
        $this->assertCount(1, $rows);
        $this->assertSame('What is due?', $rows[0]->question);
        $this->assertEquals(42, $rows[0]->courseid);
        $this->assertEquals(1, $rows[0]->grounded);
        $this->assertSame('hint', $rows[0]->answerstyle);
        // The clustering anchors are persisted: topic, primary source, cmid.
        $this->assertSame('Assignments & deadlines', $rows[0]->topic);
        $this->assertSame('Essay 2', $rows[0]->sourcetitle);
        $this->assertEquals(99, $rows[0]->cmid);

        // Disabled analytics logs nothing.
        set_config('enableanalytics', 0, 'block_eledia_aitutor');
        chat_service::send((int) $user->id, 'Another?', 42, null, \core\context\system::instance(), $client);
        $this->assertSame(1, $DB->count_records('block_eledia_aitutor_qlog'));
    }

    /**
     * Message-sent and response-received events are fired.
     */
    public function test_send_fires_events(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->create_consented_user();

        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'ok']]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $sink = $this->redirectEvents();
        chat_service::send((int) $user->id, 'Question?', null, null, \core\context\system::instance(), $client);
        $classes = array_map('get_class', $sink->get_events());

        $this->assertContains(\block_eledia_aitutor\event\message_sent::class, $classes);
        $this->assertContains(\block_eledia_aitutor\event\response_received::class, $classes);
    }

    /**
     * A failing RAG call is retried once with a fresh token, then surfaces a
     * rag_request_failed event and rethrows.
     */
    public function test_send_logs_failure_and_throws(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->create_consented_user();

        $transport = new fake_transport([
            'status' => 502, 'headers' => ['content-type' => 'text/plain'], 'body' => 'nope', 'error' => '',
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $sink = $this->redirectEvents();
        try {
            chat_service::send((int) $user->id, 'Question?', null, null, \core\context\system::instance(), $client);
            $this->fail('Expected a rag_exception.');
        } catch (\block_eledia_aitutor\local\rag_exception $e) {
            $classes = array_map('get_class', $sink->get_events());
            $this->assertContains(\block_eledia_aitutor\event\rag_request_failed::class, $classes);
        }
    }
}
