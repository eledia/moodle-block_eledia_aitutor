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

use block_elediaaitutor\local\ltm;

/**
 * Unit tests for the long-term memory opt-in helper.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\ltm
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ltm_test extends \advanced_testcase {
    /**
     * Long-term memory must be disabled unless the user explicitly opted in.
     */
    public function test_disabled_by_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(ltm::is_enabled((int) $user->id));
    }

    /**
     * Opting in and out round-trips through the user preference.
     */
    public function test_set_enabled_roundtrip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        ltm::set_enabled((int) $user->id, true);
        $this->assertTrue(ltm::is_enabled((int) $user->id));
        $this->assertEquals(1, get_user_preferences(ltm::PREF, null, $user->id));

        ltm::set_enabled((int) $user->id, false);
        $this->assertFalse(ltm::is_enabled((int) $user->id));
    }

    /**
     * The preference is strictly per-user.
     */
    public function test_per_user_isolation(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        ltm::set_enabled((int) $alice->id, true);

        $this->assertTrue(ltm::is_enabled((int) $alice->id));
        $this->assertFalse(ltm::is_enabled((int) $bob->id));
    }

    /**
     * Without a configured memory opt-in tool, no consent data leaves Moodle.
     */
    public function test_sync_without_configuration_is_noop(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        ltm::set_enabled((int) $user->id, true);

        $synced = ltm::sync_to_rag((int) $user->id, \context_system::instance());

        $this->assertFalse($synced);
        $this->assertTrue(ltm::is_enabled((int) $user->id));
    }

    /**
     * With the memory tool configured, the consent state is pushed to the
     * RAG server via the opt-in tool.
     */
    public function test_sync_calls_configured_tool(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/blocks/elediaaitutor/tests/fake_transport.php');

        if (!class_exists('\\webservice_elediamcp\\api')) {
            $this->markTestSkipped('webservice_elediamcp connector plugin is not installed.');
        }

        $service = (object) [
            'name' => 'MCP test service', 'shortname' => 'mcptest', 'enabled' => 1,
            'restrictedusers' => 0, 'downloadfiles' => 0, 'uploadfiles' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $service->id = $DB->insert_record('external_services', $service);
        set_config('services', (string) $service->id, 'webservice_elediamcp');
        set_config('mcpserviceid', $service->id, 'block_elediaaitutor');
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaaitutor');
        set_config('memoryoptintoolname', 'tutor_set_memory_optin', 'block_elediaaitutor');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        ltm::set_enabled((int) $user->id, true);

        $transport = \block_elediaaitutor\fake_transport::json_result(
            ['structuredContent' => ['accepted' => true]]);
        $client = new \block_elediaaitutor\local\rag_client(
            new \moodle_url('https://rag.example.com/mcp'), null, $transport, 30);

        $synced = ltm::sync_to_rag((int) $user->id, \context_system::instance(), $client);

        $this->assertTrue($synced);
        $payload = $transport->last_payload();
        $this->assertSame('tutor_set_memory_optin', $payload['params']['name']);
        $this->assertTrue($payload['params']['arguments']['enabled']);
        $this->assertNotEmpty($payload['params']['arguments']['moodle_token']);
    }
}
