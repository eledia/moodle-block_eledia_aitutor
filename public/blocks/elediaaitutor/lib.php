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
 * Plugin callbacks for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the tutor question-analytics report to the course navigation.
 *
 * The link appears in the course's secondary navigation ("More" menu) for
 * users holding block/elediaaitutor:viewreports while question analytics is
 * enabled site-wide — the same conditions the report page itself enforces.
 *
 * @param navigation_node $navigation The course navigation node to extend.
 * @param stdClass $course The course record.
 * @param context_course $context The course context.
 * @return void
 */
function block_elediaaitutor_extend_navigation_course(navigation_node $navigation, stdClass $course,
        context_course $context): void {
    if (!\block_elediaaitutor\local\question_log::is_enabled()
            || !has_capability('block/elediaaitutor:viewreports', $context)) {
        return;
    }

    $navigation->add(
        get_string('report_link', 'block_elediaaitutor'),
        new moodle_url('/blocks/elediaaitutor/report.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'elediaaitutorreport',
        new pix_icon('i/report', '')
    );
}
