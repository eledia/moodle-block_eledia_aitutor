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
use block_eledia_aitutor\local\conversation_repository;
use block_eledia_aitutor\local\deletion_service;
use block_eledia_aitutor\local\rag_client;
use moodle_url;

/**
 * Unit tests for the user data deletion service.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_eledia_aitutor\local\deletion_service::class)]
final class deletion_service_test extends \advanced_testcase {
    /**
     * Load the fake transport helper.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/eledia_aitutor/tests/fake_transport.php');
        parent::setUpBeforeClass();
    }

    /**
     * Without a configured delete tool, local data is erased, external deletion
     * is honestly reported as unsupported, and other users are untouched.
     */
    public function test_local_only_deletion(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $alice->id, 'a-1', null, 'one');
        conversation_repository::upsert((int) $alice->id, 'a-2', 5, 'two');
        conversation_repository::upsert((int) $bob->id, 'b-1', null, 'bob');

        $sink = $this->redirectEvents();
        $result = deletion_service::delete_all_for_user((int) $alice->id, \core\context\system::instance());

        $this->assertSame(2, $result['localdeleted']);
        $this->assertFalse($result['externalsupported']);
        $this->assertSame(0, $result['externaldeleted']);
        $this->assertCount(0, conversation_repository::list_for_user((int) $alice->id));
        $this->assertCount(1, conversation_repository::list_for_user((int) $bob->id));

        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \block_eledia_aitutor\event\data_deletion_requested
        );
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals(2, $event->other['localdeleted']);
        $this->assertEquals(0, $event->other['externalsupported']);
    }

    /**
     * Deleting with no data at all still succeeds and reports zero.
     */
    public function test_deletion_with_no_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance());

        $this->assertSame(0, $result['localdeleted']);
        $this->assertSame(0, $result['externaldeleted']);
        $this->assertSame(0, $result['externalfailed']);
    }

    /**
     * With a delete tool configured and a working client, deletion is propagated
     * per conversation before the local erase.
     */
    public function test_external_deletion_with_injected_client(): void {
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        // Configure an MCP service so the token provider can mint a real token.
        global $DB;
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_eledia_aitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        set_config('deletetoolname', 'tutor_delete_conversation', 'block_eledia_aitutor');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        conversation_repository::upsert((int) $user->id, 'c-1', null, 'one');
        conversation_repository::upsert((int) $user->id, 'c-2', null, 'two');

        $transport = fake_transport::json_result(['structuredContent' => ['deleted' => true]]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance(), $client);

        $this->assertTrue($result['externalsupported']);
        $this->assertSame(2, $result['externaldeleted']);
        $this->assertSame(0, $result['externalfailed']);
        $this->assertSame(2, $result['localdeleted']);
        $this->assertCount(0, conversation_repository::list_for_user((int) $user->id));
    }

    /**
     * The user-level delete tool is preferred over per-conversation deletion:
     * exactly one call, even with multiple conversations, and it is attempted
     * even when Moodle holds no local pointers.
     */
    public function test_user_level_delete_tool_preferred(): void {
        $this->resetAfterTest();

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        global $DB;
        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_eledia_aitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        // Both tools configured: the user-level one must win.
        set_config('deletetoolname', 'tutor_delete_conversation', 'block_eledia_aitutor');
        set_config('deleteusertoolname', 'tutor_delete_user_data', 'block_eledia_aitutor');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        conversation_repository::upsert((int) $user->id, 'c-1', null, 'one');
        conversation_repository::upsert((int) $user->id, 'c-2', null, 'two');

        $transport = fake_transport::json_result(['structuredContent' => ['deleted' => true]]);
        $client = new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance(), $client);

        $this->assertTrue($result['externalsupported']);
        $this->assertSame(1, $result['externaldeleted']);
        $this->assertSame(2, $result['localdeleted']);
        // The single call was the user-level tool, not a per-conversation delete.
        $payload = $transport->last_payload();
        $this->assertSame('tutor_delete_user_data', $payload['params']['name']);
        $this->assertArrayNotHasKey('conversation_id', $payload['params']['arguments']);

        // And with zero local records it is still attempted.
        $transport->lastbody = null;
        $result = deletion_service::delete_all_for_user((int) $user->id, \core\context\system::instance(), $client);
        $this->assertSame(0, $result['localdeleted']);
        $this->assertSame(1, $result['externaldeleted']);
        $this->assertSame('tutor_delete_user_data', $transport->last_payload()['params']['name']);
    }
}
