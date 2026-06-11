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

declare(strict_types=1);

namespace block_elediaaitutor\local;

/**
 * Builds the chat widget (shell HTML + AMD init) for any host page.
 *
 * The same widget is rendered in two places: inside the block (which passes
 * its per-instance configuration) and on the standalone page
 * blocks/elediaaitutor/view.php used for Moodle App embedding (which uses the
 * site defaults). Keeping the assembly here means both stay in lockstep and
 * the block class stays thin.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widget {
    /**
     * Detect a fatal configuration problem, returning an admin-facing message.
     *
     * @return string|null Null when configuration is healthy.
     */
    public static function config_error(): ?string {
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
     * Whether a course has opted into the tutor.
     *
     * The opt-in signal is the teacher adding the tutor block to the course —
     * the same decision that controls the web UI. The standalone page and the
     * Moodle App course entry honour it, so the tutor never appears in courses
     * whose teachers did not choose it.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function course_has_tutor(int $courseid): bool {
        global $DB;
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }
        return $DB->record_exists('block_instances', [
            'blockname' => 'elediaaitutor',
            'parentcontextid' => $coursecontext->id,
        ]);
    }

    /**
     * Render the chat shell and queue its AMD initialisation.
     *
     * The caller is responsible for require_login and the use-capability check;
     * this method only assembles the (non-secret) widget.
     *
     * @param \context $context Context the AJAX calls will run against.
     * @param int $courseid Course id passed to the RAG server, 0 for global chat.
     * @param array $options Overrides: displaymode, welcomemessage, persona,
     *                       answerstyle, allowstylechange, historyenabled, instanceid.
     * @return string The widget HTML.
     */
    public static function render(\context $context, int $courseid, array $options = []): string {
        global $OUTPUT, $PAGE, $USER;

        $displaymode = (string) ($options['displaymode']
            ?? (get_config('block_elediaaitutor', 'defaultdisplaymode') ?: 'embedded'));
        $historyenabled = (bool) ($options['historyenabled'] ?? true)
            && has_capability('block/elediaaitutor:viewhistory', $context);
        $welcome = (string) ($options['welcomemessage']
            ?? get_string('default_welcome', 'block_elediaaitutor'));
        $persona = (string) ($options['persona']
            ?? get_string('default_persona', 'block_elediaaitutor'));

        // Pedagogical answer style: default plus whether learners may switch.
        $answerstyle = (string) ($options['answerstyle'] ?? 'explain');
        if (!in_array($answerstyle, ['explain', 'hint', 'quiz'], true)) {
            $answerstyle = 'explain';
        }
        $allowstylechange = (bool) ($options['allowstylechange'] ?? true);

        $uniqid = 'elediaaitutor_' . uniqid();
        $avatarurl = $OUTPUT->image_url('logo', 'block_elediaaitutor')->out(false);
        $consented = consent::has_consented((int) $USER->id);

        // Institution-specific privacy guidelines (admin setting). When set, the
        // formatted text replaces the built-in informational sections of the
        // privacy dialogue; filters (e.g. multilang) apply at render time.
        $privacytext = (string) get_config('block_elediaaitutor', 'privacyguidelinestext');
        $privacyhtml = trim(strip_tags($privacytext)) !== ''
            ? format_text($privacytext, FORMAT_HTML, ['context' => $context])
            : '';

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
            'instanceid' => (int) ($options['instanceid'] ?? 0),
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
            'consented' => $consented,
        ];

        $html = $OUTPUT->render_from_template('block_elediaaitutor/launcher', $templatecontext);

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
            'consented' => $consented,
            'privacyhtml' => $privacyhtml,
        ]]);

        return $html;
    }
}
