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
 * Tutor library management: list built-in presets and saved tutor profiles,
 * apply them to the site or a block instance, edit/duplicate/delete profiles,
 * and import/export tutor bundles (settings + images). Admin only.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use block_elediaaitutor\form\tutor_edit_form;
use block_elediaaitutor\form\tutor_import_form;
use block_elediaaitutor\local\branding;
use block_elediaaitutor\local\presets;
use block_elediaaitutor\local\registry;
use block_elediaaitutor\local\tutor_apply;
use block_elediaaitutor\local\tutor_io;
use block_elediaaitutor\local\tutor_profile;

admin_externalpage_setup('block_elediaaitutor_managetutors');

$action = optional_param('action', 'list', PARAM_ALPHA);
$baseurl = new moodle_url('/blocks/elediaaitutor/manage_tutors.php');
$PAGE->set_url($baseurl);

/**
 * Build the list of appliable/exportable sources: built-in presets then saved
 * profiles, as a select-friendly map of "type:id" => label.
 *
 * @return array<string, string>
 */
function block_elediaaitutor_source_menu(): array {
    $menu = [];
    foreach (presets::menu() as $id => $label) {
        $menu['preset:' . $id] = get_string('tutor_preset', 'block_elediaaitutor') . ': ' . $label;
    }
    foreach (tutor_profile::get_all() as $profile) {
        $menu['profile:' . $profile->id] = format_string($profile->name);
    }
    return $menu;
}

// ---------------------------------------------------------------------------
// File-streaming actions must run before any page output.
// ---------------------------------------------------------------------------
if ($action === 'export') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data === null) {
        throw new moodle_exception('error_import_invalid', 'block_elediaaitutor');
    }
    [$type, $id] = array_pad(explode(':', $source, 2), 2, '');
    $name = $type === 'preset'
        ? get_string(presets::all()[$id]['name'], 'block_elediaaitutor')
        : (string) (tutor_profile::get((int) $id)->name ?? 'tutor');
    $shortname = $type === 'preset' ? $id : (string) (tutor_profile::get((int) $id)->shortname ?? 'tutor');
    $path = tutor_io::export($name, $shortname, $data['settings'], $data['logo'], $data['avatar']);
    send_temp_file($path, basename($path));
}

if ($action === 'exportinstance') {
    require_sesskey();
    $blockid = required_param('blockid', PARAM_INT);
    $src = tutor_apply::instance_source($blockid);
    $path = tutor_io::export('instance-' . $blockid, 'instance_' . $blockid,
        $src['settings'], $src['logo'], $src['avatar']);
    send_temp_file($path, basename($path));
}

// ---------------------------------------------------------------------------
// Mutating actions (POST forms or sesskey-guarded confirmations).
// ---------------------------------------------------------------------------
if ($action === 'save') {
    $form = new tutor_edit_form($baseurl->out(false), ['id' => optional_param('id', 0, PARAM_INT)]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $settings = [];
        foreach (registry::all() as $key => $entry) {
            if ($entry['type'] === 'file') {
                continue;
            }
            $value = $data->{'cfg_' . $key} ?? '';
            if ($value !== '' && $value !== null) {
                $settings[$key] = $value;
            }
        }
        $id = (int) $data->id;
        if ($id > 0) {
            tutor_profile::update($id, $data->name, (string) $data->description, $settings);
        } else {
            $id = tutor_profile::create($data->name, (string) $data->description, $settings,
                (string) $data->shortname);
        }
        // Save the uploaded logo/avatar into the profile's file areas.
        $syscontext = context_system::instance();
        foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA]
                as $field => $filearea) {
            if (!empty($data->$field)) {
                file_save_draft_area_files((int) $data->$field, $syscontext->id, 'block_elediaaitutor',
                    $filearea, $id, ['maxfiles' => 1, 'subdirs' => 0]);
            }
        }
        redirect($baseurl, get_string('tutor_saved', 'block_elediaaitutor'));
    }
    // Validation failed: fall through and re-render the form below.
}

if ($action === 'delete') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        tutor_profile::delete($id);
        redirect($baseurl, get_string('tutor_deleted', 'block_elediaaitutor'));
    }
    $profile = tutor_profile::get($id);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('tutor_delete_confirm', 'block_elediaaitutor', format_string($profile->name ?? '')),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    die;
}

if ($action === 'duplicate') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data !== null) {
        [$type, $id] = array_pad(explode(':', $source, 2), 2, '');
        $name = ($type === 'preset'
            ? get_string(presets::all()[$id]['name'], 'block_elediaaitutor')
            : (string) (tutor_profile::get((int) $id)->name ?? 'tutor'))
            . ' ' . get_string('tutor_copysuffix', 'block_elediaaitutor');
        $newid = tutor_profile::create($name, '', $data['settings']);
        foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA]
                as $role => $area) {
            if ($data[$role] instanceof \stored_file) {
                tutor_profile::copy_image_in($newid, $area, $data[$role]);
            }
        }
        redirect(new moodle_url($baseurl, ['action' => 'edit', 'id' => $newid]));
    }
    redirect($baseurl);
}

if ($action === 'applysite') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        $data = tutor_apply::source($source);
        if ($data !== null) {
            tutor_apply::to_site($data['settings'], $data['logo'], $data['avatar']);
        }
        redirect($baseurl, get_string('tutor_applied_site', 'block_elediaaitutor'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('tutor_applysite_confirm', 'block_elediaaitutor'),
        new moodle_url($baseurl, ['action' => 'applysite', 'source' => $source,
            'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    die;
}

if ($action === 'applyinstance') {
    require_sesskey();
    $blockid = required_param('blockid', PARAM_INT);
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data !== null) {
        tutor_apply::to_instance($blockid, $data['settings'], $data['logo'], $data['avatar']);
        redirect($baseurl, get_string('tutor_applied_instance', 'block_elediaaitutor'));
    }
    redirect($baseurl);
}

if ($action === 'importdo') {
    $blockid = optional_param('blockid', 0, PARAM_INT);
    $form = new tutor_import_form($baseurl->out(false), ['blockid' => $blockid]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $tmpdir = make_request_directory();
        $zippath = $tmpdir . '/bundle.zip';
        $form->save_file('bundle', $zippath, true);
        $bundle = tutor_io::import($zippath);
        if ($blockid > 0) {
            tutor_apply::to_instance_from_bundle($blockid, $bundle);
            redirect($baseurl, get_string('tutor_applied_instance', 'block_elediaaitutor'));
        }
        $newid = tutor_profile::create($bundle['name'] ?: 'import', '', $bundle['settings'],
            $bundle['shortname']);
        block_elediaaitutor_stage_images($newid, $bundle);
        redirect(new moodle_url($baseurl, ['action' => 'edit', 'id' => $newid]),
            get_string('tutor_imported', 'block_elediaaitutor'));
    }
    // Fall through to render the import form on validation failure.
    $action = 'import';
}

/**
 * Store a bundle's parsed images onto a profile.
 *
 * @param int $profileid Target profile id.
 * @param array $bundle Parsed bundle from tutor_io::import().
 * @return void
 */
function block_elediaaitutor_stage_images(int $profileid, array $bundle): void {
    foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA]
            as $role => $area) {
        if (!empty($bundle[$role])) {
            tutor_profile::store_image($profileid, $area, $bundle[$role]['filename'],
                $bundle[$role]['content']);
        }
    }
}

// ---------------------------------------------------------------------------
// Rendering.
// ---------------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetutors', 'block_elediaaitutor'));

if ($action === 'new' || $action === 'edit') {
    $id = optional_param('id', 0, PARAM_INT);
    $form = new tutor_edit_form($baseurl->out(false), ['id' => $id]);
    $defaults = (object) ['id' => $id];
    if ($id > 0 && ($profile = tutor_profile::get($id))) {
        $defaults->name = $profile->name;
        $defaults->shortname = $profile->shortname;
        $defaults->description = $profile->description;
        foreach (tutor_profile::settings($profile) as $key => $value) {
            $defaults->{'cfg_' . $key} = $value;
        }
        $syscontext = context_system::instance();
        foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA]
                as $field => $filearea) {
            $draftid = file_get_submitted_draft_itemid($field);
            file_prepare_draft_area($draftid, $syscontext->id, 'block_elediaaitutor', $filearea, $id,
                ['maxfiles' => 1, 'subdirs' => 0]);
            $defaults->$field = $draftid;
        }
    }
    $form->set_data($defaults);
    $form->display();
    echo $OUTPUT->footer();
    die;
}

if ($action === 'import') {
    $blockid = optional_param('blockid', 0, PARAM_INT);
    echo html_writer::start_div('eat-admin');
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-upload', 'aria-hidden' => 'true']) .
        html_writer::span(get_string('tutor_import_help', 'block_elediaaitutor')),
        'eat-admin-intro');
    $form = new tutor_import_form(new moodle_url($baseurl, ['action' => 'importdo']),
        ['blockid' => $blockid]);
    $form->display();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die;
}

// Default: the library listing.
echo html_writer::start_div('eat-admin');

echo html_writer::div(
    html_writer::tag('i', '', ['class' => 'fa fa-paint-brush', 'aria-hidden' => 'true']) .
    html_writer::span(get_string('managetutors_intro', 'block_elediaaitutor')),
    'eat-admin-intro');

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url($baseurl, ['action' => 'new']),
        get_string('tutor_new', 'block_elediaaitutor'), 'get') .
    $OUTPUT->single_button(new moodle_url($baseurl, ['action' => 'import']),
        get_string('tutor_import', 'block_elediaaitutor'), 'get'),
    'eat-toolbar');

// Site tutors: built-in presets + saved profiles, as preview cards.
echo html_writer::tag('h3', get_string('tutor_sitetutors', 'block_elediaaitutor'),
    ['class' => 'eat-section-title']);
echo html_writer::start_div('eat-tutor-grid');
foreach (presets::menu() as $pid => $plabel) {
    echo block_elediaaitutor_tutor_card($baseurl, 'preset:' . $pid, $plabel,
        get_string('tutor_preset', 'block_elediaaitutor'), 'preset', presets::settings($pid), false, 0);
}
foreach (tutor_profile::get_all() as $profile) {
    echo block_elediaaitutor_tutor_card($baseurl, 'profile:' . $profile->id, $profile->name,
        get_string('tutor_custom', 'block_elediaaitutor'), 'custom',
        tutor_profile::settings($profile), true, (int) $profile->id);
}
echo html_writer::end_div();

// Block instances: per-instance apply / export / import, as cards.
global $DB;
$instances = $DB->get_records('block_instances', ['blockname' => 'elediaaitutor'], 'id ASC');
if ($instances) {
    $sources = block_elediaaitutor_source_menu();
    echo html_writer::tag('h3', get_string('tutor_instances', 'block_elediaaitutor'),
        ['class' => 'eat-section-title']);
    echo html_writer::start_div('eat-instance-grid');
    foreach ($instances as $bi) {
        $parent = context_block::instance($bi->id)->get_parent_context();
        $location = $parent ? $parent->get_context_name(false) : get_string('system', 'admin');
        $src = tutor_apply::instance_source((int) $bi->id);

        $applyurl = new moodle_url($baseurl, ['action' => 'applyinstance', 'blockid' => $bi->id]);
        $applyform = html_writer::tag('form',
            html_writer::input_hidden_params($applyurl) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::select($sources, 'source', '', ['' => get_string('choosedots')]) .
            html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-sm btn-primary',
                'value' => get_string('tutor_apply', 'block_elediaaitutor')]),
            ['method' => 'post', 'action' => $applyurl->out_omit_querystring(), 'class' => 'eat-instance-apply']);

        $links = html_writer::link(
            new moodle_url($baseurl, ['action' => 'exportinstance', 'blockid' => $bi->id, 'sesskey' => sesskey()]),
            get_string('tutor_export', 'block_elediaaitutor'), ['class' => 'btn btn-sm btn-outline-secondary']) .
            html_writer::link(new moodle_url($baseurl, ['action' => 'import', 'blockid' => $bi->id]),
            get_string('tutor_import', 'block_elediaaitutor'), ['class' => 'btn btn-sm btn-outline-secondary']);

        echo html_writer::div(
            html_writer::div(
                html_writer::tag('i', '', ['class' => 'fa fa-cube', 'aria-hidden' => 'true']) .
                html_writer::span(s($location)) .
                html_writer::span('#' . $bi->id, 'eat-instance-id'),
                'eat-instance-loc') .
            block_elediaaitutor_preview($src['settings'], get_string('pluginname', 'block_elediaaitutor')) .
            $applyform .
            html_writer::div($links, 'eat-actions'),
            'eat-instance-card');
    }
    echo html_writer::end_div();
}

echo html_writer::end_div(); // .eat-admin
echo $OUTPUT->footer();

/**
 * Resolve a colour from a settings map to a safe hex value, or a default.
 *
 * @param array<string, mixed> $settings Registry-key => value map.
 * @param string $key Registry key of a colour token.
 * @param string $default Fallback hex.
 * @return string A safe `#rrggbb` value.
 */
function block_elediaaitutor_pcol(array $settings, string $key, string $default): string {
    $value = isset($settings[$key]) ? branding::sanitise_colour((string) $settings[$key]) : null;
    return $value ?? $default;
}

/**
 * A miniature chat mockup rendered from a tutor's own palette.
 *
 * @param array<string, mixed> $settings Registry-key => value map.
 * @param string $name Header label (raw; escaped here).
 * @return string HTML.
 */
function block_elediaaitutor_preview(array $settings, string $name): string {
    $accent = block_elediaaitutor_pcol($settings, 'brandaccent', '#1e3f59');
    $accentfg = block_elediaaitutor_pcol($settings, 'tok_accentcontrast', '#ffffff');
    $body = block_elediaaitutor_pcol($settings, 'brandsurface', '#f4f6f8');
    $botbg = block_elediaaitutor_pcol($settings, 'brandbotbubble', '#ffffff');
    $botfg = block_elediaaitutor_pcol($settings, 'tok_botfg', '#1e3f59');
    $userbg = block_elediaaitutor_pcol($settings, 'brandbubble', '#fce9db');
    $userfg = block_elediaaitutor_pcol($settings, 'tok_userfg', '#1e3f59');

    $head = html_writer::div(
        html_writer::span('', 'eat-preview-dot') . html_writer::span(s($name)),
        'eat-preview-head', ['style' => "background:$accent;color:$accentfg;"]);
    $bubbles = html_writer::div('Aa', 'eat-preview-bubble eat-preview-bot',
            ['style' => "background:$botbg;color:$botfg;"]) .
        html_writer::div('Aa', 'eat-preview-bubble eat-preview-user',
            ['style' => "background:$userbg;color:$userfg;"]);
    return html_writer::div($head . html_writer::div($bubbles, 'eat-preview-body'),
        'eat-preview', ['style' => "background:$body;"]);
}

/**
 * A tutor preview card (palette mockup + name + type + actions).
 *
 * @param moodle_url $baseurl Page base URL.
 * @param string $source 'preset:id' or 'profile:id'.
 * @param string $name Display name (raw; escaped here).
 * @param string $typelabel Localised type label.
 * @param string $typeclass 'preset' or 'custom' (drives the badge colour).
 * @param array<string, mixed> $settings The tutor's settings (for the preview).
 * @param bool $custom Whether it is an editable/deletable saved profile.
 * @param int $profileid Profile id (0 for presets).
 * @return string HTML.
 */
function block_elediaaitutor_tutor_card(moodle_url $baseurl, string $source, string $name,
        string $typelabel, string $typeclass, array $settings, bool $custom, int $profileid): string {
    $sk = ['sesskey' => sesskey()];
    $btn = static fn(moodle_url $url, string $label, string $cls): string =>
        html_writer::link($url, $label, ['class' => 'btn btn-sm ' . $cls]);

    $actions = $btn(new moodle_url($baseurl, ['action' => 'applysite', 'source' => $source] + $sk),
            get_string('tutor_applysite', 'block_elediaaitutor'), 'btn-primary') .
        $btn(new moodle_url($baseurl, ['action' => 'export', 'source' => $source] + $sk),
            get_string('tutor_export', 'block_elediaaitutor'), 'btn-outline-secondary') .
        $btn(new moodle_url($baseurl, ['action' => 'duplicate', 'source' => $source] + $sk),
            get_string('tutor_duplicate', 'block_elediaaitutor'), 'btn-outline-secondary');
    if ($custom) {
        $actions .= $btn(new moodle_url($baseurl, ['action' => 'edit', 'id' => $profileid]),
                get_string('edit'), 'btn-outline-secondary') .
            $btn(new moodle_url($baseurl, ['action' => 'delete', 'id' => $profileid] + $sk),
                get_string('delete'), 'btn-outline-danger');
    }

    $persona = (string) ($settings['persona'] ?? '');
    $tone = (string) ($settings['persona_tone'] ?? '');
    $subtitle = trim($persona . ($tone !== '' ? ' · ' . $tone : ''));

    $body = html_writer::div(
        html_writer::div(
            html_writer::span(s($name), 'eat-tutor-name') .
            html_writer::span($typelabel, 'eat-badge eat-badge-' . $typeclass),
            'eat-tutor-head') .
        html_writer::div(s($subtitle), 'eat-tutor-persona') .
        html_writer::div($actions, 'eat-actions'),
        'eat-tutor-body');

    return html_writer::div(
        block_elediaaitutor_preview($settings, $persona !== '' ? $persona : $name) . $body,
        'eat-tutor-card');
}
