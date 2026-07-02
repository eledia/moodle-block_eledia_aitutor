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

use block_eledia_aitutor\local\branding;
use block_eledia_aitutor\local\chat_mode;
use block_eledia_aitutor\local\chat_service;
use block_eledia_aitutor\local\security;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

/**
 * External function: send a chat message to the RAG/Tutor server.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /** @var string[] Accepted answer styles ('' = use the instance default). */
    private const ANSWER_STYLES = ['', 'explain', 'hint', 'quiz'];

    /** @var string[] Accepted routing intents ('' = server default 'auto'). */
    private const INTENTS = ['', 'auto', 'action', 'knowledge'];

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
            'answerstyle' => new external_value(
                PARAM_ALPHA,
                'Requested answer style, or empty for the default',
                VALUE_DEFAULT,
                ''
            ),
            'intent' => new external_value(
                PARAM_ALPHA,
                'Routing hint (auto|action|knowledge), or empty for the server default',
                VALUE_DEFAULT,
                ''
            ),
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
     * @param string $intent Routing hint (auto|action|knowledge), or '' for the server default.
     * @return array Response structure.
     * @throws moodle_exception
     */
    public static function execute(
        int $contextid,
        string $message,
        int $courseid,
        string $conversationid,
        string $answerstyle = '',
        string $intent = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'message' => $message,
            'courseid' => $courseid,
            'conversationid' => $conversationid,
            'answerstyle' => $answerstyle,
            'intent' => $intent,
        ]);

        if (!in_array($params['answerstyle'], self::ANSWER_STYLES, true)) {
            throw new \invalid_parameter_exception('Invalid answer style.');
        }
        if (!in_array($params['intent'], self::INTENTS, true)) {
            throw new \invalid_parameter_exception('Invalid intent.');
        }

        global $USER;
        $context = helper::resolve_context($params['contextid']);

        // Enforce login + course enrolment when a course context is supplied.
        if ($params['courseid'] > 0) {
            if (!security::course_chat_enabled()) {
                throw new moodle_exception('error_course_chat_disabled', 'block_eledia_aitutor');
            }
            $course = get_course($params['courseid']);
            require_login($course, false);
            helper::require_course_tutor_enabled((int) $params['courseid']);
        } else {
            if (!security::global_chat_enabled()) {
                throw new moodle_exception('error_global_chat_disabled', 'block_eledia_aitutor');
            }
            require_login();
        }

        self::validate_context($context);
        require_capability('block/eledia_aitutor:use', $context);

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
            throw new moodle_exception('llmonly_unavailable', 'block_eledia_aitutor');
        }
        $ragenabled = chat_mode::rag_enabled_for($mode);

        // The effective structured persona (instance-over-site) shapes the
        // tutor's voice; only populated sub-fields are sent to the RAG server.
        $persona = branding::persona((array) $blockconfig);

        $result = chat_service::send(
            (int) $USER->id,
            $params['message'],
            $courseid,
            $conv,
            $context,
            null,
            $effectivestyle,
            $dailylimit,
            $ragenabled,
            $persona,
            $params['intent'] !== '' ? $params['intent'] : null
        );

        return [
            'answerhtml' => $result['answerhtml'],
            'conversationid' => $result['conversationid'] ?? '',
            'iserror' => $result['iserror'],
            'answerorigin' => $result['answerorigin'] ?? 'general',
            'sources' => array_map(static fn($s) => [
                'title' => $s['title'],
                'url' => clean_param((string) $s['url'], PARAM_URL),
                'snippet' => $s['snippet'],
            ], $result['sources']),
            'confirmation' => is_array($result['confirmation'] ?? null) ? $result['confirmation'] : [],
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
            'answerorigin' => new external_value(PARAM_ALPHA, 'Answer origin: rag, mcp or general', VALUE_DEFAULT,
                'general'),
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
            'confirmation' => new external_single_structure([
                'required' => new external_value(PARAM_BOOL, 'Whether the assistant needs an explicit confirmation',
                    VALUE_DEFAULT, false),
                'yeslabel' => new external_value(PARAM_TEXT, 'Label for the confirm button', VALUE_DEFAULT, 'Ja'),
                'nolabel' => new external_value(PARAM_TEXT, 'Label for the decline button', VALUE_DEFAULT, 'Nein'),
                'yesmessage' => new external_value(PARAM_TEXT, 'Message sent when confirming', VALUE_DEFAULT, 'Ja'),
                'nomessage' => new external_value(PARAM_TEXT, 'Message sent when declining', VALUE_DEFAULT, 'Nein'),
            ], 'Optional confirmation controls for pending write actions', VALUE_DEFAULT),
        ]);
    }
}
