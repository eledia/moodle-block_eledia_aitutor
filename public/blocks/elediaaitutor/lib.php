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

/**
 * Serve per-instance branding files (logo / conversation avatar) stored in the
 * block context. The images are non-sensitive branding shown to every learner
 * who can see the block, so any logged-in user may fetch them.
 *
 * @param stdClass $course Course (or site) record.
 * @param stdClass $birecord_or_cm The block instance record.
 * @param context $context The block context.
 * @param string $filearea The requested file area.
 * @param array $args The file path/name args.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Serving options.
 * @return void Sends the file and exits, or returns false on failure.
 */
function block_elediaaitutor_pluginfile($course, $birecord_or_cm, $context, $filearea, $args,
        $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_BLOCK) {
        send_file_not_found();
    }

    $allowed = [
        \block_elediaaitutor\local\branding::INSTANCE_LOGO_FILEAREA,
        \block_elediaaitutor\local\branding::INSTANCE_AVATAR_FILEAREA,
    ];
    if (!in_array($filearea, $allowed, true)) {
        send_file_not_found();
    }

    require_login();

    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = $fs->get_file($context->id, 'block_elediaaitutor', $filearea, 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
