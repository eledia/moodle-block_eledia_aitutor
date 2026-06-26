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

use block_elediaaitutor\local\consent;
use block_elediaaitutor\local\conversation_repository;
use block_elediaaitutor\local\markdown_renderer;
use block_elediaaitutor\local\rag_client;
use block_elediaaitutor\local\security;
use block_elediaaitutor\local\token_provider;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

/**
 * External function: load previous messages of one of the user's conversations.
 *
 * Requires the RAG server to expose a history tool (configured by the admin). If
 * no history tool is configured, an empty list is returned so the UI can fall
 * back gracefully.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_history extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context id of the block'),
            'conversationid' => new external_value(PARAM_RAW, 'Server conversation id to load'),
        ]);
    }

    /**
     * Load conversation history.
     *
     * @param int $contextid Block (or system) context id.
     * @param string $conversationid Server conversation id.
     * @return array{messages: array, available: bool}
     * @throws moodle_exception
     */
    public static function execute(int $contextid, string $conversationid): array {
        global $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'conversationid' => $conversationid,
        ]);

        $context = helper::resolve_context($params['contextid']);
        require_login();
        self::validate_context($context);
        require_capability('block/elediaaitutor:viewhistory', $context);
        // History calls the external RAG server too, so the consent gate applies.
        consent::require_consent((int) $USER->id);

        // Ownership: the user may only load a conversation they own locally.
        $owned = conversation_repository::get_by_conversationid($params['conversationid'], (int) $USER->id);
        if ($owned === null) {
            throw new moodle_exception('error_conversation_not_found', 'block_elediaaitutor');
        }

        $historytool = security::history_tool_name();
        if ($historytool === '') {
            return ['messages' => [], 'available' => false];
        }

        token_provider::require_available();
        $token = token_provider::get_token((int) $USER->id);
        $client = rag_client::create();
        $messages = $client->get_history($CFG->wwwroot, $token, $params['conversationid'], $historytool);

        $out = [];
        foreach ($messages as $message) {
            $isuser = ($message['role'] ?? 'assistant') === 'user';
            $sources = [];
            foreach (($message['sources'] ?? []) as $source) {
                $sources[] = [
                    'title' => (string) ($source['title'] ?? ''),
                    'url' => clean_param((string) ($source['url'] ?? ''), PARAM_URL),
                    'snippet' => (string) ($source['snippet'] ?? ''),
                ];
            }
            $out[] = [
                'role' => $isuser ? 'user' : 'assistant',
                // User turns are returned raw and escaped client-side by the message
                // template ({{text}}); assistant turns are rendered through the same
                // safe Markdown pipeline as live answers and inserted as HTML.
                'html' => $isuser ? $message['content'] : markdown_renderer::render($message['content'], $context),
                // Citations for an assistant turn, so resume renders the same cards as live.
                'sources' => $sources,
            ];
        }

        return ['messages' => $out, 'available' => true];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Whether a history tool is configured'),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'html' => new external_value(PARAM_RAW, 'Rendered/escaped message HTML'),
                    'sources' => new external_multiple_structure(
                        new external_single_structure([
                            'title' => new external_value(PARAM_TEXT, 'Source title'),
                            'url' => new external_value(PARAM_URL, 'Source URL, or empty'),
                            'snippet' => new external_value(PARAM_TEXT, 'Source snippet, or empty'),
                        ]),
                        'Citations for this message',
                        VALUE_DEFAULT,
                        []
                    ),
                ])
            ),
        ]);
    }
}
