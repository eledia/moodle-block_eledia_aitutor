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

use context_course;
use navigation_node;

/**
 * Unit tests for the plugin callbacks in lib.php.
 *
 * @package     block_elediaaitutor
 * @covers      ::block_elediaaitutor_extend_navigation_course
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Load the plugin lib.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/elediaaitutor/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * The course navigation link appears only with analytics enabled and the
     * viewreports capability.
     */
    public function test_extend_navigation_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Analytics disabled (default): no link, even for teachers.
        $this->setUser($teacher);
        $node = new navigation_node(['text' => 'course']);
        block_elediaaitutor_extend_navigation_course($node, $course, $context);
        $this->assertFalse($node->has_children());

        set_config('enableanalytics', 1, 'block_elediaaitutor');

        // Teacher with analytics enabled: link present and pointing at the report.
        $node = new navigation_node(['text' => 'course']);
        block_elediaaitutor_extend_navigation_course($node, $course, $context);
        $report = $node->find('elediaaitutorreport', navigation_node::TYPE_SETTING);
        $this->assertNotEmpty($report);
        $this->assertStringContainsString('/blocks/elediaaitutor/report.php', $report->action->out(false));
        $this->assertStringContainsString('courseid=' . $course->id, $report->action->out(false));

        // Student: no capability, no link.
        $this->setUser($student);
        $node = new navigation_node(['text' => 'course']);
        block_elediaaitutor_extend_navigation_course($node, $course, $context);
        $this->assertFalse($node->has_children());
    }
}
