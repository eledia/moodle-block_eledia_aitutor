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
 * Orchestrates a chat turn end to end.
 *
 * This is the single place that ties together token provisioning, the RAG call,
 * Markdown rendering, conversation bookkeeping and audit events, so the external
 * (AJAX) layer stays thin and the business logic is unit-testable with a faked
 * RAG client. Capability and context checks are the caller's responsibility.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_service {
    /**
     * Process one chat turn for a user and return a render-ready response.
     *
     * @param int $userid The acting (and owning) user id.
     * @param string $message Raw user message.
     * @param int|null $courseid Course context id, or null for global chat.
     * @param string|null $conversationid Existing server conversation id, or null.
     * @param context $context Context used for safe Markdown rendering.
     * @param rag_client|null $client Optional injected client (tests).
     * @return array{answerhtml: string, answermarkdown: string, conversationid: ?string, sources: array, iserror: bool}
     * @throws \moodle_exception On validation, configuration or RAG failure.
     */
    public static function send(
        int $userid,
        string $message,
        ?int $courseid,
        ?string $conversationid,
        context $context,
        ?rag_client $client = null
    ): array {
        global $CFG;

        $message = security::validate_message($message);
        security::enforce_rate_limit($userid);
        token_provider::require_available();

        \block_elediaaitutor\event\message_sent::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['length' => \core_text::strlen($message), 'courseid' => (int) ($courseid ?: 0)],
        ])->trigger();

        $client ??= rag_client::create();
        $toolname = security::chat_tool_name();
        $systemurl = $CFG->wwwroot;
        $courseparam = $courseid ? (string) $courseid : null;

        try {
            $token = token_provider::get_token($userid);
            $result = $client->chat($systemurl, $token, $message, $courseparam, $conversationid, $toolname);
        } catch (rag_exception $e) {
            // The cached token may have been revoked/expired server-side: drop it,
            // mint a fresh one and retry exactly once before giving up.
            token_provider::forget_cached_token($userid);
            try {
                $token = token_provider::get_token($userid);
                $result = $client->chat($systemurl, $token, $message, $courseparam, $conversationid, $toolname);
            } catch (rag_exception $retry) {
                self::log_failure($userid, $context, 'rag_error');
                throw $retry;
            }
        }

        $answerhtml = markdown_renderer::render($result['answer'], $context);

        $newconversationid = $result['conversation_id'] ?? $conversationid;
        if (!empty($newconversationid)) {
            conversation_repository::upsert(
                $userid,
                (string) $newconversationid,
                $courseid,
                $message
            );
        }

        \block_elediaaitutor\event\response_received::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['sources' => count($result['sources']), 'iserror' => (int) $result['iserror']],
        ])->trigger();

        return [
            'answerhtml' => $answerhtml,
            'answermarkdown' => $result['answer'],
            'conversationid' => $newconversationid !== null ? (string) $newconversationid : null,
            'sources' => $result['sources'],
            'iserror' => $result['iserror'],
        ];
    }

    /**
     * Record a RAG failure as an audit event (no secrets, no message content).
     *
     * @param int $userid The acting user id.
     * @param context $context The context.
     * @param string $reason Short non-sensitive reason code.
     * @return void
     */
    private static function log_failure(int $userid, context $context, string $reason): void {
        \block_elediaaitutor\event\rag_request_failed::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['reason' => $reason],
        ])->trigger();
    }
}
