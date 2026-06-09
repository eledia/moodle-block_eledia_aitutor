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

use block_elediaaitutor\local\http\transport;

/**
 * A scripted {@see transport} for unit tests.
 *
 * Captures the last request and returns a canned response so the RAG client can
 * be exercised without any network access.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_transport implements transport {
    /** @var array The canned response to return. */
    public array $response;

    /** @var string|null The body of the last request received. */
    public ?string $lastbody = null;

    /** @var array|null The headers of the last request received. */
    public ?array $lastheaders = null;

    /** @var string|null The URL of the last request received. */
    public ?string $lasturl = null;

    /**
     * Constructor.
     *
     * @param array $response The response array to return from post().
     */
    public function __construct(array $response) {
        $this->response = $response;
    }

    /**
     * Build a successful JSON response transport.
     *
     * @param array $result The JSON-RPC result member.
     * @param string $contenttype Response content type.
     * @return self
     */
    public static function json_result(array $result, string $contenttype = 'application/json'): self {
        $body = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => $result]);
        return new self([
            'status' => 200,
            'headers' => ['content-type' => $contenttype],
            'body' => $body,
            'error' => '',
        ]);
    }

    /**
     * Build an SSE response transport wrapping a JSON-RPC result.
     *
     * @param array $result The JSON-RPC result member.
     * @return self
     */
    public static function sse_result(array $result): self {
        $frame = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => $result]);
        $body = "event: message\ndata: {\"jsonrpc\":\"2.0\",\"method\":\"progress\"}\n\n"
            . "event: message\ndata: " . $frame . "\n\n";
        return new self([
            'status' => 200,
            'headers' => ['Content-Type' => 'text/event-stream; charset=utf-8'],
            'body' => $body,
            'error' => '',
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $url The URL.
     * @param string[] $headers The headers.
     * @param string $body The body.
     * @param int $timeout The timeout.
     * @return array
     */
    public function post(string $url, array $headers, string $body, int $timeout): array {
        $this->lasturl = $url;
        $this->lastheaders = $headers;
        $this->lastbody = $body;
        return $this->response;
    }

    /**
     * Decode the last request body as an array.
     *
     * @return array
     */
    public function last_payload(): array {
        return json_decode($this->lastbody ?? '[]', true) ?: [];
    }
}
