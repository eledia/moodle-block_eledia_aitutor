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
 * @package     block_elediaaitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_elediaaitutor\local\branding;
use block_elediaaitutor\local\chat_mode;
use block_elediaaitutor\local\formhelper;
use block_elediaaitutor\local\registry;
use block_elediaaitutor\local\security;
use block_elediaaitutor\output\shell;

/**
 * Moodle form for the in-shell instance editor.
 */
class block_elediaaitutor_instance_shell_form extends moodleform {
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
            get_string('instance_shell_quicksettings', 'block_elediaaitutor')
        );
        $mform->setExpanded('quicksettings', true);

        $mform->addElement('text', 'config_title', get_string('config_title', 'block_elediaaitutor'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_elediaaitutor'));

        $mform->addElement(
            'selectyesno',
            'config_passcoursecontext',
            get_string('config_passcoursecontext', 'block_elediaaitutor')
        );
        $mform->setDefault('config_passcoursecontext', 1);
        $mform->addHelpButton('config_passcoursecontext', 'config_passcoursecontext', 'block_elediaaitutor');

        $mform->addElement('text', 'config_fixedcourseid', get_string('config_fixedcourseid', 'block_elediaaitutor'));
        $mform->setType('config_fixedcourseid', PARAM_INT);
        $mform->setDefault('config_fixedcourseid', 0);
        $mform->addHelpButton('config_fixedcourseid', 'config_fixedcourseid', 'block_elediaaitutor');
        $mform->disabledIf('config_fixedcourseid', 'config_passcoursecontext', 'eq', 0);

        $mform->addElement('text', 'config_dailylimit', get_string('config_dailylimit', 'block_elediaaitutor'));
        $mform->setType('config_dailylimit', PARAM_INT);
        $mform->setDefault('config_dailylimit', -1);
        $mform->addHelpButton('config_dailylimit', 'config_dailylimit', 'block_elediaaitutor');

        $grounding = chat_mode::ingestion_available($this->effective_courseid());
        $llmallowed = chat_mode::is_llm_allowed();
        if ($llmallowed && $grounding) {
            $mform->addElement(
                'select',
                'config_ragmode',
                get_string('config_ragmode', 'block_elediaaitutor'),
                [
                    chat_mode::MODE_GROUNDED => get_string('ragmode_grounded', 'block_elediaaitutor'),
                    chat_mode::MODE_LLMONLY => get_string('ragmode_llmonly', 'block_elediaaitutor'),
                ]
            );
            $mform->setDefault('config_ragmode', chat_mode::MODE_GROUNDED);
            $mform->addHelpButton('config_ragmode', 'config_ragmode', 'block_elediaaitutor');
        } else if (!$grounding) {
            $mform->addElement(
                'static',
                'ragmode_note',
                get_string('config_ragmode', 'block_elediaaitutor'),
                $llmallowed
                    ? get_string('config_ragmode_nokb', 'block_elediaaitutor')
                : get_string('llmonly_unavailable', 'block_elediaaitutor')
            );
        }

        $mform->addElement(
            'static',
            'instance_tools',
            '',
            html_writer::link(
                new moodle_url(
                    '/blocks/elediaaitutor/instance_tutor.php',
                    ['blockid' => $this->blockid]
                ),
                get_string('instancetutor_link', 'block_elediaaitutor'),
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
                get_string('reggroup_' . $group, 'block_elediaaitutor')
            );
            $mform->setExpanded('insgroup_' . $group, $group === 'persona');
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
                $options = ['' => get_string('config_usesite', 'block_elediaaitutor')];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_elediaaitutor');
                }
                $mform->addElement('select', $field, $label, $options);
                $mform->setDefault($field, '');
                break;
            case 'checkbox':
                $mform->addElement('select', $field, $label, [
                    '' => get_string('config_usesite', 'block_elediaaitutor'),
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
                            get_string('config_usesite', 'block_elediaaitutor'),
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

        if (get_string_manager()->string_exists($field . '_help', 'block_elediaaitutor')) {
            $mform->addHelpButton($field, $field, 'block_elediaaitutor');
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
        if (get_string_manager()->string_exists('reg_' . $key, 'block_elediaaitutor')) {
            return get_string('reg_' . $key, 'block_elediaaitutor');
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
                'block_elediaaitutor',
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

$record = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'elediaaitutor'], '*', MUST_EXIST);
$blockcontext = \core\context\block::instance($blockid);
require_capability('block/elediaaitutor:manage', $blockcontext);

$parent = $blockcontext->get_parent_context();
$courseid = 0;
$returnurl = new moodle_url('/blocks/elediaaitutor/manage_tutors.php');
if ($parent && $parent->contextlevel === CONTEXT_COURSE) {
    $courseid = (int) $parent->instanceid;
    $course = get_course($courseid);
    $PAGE->set_course($course);
    $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    $PAGE->set_pagelayout('incourse');
} else {
    $PAGE->set_pagelayout('standard');
}

$pageurl = new moodle_url('/blocks/elediaaitutor/edit_instance.php', ['blockid' => $blockid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($blockcontext);
$PAGE->set_title(get_string('instance_shell_title', 'block_elediaaitutor'));
$PAGE->set_heading($parent ? $parent->get_context_name(false)
    : get_string('instance_shell_title', 'block_elediaaitutor'));
shell::require_css();

$config = !empty($record->configdata) ? unserialize_object(base64_decode($record->configdata)) : new stdClass();
if (!is_object($config)) {
    $config = new stdClass();
}

$defaults = new stdClass();
foreach ((array) $config as $key => $value) {
    $defaults->{'config_' . $key} = $value;
}
$defaults->config_title = $config->title ?? get_string('pluginname', 'block_elediaaitutor');
$defaults->config_passcoursecontext = (int) ($config->passcoursecontext ?? 1);
$defaults->config_fixedcourseid = (int) ($config->fixedcourseid ?? 0);
$defaults->config_dailylimit = (int) ($config->dailylimit ?? -1);
$defaults->config_ragmode = $config->ragmode ?? chat_mode::MODE_GROUNDED;

$form = new block_elediaaitutor_instance_shell_form($pageurl, [
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
                'block_elediaaitutor',
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
shell::open(shell::ACTIVE_TUTORS);

if (!shell::is_available()) {
    echo $OUTPUT->heading(get_string('instance_shell_title', 'block_elediaaitutor'), 2);
}

echo html_writer::start_div('path-block-elediaaitutor eat-instance-shell');
echo html_writer::tag(
    'h2',
    get_string('instance_shell_title', 'block_elediaaitutor'),
    ['class' => 'eat-section-heading']
);
echo html_writer::tag(
    'p',
    get_string('instance_shell_intro', 'block_elediaaitutor'),
    ['class' => 'text-muted']
);
$form->display();
echo html_writer::end_div();

shell::close();
echo $OUTPUT->footer();
