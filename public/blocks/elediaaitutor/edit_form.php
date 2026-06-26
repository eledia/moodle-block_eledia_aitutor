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
 * Per-instance configuration form for the eLeDia.ai Tutor block.
 *
 * Structural fields (title, course wiring, limits, answer source) are fixed. The
 * persona, every visual token and the behaviour/launcher/footer toggles are
 * generated from {@see \block_elediaaitutor\local\registry}, and only the keys
 * the admin has exposed (expose_<key>) appear — empty always means "follow site".
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_elediaaitutor\local\branding;
use block_elediaaitutor\local\registry;

defined('MOODLE_INTERNAL') || die();

/**
 * Block instance settings form.
 */
class block_elediaaitutor_edit_form extends block_edit_form {
    /**
     * Define the instance-specific form fields.
     *
     * @param MoodleQuickForm $mform The form.
     * @return void
     */
    protected function specific_definition($mform): void {
        \block_elediaaitutor\local\formhelper::register_colour_element();
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Import / export this instance's tutor (settings + images), or apply a
        // site preset — available once the block exists.
        if (!empty($this->block->instance->id)) {
            $shelllink = new \moodle_url('/blocks/elediaaitutor/edit_instance.php',
                ['blockid' => (int) $this->block->instance->id]);
            $toolslink = new \moodle_url('/blocks/elediaaitutor/instance_tutor.php',
                ['blockid' => (int) $this->block->instance->id]);
            $actions = \html_writer::link($shelllink,
                    get_string('instance_shell_edit_link', 'block_elediaaitutor'),
                    ['class' => 'btn btn-primary', 'target' => '_top']) . ' ' .
                \html_writer::link($toolslink, get_string('instancetutor_link', 'block_elediaaitutor'),
                    ['class' => 'btn btn-secondary', 'target' => '_top']);
            $mform->addElement('static', 'tutorio', '', $actions);
        }

        // Title.
        $mform->addElement('text', 'config_title', get_string('config_title', 'block_elediaaitutor'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_elediaaitutor'));

        // Course context wiring.
        $mform->addElement('selectyesno', 'config_passcoursecontext',
            get_string('config_passcoursecontext', 'block_elediaaitutor'));
        $mform->setDefault('config_passcoursecontext', 1);
        $mform->addHelpButton('config_passcoursecontext', 'config_passcoursecontext', 'block_elediaaitutor');

        $mform->addElement('text', 'config_fixedcourseid', get_string('config_fixedcourseid', 'block_elediaaitutor'));
        $mform->setType('config_fixedcourseid', PARAM_INT);
        $mform->setDefault('config_fixedcourseid', 0);
        $mform->addHelpButton('config_fixedcourseid', 'config_fixedcourseid', 'block_elediaaitutor');
        $mform->disabledIf('config_fixedcourseid', 'config_passcoursecontext', 'eq', 0);

        // Daily message limit override (-1 = site default, 0 = unlimited).
        $mform->addElement('text', 'config_dailylimit', get_string('config_dailylimit', 'block_elediaaitutor'));
        $mform->setType('config_dailylimit', PARAM_INT);
        $mform->setDefault('config_dailylimit', -1);
        $mform->addHelpButton('config_dailylimit', 'config_dailylimit', 'block_elediaaitutor');

        // Answer source (grounded vs LLM-only). Offered only when it is a real
        // choice: LLM-only allowed site-wide AND the course has a knowledge base.
        $grounding = \block_elediaaitutor\local\chat_mode::ingestion_available($this->effective_courseid());
        $llmallowed = \block_elediaaitutor\local\chat_mode::is_llm_allowed();
        if ($llmallowed && $grounding) {
            $mform->addElement('select', 'config_ragmode',
                get_string('config_ragmode', 'block_elediaaitutor'), [
                    \block_elediaaitutor\local\chat_mode::MODE_GROUNDED =>
                        get_string('ragmode_grounded', 'block_elediaaitutor'),
                    \block_elediaaitutor\local\chat_mode::MODE_LLMONLY =>
                        get_string('ragmode_llmonly', 'block_elediaaitutor'),
                ]);
            $mform->setDefault('config_ragmode', \block_elediaaitutor\local\chat_mode::MODE_GROUNDED);
            $mform->addHelpButton('config_ragmode', 'config_ragmode', 'block_elediaaitutor');
        } else if (!$grounding) {
            $mform->addElement('static', 'ragmode_note',
                get_string('config_ragmode', 'block_elediaaitutor'),
                $llmallowed
                    ? get_string('config_ragmode_nokb', 'block_elediaaitutor')
                    : get_string('llmonly_unavailable', 'block_elediaaitutor'));
        }

        // Registry-driven tutor fields: persona, design tokens, behaviour,
        // launcher, footer and images — grouped, and only the keys the admin
        // has exposed for per-instance override. Empty = follow the site.
        foreach (registry::groups() as $group) {
            $exposed = array_filter(registry::group_keys($group),
                static fn(string $k): bool => registry::is_exposed($k));
            if (empty($exposed)) {
                continue;
            }
            $mform->addElement('header', 'insgroup_' . $group,
                get_string('reggroup_' . $group, 'block_elediaaitutor'));
            $mform->setExpanded('insgroup_' . $group, false);
            foreach ($exposed as $key) {
                $this->add_instance_field($mform, $key, registry::get($key));
            }
        }
    }

    /**
     * Add one per-instance field for a registry key. Empty/blank always means
     * "follow the site value", so selects gain a leading "use site" option and
     * checkboxes become a tri-state select.
     *
     * @param MoodleQuickForm $mform The form.
     * @param string $key Registry key.
     * @param array<string, mixed> $entry Registry descriptor.
     * @return void
     */
    private function add_instance_field($mform, string $key, array $entry): void {
        $field = 'config_' . $key;
        $label = $this->reglabel($key, $entry);

        switch ($entry['type']) {
            case 'file':
                $imageopts = ['maxfiles' => 1, 'subdirs' => 0,
                    'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];
                $mform->addElement('filemanager', $field, $label, null, $imageopts);
                break;

            case 'colour':
                // Moodle's native colour picker (text field + swatch; paste a hex too).
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
                // cssvalue / font / text. Tokens with friendly named options
                // become a dropdown (no raw CSS); plain text stays a text box.
                if (!empty($entry['choices'])) {
                    $current = isset($this->block->config->$key) ? (string) $this->block->config->$key : null;
                    $options = registry::choice_select_options($key,
                        get_string('config_usesite', 'block_elediaaitutor'), $current);
                    $mform->addElement('select', $field, $label, $options);
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
     * Friendly label for a registry key, falling back to the raw token name.
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
     * Prepare the per-instance logo/avatar file-manager draft areas from the
     * stored block-context files (only for areas the admin has exposed).
     *
     * @param array|\stdClass $defaults The instance config defaults.
     * @return void
     */
    public function set_data($defaults) {
        if (!empty($this->block->instance->id)) {
            $context = $this->block->context;
            $filemap = [
                'config_logo' => [branding::INSTANCE_LOGO_FILEAREA, 'logo'],
                'config_avatar' => [branding::INSTANCE_AVATAR_FILEAREA, 'avatar'],
            ];
            foreach ($filemap as $field => [$filearea, $key]) {
                if (!registry::is_exposed($key)) {
                    continue;
                }
                $draftid = file_get_submitted_draft_itemid($field);
                file_prepare_draft_area($draftid, $context->id, 'block_elediaaitutor',
                    $filearea, 0, ['maxfiles' => 1, 'subdirs' => 0]);
                $defaults->{$field} = $draftid;
            }
        }
        parent::set_data($defaults);
    }

    /**
     * The course this instance will chat in, mirroring the block's own
     * resolution: a configured fixed course id wins, otherwise the page course
     * when course context is passed, else 0 (global chat).
     *
     * @return int Course id, or 0 for global chat.
     */
    private function effective_courseid(): int {
        $config = $this->block->config ?? new \stdClass();

        $fixed = (int) ($config->fixedcourseid ?? 0);
        if ($fixed > 0) {
            return \block_elediaaitutor\local\security::course_chat_enabled() ? $fixed : 0;
        }

        $passcontext = !isset($config->passcoursecontext) || (int) $config->passcoursecontext === 1;
        $pagecourseid = (int) ($this->block->page->course->id ?? 0);
        if ($passcontext && \block_elediaaitutor\local\security::course_chat_enabled()
                && $pagecourseid > 0 && $pagecourseid !== SITEID) {
            return $pagecourseid;
        }
        return 0;
    }
}
