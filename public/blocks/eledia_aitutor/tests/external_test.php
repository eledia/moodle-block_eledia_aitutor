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
use block_eledia_aitutor\external\clear_conversation;
use block_eledia_aitutor\external\delete_my_data;
use block_eledia_aitutor\external\get_conversations;
use block_eledia_aitutor\external\send_message;
use block_eledia_aitutor\external\set_ltm;
use block_eledia_aitutor\local\conversation_repository;
use block_eledia_aitutor\local\ltm;

/**
 * Tests for the external (AJAX) functions: validation, context and capability
 * enforcement, and ownership scoping.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_eledia_aitutor\external\send_message::class)]
#[CoversClass(\block_eledia_aitutor\external\get_conversations::class)]
#[CoversClass(\block_eledia_aitutor\external\clear_conversation::class)]
#[CoversClass(\block_eledia_aitutor\external\set_ltm::class)]
#[CoversClass(\block_eledia_aitutor\external\delete_my_data::class)]
final class external_test extends \advanced_testcase {
    /**
     * An invalid context id is rejected.
     */
    public function test_send_message_invalid_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        send_message::execute(-9999, 'Hi', 0, '');
    }

    /**
     * A user for whom the capability is prohibited cannot chat.
     */
    public function test_send_message_requires_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = \core\context\system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $user->id, $context->id);
        assign_capability('block/eledia_aitutor:use', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);
        send_message::execute($context->id, 'Hi', 0, '');
    }

    /**
     * get_conversations returns only the calling user's conversations.
     */
    public function test_get_conversations_scoped_to_user(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $alice->id, 'a-1', null, 'alice msg');
        conversation_repository::upsert((int) $bob->id, 'b-1', null, 'bob msg');

        $this->setUser($alice);
        $result = get_conversations::execute(\core\context\system::instance()->id, 0);
        $result = \core_external\external_api::clean_returnvalue(get_conversations::execute_returns(), $result);

        $this->assertCount(1, $result['conversations']);
        $this->assertSame('a-1', $result['conversations'][0]['conversationid']);
    }

    /**
     * Course contexts are accepted (the standalone view.php page chats at the
     * course context); unrelated context levels stay rejected.
     */
    public function test_course_context_accepted_user_context_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_block('eledia_aitutor', [
            'parentcontextid' => \core\context\course::instance($course->id)->id,
        ]);
        conversation_repository::upsert((int) $student->id, 'c-1', (int) $course->id, 'hi');

        $this->setUser($student);
        $coursecontext = \core\context\course::instance($course->id);
        $result = get_conversations::execute($coursecontext->id, (int) $course->id);
        $result = \core_external\external_api::clean_returnvalue(get_conversations::execute_returns(), $result);
        $this->assertCount(1, $result['conversations']);

        $usercontext = \context_user::instance((int) $student->id);
        $this->expectException(\moodle_exception::class);
        get_conversations::execute($usercontext->id, 0);
    }

    /**
     * Course-scoped AJAX requests honour the teacher's opt-in signal.
     */
    public function test_course_context_requires_tutor_block(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        conversation_repository::upsert((int) $student->id, 'c-1', (int) $course->id, 'hi');

        $this->setUser($student);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notenabledincourse', 'block_eledia_aitutor'));
        get_conversations::execute(\core\context\course::instance($course->id)->id, (int) $course->id);
    }

    /**
     * A user who cannot access the course cannot use its context either.
     */
    public function test_course_context_requires_course_access(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        get_conversations::execute(\core\context\course::instance($course->id)->id, (int) $course->id);
    }

    /**
     * A user cannot delete another user's conversation.
     */
    public function test_clear_conversation_enforces_ownership(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $aliceconv = conversation_repository::upsert((int) $alice->id, 'a-1', null, 'alice msg');

        $this->setUser($bob);
        $result = clear_conversation::execute(\core\context\system::instance()->id, (int) $aliceconv->id);
        $result = \core_external\external_api::clean_returnvalue(clear_conversation::execute_returns(), $result);

        // Nothing deleted: Bob does not own it.
        $this->assertFalse($result['deleted']);
        $this->assertCount(1, conversation_repository::list_for_user((int) $alice->id));
    }

    /**
     * set_ltm stores the calling user's preference and fires the audit event.
     */
    public function test_set_ltm_roundtrip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contextid = \core\context\system::instance()->id;

        $sink = $this->redirectEvents();
        $result = set_ltm::execute($contextid, true);
        $result = \core_external\external_api::clean_returnvalue(set_ltm::execute_returns(), $result);
        $this->assertTrue($result['enabled']);
        $this->assertTrue(ltm::is_enabled((int) $user->id));

        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \block_eledia_aitutor\event\ltm_preference_changed
        );
        $this->assertCount(1, $events);

        $result = set_ltm::execute($contextid, false);
        $result = \core_external\external_api::clean_returnvalue(set_ltm::execute_returns(), $result);
        $this->assertFalse($result['enabled']);
        $this->assertFalse(ltm::is_enabled((int) $user->id));
    }

    /**
     * delete_my_data erases only the calling user's conversations and reports
     * external deletion honestly when no delete tool is configured.
     */
    public function test_delete_my_data_scoped_to_caller(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        conversation_repository::upsert((int) $alice->id, 'a-1', null, 'one');
        conversation_repository::upsert((int) $alice->id, 'a-2', null, 'two');
        conversation_repository::upsert((int) $bob->id, 'b-1', null, 'bob');

        $this->setUser($alice);
        $result = delete_my_data::execute(\core\context\system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(delete_my_data::execute_returns(), $result);

        $this->assertSame(2, $result['localdeleted']);
        $this->assertFalse($result['externalsupported']);
        $this->assertCount(0, conversation_repository::list_for_user((int) $alice->id));
        $this->assertCount(1, conversation_repository::list_for_user((int) $bob->id));
    }
}
