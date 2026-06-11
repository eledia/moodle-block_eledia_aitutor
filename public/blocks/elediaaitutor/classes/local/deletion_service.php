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

use context;

/**
 * User-initiated deletion of all tutor data the plugin holds for a user.
 *
 * Separates the two storage locations honestly:
 *  - LOCAL data (conversation pointers in {block_elediaaitutor_conv}) is always
 *    deleted, unconditionally.
 *  - EXTERNAL data (transcripts and long-term memory on the RAG/Tutor server)
 *    can only be deleted when the admin has configured a delete tool AND the
 *    MCP connector is available. The user-level tool (deleteusertoolname) is
 *    preferred — one call erases everything for the user, including memory —
 *    with the per-conversation tool (deletetoolname) as fallback. When neither
 *    is configured, the result reports external deletion as unsupported so the
 *    UI can tell the user the truth (external data remains subject to the
 *    service's retention policy) instead of implying everything is gone.
 *
 * External deletion is best-effort: individual failures are counted, never
 * fatal, and never block the local erase — a user must always be able to
 * remove their own local data. One summarising data_deletion_requested event
 * is recorded per request.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deletion_service {
    /**
     * Delete all tutor data for a user, propagating to the RAG server when possible.
     *
     * @param int $userid The user whose data is erased (also the requester).
     * @param context $context Context the request was made in (for the audit event).
     * @param rag_client|null $client Optional injected client (tests).
     * @return array{localdeleted: int, externalsupported: bool, externaldeleted: int, externalfailed: int}
     */
    public static function delete_all_for_user(int $userid, context $context, ?rag_client $client = null): array {
        global $CFG;

        $records = conversation_repository::list_for_user($userid, null, 0);
        $deleteusertool = security::delete_user_tool_name();
        $deletetool = security::delete_tool_name();
        $externalsupported = token_provider::is_connector_available()
            && ($deleteusertool !== '' || $deletetool !== '');
        $externaldeleted = 0;
        $externalfailed = 0;

        // The user-level tool is preferred: one call erases everything the RAG
        // server holds for the user (transcripts AND long-term memory), and is
        // complete even when Moodle no longer has conversation pointers — so it
        // is attempted even with zero local records. The per-conversation tool
        // is the fallback and can only reach conversations Moodle still knows.
        if ($externalsupported && ($deleteusertool !== '' || !empty($records))) {
            try {
                $client ??= rag_client::create();
                $token = token_provider::get_token($userid);
                if ($deleteusertool !== '') {
                    try {
                        $client->delete_user_data($CFG->wwwroot, $token, $deleteusertool);
                        $externaldeleted = 1;
                    } catch (rag_exception $e) {
                        $externalfailed = 1;
                    }
                } else {
                    foreach ($records as $record) {
                        try {
                            $client->delete_conversation(
                                $CFG->wwwroot,
                                $token,
                                (string) $record->conversationid,
                                $deletetool
                            );
                            $externaldeleted++;
                        } catch (rag_exception $e) {
                            $externalfailed++;
                        }
                    }
                }
            } catch (\moodle_exception $e) {
                // Configuration/token failure: everything not yet deleted counts
                // as failed, and the local erase below still proceeds.
                $remaining = $deleteusertool !== '' ? 1 : count($records);
                $externalfailed = $remaining - $externaldeleted;
                debugging('block_elediaaitutor: external deletion failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        $localdeleted = conversation_repository::delete_all_for_user($userid);
        // The user's logged analytics questions are local tutor data too.
        $questionsdeleted = question_log::delete_all_for_user($userid);

        \block_elediaaitutor\event\data_deletion_requested::create([
            'context' => $context,
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'localdeleted' => $localdeleted,
                'questionsdeleted' => $questionsdeleted,
                'externalsupported' => (int) $externalsupported,
                'externaldeleted' => $externaldeleted,
                'externalfailed' => $externalfailed,
            ],
        ])->trigger();

        return [
            'localdeleted' => $localdeleted,
            'externalsupported' => $externalsupported,
            'externaldeleted' => $externaldeleted,
            'externalfailed' => $externalfailed,
        ];
    }
}
