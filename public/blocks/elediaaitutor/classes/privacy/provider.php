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

namespace block_elediaaitutor\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the eLeDia.ai Tutor block.
 *
 * Local storage is limited to conversation metadata (a pointer to the
 * RAG-server-owned conversation plus a short last-message preview). Full chat
 * transcripts are NOT stored in Moodle — they live on the external RAG/Tutor
 * server, which is declared here as an external location. The user-scoped MCP
 * token's own metadata is owned and exported/erased by webservice_elediamcp.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data stored or transmitted by this plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_elediaaitutor_conv', [
            'userid' => 'privacy:metadata:block_elediaaitutor_conv:userid',
            'courseid' => 'privacy:metadata:block_elediaaitutor_conv:courseid',
            'conversationid' => 'privacy:metadata:block_elediaaitutor_conv:conversationid',
            'title' => 'privacy:metadata:block_elediaaitutor_conv:title',
            'lastpreview' => 'privacy:metadata:block_elediaaitutor_conv:lastpreview',
            'timecreated' => 'privacy:metadata:block_elediaaitutor_conv:timecreated',
            'timemodified' => 'privacy:metadata:block_elediaaitutor_conv:timemodified',
        ], 'privacy:metadata:block_elediaaitutor_conv');

        // Data sent to the external RAG/Tutor server, where full history lives.
        $collection->add_external_location_link('rag_server', [
            'userid' => 'privacy:metadata:rag_server:userid',
            'message' => 'privacy:metadata:rag_server:message',
            'courseid' => 'privacy:metadata:rag_server:courseid',
            'conversationid' => 'privacy:metadata:rag_server:conversationid',
        ], 'privacy:metadata:rag_server');

        return $collection;
    }

    /**
     * Conversation metadata lives at the system context.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_data($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Find users with data in the given context.
     *
     * @param userlist $userlist The userlist.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_elediaaitutor_conv}', []);
    }

    /**
     * Export conversation metadata for the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::contains_system_context($contextlist->get_contexts())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('block_elediaaitutor_conv', ['userid' => $userid], 'timecreated ASC');
        if (empty($records)) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (object) [
                'conversationid' => $record->conversationid,
                'courseid' => (int) ($record->courseid ?? 0),
                'title' => $record->title,
                'lastpreview' => $record->lastpreview,
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
        }

        writer::with_context(context_system::instance())->export_data(
            [get_string('privacy:conversations', 'block_elediaaitutor')],
            (object) ['conversations' => $data]
        );
    }

    /**
     * Delete all conversation metadata in the given context.
     *
     * @param context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        $DB->delete_records('block_elediaaitutor_conv');
    }

    /**
     * Delete a user's conversation metadata in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!self::contains_system_context($contextlist->get_contexts())) {
            return;
        }
        $DB->delete_records('block_elediaaitutor_conv', ['userid' => (int) $contextlist->get_user()->id]);
    }

    /**
     * Delete conversation metadata for multiple users in the given context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('block_elediaaitutor_conv', "userid $insql", $params);
    }

    /**
     * Whether the user owns any conversation metadata.
     *
     * @param int $userid The user id.
     * @return bool
     */
    protected static function user_has_data(int $userid): bool {
        global $DB;
        return $DB->record_exists('block_elediaaitutor_conv', ['userid' => $userid]);
    }

    /**
     * Whether a context list contains the system context.
     *
     * @param context[] $contexts The contexts.
     * @return bool
     */
    protected static function contains_system_context(array $contexts): bool {
        foreach ($contexts as $context) {
            if ($context instanceof context_system) {
                return true;
            }
        }
        return false;
    }
}
