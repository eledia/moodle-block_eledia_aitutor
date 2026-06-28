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
 * Standalone chat page for the eLeDia.ai Tutor.
 *
 * Hosts the same widget the block renders, on its own page. Primary use case:
 * embedding the tutor in the Moodle App via a tool_mobile custom menu item
 * (the app opens site URLs with auto-login), where third-party blocks are not
 * rendered. Also usable in any browser as a focused, full-page chat.
 *
 * Parameters:
 * - courseid (optional): chat in that course's context (enrolment + capability
 *   enforced); omit or 0 for global chat at the system context.
 * - embedded (optional bool): use Moodle's chrome-less "embedded" page layout —
 *   pass 1 when opening inside the app or an iframe.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use block_eledia_aitutor\local\security;
use block_eledia_aitutor\local\widget;
use block_eledia_aitutor\output\shell;

require(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$embedded = optional_param('embedded', 0, PARAM_BOOL);

if ($courseid > 0 && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course, false);
    $context = \core\context\course::instance($course->id);
    $enabled = security::course_chat_enabled();
    $disabledstring = 'error_course_chat_disabled';
} else {
    require_login(null, false);
    $courseid = 0;
    $context = \core\context\system::instance();
    $enabled = security::global_chat_enabled();
    $disabledstring = 'error_global_chat_disabled';
}

if (isguestuser()) {
    throw new moodle_exception('noguest');
}
require_capability('block/eledia_aitutor:use', $context);

$useshell = !$embedded
    && $courseid === 0
    && shell::is_available()
    && has_capability('moodle/site:config', \core\context\system::instance());

$PAGE->set_url(new moodle_url(
    '/blocks/eledia_aitutor/view.php',
    ['courseid' => $courseid, 'embedded' => $embedded]
));
$PAGE->set_context($context);
$PAGE->set_pagelayout($embedded ? 'embedded' : 'standard');
if (!$embedded) {
    $PAGE->blocks->show_only_fake_blocks(true);
}
$PAGE->set_title(get_string('default_persona', 'block_eledia_aitutor'));
if ($courseid > 0) {
    $PAGE->set_heading(format_string($course->fullname));
} else {
    $PAGE->set_heading($useshell ? '' : get_string('default_persona', 'block_eledia_aitutor'));
}
if ($useshell) {
    shell::require_css();
}
$PAGE->add_body_class('eledia_aitutor-pagebody');

echo $OUTPUT->header();
if ($useshell) {
    shell::open(shell::ACTIVE_PREVIEW);
}

if (!$enabled) {
    echo $OUTPUT->notification(get_string($disabledstring, 'block_eledia_aitutor'), 'info');
    if ($useshell) {
        shell::close();
    }
    echo $OUTPUT->footer();
    die;
}

// Course chat is opt-in per course: the teacher enables it by adding the
// tutor block. Without it, the page declines with a friendly notice.
if ($courseid > 0 && !widget::course_has_tutor($courseid)) {
    echo $OUTPUT->notification(get_string('notenabledincourse', 'block_eledia_aitutor'), 'info');
    if ($useshell) {
        shell::close();
    }
    echo $OUTPUT->footer();
    die;
}

$configerror = widget::config_error();
if ($configerror !== null) {
    $canmanage = has_capability('block/eledia_aitutor:manage', $context);
    echo $OUTPUT->render_from_template('block_eledia_aitutor/unavailable', [
        'isadmin' => $canmanage,
        'message' => $canmanage ? $configerror : get_string('unavailable_user', 'block_eledia_aitutor'),
    ]);
    if ($useshell) {
        shell::close();
    }
    echo $OUTPUT->footer();
    die;
}

// Always the inline (embedded display mode) panel: on a dedicated page the
// launcher/overlay modes make no sense.
echo html_writer::div(
    widget::render($context, $courseid, ['displaymode' => 'embedded']),
    'eledia_aitutor-page'
);

if ($useshell) {
    shell::close();
}
echo $OUTPUT->footer();
