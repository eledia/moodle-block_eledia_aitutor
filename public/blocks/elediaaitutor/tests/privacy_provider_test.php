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

use block_elediaaitutor\local\conversation_repository;
use block_elediaaitutor\privacy\provider;
use context_system;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider tests.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\privacy\provider
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Metadata describes the conversation table and the external RAG location.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('block_elediaaitutor');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);
    }

    /**
     * A user with conversations is reported at the system context.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $user->id, 'c-1', null, 'hi');

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(context_system::instance()->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Export writes the user's conversation metadata.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $user->id, 'c-1', 5, 'hello world');

        $contextlist = new approved_contextlist($user, 'block_elediaaitutor', [context_system::instance()->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context(context_system::instance());
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Deleting a user's data removes only their rows.
     */
    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $alice->id, 'a-1', null, 'hi');
        conversation_repository::upsert((int) $bob->id, 'b-1', null, 'hi');

        $contextlist = new approved_contextlist($alice, 'block_elediaaitutor', [context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertCount(0, conversation_repository::list_for_user((int) $alice->id));
        $this->assertCount(1, conversation_repository::list_for_user((int) $bob->id));
    }

    /**
     * Deleting all data in the context clears the table.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $user->id, 'c-1', null, 'hi');

        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertCount(0, conversation_repository::list_for_user((int) $user->id));
    }
}
