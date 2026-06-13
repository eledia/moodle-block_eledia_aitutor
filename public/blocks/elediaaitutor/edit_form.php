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
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Title.
        $mform->addElement('text', 'config_title', get_string('config_title', 'block_elediaaitutor'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_elediaaitutor'));

        // Display mode.
        $modes = [
            'embedded' => get_string('displaymode_embedded', 'block_elediaaitutor'),
            'docked' => get_string('displaymode_docked', 'block_elediaaitutor'),
            'modal' => get_string('displaymode_modal', 'block_elediaaitutor'),
            'fullscreen' => get_string('displaymode_fullscreen', 'block_elediaaitutor'),
        ];
        $mform->addElement('select', 'config_displaymode', get_string('config_displaymode', 'block_elediaaitutor'), $modes);
        $mform->setDefault('config_displaymode', get_config('block_elediaaitutor', 'defaultdisplaymode') ?: 'embedded');
        $mform->addHelpButton('config_displaymode', 'config_displaymode', 'block_elediaaitutor');

        // Course context.
        $mform->addElement('selectyesno', 'config_passcoursecontext',
            get_string('config_passcoursecontext', 'block_elediaaitutor'));
        $mform->setDefault('config_passcoursecontext', 1);
        $mform->addHelpButton('config_passcoursecontext', 'config_passcoursecontext', 'block_elediaaitutor');

        // Optional fixed course id.
        $mform->addElement('text', 'config_fixedcourseid', get_string('config_fixedcourseid', 'block_elediaaitutor'));
        $mform->setType('config_fixedcourseid', PARAM_INT);
        $mform->setDefault('config_fixedcourseid', 0);
        $mform->addHelpButton('config_fixedcourseid', 'config_fixedcourseid', 'block_elediaaitutor');
        $mform->disabledIf('config_fixedcourseid', 'config_passcoursecontext', 'eq', 0);

        // Welcome message.
        $mform->addElement('textarea', 'config_welcomemessage',
            get_string('config_welcomemessage', 'block_elediaaitutor'), ['rows' => 3, 'cols' => 50]);
        $mform->setType('config_welcomemessage', PARAM_TEXT);
        $mform->setDefault('config_welcomemessage', get_string('default_welcome', 'block_elediaaitutor'));

        // Persona label.
        $mform->addElement('text', 'config_persona', get_string('config_persona', 'block_elediaaitutor'));
        $mform->setType('config_persona', PARAM_TEXT);
        $mform->setDefault('config_persona', get_string('default_persona', 'block_elediaaitutor'));

        // Per-instance branding overrides (empty = use the site branding). The
        // logo, footer/white-label, font and custom CSS are institution-level
        // (site admin) only.
        $thememenu = ['' => get_string('config_theme_site', 'block_elediaaitutor')]
            + \block_elediaaitutor\local\themes::menu();
        $mform->addElement('select', 'config_theme',
            get_string('config_theme', 'block_elediaaitutor'), $thememenu);
        $mform->setDefault('config_theme', '');
        $mform->addHelpButton('config_theme', 'config_theme', 'block_elediaaitutor');

        $mform->addElement('text', 'config_launchlabel',
            get_string('config_launchlabel', 'block_elediaaitutor'));
        $mform->setType('config_launchlabel', PARAM_TEXT);
        $mform->addHelpButton('config_launchlabel', 'config_launchlabel', 'block_elediaaitutor');

        $mform->addElement('text', 'config_brandaccent',
            get_string('config_brandaccent', 'block_elediaaitutor'), ['placeholder' => '#1e3f59']);
        $mform->setType('config_brandaccent', PARAM_TEXT);
        $mform->addHelpButton('config_brandaccent', 'config_brandaccent', 'block_elediaaitutor');

        $mform->addElement('text', 'config_brandbubble',
            get_string('config_brandbubble', 'block_elediaaitutor'), ['placeholder' => '#fce9db']);
        $mform->setType('config_brandbubble', PARAM_TEXT);
        $mform->addHelpButton('config_brandbubble', 'config_brandbubble', 'block_elediaaitutor');

        $mform->addElement('text', 'config_brandbotbubble',
            get_string('config_brandbotbubble', 'block_elediaaitutor'), ['placeholder' => '#ffffff']);
        $mform->setType('config_brandbotbubble', PARAM_TEXT);
        $mform->addHelpButton('config_brandbotbubble', 'config_brandbotbubble', 'block_elediaaitutor');

        // Launcher button style ('' = follow the site setting).
        $mform->addElement('select', 'config_launcherstyle',
            get_string('config_launcherstyle', 'block_elediaaitutor'), [
                '' => get_string('config_launcherstyle_site', 'block_elediaaitutor'),
                'pill' => get_string('launcherstyle_pill', 'block_elediaaitutor'),
                'solid' => get_string('launcherstyle_solid', 'block_elediaaitutor'),
            ]);
        $mform->setDefault('config_launcherstyle', '');
        $mform->addHelpButton('config_launcherstyle', 'config_launcherstyle', 'block_elediaaitutor');

        // Per-instance logo + conversation avatar uploads (empty = site branding).
        $imageopts = ['maxfiles' => 1, 'subdirs' => 0,
            'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];
        $mform->addElement('filemanager', 'config_logo',
            get_string('config_logo', 'block_elediaaitutor'), null, $imageopts);
        $mform->addHelpButton('config_logo', 'config_logo', 'block_elediaaitutor');
        $mform->addElement('filemanager', 'config_avatar',
            get_string('config_avatar', 'block_elediaaitutor'), null, $imageopts);
        $mform->addHelpButton('config_avatar', 'config_avatar', 'block_elediaaitutor');

        // Pedagogical answer style.
        $styles = [
            'explain' => get_string('answerstyle_explain', 'block_elediaaitutor'),
            'hint' => get_string('answerstyle_hint', 'block_elediaaitutor'),
            'quiz' => get_string('answerstyle_quiz', 'block_elediaaitutor'),
        ];
        $mform->addElement('select', 'config_answerstyle', get_string('config_answerstyle', 'block_elediaaitutor'),
            $styles);
        $mform->setDefault('config_answerstyle', 'explain');
        $mform->addHelpButton('config_answerstyle', 'config_answerstyle', 'block_elediaaitutor');

        $mform->addElement('selectyesno', 'config_allowstylechange',
            get_string('config_allowstylechange', 'block_elediaaitutor'));
        $mform->setDefault('config_allowstylechange', 1);

        // Prompt starters (one per line; empty falls back to the site default).
        $mform->addElement('textarea', 'config_promptstarters',
            get_string('config_promptstarters', 'block_elediaaitutor'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('config_promptstarters', PARAM_TEXT);
        $mform->addHelpButton('config_promptstarters', 'config_promptstarters', 'block_elediaaitutor');

        // Daily message limit override (-1 = site default, 0 = unlimited).
        $mform->addElement('text', 'config_dailylimit', get_string('config_dailylimit', 'block_elediaaitutor'));
        $mform->setType('config_dailylimit', PARAM_INT);
        $mform->setDefault('config_dailylimit', -1);
        $mform->addHelpButton('config_dailylimit', 'config_dailylimit', 'block_elediaaitutor');

        // Answer source (grounded vs LLM-only). The choice is only offered when
        // it is real: LLM-only must be allowed site-wide, and grounded answers
        // require the course to have an ingested knowledge base. When grounding
        // is unavailable the form states the effective mode instead of offering
        // an inert option (the server forces it either way).
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
            // No knowledge base: state the effective mode (LLM-only, or
            // unavailable when LLM-only is disallowed) instead of a dead select.
            $mform->addElement('static', 'ragmode_note',
                get_string('config_ragmode', 'block_elediaaitutor'),
                $llmallowed
                    ? get_string('config_ragmode_nokb', 'block_elediaaitutor')
                    : get_string('llmonly_unavailable', 'block_elediaaitutor'));
        }

        // History enabled.
        $mform->addElement('selectyesno', 'config_historyenabled',
            get_string('config_historyenabled', 'block_elediaaitutor'));
        $mform->setDefault('config_historyenabled', 1);
    }

    /**
     * Prepare the per-instance logo/avatar file-manager draft areas from the
     * stored block-context files (mirrors the block_html pattern).
     *
     * @param array|\stdClass $defaults The instance config defaults.
     * @return void
     */
    public function set_data($defaults) {
        if (!empty($this->block->instance->id)) {
            $context = $this->block->context;
            foreach ([
                'config_logo' => \block_elediaaitutor\local\branding::INSTANCE_LOGO_FILEAREA,
                'config_avatar' => \block_elediaaitutor\local\branding::INSTANCE_AVATAR_FILEAREA,
            ] as $field => $filearea) {
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
