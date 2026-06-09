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

namespace block_elediaaitutor;

use block_elediaaitutor\local\chat_service;
use block_elediaaitutor\local\conversation_repository;
use block_elediaaitutor\local\rag_client;
use context_system;
use moodle_url;

/**
 * Integration tests for the chat orchestration, including real token
 * provisioning through webservice_elediamcp.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\chat_service
 * @covers      \block_elediaaitutor\local\token_provider
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
        require_once($CFG->dirroot . '/blocks/elediaaitutor/tests/fake_transport.php');
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
        set_config('mcpserviceid', $service->id, 'block_elediaaitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaaitutor');
        set_config('chattoolname', 'tutor_chat', 'block_elediaaitutor');

        return (int) $service->id;
    }

    /**
     * A full chat turn provisions a token, calls the RAG server and persists
     * the conversation pointer.
     */
    public function test_send_provisions_token_and_persists_conversation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_mcp_service();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $transport = fake_transport::json_result([
            'structuredContent' => ['answer' => 'Hello **world**', 'conversation_id' => 'conv-77'],
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $result = chat_service::send((int) $user->id, 'Hi tutor', null, null, context_system::instance(), $client);

        // Answer is rendered to safe HTML.
        $this->assertStringContainsString('<strong>world</strong>', $result['answerhtml']);
        $this->assertSame('conv-77', $result['conversationid']);

        // A user-scoped MCP token was provisioned by the connector.
        $token = $transport->last_payload()['params']['arguments']['moodle_token'];
        $this->assertNotEmpty($token);
        $this->assertTrue($DB->record_exists('webservice_elediamcp_token', [
            'userid' => $user->id,
            'component' => 'block_elediaaitutor',
        ]));

        // The conversation pointer was stored for the owner.
        $rows = conversation_repository::list_for_user((int) $user->id);
        $this->assertCount(1, $rows);
        $this->assertSame('conv-77', $rows[0]->conversationid);
    }

    /**
     * Message-sent and response-received events are fired.
     */
    public function test_send_fires_events(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'ok']]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $sink = $this->redirectEvents();
        chat_service::send((int) $user->id, 'Question?', null, null, context_system::instance(), $client);
        $classes = array_map('get_class', $sink->get_events());

        $this->assertContains(\block_elediaaitutor\event\message_sent::class, $classes);
        $this->assertContains(\block_elediaaitutor\event\response_received::class, $classes);
    }

    /**
     * A failing RAG call is retried once with a fresh token, then surfaces a
     * rag_request_failed event and rethrows.
     */
    public function test_send_logs_failure_and_throws(): void {
        $this->resetAfterTest();
        $this->configure_mcp_service();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $transport = new fake_transport([
            'status' => 502, 'headers' => ['content-type' => 'text/plain'], 'body' => 'nope', 'error' => '',
        ]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $sink = $this->redirectEvents();
        try {
            chat_service::send((int) $user->id, 'Question?', null, null, context_system::instance(), $client);
            $this->fail('Expected a rag_exception.');
        } catch (\block_elediaaitutor\local\rag_exception $e) {
            $classes = array_map('get_class', $sink->get_events());
            $this->assertContains(\block_elediaaitutor\event\rag_request_failed::class, $classes);
        }
    }
}
