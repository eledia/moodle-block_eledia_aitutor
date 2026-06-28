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

namespace block_eledia_aitutor\external;

use block_eledia_aitutor\local\conversation_repository;
use block_eledia_aitutor\local\rag_client;
use block_eledia_aitutor\local\security;
use block_eledia_aitutor\local\token_provider;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: clear/delete one of the current user's conversations.
 *
 * When a delete tool is configured, the conversation is first deleted on the RAG
 * server (best-effort) and then the local pointer/metadata is removed. If no
 * delete tool is configured the RAG server keeps the transcript per its own
 * retention policy and only the local pointer is removed.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clear_conversation extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context id of the block'),
            'id' => new external_value(PARAM_INT, 'Local conversation row id to delete'),
        ]);
    }

    /**
     * Delete a conversation owned by the current user.
     *
     * @param int $contextid Block (or system) context id.
     * @param int $id Local conversation row id.
     * @return array{deleted: bool}
     */
    public static function execute(int $contextid, int $id): array {
        global $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'id' => $id,
        ]);

        $context = helper::resolve_context($params['contextid']);
        require_login();
        self::validate_context($context);
        require_capability('block/eledia_aitutor:deleteownhistory', $context);

        $userid = (int) $USER->id;
        $record = conversation_repository::get_owned($params['id'], $userid);
        if ($record === null) {
            return ['deleted' => false];
        }

        // Propagate the deletion to the RAG server when a delete tool is
        // configured. Best-effort: a server failure is logged but never blocks
        // the local deletion, so the user can always remove their own data.
        $deletetool = security::delete_tool_name();
        if (
            $deletetool !== '' && !empty($record->conversationid)
                && security::mcp_enabled()
                && token_provider::is_connector_available()
        ) {
            try {
                $token = token_provider::get_token($userid);
                rag_client::create()->delete_conversation(
                    $CFG->wwwroot,
                    $token,
                    (string) $record->conversationid,
                    $deletetool
                );
            } catch (\moodle_exception $e) {
                \block_eledia_aitutor\event\rag_request_failed::create([
                    'context' => $context,
                    'userid' => $userid,
                    'other' => ['reason' => 'delete'],
                ])->trigger();
                debugging('block_eledia_aitutor: server-side conversation delete failed: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $deleted = conversation_repository::delete_owned($params['id'], $userid);

        return ['deleted' => $deleted];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Whether a conversation was deleted'),
        ]);
    }
}
