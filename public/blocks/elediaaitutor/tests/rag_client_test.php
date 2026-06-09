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

namespace block_elediaaitutor;

use block_elediaaitutor\local\rag_client;
use block_elediaaitutor\local\rag_exception;
use moodle_url;

/**
 * Unit tests for the RAG MCP client.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\rag_client
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rag_client_test extends \advanced_testcase {
    /**
     * Load the test transport helper.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/elediaaitutor/tests/fake_transport.php');
        parent::setUpBeforeClass();
    }

    /**
     * Build a client over a fake transport.
     *
     * @param fake_transport $transport The transport.
     * @return rag_client
     */
    private function client(fake_transport $transport): rag_client {
        return new rag_client(new moodle_url('https://rag.example.com/mcp'), null, $transport, 30);
    }

    /**
     * The request payload is a well-formed tools/call with the expected args.
     */
    public function test_chat_builds_tools_call_payload(): void {
        $this->resetAfterTest();
        $transport = fake_transport::json_result([
            'structuredContent' => ['answer' => 'Hello', 'conversation_id' => 'c-1'],
        ]);
        $client = $this->client($transport);

        $client->chat('https://moodle.example.com', 'SECRET-TOKEN', 'Hi there',
            '42', null, 'tutor_chat');

        $payload = $transport->last_payload();
        $this->assertSame('2.0', $payload['jsonrpc']);
        $this->assertSame('tools/call', $payload['method']);
        $this->assertSame('tutor_chat', $payload['params']['name']);
        $args = $payload['params']['arguments'];
        $this->assertSame('https://moodle.example.com', $args['system_url']);
        $this->assertSame('SECRET-TOKEN', $args['moodle_token']);
        $this->assertSame('Hi there', $args['user_message']);
        $this->assertSame('42', $args['course_id']);
        $this->assertArrayNotHasKey('conversation_id', $args);

        // Accept header advertises both JSON and SSE.
        $accept = implode("\n", $transport->lastheaders);
        $this->assertStringContainsString('text/event-stream', $accept);
    }

    /**
     * Structured content is normalised into answer/conversation/sources.
     */
    public function test_chat_normalises_structured_content(): void {
        $this->resetAfterTest();
        $transport = fake_transport::json_result([
            'structuredContent' => [
                'answer' => 'You are enrolled in 3 courses.',
                'conversation_id' => 'conv-99',
                'sources' => [
                    ['title' => 'My courses', 'url' => 'https://moodle.example.com/my', 'snippet' => 'Course list'],
                    'Plain source string',
                ],
            ],
        ]);
        $result = $this->client($transport)->chat('https://m', 't', 'Which courses?', null, null, 'tutor_chat');

        $this->assertSame('You are enrolled in 3 courses.', $result['answer']);
        $this->assertSame('conv-99', $result['conversation_id']);
        $this->assertCount(2, $result['sources']);
        $this->assertSame('My courses', $result['sources'][0]['title']);
        $this->assertSame('Plain source string', $result['sources'][1]['title']);
        $this->assertFalse($result['iserror']);
    }

    /**
     * A text content payload (no structured content) is treated as the answer.
     */
    public function test_chat_falls_back_to_text_content(): void {
        $this->resetAfterTest();
        $transport = fake_transport::json_result([
            'content' => [
                ['type' => 'text', 'text' => '# Heading\n\nSome **markdown**.'],
            ],
        ]);
        $result = $this->client($transport)->chat('https://m', 't', 'Q', null, null, 'tutor_chat');
        $this->assertStringContainsString('markdown', $result['answer']);
        $this->assertNull($result['conversation_id']);
    }

    /**
     * Conversation id is forwarded on follow-up turns.
     */
    public function test_chat_forwards_conversation_id(): void {
        $this->resetAfterTest();
        $transport = fake_transport::json_result(['structuredContent' => ['answer' => 'ok']]);
        $this->client($transport)->chat('https://m', 't', 'Q', null, 'prev-conv', 'tutor_chat');
        $args = $transport->last_payload()['params']['arguments'];
        $this->assertSame('prev-conv', $args['conversation_id']);
    }

    /**
     * SSE responses are parsed and the final JSON-RPC frame is used.
     */
    public function test_chat_parses_sse_stream(): void {
        $this->resetAfterTest();
        $transport = fake_transport::sse_result([
            'structuredContent' => ['answer' => 'Streamed answer', 'conversation_id' => 's-1'],
        ]);
        $result = $this->client($transport)->chat('https://m', 't', 'Q', null, null, 'tutor_chat');
        $this->assertSame('Streamed answer', $result['answer']);
        $this->assertSame('s-1', $result['conversation_id']);
    }

    /**
     * A JSON-RPC error is surfaced as a rag_exception (no detail leaked to user).
     */
    public function test_chat_jsonrpc_error_throws(): void {
        $this->resetAfterTest();
        $transport = new fake_transport([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32000, 'message' => 'boom']]),
            'error' => '',
        ]);
        $this->expectException(rag_exception::class);
        $this->client($transport)->chat('https://m', 't', 'Q', null, null, 'tutor_chat');
    }

    /**
     * A transport-level error throws.
     */
    public function test_chat_transport_error_throws(): void {
        $this->resetAfterTest();
        $transport = new fake_transport([
            'status' => 0, 'headers' => [], 'body' => '', 'error' => 'Could not resolve host',
        ]);
        $this->expectException(rag_exception::class);
        $this->client($transport)->chat('https://m', 't', 'Q', null, null, 'tutor_chat');
    }

    /**
     * A non-2xx HTTP status throws.
     */
    public function test_chat_http_error_throws(): void {
        $this->resetAfterTest();
        $transport = new fake_transport([
            'status' => 502, 'headers' => ['content-type' => 'text/plain'], 'body' => 'bad gateway', 'error' => '',
        ]);
        $this->expectException(rag_exception::class);
        $this->client($transport)->chat('https://m', 't', 'Q', null, null, 'tutor_chat');
    }

    /**
     * History tool results are normalised into role/content rows.
     */
    public function test_get_history_normalises_messages(): void {
        $this->resetAfterTest();
        $transport = fake_transport::json_result([
            'structuredContent' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                    ['role' => 'assistant', 'content' => 'Hi!'],
                    ['role' => 'system', 'content' => ''], // Dropped (empty).
                ],
            ],
        ]);
        $messages = $this->client($transport)->get_history('https://m', 't', 'conv-1', 'tutor_get_history');
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('assistant', $messages[1]['role']);
    }
}
