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

use block_eledia_aitutor\local\http\curl_transport;
use block_eledia_aitutor\local\http\transport;
use moodle_url;

/**
 * Server-side client for the external RAG/Tutor MCP server.
 *
 * Speaks MCP over Streamable HTTP: a single JSON-RPC 2.0 {@code tools/call}
 * request is POSTed to the configured endpoint, and the response is accepted as
 * either a plain JSON body or a {@code text/event-stream} (SSE) body, per the
 * MCP Streamable HTTP transport. The client normalises the tool result into a
 * predictable shape — answer markdown, an optional server conversation id and a
 * list of sources/citations — regardless of whether the server returns
 * structured content or a text payload.
 *
 * This class never has any knowledge of the Moodle user beyond the values it is
 * handed; the user-scoped Moodle MCP token is provisioned elsewhere
 * ({@see token_provider}) and passed in as a tool argument. The token and any
 * configured RAG authorization header are sent server-to-server only and are
 * never returned to the caller or logged.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rag_client {
    /** @var string MCP protocol version advertised on requests. */
    private const MCP_PROTOCOL_VERSION = '2025-06-18';

    /** @var moodle_url Validated RAG endpoint. */
    private moodle_url $endpoint;

    /** @var string[] Additional HTTP headers, including authorization when configured. */
    private array $authheaders;

    /** @var transport HTTP transport. */
    private transport $transport;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /**
     * Constructor.
     *
     * @param moodle_url $endpoint Validated RAG MCP endpoint.
     * @param string[]|null $authheaders Additional HTTP headers to send.
     * @param transport $transport HTTP transport.
     * @param int $timeout Request timeout in seconds.
     */
    public function __construct(moodle_url $endpoint, ?array $authheaders, transport $transport, int $timeout) {
        $this->endpoint = $endpoint;
        $this->authheaders = $authheaders ?? [];
        $this->transport = $transport;
        $this->timeout = $timeout;
    }

    /**
     * Build a client from plugin configuration and the default cURL transport.
     *
     * @param transport|null $transport Optional transport override (tests).
     * @return self
     * @throws \moodle_exception When the configured RAG URL is missing or invalid.
     */
    public static function create(?transport $transport = null): self {
        $endpoint = security::validated_rag_url();

        global $CFG;

        $authheaders = [];
        $method = (string) security::get_config('ragauthmethod', 'none');
        $tokenvalue = trim((string) security::get_config('ragauthtoken', ''));
        if ($tokenvalue !== '') {
            if ($method === 'bearer') {
                $authheaders[] = 'Authorization: Bearer ' . $tokenvalue;
            } else if ($method === 'header') {
                // Admin supplies one or more complete "Name: value" headers.
                $authheaders = array_values(array_filter(array_map('trim', preg_split('/\R/', $tokenvalue) ?: [])));
            }
        }

        $endpointparts = parse_url($endpoint->out(false));
        $wwwrootparts = parse_url($CFG->wwwroot);
        if (($endpointparts['host'] ?? '') === 'host.docker.internal' && !empty($wwwrootparts['host'])) {
            $host = $wwwrootparts['host'];
            if (!empty($wwwrootparts['port'])) {
                $host .= ':' . $wwwrootparts['port'];
            }
            $authheaders[] = 'Host: ' . $host;
        }

        return new self(
            $endpoint,
            $authheaders,
            $transport ?? new curl_transport(security::allow_private_network()),
            security::request_timeout()
        );
    }

    /**
     * Send a chat turn to the RAG/Tutor server.
     *
     * @param string $systemurl This Moodle site's wwwroot.
     * @param string $moodletoken User-scoped Moodle MCP token (server-to-server only).
     * @param string $usermessage The validated user message.
     * @param string|null $courseid Optional course context id.
     * @param string|null $conversationid Optional existing conversation id.
     * @param string $toolname Chat tool name to invoke.
     * @param bool|null $ltmenabled The user's long-term memory consent, or null
     *                              to omit the flag entirely (server without
     *                              memory support; strict schemas stay happy).
     * @param string|null $answerstyle Pedagogical answer style (explain|hint|quiz),
     *                                 or null/empty to omit (server default: explain).
     * @param string|null $userlang The user's Moodle language code (e.g. "de"),
     *                              or null/empty to omit.
     * @param bool|null $ragenabled Whether the agent may use its retrieval/
     *                              knowledge-base tool. When false the agent
     *                              answers from the model alone (LLM-only). Null
     *                              omits the flag (server default: enabled).
     * @param array|null $persona Structured persona (any of name/role/tone/
     *                            audience/instructions); empty/null sends none.
     * @return array{answer: string, conversation_id: ?string, sources: array, topic: ?string, answer_origin: string, confirmation: ?array, iserror: bool}
     * @throws rag_exception On transport or protocol failure.
     */
    public function chat(
        string $systemurl,
        string $moodletoken,
        string $usermessage,
        ?string $courseid,
        ?string $conversationid,
        string $toolname,
        ?bool $ltmenabled = null,
        ?string $answerstyle = null,
        ?string $userlang = null,
        ?bool $ragenabled = null,
        ?array $persona = null
    ): array {
        $arguments = [
            'system_url' => $systemurl,
            'user_message' => $usermessage,
        ];
        if ($moodletoken !== '') {
            $arguments['moodle_token'] = $moodletoken;
        }
        if ($courseid !== null && $courseid !== '') {
            $arguments['course_id'] = $courseid;
        }
        if ($conversationid !== null && $conversationid !== '') {
            $arguments['conversation_id'] = $conversationid;
        }
        if ($ltmenabled !== null) {
            $arguments['ltm_enabled'] = $ltmenabled;
        }
        if ($answerstyle !== null && $answerstyle !== '') {
            $arguments['answer_style'] = $answerstyle;
        }
        if ($userlang !== null && $userlang !== '') {
            $arguments['user_lang'] = $userlang;
        }
        if ($ragenabled !== null) {
            $arguments['rag_enabled'] = $ragenabled;
        }
        // Structured persona (name/role/tone/audience/instructions) — only the
        // populated sub-fields are sent; the server uses them as system-prompt
        // guidance for the tutor's voice. See docs/rag_server_spec.md A.1.
        if (!empty($persona)) {
            $arguments['persona'] = $persona;
        }

        $result = $this->call_tool($toolname, $arguments);
        return $this->normalise_tool_result($result);
    }

    /**
     * Load history for a conversation via the configured history tool.
     *
     * @param string $systemurl This Moodle site's wwwroot.
     * @param string $moodletoken User-scoped Moodle MCP token.
     * @param string $conversationid Conversation id to load.
     * @param string $toolname History tool name to invoke.
     * @return array List of {role, content, sources} message arrays.
     * @throws rag_exception On transport or protocol failure.
     */
    public function get_history(
        string $systemurl,
        string $moodletoken,
        string $conversationid,
        string $toolname
    ): array {
        $arguments = [
            'system_url' => $systemurl,
            'conversation_id' => $conversationid,
        ];
        if ($moodletoken !== '') {
            $arguments['moodle_token'] = $moodletoken;
        }

        $result = $this->call_tool($toolname, $arguments);

        $structured = $result['structuredContent'] ?? null;
        $messages = [];
        if (is_array($structured) && isset($structured['messages']) && is_array($structured['messages'])) {
            $messages = $structured['messages'];
        } else {
            // Fall back to parsing a JSON text payload.
            $decoded = json_decode($this->collect_text($result), true);
            if (is_array($decoded) && isset($decoded['messages']) && is_array($decoded['messages'])) {
                $messages = $decoded['messages'];
            }
        }

        $clean = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = (string) ($message['role'] ?? 'assistant');
            $content = (string) ($message['content'] ?? ($message['text'] ?? ''));
            if ($content === '') {
                continue;
            }
            // Per-message sources (same aliases/shape as live answers), so resumed
            // conversations render the same citation cards. Absent ⇒ none.
            $sources = [];
            foreach (['sources', 'citations', 'documents', 'references'] as $key) {
                if (isset($message[$key]) && is_array($message[$key])) {
                    $sources = $this->normalise_sources($message[$key]);
                    break;
                }
            }
            $clean[] = [
                'role' => $role === 'user' ? 'user' : 'assistant',
                'content' => $content,
                'sources' => $sources,
            ];
        }
        return $clean;
    }

    /**
     * Record the user's long-term memory consent on the RAG server.
     *
     * Sent immediately when the user toggles the opt-in. Per the integration
     * contract, enabled=false also instructs the server to erase any memory it
     * has already stored for the user (consent revocation = erasure).
     *
     * @param string $systemurl This Moodle site's wwwroot.
     * @param string $moodletoken User-scoped Moodle MCP token.
     * @param bool $enabled The user's consent state.
     * @param string $toolname Memory opt-in tool name to invoke.
     * @return void
     * @throws rag_exception On transport or protocol failure.
     */
    public function set_memory_optin(
        string $systemurl,
        string $moodletoken,
        bool $enabled,
        string $toolname
    ): void {
        $this->call_tool($toolname, [
            'system_url' => $systemurl,
            'moodle_token' => $moodletoken,
            'enabled' => $enabled,
        ]);
    }

    /**
     * Re-derive canonical topic labels for a batch of logged questions.
     *
     * A SITE-LEVEL service operation: the moodle_token belongs to the tutor's
     * auto-provisioned maintenance account ({@see service_user}) — never to a
     * real person — and exists so the server can authenticate the call exactly
     * like every other tool, with no shared transport secret required. The
     * supplied existing labels form the registry the server should classify
     * into.
     *
     * @param string $systemurl This Moodle site's wwwroot.
     * @param string $courseid The course the questions belong to.
     * @param string[] $existinglabels Current topic labels for the course.
     * @param array $questions List of ['id' => int, 'text' => string] entries.
     * @param string $toolname Recluster tool name to invoke.
     * @param string|null $moodletoken Maintenance-account MCP token, if available.
     * @return array<int,string> Map of question id => topic label.
     * @throws rag_exception On transport or protocol failure.
     */
    public function recluster_questions(
        string $systemurl,
        string $courseid,
        array $existinglabels,
        array $questions,
        string $toolname,
        ?string $moodletoken = null
    ): array {
        $args = [
            'system_url' => $systemurl,
            'course_id' => $courseid,
            'existing_labels' => array_values($existinglabels),
            'questions' => array_values($questions),
        ];
        if ($moodletoken !== null && $moodletoken !== '') {
            $args['moodle_token'] = $moodletoken;
        }
        $result = $this->call_tool($toolname, $args);

        $structured = $result['structuredContent'] ?? null;
        $topics = null;
        if (is_array($structured) && isset($structured['topics']) && is_array($structured['topics'])) {
            $topics = $structured['topics'];
        } else {
            $decoded = json_decode($this->collect_text($result), true);
            if (is_array($decoded) && isset($decoded['topics']) && is_array($decoded['topics'])) {
                $topics = $decoded['topics'];
            }
        }
        if ($topics === null) {
            throw new rag_exception('error_rag_bad_response', 'recluster: no topics in result');
        }

        $map = [];
        foreach ($topics as $entry) {
            if (!is_array($entry) || !isset($entry['id'])) {
                continue;
            }
            $topic = \core_text::substr(trim((string) ($entry['topic'] ?? '')), 0, 100);
            if ($topic !== '') {
                $map[(int) $entry['id']] = $topic;
            }
        }
        return $map;
    }

    /**
     * Ask the RAG server to delete ALL data it holds for the authenticated user.
     *
     * Covers every conversation/transcript and any long-term memory — complete
     * by definition, even for conversations Moodle no longer has pointers to.
     *
     * @param string $systemurl This Moodle site's wwwroot.
     * @param string $moodletoken User-scoped Moodle MCP token.
     * @param string $toolname User-level delete tool name to invoke.
     * @return void
     * @throws rag_exception On transport or protocol failure.
     */
    public function delete_user_data(
        string $systemurl,
        string $moodletoken,
        string $toolname
    ): void {
        $this->call_tool($toolname, [
            'system_url' => $systemurl,
            'moodle_token' => $moodletoken,
        ]);
    }

    /**
     * Ask the RAG server to delete a conversation it owns.
     *
     * Best-effort: throws {@see rag_exception} on transport/protocol failure so
     * the caller can log it, but the caller should still remove its local record.
     *
     * @param string $systemurl This Moodle site's wwwroot.
     * @param string $moodletoken User-scoped Moodle MCP token.
     * @param string $conversationid Conversation id to delete.
     * @param string $toolname Delete tool name to invoke.
     * @return void
     * @throws rag_exception On transport or protocol failure.
     */
    public function delete_conversation(
        string $systemurl,
        string $moodletoken,
        string $conversationid,
        string $toolname
    ): void {
        $this->call_tool($toolname, [
            'system_url' => $systemurl,
            'moodle_token' => $moodletoken,
            'conversation_id' => $conversationid,
        ]);
    }

    /**
     * Execute a single MCP tools/call request and return the JSON-RPC result.
     *
     * @param string $toolname Tool name.
     * @param array $arguments Tool arguments.
     * @return array The {@code result} member of the JSON-RPC response.
     * @throws rag_exception On any transport or protocol error.
     */
    private function call_tool(string $toolname, array $arguments): array {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => $toolname,
                'arguments' => $arguments,
            ],
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'MCP-Protocol-Version: ' . self::MCP_PROTOCOL_VERSION,
        ];
        $headers = array_merge($headers, $this->authheaders);

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = $this->transport->post($this->endpoint->out(false), $headers, (string) $body, $this->timeout);

        if ($response['error'] !== '') {
            throw new rag_exception('error_rag_unavailable', 'transport: ' . $response['error']);
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new rag_exception('error_rag_unavailable', 'http status ' . $response['status']);
        }

        $envelope = $this->decode_envelope($response);
        if (isset($envelope['error'])) {
            $code = $envelope['error']['code'] ?? '?';
            throw new rag_exception('error_rag_unavailable', 'jsonrpc error ' . $code);
        }
        if (!isset($envelope['result']) || !is_array($envelope['result'])) {
            throw new rag_exception('error_rag_bad_response', 'missing result');
        }

        return $envelope['result'];
    }

    /**
     * Decode the response body, accepting either JSON or an SSE event stream.
     *
     * @param array $response Transport response.
     * @return array Decoded JSON-RPC envelope.
     * @throws rag_exception When no decodable JSON-RPC message is present.
     */
    private function decode_envelope(array $response): array {
        $body = trim($response['body']);
        if ($body === '') {
            throw new rag_exception('error_rag_bad_response', 'empty body');
        }

        $contenttype = '';
        foreach ($response['headers'] as $name => $value) {
            if (strtolower((string) $name) === 'content-type') {
                $contenttype = strtolower((string) $value);
                break;
            }
        }

        $isstream = str_contains($contenttype, 'text/event-stream')
            || (str_starts_with($body, 'event:') || str_starts_with($body, 'data:'));

        if ($isstream) {
            $envelope = $this->parse_sse($body);
            if ($envelope !== null) {
                return $envelope;
            }
            throw new rag_exception('error_rag_bad_response', 'no json-rpc frame in sse stream');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new rag_exception('error_rag_bad_response', 'invalid json');
        }
        return $decoded;
    }

    /**
     * Extract the last JSON-RPC frame from an SSE body.
     *
     * Walks {@code data:} lines (concatenating multi-line data per the SSE spec)
     * and returns the final frame that parses as a JSON-RPC message — that is the
     * response to our request once any intermediate streaming events have passed.
     *
     * @param string $body Raw SSE body.
     * @return array|null Decoded envelope, or null when none found.
     */
    private function parse_sse(string $body): ?array {
        $found = null;
        $datalines = [];
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $lines[] = ''; // Sentinel to flush the final event.

        foreach ($lines as $line) {
            if ($line === '') {
                if ($datalines !== []) {
                    $payload = implode("\n", $datalines);
                    $datalines = [];
                    $decoded = json_decode($payload, true);
                    if (is_array($decoded) && (isset($decoded['result']) || isset($decoded['error']))) {
                        $found = $decoded;
                    }
                }
                continue;
            }
            if (str_starts_with($line, 'data:')) {
                $datalines[] = ltrim(substr($line, 5), ' ');
            }
        }
        return $found;
    }

    /**
     * Normalise an MCP tool result into the block's answer shape.
     *
     * Prefers {@code structuredContent} when the server provides it, otherwise
     * inspects the text content (treating it as JSON when it parses, else as
     * markdown). Recognises a range of common key names for the answer body,
     * conversation id and sources so the block works with differently-shaped
     * RAG servers without configuration.
     *
     * @param array $result The JSON-RPC result.
     * @return array{answer: string, conversation_id: ?string, sources: array, topic: ?string, answer_origin: string, confirmation: ?array, iserror: bool}
     */
    private function normalise_tool_result(array $result): array {
        $iserror = !empty($result['isError']);
        $structured = is_array($result['structuredContent'] ?? null) ? $result['structuredContent'] : null;
        $text = $this->collect_text($result);

        $payload = $structured;
        if ($payload === null && $text !== '') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $answer = '';
        $conversationid = null;
        $sources = [];
        $topic = null;
        $answerorigin = 'general';
        $confirmation = null;

        if (is_array($payload)) {
            foreach (['answer', 'text', 'message', 'response', 'content', 'output'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '') {
                    $answer = $payload[$key];
                    break;
                }
            }
            foreach (['conversation_id', 'conversationId', 'session_id', 'sessionId', 'thread_id'] as $key) {
                if (!empty($payload[$key]) && is_scalar($payload[$key])) {
                    $conversationid = (string) $payload[$key];
                    break;
                }
            }
            foreach (['sources', 'citations', 'documents', 'references'] as $key) {
                if (isset($payload[$key]) && is_array($payload[$key])) {
                    $sources = $this->normalise_sources($payload[$key]);
                    if (!empty($sources)) {
                        $answerorigin = 'rag';
                    }
                    break;
                }
            }
            if (!empty($payload['answer_origin']) && is_string($payload['answer_origin'])) {
                $candidate = clean_param($payload['answer_origin'], PARAM_ALPHA);
                if (in_array($candidate, ['rag', 'mcp', 'general'], true)) {
                    $answerorigin = $candidate;
                }
            }
            // Canonical topic label for analytics clustering (see the spec).
            foreach (['topic', 'subject'] as $key) {
                if (!empty($payload[$key]) && is_string($payload[$key])) {
                    $topic = \core_text::substr(trim($payload[$key]), 0, 100);
                    break;
                }
            }
            if (is_array($payload['confirmation'] ?? null) && !empty($payload['confirmation']['required'])) {
                $confirmation = [
                    'required' => true,
                    'yeslabel' => clean_param((string) ($payload['confirmation']['yeslabel'] ?? 'Ja'), PARAM_TEXT),
                    'nolabel' => clean_param((string) ($payload['confirmation']['nolabel'] ?? 'Nein'), PARAM_TEXT),
                    'yesmessage' => clean_param((string) ($payload['confirmation']['yesmessage'] ?? 'Ja'), PARAM_TEXT),
                    'nomessage' => clean_param((string) ($payload['confirmation']['nomessage'] ?? 'Nein'), PARAM_TEXT),
                ];
            }
        }

        if ($answer === '') {
            // No structured/JSON answer: fall back to the raw text content.
            $answer = $text;
        }
        if ($answer === '' && $iserror) {
            $answer = get_string('error_rag_tool_error', 'block_eledia_aitutor');
        }

        return [
            'answer' => $answer,
            'conversation_id' => $conversationid,
            'sources' => $sources,
            'topic' => $topic,
            'answer_origin' => $answerorigin,
            'confirmation' => $confirmation,
            'iserror' => $iserror,
        ];
    }

    /**
     * Concatenate the text parts of an MCP content array.
     *
     * @param array $result The JSON-RPC result.
     * @return string
     */
    private function collect_text(array $result): string {
        $parts = [];
        $content = $result['content'] ?? [];
        if (is_array($content)) {
            foreach ($content as $item) {
                if (is_array($item) && ($item['type'] ?? '') === 'text' && isset($item['text'])) {
                    $parts[] = (string) $item['text'];
                }
            }
        }
        return trim(implode("\n\n", $parts));
    }

    /**
     * Normalise a heterogeneous sources array into {title, url, snippet} rows.
     *
     * @param array $raw Raw sources from the RAG server.
     * @return array<int,array{title: string,url: string,snippet: string}>
     */
    private function normalise_sources(array $raw): array {
        $sources = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $sources[] = ['title' => $item, 'url' => '', 'snippet' => ''];
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $title = (string) ($item['title'] ?? ($item['name'] ?? ($item['source'] ?? '')));
            $url = clean_param((string) ($item['url'] ?? ($item['link'] ?? ($item['uri'] ?? ''))), PARAM_URL);
            $snippet = (string) ($item['snippet'] ?? ($item['text'] ?? ($item['excerpt'] ?? '')));
            if ($title === '' && $url === '' && $snippet === '') {
                continue;
            }
            if ($title === '') {
                $title = $url !== '' ? $url : get_string('source', 'block_eledia_aitutor');
            }
            $sources[] = ['title' => $title, 'url' => $url, 'snippet' => $snippet];
        }
        return $sources;
    }
}
