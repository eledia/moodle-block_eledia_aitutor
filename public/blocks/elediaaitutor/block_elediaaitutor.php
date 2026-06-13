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

use block_elediaaitutor\local\security;
use block_elediaaitutor\local\widget;

/**
 * eLeDia.ai Tutor block.
 *
 * Renders a polished, Moodle-native chatbot shell and wires it to the block's
 * authenticated AJAX endpoints. All RAG/Tutor traffic and secret handling happen
 * server-side; the browser only ever talks to Moodle.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_elediaaitutor extends block_base {
    /**
     * Initialise the block.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_elediaaitutor');
    }

    /**
     * This block has a global settings page.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Allow the block on all page types.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['all' => true];
    }

    /**
     * Only one instance per page makes sense.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Hide Moodle's block title chrome.
     *
     * The widget renders its own polished header (avatar, persona, presence and
     * actions), so the surrounding block title bar would only duplicate it.
     *
     * @return bool
     */
    public function hide_header(): bool {
        return true;
    }

    /**
     * Apply the per-instance title from configuration.
     *
     * @return void
     */
    public function specialization(): void {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        } else {
            $this->title = get_string('pluginname', 'block_elediaaitutor');
        }
    }

    /**
     * Build the block content.
     *
     * @return stdClass|null
     */
    public function get_content(): ?stdClass {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $context = $this->context;
        if ($context === null || !isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        // Surface configuration problems to those who can fix them; everyone else
        // gets a friendly unavailable notice instead of a broken widget.
        $canmanage = has_capability('block/elediaaitutor:manage', $context);
        $configerror = widget::config_error();
        if ($configerror !== null) {
            $this->content->text = $OUTPUT->render_from_template('block_elediaaitutor/unavailable', [
                'isadmin' => $canmanage,
                'message' => $canmanage ? $configerror : get_string('unavailable_user', 'block_elediaaitutor'),
            ]);
            return $this->content;
        }

        if (!has_capability('block/elediaaitutor:use', $context)) {
            $this->content->text = '';
            return $this->content;
        }

        // The shared widget builder assembles the shell + AMD init; the block
        // contributes its per-instance configuration. The standalone page
        // (view.php, used for Moodle App embedding) renders the same widget.
        $this->content->text = widget::render($context, $this->resolve_course_id(), [
            'instanceid' => (int) $this->instance->id,
            'displaymode' => $this->get_instance_config('displaymode',
                get_config('block_elediaaitutor', 'defaultdisplaymode') ?: 'embedded'),
            'historyenabled' => (int) $this->get_instance_config('historyenabled', 1) === 1,
            'welcomemessage' => (string) $this->get_instance_config('welcomemessage',
                get_string('default_welcome', 'block_elediaaitutor')),
            'persona' => (string) $this->get_instance_config('persona',
                get_string('default_persona', 'block_elediaaitutor')),
            'answerstyle' => (string) $this->get_instance_config('answerstyle', 'explain'),
            'allowstylechange' => (int) $this->get_instance_config('allowstylechange', 1) === 1,
            'promptstarters' => (string) $this->get_instance_config('promptstarters', ''),
            'ragmode' => (string) $this->get_instance_config('ragmode', 'grounded'),
            'theme' => (string) $this->get_instance_config('theme', ''),
            'launchlabel' => (string) $this->get_instance_config('launchlabel', ''),
            'launcherstyle' => (string) $this->get_instance_config('launcherstyle', ''),
            'brandaccent' => (string) $this->get_instance_config('brandaccent', ''),
            'brandbubble' => (string) $this->get_instance_config('brandbubble', ''),
            'brandbotbubble' => (string) $this->get_instance_config('brandbotbubble', ''),
        ]);

        // Teachers reach the question-analytics report via the course
        // navigation; see block_elediaaitutor_extend_navigation_course().

        return $this->content;
    }

    /**
     * Persist instance config, saving the per-instance logo/avatar uploads from
     * their draft areas into the block context (mirrors block_html).
     *
     * @param stdClass $data Submitted config.
     * @param bool $nolongerused Unused.
     * @return void
     */
    public function instance_config_save($data, $nolongerused = false): void {
        if ($this->context) {
            foreach ([
                'logo' => \block_elediaaitutor\local\branding::INSTANCE_LOGO_FILEAREA,
                'avatar' => \block_elediaaitutor\local\branding::INSTANCE_AVATAR_FILEAREA,
            ] as $field => $filearea) {
                if (!empty($data->$field)) {
                    file_save_draft_area_files((int) $data->$field, $this->context->id,
                        'block_elediaaitutor', $filearea, 0, ['maxfiles' => 1, 'subdirs' => 0]);
                }
                // The draft id is not stored in config (the files live in the area).
                unset($data->$field);
            }
        }
        parent::instance_config_save($data, $nolongerused);
    }

    /**
     * Resolve which course id (if any) to pass to the RAG server.
     *
     * A fixed course id configured on the instance wins; otherwise the current
     * course context is used when the instance opts into course context and the
     * page is inside a real course. Global chat returns 0.
     *
     * @return int
     */
    private function resolve_course_id(): int {
        $fixed = (int) $this->get_instance_config('fixedcourseid', 0);
        if ($fixed > 0) {
            return security::course_chat_enabled() ? $fixed : 0;
        }

        $passcontext = (int) $this->get_instance_config('passcoursecontext', 1) === 1;
        if ($passcontext && security::course_chat_enabled()
                && !empty($this->page->course->id) && (int) $this->page->course->id !== SITEID) {
            return (int) $this->page->course->id;
        }
        return 0;
    }

    /**
     * Read an instance config value with a default.
     *
     * @param string $name Config key.
     * @param mixed $default Default value.
     * @return mixed
     */
    private function get_instance_config(string $name, mixed $default): mixed {
        if (isset($this->config->$name) && $this->config->$name !== '') {
            return $this->config->$name;
        }
        return $default;
    }

    /**
     * Add the block's distinguishing CSS class to the container attributes.
     *
     * @return array
     */
    public function html_attributes(): array {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_elediaaitutor';
        return $attributes;
    }
}
