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

namespace block_elediaaitutor\external;

use block_elediaaitutor\local\chat_mode;
use block_elediaaitutor\local\chat_service;
use block_elediaaitutor\local\security;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

/**
 * External function: send a chat message to the RAG/Tutor server.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /** @var string[] Accepted answer styles ('' = use the instance default). */
    private const ANSWER_STYLES = ['', 'explain', 'hint', 'quiz'];

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context id of the block (for capability checks)'),
            'message' => new external_value(PARAM_RAW, 'The user message'),
            'courseid' => new external_value(PARAM_INT, 'Course context id, or 0 for global', VALUE_DEFAULT, 0),
            'conversationid' => new external_value(PARAM_RAW, 'Existing conversation id, or empty', VALUE_DEFAULT, ''),
            'answerstyle' => new external_value(PARAM_ALPHA, 'Requested answer style, or empty for the default',
                VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Send a message and return the assistant response.
     *
     * @param int $contextid Block (or system) context id.
     * @param string $message Raw user message.
     * @param int $courseid Course id, or 0.
     * @param string $conversationid Existing conversation id, or ''.
     * @param string $answerstyle Requested answer style, or '' for the instance default.
     * @return array Response structure.
     * @throws moodle_exception
     */
    public static function execute(int $contextid, string $message, int $courseid, string $conversationid,
            string $answerstyle = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'message' => $message,
            'courseid' => $courseid,
            'conversationid' => $conversationid,
            'answerstyle' => $answerstyle,
        ]);

        if (!in_array($params['answerstyle'], self::ANSWER_STYLES, true)) {
            throw new \invalid_parameter_exception('Invalid answer style.');
        }

        global $USER;
        $context = helper::resolve_context($params['contextid']);

        // Enforce login + course enrolment when a course context is supplied.
        if ($params['courseid'] > 0) {
            if (!security::course_chat_enabled()) {
                throw new moodle_exception('error_course_chat_disabled', 'block_elediaaitutor');
            }
            $course = get_course($params['courseid']);
            require_login($course, false);
        } else {
            if (!security::global_chat_enabled()) {
                throw new moodle_exception('error_global_chat_disabled', 'block_elediaaitutor');
            }
            require_login();
        }

        self::validate_context($context);
        require_capability('block/elediaaitutor:use', $context);

        $conv = $params['conversationid'] !== '' ? $params['conversationid'] : null;
        $courseid = $params['courseid'] > 0 ? $params['courseid'] : null;

        // Resolve the effective answer style server-side: the client's choice is
        // honoured only when the teacher allows style changes on this instance —
        // a locked instance always uses its configured default, whatever the
        // client sent.
        $blockconfig = helper::block_config($context);
        $default = $blockconfig->answerstyle ?? 'explain';
        if (!in_array($default, self::ANSWER_STYLES, true) || $default === '') {
            $default = 'explain';
        }
        $allowchange = !isset($blockconfig->allowstylechange) || (int) $blockconfig->allowstylechange === 1;
        $effectivestyle = ($allowchange && $params['answerstyle'] !== '') ? $params['answerstyle'] : $default;

        // Daily message limit: the instance may override the site default
        // (-1 / unset = site default, 0 = unlimited, >0 = messages per day).
        $dailylimit = null;
        if (isset($blockconfig->dailylimit) && (int) $blockconfig->dailylimit >= 0) {
            $dailylimit = (int) $blockconfig->dailylimit;
        }

        // Resolve the answer mode authoritatively (the client cannot widen it):
        // grounded vs LLM-only, honouring the site gate and the course's
        // ingestion state. Refuse the turn when the tutor is unavailable here.
        $mode = chat_mode::resolve((int) ($courseid ?? 0), $blockconfig);
        if ($mode === chat_mode::MODE_UNAVAILABLE) {
            throw new moodle_exception('llmonly_unavailable', 'block_elediaaitutor');
        }
        $ragenabled = chat_mode::rag_enabled_for($mode);

        $result = chat_service::send((int) $USER->id, $params['message'], $courseid, $conv, $context, null,
            $effectivestyle, $dailylimit, $ragenabled);

        return [
            'answerhtml' => $result['answerhtml'],
            'conversationid' => $result['conversationid'] ?? '',
            'iserror' => $result['iserror'],
            'sources' => array_map(static fn($s) => [
                'title' => $s['title'],
                'url' => $s['url'],
                'snippet' => $s['snippet'],
            ], $result['sources']),
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'answerhtml' => new external_value(PARAM_RAW, 'Sanitised HTML of the assistant answer'),
            'conversationid' => new external_value(PARAM_RAW, 'Server conversation id, or empty'),
            'iserror' => new external_value(PARAM_BOOL, 'Whether the tool reported an error'),
            'sources' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Source title'),
                    'url' => new external_value(PARAM_URL, 'Source URL, or empty'),
                    'snippet' => new external_value(PARAM_TEXT, 'Source snippet, or empty'),
                ]),
                'Citations/sources returned by the RAG server',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
