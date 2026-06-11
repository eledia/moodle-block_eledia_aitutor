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
     * @param string|null $answerstyle Effective answer style (explain|hint|quiz), already
     *                                 validated and lock-enforced by the caller.
     * @return array{answerhtml: string, answermarkdown: string, conversationid: ?string, sources: array, iserror: bool}
     * @throws \moodle_exception On validation, configuration or RAG failure.
     */
    public static function send(
        int $userid,
        string $message,
        ?int $courseid,
        ?string $conversationid,
        context $context,
        ?rag_client $client = null,
        ?string $answerstyle = null
    ): array {
        global $CFG;

        // First-use consent gate: no message ever leaves Moodle before the user
        // has acknowledged the privacy guidelines (documented acknowledgement).
        consent::require_consent($userid);

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

        // The consent flag is only transmitted when the admin has declared the
        // RAG server memory-capable (memory opt-in tool configured); servers
        // without memory support never receive it.
        $ltmflag = security::memory_optin_tool_name() !== '' ? ltm::is_enabled($userid) : null;
        $userlang = current_language();

        try {
            $token = token_provider::get_token($userid);
            $result = $client->chat($systemurl, $token, $message, $courseparam, $conversationid, $toolname,
                $ltmflag, $answerstyle, $userlang);
        } catch (rag_exception $e) {
            // The cached token may have been revoked/expired server-side: drop it,
            // mint a fresh one and retry exactly once before giving up.
            token_provider::forget_cached_token($userid);
            try {
                $token = token_provider::get_token($userid);
                $result = $client->chat($systemurl, $token, $message, $courseparam, $conversationid, $toolname,
                    $ltmflag, $answerstyle, $userlang);
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

        // Opt-in question analytics (question text only — never the answer).
        // The server-supplied topic and the primary cited source anchor the
        // hotspot clustering. Never let an analytics hiccup break the chat.
        try {
            $primary = $result['sources'][0] ?? null;
            $sourcetitle = is_array($primary) && !empty($primary['title']) ? (string) $primary['title'] : null;
            $cmid = is_array($primary) ? self::extract_cmid((string) ($primary['url'] ?? '')) : null;
            question_log::log($userid, $courseid, $message, !empty($result['sources']), $answerstyle,
                $result['topic'] ?? null, $sourcetitle, $cmid);
        } catch (\moodle_exception $e) {
            debugging('block_elediaaitutor: question analytics logging failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }

        return [
            'answerhtml' => $answerhtml,
            'answermarkdown' => $result['answer'],
            'conversationid' => $newconversationid !== null ? (string) $newconversationid : null,
            'sources' => $result['sources'],
            'iserror' => $result['iserror'],
        ];
    }

    /**
     * Resolve a course module id from a primary-source URL.
     *
     * Only URLs on this site that follow the /mod/<name>/view.php?id=N pattern
     * resolve; anything else (external links, non-module pages) returns null.
     *
     * @param string $url The source URL.
     * @return int|null The cmid, or null when not a local module URL.
     */
    private static function extract_cmid(string $url): ?int {
        global $CFG;

        if ($url === '') {
            return null;
        }
        $site = parse_url($CFG->wwwroot);
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['host'] ?? '') !== ($site['host'] ?? '')) {
            return null;
        }
        if (!str_contains($parts['path'] ?? '', '/mod/')) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        return isset($query['id']) ? (int) $query['id'] : null;
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
