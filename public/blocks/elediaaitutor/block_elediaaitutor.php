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

use block_elediaaitutor\local\ltm;
use block_elediaaitutor\local\security;
use block_elediaaitutor\local\token_provider;

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
        global $OUTPUT, $USER, $PAGE;

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
        $configerror = $this->detect_config_error();
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

        $courseid = $this->resolve_course_id();
        $displaymode = $this->get_instance_config('displaymode', get_config('block_elediaaitutor', 'defaultdisplaymode') ?: 'embedded');
        $historyenabled = (int) $this->get_instance_config('historyenabled', 1) === 1
            && has_capability('block/elediaaitutor:viewhistory', $context);

        $uniqid = 'elediaaitutor_' . uniqid();
        $welcome = (string) $this->get_instance_config('welcomemessage', get_string('default_welcome', 'block_elediaaitutor'));
        $persona = (string) $this->get_instance_config('persona', get_string('default_persona', 'block_elediaaitutor'));

        $avatarurl = $OUTPUT->image_url('logo', 'block_elediaaitutor')->out(false);

        // Pedagogical answer style: instance default plus whether learners may switch.
        $answerstyle = (string) $this->get_instance_config('answerstyle', 'explain');
        if (!in_array($answerstyle, ['explain', 'hint', 'quiz'], true)) {
            $answerstyle = 'explain';
        }
        $allowstylechange = (int) $this->get_instance_config('allowstylechange', 1) === 1;
        $styles = [];
        foreach (['explain', 'hint', 'quiz'] as $style) {
            $styles[] = [
                'key' => $style,
                'label' => get_string('answerstyle_' . $style, 'block_elediaaitutor'),
                'active' => $style === $answerstyle,
            ];
        }

        $templatecontext = [
            'uniqid' => $uniqid,
            'instanceid' => (int) $this->instance->id,
            'displaymode' => $displaymode,
            'embedded' => $displaymode === 'embedded',
            'persona' => format_string($persona),
            'avatarurl' => $avatarurl,
            'welcome' => format_text($welcome, FORMAT_MOODLE, ['context' => $context, 'filter' => false]),
            'historyenabled' => $historyenabled,
            'launchlabel' => get_string('launch', 'block_elediaaitutor'),
            'stylechoice' => $allowstylechange,
            'styles' => $styles,
            'stylelocked' => !$allowstylechange && $answerstyle !== 'explain',
            'lockedlabel' => get_string('answerstyle_' . $answerstyle, 'block_elediaaitutor'),
        ];

        $this->content->text = $OUTPUT->render_from_template('block_elediaaitutor/launcher', $templatecontext);

        // The JS module owns all behaviour; it only receives non-secret config.
        $PAGE->requires->js_call_amd('block_elediaaitutor/chat', 'init', [[
            'uniqid' => $uniqid,
            'contextid' => $context->id,
            'courseid' => $courseid,
            'displaymode' => $displaymode,
            'historyenabled' => $historyenabled,
            'streaming' => security::streaming_enabled(),
            'maxlength' => security::max_message_length(),
            'persona' => format_string($persona),
            'avatarurl' => $avatarurl,
            'ltmenabled' => ltm::is_enabled((int) $USER->id),
            'candelete' => has_capability('block/elediaaitutor:deleteownhistory', $context),
            'answerstyle' => $answerstyle,
            'allowstylechange' => $allowstylechange,
        ]]);

        // Teachers get a footer link to the course question-analytics report.
        if ($courseid > 0 && \block_elediaaitutor\local\question_log::is_enabled()) {
            $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
            if ($coursecontext && has_capability('block/elediaaitutor:viewreports', $coursecontext)) {
                $this->content->footer = html_writer::link(
                    new moodle_url('/blocks/elediaaitutor/report.php', ['courseid' => $courseid]),
                    get_string('report_link', 'block_elediaaitutor')
                );
            }
        }

        return $this->content;
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
     * Detect a fatal configuration problem, returning an admin-facing message.
     *
     * @return string|null Null when configuration is healthy.
     */
    private function detect_config_error(): ?string {
        if (!token_provider::is_connector_available()) {
            return get_string('error_connector_missing', 'block_elediaaitutor');
        }
        if (security::mcp_service_id() <= 0) {
            return get_string('error_service_not_configured', 'block_elediaaitutor');
        }
        if (security::rag_server_url() === '') {
            return get_string('error_rag_url_missing', 'block_elediaaitutor');
        }
        return null;
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
