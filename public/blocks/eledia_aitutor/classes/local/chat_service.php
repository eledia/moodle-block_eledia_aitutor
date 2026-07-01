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

namespace block_eledia_aitutor\local;

use context;

/**
 * Orchestrates a chat turn end to end.
 *
 * This is the single place that ties together token provisioning, the RAG call,
 * Markdown rendering, conversation bookkeeping and audit events, so the external
 * (AJAX) layer stays thin and the business logic is unit-testable with a faked
 * RAG client. Capability and context checks are the caller's responsibility.
 *
 * @package     block_eledia_aitutor
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
     * @param int|null $dailylimit Effective daily message limit (0 = unlimited);
     *                             null falls back to the site setting.
     * @param bool|null $ragenabled Whether the RAG agent may use its knowledge-base
     *                              tool (false = LLM-only); null omits the flag.
     * @param array|null $persona Structured persona to send to the RAG server
     *                            (name/role/tone/audience/instructions); null sends none.
     * @return array{answerhtml: string, answermarkdown: string, conversationid: ?string, sources: array, answerorigin: string, confirmation: ?array, iserror: bool}
     * @throws \moodle_exception On validation, configuration, quota or RAG failure.
     */
    public static function send(
        int $userid,
        string $message,
        ?int $courseid,
        ?string $conversationid,
        context $context,
        ?rag_client $client = null,
        ?string $answerstyle = null,
        ?int $dailylimit = null,
        ?bool $ragenabled = null,
        ?array $persona = null
    ): array {
        global $CFG;

        // A turn is "grounded" (retrieval + the Moodle MCP call-back) unless the
        // caller explicitly disabled it. Only grounded turns mint/refresh the
        // user-scoped MCP token; LLM-only turns ($ragenabled === false) never call
        // back, so they need no token and no connector. Legacy callers that omit
        // the flag (null) keep the historical grounded behaviour.
        $grounded = ($ragenabled !== false);

        // First-use consent gate: no message ever leaves Moodle before the user
        // has acknowledged the privacy guidelines (documented acknowledgement).
        consent::require_consent($userid);

        $message = security::validate_message($message);
        security::enforce_rate_limit($userid);

        // Daily quota (cost governance): enforced before any RAG call.
        $dailylimit ??= security::daily_message_limit();
        if ($dailylimit > 0) {
            usage::assert_within_limit($userid, $dailylimit);
        }

        \block_eledia_aitutor\event\message_sent::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['length' => \core_text::strlen($message), 'courseid' => (int) ($courseid ?: 0)],
        ])->trigger();

        try {
            $client ??= rag_client::create();
        } catch (\moodle_exception $e) {
            self::log_failure($userid, $context, self::failure_phase($e), $e, $courseid);
            throw $e;
        }
        $toolname = security::chat_tool_name();
        $systemurl = $CFG->wwwroot;
        $courseparam = $courseid ? (string) $courseid : null;

        // The consent flag is only transmitted when the admin has declared the
        // RAG server memory-capable (memory opt-in tool configured); servers
        // without memory support never receive it.
        $ltmflag = security::memory_optin_tool_name() !== '' ? ltm::is_enabled($userid) : null;
        $userlang = current_language();

        try {
            $token = self::moodle_token_for_user($userid, $grounded);
            $result = $client->chat(
                $systemurl,
                $token,
                $message,
                $courseparam,
                $conversationid,
                $toolname,
                $ltmflag,
                $answerstyle,
                $userlang,
                $ragenabled,
                $persona
            );
        } catch (rag_exception $e) {
            // The cached token may have been revoked/expired server-side: drop it,
            // mint a fresh one and retry exactly once before giving up. Only a
            // grounded turn ever used a token, so only it needs to forget one.
            if ($grounded) {
                token_provider::forget_cached_token($userid);
            }
            try {
                $token = self::moodle_token_for_user($userid, $grounded);
                $result = $client->chat(
                    $systemurl,
                    $token,
                    $message,
                    $courseparam,
                    $conversationid,
                    $toolname,
                    $ltmflag,
                    $answerstyle,
                    $userlang,
                    $ragenabled,
                    $persona
                );
            } catch (rag_exception $retry) {
                self::log_failure($userid, $context, 'rag_error', $retry, $courseid);
                throw $retry;
            } catch (\moodle_exception $retry) {
                self::log_failure($userid, $context, self::failure_phase($retry), $retry, $courseid);
                throw $retry;
            }
        } catch (\moodle_exception $e) {
            self::log_failure($userid, $context, self::failure_phase($e), $e, $courseid);
            throw $e;
        }

        if (!empty($result['iserror'])) {
            self::log_failure(
                $userid,
                $context,
                'tool_error',
                null,
                $courseid,
                'Tutor tool returned an error result.'
            );
        }

        // In LLM-only mode no retrieval happened (or must be treated as if it
        // hadn't): drop any sources a non-compliant server returned, so the UI
        // never shows a "grounded" badge and analytics record grounded=false.
        if ($ragenabled === false) {
            $result['sources'] = [];
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

        \block_eledia_aitutor\event\response_received::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['sources' => count($result['sources']), 'iserror' => (int) $result['iserror']],
        ])->trigger();

        // Count the successful turn against the daily quota (failed RAG calls
        // never reach this point and therefore never consume quota).
        usage::increment($userid);

        // Opt-in question analytics (question text only — never the answer).
        // The server-supplied topic and the primary cited source anchor the
        // hotspot clustering. Never let an analytics hiccup break the chat.
        try {
            $primary = $result['sources'][0] ?? null;
            $sourcetitle = is_array($primary) && !empty($primary['title']) ? (string) $primary['title'] : null;
            $cmid = is_array($primary) ? self::extract_cmid((string) ($primary['url'] ?? '')) : null;
            question_log::log(
                $userid,
                $courseid,
                $message,
                !empty($result['sources']),
                $answerstyle,
                $result['topic'] ?? null,
                $sourcetitle,
                $cmid
            );
        } catch (\moodle_exception $e) {
            debugging(
                'block_eledia_aitutor: question analytics logging failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        return [
            'answerhtml' => $answerhtml,
            'answermarkdown' => $result['answer'],
            'conversationid' => $newconversationid !== null ? (string) $newconversationid : null,
            'sources' => $result['sources'],
            'answerorigin' => $result['answer_origin'] ?? (!empty($result['sources']) ? 'rag' : 'general'),
            'confirmation' => $result['confirmation'] ?? null,
            'iserror' => $result['iserror'],
        ];
    }

    /**
     * Return the Moodle-MCP token for this user, or an empty string for an
     * LLM-only turn.
     *
     * Only a grounded turn calls back into Moodle, so only it mints a user-scoped
     * MCP token (which requires the webservice_elediamcp connector). An LLM-only
     * turn never calls back, so it needs no token and the connector is not
     * required — this is what lets the tutor answer in LLM-only mode without the
     * MCP plugin installed.
     *
     * @param int $userid The user id.
     * @param bool $grounded Whether this turn uses retrieval + the Moodle call-back.
     * @return string The token, or '' for an LLM-only turn.
     * @throws \moodle_exception When grounded but the connector/service is unavailable.
     */
    private static function moodle_token_for_user(int $userid, bool $grounded): string {
        if (!$grounded) {
            return '';
        }
        return token_provider::get_token($userid);
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
     * @param \Throwable|null $exception Optional exception with non-sensitive debug info.
     * @param int|null $courseid Course id, if known.
     * @param string|null $detail Optional non-sensitive detail.
     * @return void
     */
    private static function log_failure(
        int $userid,
        context $context,
        string $reason,
        ?\Throwable $exception = null,
        ?int $courseid = null,
        ?string $detail = null
    ): void {
        \block_eledia_aitutor\event\rag_request_failed::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['reason' => $reason],
        ])->trigger();

        diagnostics::record($userid, $context, $reason, $exception, $courseid, $detail);
    }

    /**
     * Classify an exception into a short admin-facing phase.
     *
     * @param \Throwable $exception The exception.
     * @return string
     */
    private static function failure_phase(\Throwable $exception): string {
        if ($exception instanceof rag_exception) {
            return 'rag';
        }
        if ($exception instanceof \moodle_exception) {
            $errorcode = (string) ($exception->errorcode ?? '');
            if (str_contains($errorcode, 'service') || str_contains($errorcode, 'token')) {
                return 'mcp';
            }
            if (str_contains($errorcode, 'config') || str_contains($errorcode, 'url')) {
                return 'configuration';
            }
        }
        return 'chat';
    }
}
