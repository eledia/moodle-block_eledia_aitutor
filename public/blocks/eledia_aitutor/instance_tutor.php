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
 * Teacher-facing tutor management for a single block instance: import or export
 * this block's tutor (settings + images), or apply a site preset / saved tutor
 * to it. Scoped to one instance and gated by block/eledia_aitutor:manage on the
 * block context (granted to editing teachers); site-wide profile management
 * stays on the admin page (manage_tutors.php).
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_eledia_aitutor\form\tutor_import_form;
use block_eledia_aitutor\local\icon;
use block_eledia_aitutor\local\presets;
use block_eledia_aitutor\local\tutor_apply;
use block_eledia_aitutor\local\tutor_io;
use block_eledia_aitutor\local\tutor_profile;
use block_eledia_aitutor\output\shell;

$blockid = required_param('blockid', PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA);

require_login();

global $DB;
$DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'eledia_aitutor'], '*', MUST_EXIST);
$blockcontext = \core\context\block::instance($blockid);
require_capability('block/eledia_aitutor:manage', $blockcontext);
$pageurl = new moodle_url('/blocks/eledia_aitutor/instance_tutor.php', ['blockid' => $blockid]);

// Set up the page context up front, before instantiating any form (form setup
// needs $PAGE->context) and before any output.
$parent = $blockcontext->get_parent_context();
$PAGE->set_url($pageurl);
$PAGE->set_context($blockcontext);
if ($parent && $parent->contextlevel == CONTEXT_COURSE) {
    $PAGE->set_course(get_course($parent->instanceid));
    $PAGE->set_pagelayout('incourse');
} else {
    $PAGE->set_pagelayout('standard');
}
$PAGE->set_title(get_string('instancetutor_title', 'block_eledia_aitutor'));
// Empty: the instance shell renders its own header ("eLeDia.ai Tutor | Tutor (course)"),
// so a separate course-name page heading above it would be redundant.
$PAGE->set_heading('');
shell::require_css();

// Export streams a file and must run before any output.
if ($action === 'export') {
    require_sesskey();
    $src = tutor_apply::instance_source($blockid);
    $path = tutor_io::export(
        'instance-' . $blockid,
        'instance_' . $blockid,
        $src['settings'],
        $src['logo'],
        $src['avatar']
    );
    send_temp_file($path, basename($path));
}

// Apply a site preset / saved tutor to this instance (exposure-gated).
if ($action === 'apply') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data !== null) {
        tutor_apply::to_instance($blockid, $data['settings'], $data['logo'], $data['avatar']);
        redirect($pageurl, get_string('tutor_applied_instance', 'block_eledia_aitutor'));
    }
    redirect($pageurl);
}

$importform = new tutor_import_form(
    new moodle_url($pageurl, ['action' => 'importdo']),
    ['blockid' => $blockid]
);
if ($action === 'importdo') {
    if ($importform->is_cancelled()) {
        redirect($pageurl);
    }
    if ($data = $importform->get_data()) {
        $tmpdir = make_request_directory();
        $zippath = $tmpdir . '/bundle.zip';
        $importform->save_file('bundle', $zippath, true);
        $bundle = tutor_io::import($zippath);
        tutor_apply::to_instance_from_bundle($blockid, $bundle);
        redirect($pageurl, get_string('tutor_imported', 'block_eledia_aitutor'));
    }
}

echo $OUTPUT->header();
shell::open_instance($blockid, shell::ACTIVE_INSTANCE_TUTOR);
echo $OUTPUT->heading(get_string('instancetutor_title', 'block_eledia_aitutor'));

echo html_writer::start_div('eat-admin');
echo html_writer::div(
    icon::render('paint-brush') .
    html_writer::span(get_string('instancetutor_intro', 'block_eledia_aitutor')),
    'eat-admin-intro'
);

echo html_writer::start_div('eat-cards');

// Export this instance's tutor.
echo html_writer::div(
    html_writer::tag(
        'h3',
        icon::render('download') . ' ' .
        get_string('tutor_export', 'block_eledia_aitutor')
    ) .
    html_writer::tag('p', get_string('instancetutor_exporthelp', 'block_eledia_aitutor')) .
    $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'export', 'sesskey' => sesskey()]),
        get_string('instancetutor_exportbtn', 'block_eledia_aitutor'),
        'get'
    ),
    'eat-card'
);

// Apply a site preset / saved tutor.
$sources = ['' => get_string('choosedots')];
foreach (presets::menu() as $pid => $plabel) {
    $sources['preset:' . $pid] = get_string('tutor_preset', 'block_eledia_aitutor') . ': ' . $plabel;
}
foreach (tutor_profile::get_all() as $profile) {
    $sources['profile:' . $profile->id] = format_string($profile->name);
}
$applyurl = new moodle_url($pageurl, ['action' => 'apply']);
$applyform = html_writer::tag(
    'form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blockid', 'value' => $blockid]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::div(
        html_writer::select($sources, 'source', '', false) .
        html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
            'value' => get_string('tutor_apply', 'block_eledia_aitutor')]),
        'eat-instance-apply'
    ),
    ['method' => 'post', 'action' => $applyurl->out_omit_querystring()]
);
echo html_writer::div(
    html_writer::tag(
        'h3',
        icon::render('magic') . ' ' .
        get_string('instancetutor_applyheading', 'block_eledia_aitutor')
    ) .
    html_writer::tag('p', get_string('instancetutor_applyhelp', 'block_eledia_aitutor')) .
    $applyform,
    'eat-card'
);

echo html_writer::end_div(); // End of .eat-cards.

// Import a bundle into this instance (full-width card with the upload form).
echo html_writer::start_div('eat-card');
echo html_writer::tag(
    'h3',
    icon::render('upload') . ' ' .
    get_string('tutor_import', 'block_eledia_aitutor')
);
echo html_writer::tag('p', get_string('tutor_import_help', 'block_eledia_aitutor'));
$importform->display();
echo html_writer::end_div();

echo html_writer::end_div(); // End of .eat-admin.
shell::close();
echo $OUTPUT->footer();
