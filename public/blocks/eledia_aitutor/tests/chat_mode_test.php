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

use PHPUnit\Framework\Attributes\CoversClass;
use block_eledia_aitutor\local\chat_mode;

/**
 * Tests for the grounded / LLM-only / unavailable mode resolution.
 *
 * Ingestion availability is driven through local_ragingest's per-course
 * marking (the plugin is present in this tree); the admin gate via the
 * allowllmonly setting.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_eledia_aitutor\local\chat_mode::class)]
final class chat_mode_test extends \advanced_testcase {
    /**
     * Skip if the ingestion plugin isn't installed (it should be in this tree).
     */
    protected function require_ragingest(): void {
        if (!class_exists('\\local_ragingest\\course_gate')) {
            $this->markTestSkipped('local_ragingest is not installed.');
        }
    }

    /**
     * Create a course and (optionally) mark it for ingestion via its category.
     *
     * @param bool $ingested Whether to mark the course's category for ingestion.
     * @return int The course id.
     */
    private function make_course(bool $ingested): int {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', $ingested ? (string) $cat->id : '', 'local_ragingest');
        // ingestion_available() requires BOTH the gate (the category above) AND a recorded
        // ingestion state, so mark the course ingested through ragingest's own API.
        if ($ingested && class_exists('\\local_ragingest\\course_state')) {
            \local_ragingest\course_state::set_ingested((int) $course->id, true);
        }
        return (int) $course->id;
    }

    /**
     * The full decision matrix.
     */
    public function test_resolution_matrix(): void {
        $this->resetAfterTest();
        $this->require_ragingest();

        $ingested = (object) ['ragmode' => chat_mode::MODE_GROUNDED];
        $llmpref = (object) ['ragmode' => chat_mode::MODE_LLMONLY];

        // LLM-only allowed.
        set_config('allowllmonly', 1, 'block_eledia_aitutor');
        $cid = $this->make_course(true);
        $this->assertSame(chat_mode::MODE_GROUNDED, chat_mode::resolve($cid, $ingested));
        $this->assertSame(chat_mode::MODE_LLMONLY, chat_mode::resolve($cid, $llmpref));

        // Allowed + not ingested → forced LLM-only regardless of instance choice.
        $cidoff = $this->make_course(false);
        $this->assertSame(chat_mode::MODE_LLMONLY, chat_mode::resolve($cidoff, $ingested));

        // LLM-only disallowed.
        set_config('allowllmonly', 0, 'block_eledia_aitutor');
        $cid2 = $this->make_course(true);
        // Instance asked for LLM-only, but it's disallowed → grounded.
        $this->assertSame(chat_mode::MODE_GROUNDED, chat_mode::resolve($cid2, $llmpref));
        // Disallowed + not ingested → unavailable.
        $cidoff2 = $this->make_course(false);
        $this->assertSame(chat_mode::MODE_UNAVAILABLE, chat_mode::resolve($cidoff2, $ingested));
    }

    /**
     * rag_enabled_for maps modes to the wire flag.
     */
    public function test_rag_enabled_for(): void {
        $this->assertTrue(chat_mode::rag_enabled_for(chat_mode::MODE_GROUNDED));
        $this->assertFalse(chat_mode::rag_enabled_for(chat_mode::MODE_LLMONLY));
        $this->assertNull(chat_mode::rag_enabled_for(chat_mode::MODE_UNAVAILABLE));
    }

    /**
     * is_llm_allowed reflects the admin setting (default allowed).
     */
    public function test_is_llm_allowed(): void {
        $this->resetAfterTest();
        set_config('allowllmonly', 1, 'block_eledia_aitutor');
        $this->assertTrue(chat_mode::is_llm_allowed());
        set_config('allowllmonly', 0, 'block_eledia_aitutor');
        $this->assertFalse(chat_mode::is_llm_allowed());
    }
}
