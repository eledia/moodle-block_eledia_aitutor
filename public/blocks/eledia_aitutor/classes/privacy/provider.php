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

namespace block_eledia_aitutor\privacy;

use context;
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
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the personal data stored or transmitted by this plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_eledia_aitutor_conv', [
            'userid' => 'privacy:metadata:block_eledia_aitutor_conv:userid',
            'courseid' => 'privacy:metadata:block_eledia_aitutor_conv:courseid',
            'conversationid' => 'privacy:metadata:block_eledia_aitutor_conv:conversationid',
            'title' => 'privacy:metadata:block_eledia_aitutor_conv:title',
            'lastpreview' => 'privacy:metadata:block_eledia_aitutor_conv:lastpreview',
            'timecreated' => 'privacy:metadata:block_eledia_aitutor_conv:timecreated',
            'timemodified' => 'privacy:metadata:block_eledia_aitutor_conv:timemodified',
        ], 'privacy:metadata:block_eledia_aitutor_conv');

        // Data sent to the external RAG/Tutor server, where full history lives.
        $collection->add_external_location_link('rag_server', [
            'userid' => 'privacy:metadata:rag_server:userid',
            'message' => 'privacy:metadata:rag_server:message',
            'courseid' => 'privacy:metadata:rag_server:courseid',
            'conversationid' => 'privacy:metadata:rag_server:conversationid',
        ], 'privacy:metadata:rag_server');

        // Opt-in question analytics (questions only, never answers).
        $collection->add_database_table('block_eledia_aitutor_qlog', [
            'userid' => 'privacy:metadata:block_eledia_aitutor_qlog:userid',
            'courseid' => 'privacy:metadata:block_eledia_aitutor_qlog:courseid',
            'question' => 'privacy:metadata:block_eledia_aitutor_qlog:question',
            'grounded' => 'privacy:metadata:block_eledia_aitutor_qlog:grounded',
            'answerstyle' => 'privacy:metadata:block_eledia_aitutor_qlog:answerstyle',
            'topic' => 'privacy:metadata:block_eledia_aitutor_qlog:topic',
            'sourcetitle' => 'privacy:metadata:block_eledia_aitutor_qlog:sourcetitle',
            'cmid' => 'privacy:metadata:block_eledia_aitutor_qlog:cmid',
            'timecreated' => 'privacy:metadata:block_eledia_aitutor_qlog:timecreated',
        ], 'privacy:metadata:block_eledia_aitutor_qlog');

        // Daily message counters for quota enforcement.
        $collection->add_database_table('block_eledia_aitutor_usage', [
            'userid' => 'privacy:metadata:block_eledia_aitutor_usage:userid',
            'daykey' => 'privacy:metadata:block_eledia_aitutor_usage:daykey',
            'messagecount' => 'privacy:metadata:block_eledia_aitutor_usage:messagecount',
        ], 'privacy:metadata:block_eledia_aitutor_usage');

        // Short admin diagnostics for failed tutor calls; no prompts or answers.
        $collection->add_database_table('block_eledia_aitutor_diag', [
            'userid' => 'privacy:metadata:block_eledia_aitutor_diag:userid',
            'courseid' => 'privacy:metadata:block_eledia_aitutor_diag:courseid',
            'contextid' => 'privacy:metadata:block_eledia_aitutor_diag:contextid',
            'phase' => 'privacy:metadata:block_eledia_aitutor_diag:phase',
            'errorcode' => 'privacy:metadata:block_eledia_aitutor_diag:errorcode',
            'detail' => 'privacy:metadata:block_eledia_aitutor_diag:detail',
            'timecreated' => 'privacy:metadata:block_eledia_aitutor_diag:timecreated',
        ], 'privacy:metadata:block_eledia_aitutor_diag');

        // Documented first-use acknowledgement of the privacy guidelines.
        $collection->add_database_table('block_eledia_aitutor_consent', [
            'userid' => 'privacy:metadata:block_eledia_aitutor_consent:userid',
            'timecreated' => 'privacy:metadata:block_eledia_aitutor_consent:timecreated',
        ], 'privacy:metadata:block_eledia_aitutor_consent');

        // The long-term memory opt-in (a user preference; off by default).
        $collection->add_user_preference(
            \block_eledia_aitutor\local\ltm::PREF,
            'privacy:metadata:preference:ltm'
        );

        return $collection;
    }

    /**
     * Export the user's preferences for this plugin.
     *
     * @param int $userid The user id.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $value = get_user_preferences(\block_eledia_aitutor\local\ltm::PREF, null, $userid);
        if ($value === null) {
            return;
        }
        writer::export_user_preference(
            'block_eledia_aitutor',
            \block_eledia_aitutor\local\ltm::PREF,
            transform::yesno($value),
            get_string('privacy:metadata:preference:ltm', 'block_eledia_aitutor')
        );
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
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_eledia_aitutor_conv}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_eledia_aitutor_qlog}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_eledia_aitutor_consent}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_eledia_aitutor_usage}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_eledia_aitutor_diag}', []);
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
        $records = $DB->get_records('block_eledia_aitutor_conv', ['userid' => $userid], 'timecreated ASC');
        if (!empty($records)) {
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
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:conversations', 'block_eledia_aitutor')],
                (object) ['conversations' => $data]
            );
        }

        $questions = $DB->get_records('block_eledia_aitutor_qlog', ['userid' => $userid], 'timecreated ASC');
        if (!empty($questions)) {
            $data = [];
            foreach ($questions as $record) {
                $data[] = (object) [
                    'courseid' => (int) $record->courseid,
                    'question' => $record->question,
                    'grounded' => transform::yesno($record->grounded),
                    'answerstyle' => $record->answerstyle,
                    'topic' => $record->topic,
                    'sourcetitle' => $record->sourcetitle,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:questions', 'block_eledia_aitutor')],
                (object) ['questions' => $data]
            );
        }

        $counters = $DB->get_records('block_eledia_aitutor_usage', ['userid' => $userid], 'daykey ASC');
        if (!empty($counters)) {
            $data = [];
            foreach ($counters as $record) {
                $data[] = (object) [
                    'day' => (string) $record->daykey,
                    'messages' => (int) $record->messagecount,
                ];
            }
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:usage', 'block_eledia_aitutor')],
                (object) ['days' => $data]
            );
        }

        $diagnostics = $DB->get_records('block_eledia_aitutor_diag', ['userid' => $userid], 'timecreated ASC');
        if (!empty($diagnostics)) {
            $data = [];
            foreach ($diagnostics as $record) {
                $data[] = (object) [
                    'courseid' => (int) $record->courseid,
                    'contextid' => (int) $record->contextid,
                    'phase' => $record->phase,
                    'errorcode' => $record->errorcode,
                    'detail' => $record->detail,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:diagnostics', 'block_eledia_aitutor')],
                (object) ['diagnostics' => $data]
            );
        }

        $consenttime = \block_eledia_aitutor\local\consent::time_consented((int) $userid);
        if ($consenttime !== null) {
            writer::with_context(\core\context\system::instance())->export_data(
                [get_string('privacy:consent', 'block_eledia_aitutor')],
                (object) ['timeconsented' => transform::datetime($consenttime)]
            );
        }
    }

    /**
     * Delete all conversation metadata in the given context.
     *
     * @param context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof \core\context\system) {
            return;
        }
        $DB->delete_records('block_eledia_aitutor_conv');
        $DB->delete_records('block_eledia_aitutor_qlog');
        $DB->delete_records('block_eledia_aitutor_consent');
        $DB->delete_records('block_eledia_aitutor_usage');
        $DB->delete_records('block_eledia_aitutor_diag');
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
        $DB->delete_records('block_eledia_aitutor_conv', ['userid' => (int) $contextlist->get_user()->id]);
        $DB->delete_records('block_eledia_aitutor_qlog', ['userid' => (int) $contextlist->get_user()->id]);
        $DB->delete_records('block_eledia_aitutor_consent', ['userid' => (int) $contextlist->get_user()->id]);
        $DB->delete_records('block_eledia_aitutor_usage', ['userid' => (int) $contextlist->get_user()->id]);
        $DB->delete_records('block_eledia_aitutor_diag', ['userid' => (int) $contextlist->get_user()->id]);
    }

    /**
     * Delete conversation metadata for multiple users in the given context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('block_eledia_aitutor_conv', "userid $insql", $params);
        $DB->delete_records_select('block_eledia_aitutor_qlog', "userid $insql", $params);
        $DB->delete_records_select('block_eledia_aitutor_consent', "userid $insql", $params);
        $DB->delete_records_select('block_eledia_aitutor_usage', "userid $insql", $params);
        $DB->delete_records_select('block_eledia_aitutor_diag', "userid $insql", $params);
    }

    /**
     * Whether the user owns any conversation metadata.
     *
     * @param int $userid The user id.
     * @return bool
     */
    protected static function user_has_data(int $userid): bool {
        global $DB;
        return $DB->record_exists('block_eledia_aitutor_conv', ['userid' => $userid])
            || $DB->record_exists('block_eledia_aitutor_qlog', ['userid' => $userid])
            || $DB->record_exists('block_eledia_aitutor_consent', ['userid' => $userid])
            || $DB->record_exists('block_eledia_aitutor_usage', ['userid' => $userid])
            || $DB->record_exists('block_eledia_aitutor_diag', ['userid' => $userid]);
    }

    /**
     * Whether a context list contains the system context.
     *
     * @param context[] $contexts The contexts.
     * @return bool
     */
    protected static function contains_system_context(array $contexts): bool {
        foreach ($contexts as $context) {
            if ($context instanceof \core\context\system) {
                return true;
            }
        }
        return false;
    }
}
