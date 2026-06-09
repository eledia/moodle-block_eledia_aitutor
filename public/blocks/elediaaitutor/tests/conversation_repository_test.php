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

/**
 * Unit tests for the conversation repository.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\conversation_repository
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conversation_repository_test extends \advanced_testcase {
    /**
     * Upsert creates once and updates thereafter (idempotent on the server id).
     */
    public function test_upsert_creates_then_updates(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $first = conversation_repository::upsert((int) $user->id, 'conv-1', 7, 'First message');
        $this->assertNotEmpty($first->id);

        $second = conversation_repository::upsert((int) $user->id, 'conv-1', 7, 'Second message');
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Second message', $second->lastpreview);

        $rows = conversation_repository::list_for_user((int) $user->id);
        $this->assertCount(1, $rows);
    }

    /**
     * Listing and fetching is scoped strictly to the owner.
     */
    public function test_ownership_scoping(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        $aliceconv = conversation_repository::upsert((int) $alice->id, 'a-1', null, 'hi');
        conversation_repository::upsert((int) $bob->id, 'b-1', null, 'hi');

        $this->assertCount(1, conversation_repository::list_for_user((int) $alice->id));
        $this->assertNotNull(conversation_repository::get_owned((int) $aliceconv->id, (int) $alice->id));
        // Bob cannot fetch Alice's conversation.
        $this->assertNull(conversation_repository::get_owned((int) $aliceconv->id, (int) $bob->id));
    }

    /**
     * Delete only works for the owner.
     */
    public function test_delete_owned(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $conv = conversation_repository::upsert((int) $alice->id, 'a-1', null, 'hi');

        $this->assertFalse(conversation_repository::delete_owned((int) $conv->id, (int) $bob->id));
        $this->assertTrue(conversation_repository::delete_owned((int) $conv->id, (int) $alice->id));
        $this->assertCount(0, conversation_repository::list_for_user((int) $alice->id));
    }

    /**
     * Creating a conversation fires the conversation_created event once.
     */
    public function test_create_fires_event(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        conversation_repository::upsert((int) $user->id, 'conv-x', null, 'hi');
        conversation_repository::upsert((int) $user->id, 'conv-x', null, 'again');
        $events = array_filter($sink->get_events(),
            static fn($e) => $e instanceof \block_elediaaitutor\event\conversation_created);
        $this->assertCount(1, $events);
    }
}
