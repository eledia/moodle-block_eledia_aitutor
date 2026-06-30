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
 * Plugin-shell editor for a single eLeDia.ai Tutor block instance.
 *
 * @package     block_eledia_aitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_eledia_aitutor\local\branding;
use block_eledia_aitutor\local\chat_mode;
use block_eledia_aitutor\local\formhelper;
use block_eledia_aitutor\local\registry;
use block_eledia_aitutor\local\security;
use block_eledia_aitutor\local\widget;
use block_eledia_aitutor\output\shell;

/**
 * Moodle form for the in-shell instance editor.
 */
class block_eledia_aitutor_instance_shell_form extends moodleform {
    /** @var int Block instance id. */
    private int $blockid;

    /** @var \core\context\block Block context. */
    private \core\context\block $blockcontext;

    /** @var stdClass Existing block config. */
    private stdClass $config;

    /** @var int Parent course id, or 0. */
    private int $courseid;

    /**
     * Constructor.
     *
     * @param moodle_url|string $action Form action.
     * @param array<string, mixed> $customdata Custom form data.
     */
    public function __construct($action = null, $customdata = null) {
        $this->blockid = (int) ($customdata['blockid'] ?? 0);
        $this->blockcontext = $customdata['blockcontext'];
        $this->config = $customdata['config'];
        $this->courseid = (int) ($customdata['courseid'] ?? 0);
        parent::__construct($action, $customdata);
    }

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition(): void {
        formhelper::register_colour_element();
        $mform = $this->_form;

        $mform->addElement(
            'header',
            'quicksettings',
            get_string('instance_shell_quicksettings', 'block_eledia_aitutor')
        );
        $mform->setExpanded('quicksettings', true);

        $mform->addElement('text', 'config_title', get_string('config_title', 'block_eledia_aitutor'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_eledia_aitutor'));

        $mform->addElement(
            'selectyesno',
            'config_passcoursecontext',
            get_string('config_passcoursecontext', 'block_eledia_aitutor')
        );
        $mform->setDefault('config_passcoursecontext', 1);
        $mform->addHelpButton('config_passcoursecontext', 'config_passcoursecontext', 'block_eledia_aitutor');

        $mform->addElement('text', 'config_fixedcourseid', get_string('config_fixedcourseid', 'block_eledia_aitutor'));
        $mform->setType('config_fixedcourseid', PARAM_INT);
        $mform->setDefault('config_fixedcourseid', 0);
        $mform->addHelpButton('config_fixedcourseid', 'config_fixedcourseid', 'block_eledia_aitutor');
        $mform->disabledIf('config_fixedcourseid', 'config_passcoursecontext', 'eq', 0);

        $mform->addElement('text', 'config_dailylimit', get_string('config_dailylimit', 'block_eledia_aitutor'));
        $mform->setType('config_dailylimit', PARAM_INT);
        $mform->setDefault('config_dailylimit', -1);
        $mform->addHelpButton('config_dailylimit', 'config_dailylimit', 'block_eledia_aitutor');

        $grounding = chat_mode::ingestion_available($this->effective_courseid());
        $llmallowed = chat_mode::is_llm_allowed();
        if ($llmallowed && $grounding) {
            $mform->addElement(
                'select',
                'config_ragmode',
                get_string('config_ragmode', 'block_eledia_aitutor'),
                [
                    chat_mode::MODE_GROUNDED => get_string('ragmode_grounded', 'block_eledia_aitutor'),
                    chat_mode::MODE_LLMONLY => get_string('ragmode_llmonly', 'block_eledia_aitutor'),
                ]
            );
            $mform->setDefault('config_ragmode', chat_mode::MODE_GROUNDED);
            $mform->addHelpButton('config_ragmode', 'config_ragmode', 'block_eledia_aitutor');
        } else if (!$grounding) {
            $mform->addElement(
                'static',
                'ragmode_note',
                get_string('config_ragmode', 'block_eledia_aitutor'),
                $llmallowed
                    ? get_string('config_ragmode_nokb', 'block_eledia_aitutor')
                : get_string('llmonly_unavailable', 'block_eledia_aitutor')
            );
        }

        $mform->addElement(
            'static',
            'instance_tools',
            '',
            html_writer::link(
                new moodle_url(
                    '/blocks/eledia_aitutor/instance_tutor.php',
                    ['blockid' => $this->blockid]
                ),
                get_string('instancetutor_link', 'block_eledia_aitutor'),
                ['class' => 'btn btn-secondary']
            )
        );

        foreach (registry::groups() as $group) {
            $exposed = array_filter(
                registry::group_keys($group),
                static fn(string $key): bool => registry::is_exposed($key)
            );
            if (empty($exposed)) {
                continue;
            }
            $mform->addElement(
                'header',
                'insgroup_' . $group,
                get_string('reggroup_' . $group, 'block_eledia_aitutor')
            );
            // Expanded by default: the settings-hub JS shows one section at a time,
            // so collapsed groups would hide content behind the active card.
            $mform->setExpanded('insgroup_' . $group, true);
            foreach ($exposed as $key) {
                $this->add_instance_field($mform, $key, registry::get($key));
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Add one registry-backed instance field.
     *
     * @param MoodleQuickForm $mform Form object.
     * @param string $key Registry key.
     * @param array<string, mixed> $entry Registry descriptor.
     * @return void
     */
    private function add_instance_field($mform, string $key, array $entry): void {
        $field = 'config_' . $key;
        $label = $this->reglabel($key, $entry);

        switch ($entry['type']) {
            case 'file':
                $mform->addElement('filemanager', $field, $label, null, [
                    'maxfiles' => 1,
                    'subdirs' => 0,
                    'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif'],
                ]);
                break;
            case 'colour':
                $mform->addElement('eatcolour', $field, $label);
                $mform->setType($field, PARAM_TEXT);
                break;
            case 'textarea':
                $mform->addElement('textarea', $field, $label, ['rows' => 3, 'cols' => 50]);
                $mform->setType($field, PARAM_TEXT);
                break;
            case 'select':
                $options = ['' => get_string('config_usesite', 'block_eledia_aitutor')];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_eledia_aitutor');
                }
                $mform->addElement('select', $field, $label, $options);
                $mform->setDefault($field, '');
                break;
            case 'checkbox':
                $mform->addElement('select', $field, $label, [
                    '' => get_string('config_usesite', 'block_eledia_aitutor'),
                    '1' => get_string('yes'),
                    '0' => get_string('no'),
                ]);
                $mform->setDefault($field, '');
                break;
            default:
                if (!empty($entry['choices'])) {
                    $current = isset($this->config->$key) ? (string) $this->config->$key : null;
                    $mform->addElement(
                        'select',
                        $field,
                        $label,
                        registry::choice_select_options(
                            $key,
                            get_string('config_usesite', 'block_eledia_aitutor'),
                            $current
                        )
                    );
                    $mform->setDefault($field, '');
                } else {
                    $mform->addElement('text', $field, $label);
                    $mform->setType($field, PARAM_TEXT);
                }
                break;
        }

        if (get_string_manager()->string_exists($field . '_help', 'block_eledia_aitutor')) {
            $mform->addHelpButton($field, $field, 'block_eledia_aitutor');
        }
    }

    /**
     * Friendly field label.
     *
     * @param string $key Registry key.
     * @param array<string, mixed> $entry Registry descriptor.
     * @return string
     */
    private function reglabel(string $key, array $entry): string {
        if (get_string_manager()->string_exists('reg_' . $key, 'block_eledia_aitutor')) {
            return get_string('reg_' . $key, 'block_eledia_aitutor');
        }
        return (string) ($entry['token'] ?? $key);
    }

    /**
     * Prepare defaults and file-manager drafts.
     *
     * @param array|stdClass $defaults Defaults.
     * @return void
     */
    public function set_data($defaults): void {
        $filemap = [
            'config_logo' => [branding::INSTANCE_LOGO_FILEAREA, 'logo'],
            'config_avatar' => [branding::INSTANCE_AVATAR_FILEAREA, 'avatar'],
        ];
        foreach ($filemap as $field => [$filearea, $key]) {
            if (!registry::is_exposed($key)) {
                continue;
            }
            $draftid = file_get_submitted_draft_itemid($field);
            file_prepare_draft_area(
                $draftid,
                $this->blockcontext->id,
                'block_eledia_aitutor',
                $filearea,
                0,
                ['maxfiles' => 1, 'subdirs' => 0]
            );
            $defaults->$field = $draftid;
        }

        parent::set_data($defaults);
    }

    /**
     * Resolve the course id used for the grounding-mode availability check.
     *
     * @return int Course id, or 0 for global chat.
     */
    private function effective_courseid(): int {
        $fixed = (int) ($this->config->fixedcourseid ?? 0);
        if ($fixed > 0) {
            return security::course_chat_enabled() ? $fixed : 0;
        }

        $passcontext = !isset($this->config->passcoursecontext) || (int) $this->config->passcoursecontext === 1;
        if ($passcontext && security::course_chat_enabled() && $this->courseid > 0 && $this->courseid !== SITEID) {
            return $this->courseid;
        }
        return 0;
    }
}

$blockid = required_param('blockid', PARAM_INT);

require_login();

global $DB, $OUTPUT, $PAGE;

$record = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'eledia_aitutor'], '*', MUST_EXIST);
$blockcontext = \core\context\block::instance($blockid);
require_capability('block/eledia_aitutor:manage', $blockcontext);

$parent = $blockcontext->get_parent_context();
$courseid = 0;
$returnurl = new moodle_url('/blocks/eledia_aitutor/manage_tutors.php');
if ($parent && $parent->contextlevel === CONTEXT_COURSE) {
    $courseid = (int) $parent->instanceid;
    $course = get_course($courseid);
    $PAGE->set_course($course);
    $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    $PAGE->set_pagelayout('incourse');
} else {
    $PAGE->set_pagelayout('standard');
}

$pageurl = new moodle_url('/blocks/eledia_aitutor/edit_instance.php', ['blockid' => $blockid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($blockcontext);
$PAGE->set_title(get_string('instance_shell_title', 'block_eledia_aitutor'));
// Empty: the instance shell renders its own header ("eLeDia.ai Tutor | Settings (course)"),
// so a separate course-name page heading above it would be redundant.
$PAGE->set_heading('');
shell::require_css();

$config = !empty($record->configdata) ? unserialize_object(base64_decode($record->configdata)) : new stdClass();
if (!is_object($config)) {
    $config = new stdClass();
}

$defaults = new stdClass();
foreach ((array) $config as $key => $value) {
    $defaults->{'config_' . $key} = $value;
}
$defaults->config_title = $config->title ?? get_string('pluginname', 'block_eledia_aitutor');
$defaults->config_passcoursecontext = (int) ($config->passcoursecontext ?? 1);
$defaults->config_fixedcourseid = (int) ($config->fixedcourseid ?? 0);
$defaults->config_dailylimit = (int) ($config->dailylimit ?? -1);
$defaults->config_ragmode = $config->ragmode ?? chat_mode::MODE_GROUNDED;

$form = new block_eledia_aitutor_instance_shell_form($pageurl, [
    'blockid' => $blockid,
    'blockcontext' => $blockcontext,
    'config' => $config,
    'courseid' => $courseid,
]);
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $config->title = trim((string) ($data->config_title ?? ''));
    $config->passcoursecontext = (int) ($data->config_passcoursecontext ?? 1);
    $config->fixedcourseid = (int) ($data->config_fixedcourseid ?? 0);
    $config->dailylimit = (int) ($data->config_dailylimit ?? -1);
    if (isset($data->config_ragmode)) {
        $config->ragmode = (string) $data->config_ragmode;
    }

    foreach (registry::instanceable_keys() as $key) {
        if (!registry::is_exposed($key)) {
            continue;
        }
        $entry = registry::get($key);
        if (($entry['type'] ?? '') === 'file') {
            continue;
        }
        $field = 'config_' . $key;
        if (!property_exists($data, $field)) {
            continue;
        }
        $value = $data->$field;
        if ($value === '' || $value === null) {
            unset($config->$key);
            continue;
        }
        $clean = registry::sanitise($key, $value);
        if ($clean === null || $clean === '') {
            unset($config->$key);
        } else {
            $config->$key = $clean;
        }
    }

    foreach (
        [
        'config_logo' => branding::INSTANCE_LOGO_FILEAREA,
        'config_avatar' => branding::INSTANCE_AVATAR_FILEAREA,
        ] as $field => $filearea
    ) {
        if (property_exists($data, $field)) {
            file_save_draft_area_files(
                (int) $data->$field,
                $blockcontext->id,
                'block_eledia_aitutor',
                $filearea,
                0,
                ['maxfiles' => 1, 'subdirs' => 0]
            );
        }
    }

    $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $blockid]);
    if ($courseid > 0) {
        rebuild_course_cache($courseid, true);
    }
    redirect($pageurl, get_string('changessaved'));
}

echo $OUTPUT->header();
shell::open_instance($blockid, shell::ACTIVE_INSTANCE_SETTINGS);

if (!shell::is_available()) {
    echo $OUTPUT->heading(get_string('instance_shell_title', 'block_eledia_aitutor'), 2);
}

echo html_writer::start_div('path-block-eledia_aitutor eat-instance-shell');
echo html_writer::tag(
    'h2',
    get_string('instance_shell_title', 'block_eledia_aitutor'),
    ['class' => 'eat-section-heading']
);
echo html_writer::tag(
    'p',
    get_string('instance_shell_intro', 'block_eledia_aitutor'),
    ['class' => 'text-muted']
);
// Site admins keep one-click access to the site-wide settings hub (the per-instance
// shell otherwise only exposes this block's own settings).
if (has_capability('moodle/site:config', \core\context\system::instance())) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'blocksettingeledia_aitutor']),
            get_string('instance_shell_siteadmin_link', 'block_eledia_aitutor'),
            ['class' => 'btn btn-outline-secondary btn-sm']
        ),
        'eat-instance-siteadmin mb-3'
    );
}

$form->display();

// Live preview: a floating, draggable panel that overlays the page (so it never
// affects the form layout) and re-themes instantly as design fields change. Drag it by
// its title bar; the chevron minimises it. See instance_preview.js.
$previewcfg = (array) $config;
$previewcfg['instanceid'] = $blockid;
$previewcfg['displaymode'] = 'embedded';
$previewcfg['preview'] = true;
echo html_writer::start_div('eat-preview-float', ['data-region' => 'eat-preview-float']);
echo html_writer::start_div('eat-preview-float__bar', ['data-region' => 'eat-preview-drag']);
echo html_writer::tag(
    'span',
    get_string('instance_preview_heading', 'block_eledia_aitutor'),
    ['class' => 'eat-preview-float__title']
);
echo html_writer::tag(
    'button',
    '<i class="fa fa-window-minimize" aria-hidden="true"></i>',
    [
        'type' => 'button',
        'class' => 'eat-preview-float__toggle',
        'data-action' => 'eat-preview-toggle',
        'aria-label' => get_string('instance_preview_toggle', 'block_eledia_aitutor'),
    ]
);
echo html_writer::end_div();
echo html_writer::start_div('eat-preview-float__body');
echo html_writer::div(
    widget::render($blockcontext, $courseid, $previewcfg),
    'eledia_aitutor-page eat-instance-preview'
);
echo html_writer::end_div();
echo html_writer::end_div();

// Wrap the moodleform in the settings hub (Design / Conversation / Technical cards),
// mirroring the admin settings experience. The group -> major mapping matches the
// admin settings page (see settings.php). Degrades to the plain form if the JS
// cannot build the hub.
if (shell::is_available()) {
    $designgroups = ['accent', 'surfaces', 'text', 'bubbles', 'states', 'shape', 'effects', 'launcher', 'footer', 'files'];
    $conversationgroups = ['persona', 'conversation'];
    $groupmajors = ['quicksettings' => 'technical'];
    foreach ($designgroups as $g) {
        $groupmajors['insgroup_' . $g] = 'design';
    }
    foreach ($conversationgroups as $g) {
        $groupmajors['insgroup_' . $g] = 'conversation';
    }
    $hubconfig = [
        'sectionCards' => [
            [
                'key' => 'design',
                'icon' => 'fa-palette',
                'title' => get_string('setting_section_design', 'block_eledia_aitutor'),
                'body' => get_string('setting_section_design_desc', 'block_eledia_aitutor'),
            ],
            [
                'key' => 'conversation',
                'icon' => 'fa-comments',
                'title' => get_string('setting_section_conversation', 'block_eledia_aitutor'),
                'body' => get_string('setting_section_conversation_desc', 'block_eledia_aitutor'),
            ],
            [
                'key' => 'technical',
                'icon' => 'fa-plug',
                'title' => get_string('setting_section_technical', 'block_eledia_aitutor'),
                'body' => get_string('setting_section_technical_desc', 'block_eledia_aitutor'),
            ],
        ],
        'groupMajors' => $groupmajors,
        'hubTitle' => get_string('settings_hub_title', 'block_eledia_aitutor'),
        'hubDesc' => get_string('settings_hub_desc', 'block_eledia_aitutor'),
        'topicsLabel' => get_string('settings_hub_topics_label', 'block_eledia_aitutor'),
    ];
    $encodedhub = json_encode(
        $hubconfig,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $PAGE->requires->js_amd_inline(
        "require(['block_eledia_aitutor/instance_settings_shell'], function(shell) {"
        . "shell.init({$encodedhub});"
        . "});"
    );
}

// Live preview: re-theme the inert preview widget client-side as the design fields
// change. Pass the registry key -> --eat-token map and each token field's type so the
// module knows how to read/apply each value, plus the defaults used when a field is
// cleared (so the preview reverts exactly like the saved render would).
$previewtokens = [];
$previewtypes = [];
foreach (registry::token_keys() as $regkey => $token) {
    $previewtokens[$regkey] = $token;
    $entry = registry::get($regkey);
    $previewtypes[$regkey] = (string) ($entry['type'] ?? '');
}
$previewmeta = [
    'tokenMap' => $previewtokens,
    'fieldTypes' => $previewtypes,
    'defaults' => [
        'welcome' => get_string('default_welcome', 'block_eledia_aitutor'),
        'persona' => get_string('default_persona', 'block_eledia_aitutor'),
        'poweredby' => get_string('poweredby', 'block_eledia_aitutor'),
    ],
];
$encodedpreview = json_encode(
    $previewmeta,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$PAGE->requires->js_amd_inline(
    "require(['block_eledia_aitutor/instance_preview'], function(preview) {"
    . "preview.init({$encodedpreview});"
    . "});"
);

echo html_writer::end_div();

shell::close();
echo $OUTPUT->footer();
