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
use block_eledia_aitutor\local\widget;

/**
 * Tests for the mode-aware widget::config_error() gate.
 *
 * The MCP connector (webservice_elediamcp) + a configured external service are
 * required only for GROUNDED answers (which call back into Moodle); LLM-only
 * mode requires neither — only the RAG/Tutor server URL, which every mode uses.
 * The connector-absent branch is exercised by the isolated Behat run (the
 * connector class genuinely does not exist there), since it is present in this
 * tree.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_eledia_aitutor\local\widget::class)]
final class widget_test extends \advanced_testcase {
    /**
     * Skip if the ingestion plugin isn't installed (needed to force grounded mode).
     */
    protected function require_ragingest(): void {
        if (!class_exists('\\local_ragingest\\course_gate')) {
            $this->markTestSkipped('local_ragingest is not installed.');
        }
    }

    /**
     * Create a course marked ingested so it resolves to grounded mode.
     *
     * @return int The course id.
     */
    private function make_ingested_course(): int {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_ragingest');
        \local_ragingest\course_state::set_ingested((int) $course->id, true);
        return (int) $course->id;
    }

    /**
     * A missing RAG/Tutor server URL is a fatal config error in every mode.
     */
    public function test_missing_rag_url_errors(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', '', 'block_eledia_aitutor');
        $this->assertSame(
            get_string('error_rag_url_missing', 'block_eledia_aitutor'),
            widget::config_error(0)
        );
    }

    /**
     * LLM-only mode needs neither the connector nor a service: healthy with just
     * the RAG URL, even with no MCP service selected.
     */
    public function test_llmonly_needs_no_connector_or_service(): void {
        $this->resetAfterTest();
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        set_config('allowllmonly', 1, 'block_eledia_aitutor');
        set_config('mcpserviceid', 0, 'block_eledia_aitutor');
        // Global chat with no ingestion endpoint resolves to LLM-only.
        set_config('rag_endpoint_url', '', 'local_ragingest');
        $this->assertSame(
            chat_mode::MODE_LLMONLY,
            chat_mode::resolve(0, (object) ['ragmode' => chat_mode::MODE_GROUNDED])
        );
        $this->assertNull(widget::config_error(0));
    }

    /**
     * Grounded mode requires a configured external service (the connector itself
     * is present in this tree, so this exercises the service-id branch).
     */
    public function test_grounded_requires_service(): void {
        $this->resetAfterTest();
        $this->require_ragingest();
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_eledia_aitutor');
        set_config('allowllmonly', 1, 'block_eledia_aitutor');
        $courseid = $this->make_ingested_course();
        $grounded = ['ragmode' => chat_mode::MODE_GROUNDED];

        // Sanity: this course resolves to grounded.
        $this->assertSame(
            chat_mode::MODE_GROUNDED,
            chat_mode::resolve($courseid, (object) $grounded)
        );

        // No external service selected → a fatal config error for grounded.
        set_config('mcpserviceid', 0, 'block_eledia_aitutor');
        $this->assertSame(
            get_string('error_service_not_configured', 'block_eledia_aitutor'),
            widget::config_error($courseid, $grounded)
        );

        // Service selected → healthy.
        set_config('mcpserviceid', 1, 'block_eledia_aitutor');
        $this->assertNull(widget::config_error($courseid, $grounded));
    }
}
