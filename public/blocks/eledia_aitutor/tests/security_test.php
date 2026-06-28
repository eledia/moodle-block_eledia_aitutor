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

namespace block_eledia_aitutor;

use block_eledia_aitutor\local\security;

/**
 * Unit tests for the security/configuration helper.
 *
 * @package     block_eledia_aitutor
 * @covers      \block_eledia_aitutor\local\security
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class security_test extends \advanced_testcase {
    /**
     * A valid HTTPS RAG URL is accepted.
     */
    public function test_validated_rag_url_accepts_https(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        $url = security::validated_rag_url();
        $this->assertStringStartsWith('https://rag.example.com', $url->out(false));
    }

    /**
     * MCP starts disabled so the tutor can run as a plain LLM/RAG chat.
     */
    public function test_mcp_is_optional_by_default(): void {
        $this->resetAfterTest();
        $this->assertFalse(security::mcp_enabled());

        set_config('enablemcp', 1, 'block_eledia_aitutor');
        $this->assertTrue(security::mcp_enabled());
    }

    /**
     * A missing RAG URL throws.
     */
    public function test_validated_rag_url_missing_throws(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', '', 'block_eledia_aitutor');
        $this->expectException(\moodle_exception::class);
        security::validated_rag_url();
    }

    /**
     * A plain HTTP URL is rejected unless insecure transport is allowed.
     */
    public function test_validated_rag_url_rejects_http_by_default(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', 'http://rag.example.com/mcp', 'block_eledia_aitutor');
        $this->expectException(\moodle_exception::class);
        security::validated_rag_url();
    }

    /**
     * HTTP is accepted only when the admin opts in.
     */
    public function test_validated_rag_url_allows_http_when_opted_in(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', 'http://localhost:8080/mcp', 'block_eledia_aitutor');
        set_config('allowinsecuretransport', 1, 'block_eledia_aitutor');
        $url = security::validated_rag_url();
        $this->assertStringStartsWith('http://localhost', $url->out(false));
    }

    /**
     * A non-HTTP scheme (SSRF vector) is rejected.
     */
    public function test_validated_rag_url_rejects_other_schemes(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', 'file:///etc/passwd', 'block_eledia_aitutor');
        set_config('allowinsecuretransport', 1, 'block_eledia_aitutor');
        $this->expectException(\moodle_exception::class);
        security::validated_rag_url();
    }

    /**
     * Empty messages are rejected.
     */
    public function test_validate_message_rejects_empty(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        security::validate_message('   ');
    }

    /**
     * Over-long messages are rejected.
     */
    public function test_validate_message_enforces_length(): void {
        $this->resetAfterTest();
        set_config('maxmessagelength', 10, 'block_eledia_aitutor');
        $this->expectException(\moodle_exception::class);
        security::validate_message(str_repeat('a', 11));
    }

    /**
     * The rate limiter trips after the configured number of messages.
     */
    public function test_rate_limit_trips(): void {
        $this->resetAfterTest();
        set_config('ratelimitperminute', 3, 'block_eledia_aitutor');
        for ($i = 0; $i < 3; $i++) {
            security::enforce_rate_limit(123);
        }
        $this->expectException(\moodle_exception::class);
        security::enforce_rate_limit(123);
    }

    /**
     * A zero limit disables rate limiting.
     */
    public function test_rate_limit_disabled(): void {
        $this->resetAfterTest();
        set_config('ratelimitperminute', 0, 'block_eledia_aitutor');
        for ($i = 0; $i < 50; $i++) {
            security::enforce_rate_limit(456);
        }
        $this->assertTrue(true); // Reached without exception.
    }
}
