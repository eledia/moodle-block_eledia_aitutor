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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: list the current user's stored conversations.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_conversations extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context id of the block'),
            'courseid' => new external_value(PARAM_INT, 'Course filter, or 0 for all', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * List the current user's conversations.
     *
     * @param int $contextid Block (or system) context id.
     * @param int $courseid Course filter, or 0.
     * @return array{conversations: array}
     */
    public static function execute(int $contextid, int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'courseid' => $courseid,
        ]);

        $context = helper::resolve_context($params['contextid']);
        require_login();
        self::validate_context($context);
        require_capability('block/eledia_aitutor:viewhistory', $context);

        $filter = $params['courseid'] > 0 ? $params['courseid'] : null;
        helper::require_course_tutor_enabled((int) ($filter ?? 0));
        $records = conversation_repository::list_for_user((int) $USER->id, $filter);

        $conversations = array_map(static fn($r) => [
            'id' => (int) $r->id,
            'conversationid' => (string) $r->conversationid,
            'title' => (string) ($r->title ?? ''),
            'preview' => (string) ($r->lastpreview ?? ''),
            'courseid' => (int) ($r->courseid ?? 0),
            'timemodified' => (int) $r->timemodified,
        ], $records);

        return ['conversations' => $conversations];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'conversations' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Local conversation row id'),
                    'conversationid' => new external_value(PARAM_RAW, 'Server conversation id'),
                    'title' => new external_value(PARAM_TEXT, 'Display title, or empty'),
                    'preview' => new external_value(PARAM_TEXT, 'Last message preview, or empty'),
                    'courseid' => new external_value(PARAM_INT, 'Course id, or 0'),
                    'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp'),
                ])
            ),
        ]);
    }
}
