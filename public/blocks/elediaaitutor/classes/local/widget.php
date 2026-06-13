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
    /**
     * The admin custom CSS, scoped to the widget classes and returned at most
     * once per request (it is global, so emitting it per instance would
     * duplicate identical rules on pages with several blocks).
     *
     * @return string The custom CSS, or '' (also '' on subsequent calls).
     */
    private static function custom_css_once(): string {
        static $emitted = false;
        if ($emitted) {
            return '';
        }
        $css = security::custom_css();
        if ($css === '') {
            return '';
        }
        $emitted = true;
        return $css;
    }

    public static function render(\context $context, int $courseid, array $options = []): string {
        global $OUTPUT, $PAGE, $USER;

        // Resolve the answer mode (grounded / LLM-only / unavailable). When the
        // tutor cannot answer here (no knowledge base and LLM-only disallowed),
        // show a friendly notice and skip the chat UI entirely.
        $blockconfig = (object) ['ragmode' => (string) ($options['ragmode'] ?? chat_mode::MODE_GROUNDED)];
        $mode = chat_mode::resolve($courseid, $blockconfig);
        if ($mode === chat_mode::MODE_UNAVAILABLE) {
            return $OUTPUT->render_from_template('block_elediaaitutor/unavailable', [
                'isadmin' => false,
                'message' => get_string('llmonly_unavailable', 'block_elediaaitutor'),
            ]);
        }

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
        $consented = consent::has_consented((int) $USER->id);

        // Resolve institutional branding (per-instance overrides over the site
        // defaults). Two logos: the tutor logo (header + launcher) and the
        // conversation avatar (per-message). Each: instance upload ?: site
        // upload ?: (avatar) the logo ?: the built-in eLeDia mark.
        $brand = branding::resolve($options);
        $defaultlogo = $OUTPUT->image_url('logo', 'block_elediaaitutor')->out(false);
        $logourl = branding::instance_file_url($context, branding::INSTANCE_LOGO_FILEAREA)
            ?: branding::site_logo_url() ?: $defaultlogo;
        $avatarurl = branding::instance_file_url($context, branding::INSTANCE_AVATAR_FILEAREA)
            ?: branding::site_avatar_url() ?: $logourl;
        $brandstyle = branding::css_variables($brand);

        // Institution-specific privacy guidelines (admin setting). When set, the
        // formatted text replaces the built-in informational sections of the
        // privacy dialogue; filters (e.g. multilang) apply at render time.
        $privacytext = (string) get_config('block_elediaaitutor', 'privacyguidelinestext');
        $privacyhtml = trim(strip_tags($privacytext)) !== ''
            ? format_text($privacytext, FORMAT_HTML, ['context' => $context])
            : '';

        // Prompt starters: instance value, falling back to the site default.
        // One per line, capped so the welcome area stays tidy.
        $startersraw = (string) ($options['promptstarters'] ?? '');
        if (trim($startersraw) === '') {
            $startersraw = (string) get_config('block_elediaaitutor', 'promptstarters');
        }
        $starters = [];
        foreach (preg_split('/\R/', $startersraw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $starters[] = format_string($line);
            }
            if (count($starters) >= 6) {
                break;
            }
        }

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
            'logourl' => $logourl,
            'avatarurl' => $avatarurl,
            'welcome' => format_text($welcome, FORMAT_MOODLE, ['context' => $context, 'filter' => false]),
            'historyenabled' => $historyenabled,
            'launchlabel' => $brand['launchlabel'],
            'launcherstyle' => $brand['launcherstyle'],
            'launchfab' => $brand['launcherstyle'] === 'fab',
            'stylechoice' => $allowstylechange,
            'styles' => $styles,
            'stylelocked' => !$allowstylechange && $answerstyle !== 'explain',
            'lockedlabel' => get_string('answerstyle_' . $answerstyle, 'block_elediaaitutor'),
            'consented' => $consented,
            'starters' => $starters,
            'hasstarters' => !empty($starters),
            'llmonly' => $mode === chat_mode::MODE_LLMONLY,
            // Branding: CSS-variable overrides applied inline on the root AND the
            // panel. The panel re-declares the --eat-* tokens on itself (it is
            // portalled out of the root in overlay modes), so an inline style is
            // what reliably wins for both elements.
            'brandvars' => $brandstyle,
            'showfooter' => $brand['footertext'] !== '',
            'footertext' => $brand['footertext'],
            'customcss' => self::custom_css_once(),
        ];

        // Non-secret JS config. This can be large (institution privacy HTML,
        // brand variables), so it is embedded as a JSON data-island in the
        // template rather than passed through js_call_amd, whose argument
        // string Moodle caps at 1024 chars.
        $jsconfig = [
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
            'ragmode' => $mode,
            // Brand variables so JS-created modals (portalled to <body>) can be
            // themed too — see TutorChat.applyBrand().
            'brandvars' => $brandstyle,
        ];
        // JSON_HEX_TAG keeps any HTML in privacyhtml from closing the <script>.
        $templatecontext['configjson'] = json_encode($jsconfig,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html = $OUTPUT->render_from_template('block_elediaaitutor/launcher', $templatecontext);

        // Pass only the element id; the JS reads the rest from the data-island.
        $PAGE->requires->js_call_amd('block_elediaaitutor/chat', 'init', [$uniqid]);

        return $html;
    }
}
