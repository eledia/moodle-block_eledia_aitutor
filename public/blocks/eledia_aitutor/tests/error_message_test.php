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

/**
 * Tests for the role-aware chat error message helper.
 *
 * @package    block_eledia_aitutor
 * @copyright  2026 eLeDia GmbH / LernHive
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace block_eledia_aitutor;

use block_eledia_aitutor\local\error_message;
use block_eledia_aitutor\local\rag_exception;

/**
 * Covers phase classification and the learner-vs-manager message gating.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(error_message::class)]
final class error_message_test extends \basic_testcase {
    public function test_rag_exception_is_rag_phase(): void {
        $this->assertSame('rag', error_message::phase(
            new rag_exception('error_rag_unavailable', 'http status 502')
        ));
    }

    public function test_mcp_and_config_and_generic_phases(): void {
        $this->assertSame('mcp', error_message::phase(
            new \moodle_exception('error_no_mcp_service', 'block_eledia_aitutor')
        ));
        $this->assertSame('mcp', error_message::phase(
            new \moodle_exception('tokenmissing', 'block_eledia_aitutor')
        ));
        $this->assertSame('config', error_message::phase(
            new \moodle_exception('error_rag_url_missing', 'block_eledia_aitutor')
        ));
        // A daily-limit style message must stay generic so it is not reworded.
        $this->assertSame('generic', error_message::phase(
            new \moodle_exception('dailylimitreached', 'block_eledia_aitutor')
        ));
    }

    public function test_learner_gets_neutral_key_without_detail(): void {
        $e = new rag_exception('error_rag_unavailable', 'http status 502');
        [$key, $a] = error_message::for_exception($e, false);
        $this->assertSame('error_rag_learner', $key);
        $this->assertNull($a);
    }

    public function test_manager_gets_detail_key_with_reason(): void {
        $e = new rag_exception('error_rag_unavailable', 'http status 502');
        [$key, $a] = error_message::for_exception($e, true);
        $this->assertSame('error_rag_detail', $key);
        $this->assertSame('http status 502', $a);
    }

    public function test_detail_falls_back_to_message_when_no_debuginfo(): void {
        $e = new \moodle_exception('error_no_mcp_service', 'block_eledia_aitutor');
        [$key, $a] = error_message::for_exception($e, true);
        $this->assertSame('error_mcp_detail', $key);
        $this->assertNotSame('', (string) $a);
    }

    public function test_all_referenced_strings_exist(): void {
        foreach (['rag', 'mcp', 'config', 'generic'] as $phase) {
            foreach (['learner', 'detail'] as $variant) {
                $this->assertTrue(
                    get_string_manager()->string_exists("error_{$phase}_{$variant}", 'block_eledia_aitutor'),
                    "Missing lang string error_{$phase}_{$variant}"
                );
            }
        }
    }
}
