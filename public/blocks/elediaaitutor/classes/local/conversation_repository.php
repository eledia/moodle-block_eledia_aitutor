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

namespace block_elediaaitutor\local;

use stdClass;

/**
 * Repository for local conversation metadata.
 *
 * Stores only a lightweight pointer to each conversation owned by the external
 * RAG/Tutor server (its conversation id) plus display metadata (title, last
 * preview, timestamps). Full transcripts live on the RAG server. Every method
 * is scoped by owner so a user can only ever touch their own conversations.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_repository {
    /** @var string Backing table. */
    private const TABLE = 'block_elediaaitutor_conv';

    /** @var int Max stored preview length (characters). */
    private const PREVIEW_LENGTH = 200;

    /**
     * Record (or update) a conversation pointer after a successful chat turn.
     *
     * Idempotent on (userid, conversationid): creating fires the conversation
     * created event once; later turns only refresh the preview/timestamp.
     *
     * @param int $userid Owner.
     * @param string $conversationid Server-issued conversation id.
     * @param int|null $courseid Course context, or null for global.
     * @param string $preview Last message preview (truncated on store).
     * @param string|null $title Optional display title.
     * @return stdClass The stored record.
     */
    public static function upsert(
        int $userid,
        string $conversationid,
        ?int $courseid,
        string $preview,
        ?string $title = null
    ): stdClass {
        global $DB;

        $now = time();
        $preview = \core_text::substr(trim($preview), 0, self::PREVIEW_LENGTH);
        $existing = $DB->get_record(self::TABLE, ['userid' => $userid, 'conversationid' => $conversationid]);

        if ($existing) {
            $existing->lastpreview = $preview;
            $existing->timemodified = $now;
            if ($title !== null && $title !== '') {
                $existing->title = \core_text::substr($title, 0, 255);
            }
            $DB->update_record(self::TABLE, $existing);
            return $existing;
        }

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid ?: null,
            'conversationid' => $conversationid,
            'title' => $title !== null && $title !== '' ? \core_text::substr($title, 0, 255) : null,
            'lastpreview' => $preview,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        \block_elediaaitutor\event\conversation_created::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'relateduserid' => $userid,
            'other' => ['courseid' => (int) ($courseid ?: 0)],
        ])->trigger();

        return $record;
    }

    /**
     * List a user's conversations, newest first.
     *
     * @param int $userid Owner.
     * @param int|null $courseid Optional course filter.
     * @param int $limit Max rows (0 = no limit).
     * @return stdClass[]
     */
    public static function list_for_user(int $userid, ?int $courseid = null, int $limit = 50): array {
        global $DB;
        $conditions = ['userid' => $userid];
        if ($courseid !== null) {
            $conditions['courseid'] = $courseid ?: null;
        }
        return array_values($DB->get_records(self::TABLE, $conditions, 'timemodified DESC, id DESC', '*', 0, $limit));
    }

    /**
     * Fetch one conversation, enforcing ownership.
     *
     * @param int $id Conversation row id.
     * @param int $userid Owner who must match.
     * @return stdClass|null Null when not found or not owned by the user.
     */
    public static function get_owned(int $id, int $userid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id, 'userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Resolve a server conversation id to an owned record.
     *
     * @param string $conversationid Server conversation id.
     * @param int $userid Owner who must match.
     * @return stdClass|null
     */
    public static function get_by_conversationid(string $conversationid, int $userid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['conversationid' => $conversationid, 'userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Delete a conversation pointer, enforcing ownership.
     *
     * @param int $id Conversation row id.
     * @param int $userid Owner who must match.
     * @return bool True when a row was deleted.
     */
    public static function delete_owned(int $id, int $userid): bool {
        global $DB;
        $record = self::get_owned($id, $userid);
        if ($record === null) {
            return false;
        }
        $DB->delete_records(self::TABLE, ['id' => $record->id, 'userid' => $userid]);

        \block_elediaaitutor\event\conversation_cleared::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'relateduserid' => $userid,
        ])->trigger();

        return true;
    }
}
