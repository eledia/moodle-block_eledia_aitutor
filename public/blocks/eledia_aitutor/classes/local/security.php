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

use cache;
use moodle_exception;
use moodle_url;

/**
 * Security and configuration helper for the eLeDia.ai Tutor block.
 *
 * Centralises plugin configuration access, outgoing-URL (SSRF) validation,
 * message-length enforcement and per-user rate limiting. Keeping these concerns
 * in one auditable place keeps the external functions and the RAG client small
 * and easy to review.
 *
 * The configured RAG server URL is the *only* host the block ever talks to:
 * users can never supply or influence the destination URL, which is the primary
 * SSRF control.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security {
    /** @var string Configuration component name. */
    public const CONFIG_COMPONENT = 'block_eledia_aitutor';

    /**
     * Read a plugin configuration value with a default fallback.
     *
     * @param string $name Configuration setting name.
     * @param mixed $default Default value returned when the setting is unset.
     * @return mixed
     */
    public static function get_config(string $name, mixed $default = null): mixed {
        $value = get_config(self::CONFIG_COMPONENT, $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    /**
     * The configured RAG/Tutor MCP server endpoint, trimmed.
     *
     * @return string Empty string when unconfigured.
     */
    public static function rag_server_url(): string {
        return trim((string) self::get_config('ragserverurl', ''));
    }

    /**
     * The configured RAG chat tool name.
     *
     * @return string
     */
    public static function chat_tool_name(): string {
        return trim((string) self::get_config('chattoolname', 'tutor_chat'));
    }

    /**
     * The configured RAG history tool name (optional feature).
     *
     * @return string Empty string when history is not configured.
     */
    public static function history_tool_name(): string {
        return trim((string) self::get_config('historytoolname', ''));
    }

    /**
     * The configured RAG delete tool name (optional feature).
     *
     * When set, deleting a conversation also removes it on the RAG server.
     *
     * @return string Empty string when server-side deletion is not configured.
     */
    public static function delete_tool_name(): string {
        return trim((string) self::get_config('deletetoolname', ''));
    }

    /**
     * The configured RAG user-level delete tool name (optional feature).
     *
     * When set, "delete all my data" requests are propagated as a single call
     * that erases everything the RAG server holds for the user (transcripts and
     * long-term memory) — complete even when Moodle no longer holds conversation
     * pointers. Preferred over the per-conversation delete tool.
     *
     * @return string Empty string when not configured.
     */
    public static function delete_user_tool_name(): string {
        return trim((string) self::get_config('deleteusertoolname', ''));
    }

    /**
     * The configured RAG question-reclustering tool name (optional feature).
     *
     * When set (and question analytics is enabled), a nightly task sends recent
     * logged questions per course to the RAG server and updates their canonical
     * topic labels, converging the hotspot analytics even when per-answer
     * labels drifted.
     *
     * @return string Empty string when not configured.
     */
    public static function recluster_tool_name(): string {
        return trim((string) self::get_config('reclustertoolname', ''));
    }

    /**
     * The configured RAG memory opt-in tool name (optional feature).
     *
     * A non-empty value declares that the RAG server supports long-term memory:
     * opt-in changes are synced through this tool, and every chat call carries
     * the user's current consent as the ltm_enabled argument. Empty means the
     * server has no memory support and no consent data is ever transmitted.
     *
     * @return string Empty string when not configured.
     */
    public static function memory_optin_tool_name(): string {
        return trim((string) self::get_config('memoryoptintoolname', ''));
    }

    /**
     * The configured MCP external service id used to mint user tokens.
     *
     * @return int Zero when unconfigured.
     */
    public static function mcp_service_id(): int {
        return (int) self::get_config('mcpserviceid', 0);
    }

    /**
     * Whether Moodle-MCP tools should be used for chat requests.
     *
     * When disabled, the tutor still sends regular chat/RAG requests but does
     * not mint or transmit user-scoped Moodle MCP tokens. This allows a pure
     * LLM/RAG setup without the optional webservice_elediamcp connector.
     *
     * @return bool
     */
    public static function mcp_enabled(): bool {
        return (int) self::get_config('enablemcp', 0) === 1;
    }

    /**
     * Outgoing HTTP timeout in seconds for RAG calls.
     *
     * @return int
     */
    public static function request_timeout(): int {
        return max(1, (int) self::get_config('requesttimeout', 30));
    }

    /**
     * Maximum accepted user message length, in characters.
     *
     * @return int
     */
    public static function max_message_length(): int {
        return max(1, (int) self::get_config('maxmessagelength', 4000));
    }

    /**
     * Lifetime, in seconds, of a provisioned user MCP token (0 = no expiry).
     *
     * @return int
     */
    public static function token_lifetime(): int {
        return max(0, (int) self::get_config('tokenlifetime', 3600));
    }

    /**
     * Whether global (non-course) chat is allowed.
     *
     * @return bool
     */
    public static function global_chat_enabled(): bool {
        return (int) self::get_config('enableglobalchat', 1) === 1;
    }

    /**
     * Whether course-context chat is allowed.
     *
     * @return bool
     */
    public static function course_chat_enabled(): bool {
        return (int) self::get_config('enablecoursechat', 1) === 1;
    }

    /**
     * Site-wide daily message limit per user (0 = unlimited).
     *
     * Block instances may override this (see send_message); the counter itself
     * is always global per user, since cost accrues per user, not per course.
     *
     * @return int
     */
    public static function daily_message_limit(): int {
        return max(0, (int) self::get_config('dailymessagelimit', 0));
    }

    /**
     * Whether streaming responses are enabled (when supported by the server).
     *
     * @return bool
     */
    public static function streaming_enabled(): bool {
        return (int) self::get_config('streamingenabled', 0) === 1;
    }

    /**
     * Logging verbosity: 0 = errors only, 1 = normal, 2 = verbose (no secrets ever).
     *
     * @return int
     */
    public static function logging_verbosity(): int {
        return (int) self::get_config('loggingverbosity', 1);
    }

    /**
     * Admin custom CSS (trusted; targets the widget's .eledia_aitutor-* classes),
     * or '' when unset.
     *
     * The per-token branding values, persona fields and other tutor settings are
     * resolved through the {@see registry} and {@see branding}, not via dedicated
     * getters here; this class keeps only the infrastructure/security settings.
     *
     * @return string
     */
    public static function custom_css(): string {
        $css = trim((string) self::get_config('customcss', ''));
        if ($css === '') {
            return '';
        }

        // The value is rendered inside a <style> element. CSS itself does not
        // need angle brackets, so remove them to prevent </style> breakouts.
        $css = str_replace(["\0", '<', '>'], '', $css);

        // Keep the setting intentionally small: no remote imports, no legacy
        // expression() payloads, no javascript: URLs.
        $css = preg_replace('/@import\b[^;]*(;|$)/i', '', $css) ?? '';
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css) ?? '';
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? '';

        return trim($css);
    }

    /**
     * Whether to bypass Moodle's cURL security helper for the RAG host.
     *
     * Off by default. Intended only for RAG servers on an internal network or a
     * local-development host (e.g. host.docker.internal), whose private address
     * or non-standard port would otherwise be rejected by the site's cURL
     * blocked-hosts / allowed-ports policy.
     *
     * @return bool
     */
    public static function allow_private_network(): bool {
        return (int) self::get_config('allowprivatenetwork', 0) === 1;
    }

    /**
     * Validate the *configured* RAG server URL and return it as a moodle_url.
     *
     * This guards against an admin pasting a malformed or non-HTTP(S) endpoint
     * and is the SSRF trust boundary: only this admin-set value is ever used as
     * a request destination. A plain http:// endpoint is rejected unless the
     * admin has explicitly opted into insecure transport.
     *
     * @return moodle_url
     * @throws moodle_exception When the URL is missing or unacceptable.
     */
    public static function validated_rag_url(): moodle_url {
        $raw = self::rag_server_url();
        if ($raw === '') {
            throw new moodle_exception('error_rag_url_missing', 'block_eledia_aitutor');
        }

        $parts = parse_url($raw);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new moodle_exception('error_rag_url_invalid', 'block_eledia_aitutor');
        }

        $scheme = strtolower($parts['scheme']);
        $allowinsecure = (int) self::get_config('allowinsecuretransport', 0) === 1;
        if ($scheme !== 'https' && !($scheme === 'http' && $allowinsecure)) {
            throw new moodle_exception('error_rag_url_insecure', 'block_eledia_aitutor');
        }

        // Moodle_url normalises and the downstream curl call runs through
        // Moodle's curl wrapper, which applies the site cURL security helper
        // (blocked hosts / ports) as defence in depth.
        return new moodle_url($raw);
    }

    /**
     * Validate and normalise an incoming user message.
     *
     * @param string $message Raw user message.
     * @return string Trimmed message.
     * @throws moodle_exception When empty or too long.
     */
    public static function validate_message(string $message): string {
        $message = trim($message);
        if ($message === '') {
            throw new moodle_exception('error_message_empty', 'block_eledia_aitutor');
        }
        if (\core_text::strlen($message) > self::max_message_length()) {
            throw new moodle_exception(
                'error_message_too_long',
                'block_eledia_aitutor',
                '',
                self::max_message_length()
            );
        }
        return $message;
    }

    /**
     * Enforce a simple per-user sliding-window rate limit on chat requests.
     *
     * Counters live in the {@see cache} application store so they survive across
     * requests and can be shared across a cluster. A limit of 0 disables it.
     *
     * @param int $userid The acting user id.
     * @return void
     * @throws moodle_exception When the per-minute limit is exceeded.
     */
    public static function enforce_rate_limit(int $userid): void {
        $perminute = max(0, (int) self::get_config('ratelimitperminute', 20));
        if ($perminute === 0) {
            return;
        }

        $cache = cache::make('block_eledia_aitutor', 'ratelimit');
        $window = (int) floor(time() / 60);
        $key = $userid . '_m_' . $window;
        $count = ((int) ($cache->get($key) ?: 0)) + 1;
        $cache->set($key, $count);

        if ($count > $perminute) {
            throw new moodle_exception(
                'error_rate_limited',
                'block_eledia_aitutor',
                '',
                60 - (time() % 60)
            );
        }
    }
}
