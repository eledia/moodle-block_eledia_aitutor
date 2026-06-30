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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Global output hook callbacks for the eLeDia.ai Tutor block.
 *
 * @package     block_eledia_aitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_eledia_aitutor;

use core\hook\output\before_http_headers;
use core\hook\output\before_standard_top_of_body_html_generation;
use moodle_url;

/**
 * Output hooks.
 */
final class hook_callbacks {
    /**
     * Inject an admin-only navbar launcher that opens the tutor dashboard.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function inject_admin_launcher(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (in_array($PAGE->pagelayout, ['login', 'popup', 'embedded', 'maintenance'], true)) {
            return;
        }
        if (!has_capability('moodle/site:config', \core\context\system::instance())) {
            return;
        }

        $url = (new moodle_url('/blocks/eledia_aitutor/configuration.php'))->out(false);
        $label = get_string('navbar_dashboard_label', 'block_eledia_aitutor');
        $PAGE->requires->js_call_amd('block_eledia_aitutor/admin_launcher', 'init');
        $hook->add_html(
            '<div id="eat-admin-launcher-host" class="eat-admin-launcher-host" hidden>' .
                '<a class="eat-admin-launcher-host__link" href="' . s($url) . '" title="' . s($label) . '" ' .
                    'aria-label="' . s($label) . '">' .
                    '<span class="eat-admin-launcher-host__icon" aria-hidden="true">' .
                        '<svg viewBox="0 0 24 24" focusable="false">' .
                            '<path d="M5.4 8.3c0-2.1 1.8-3.8 4-3.8h5.2c2.2 0 4 1.7 4 3.8v3.1' .
                                'c0 2.1-1.8 3.8-4 3.8h-2.5l-4.1 3v-3.3c-1.5-.5-2.6-1.8-2.6-3.5V8.3z"/>' .
                            '<path d="M16.7 3.2l.5 1.3 1.3.5-1.3.5-.5 1.3-.5-1.3-1.3-.5 1.3-.5.5-1.3z"/>' .
                            '<path d="M9 10.1h.01"/>' .
                            '<path d="M12 10.1h.01"/>' .
                            '<path d="M15 10.1h.01"/>' .
                        '</svg>' .
                    '</span>' .
                    '<span class="sr-only">' . s($label) . '</span>' .
                '</a>' .
            '</div>'
        );
    }

    /**
     * Send the block's "Configure" action to the Plugin Shell instead of Moodle's
     * generic block edit form — the shell is the suitable settings UI for this tutor.
     *
     * Site admins land on the full admin settings hub; teachers (block managers) land
     * on the per-instance shell. Appending {@code &eatfullform=1} to the configure URL
     * still reaches the standard form (e.g. for the "where this block appears"
     * placement/visibility options).
     *
     * @param before_http_headers $hook Unused; the trigger fires before any output.
     */
    public static function redirect_block_config(before_http_headers $hook): void {
        global $DB;

        // No-JS fallback. With JavaScript on, the block "Configure" control opens a
        // dynamic-form modal (core_block/edit) and never navigates with bui_editid, so
        // the redirect is handled client-side by the configure_redirect AMD module
        // ({@see self::configure_block_in_shell()}). Without JS, the control falls back
        // to a bui_editid page load, which this intercepts.
        // Only the initial "Configure" click — a GET carrying bui_editid. The save POST
        // and an explicit request for the full Moodle form (eatfullform=1) are left alone.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        $editid = optional_param('bui_editid', 0, PARAM_INT);
        if ($editid <= 0 || optional_param('eatfullform', 0, PARAM_BOOL)) {
            return;
        }
        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Intercept only our own block; every other block's config is untouched.
        $instance = $DB->get_record('block_instances', ['id' => $editid, 'blockname' => 'eledia_aitutor']);
        if (!$instance) {
            return;
        }
        $blockcontext = \core\context\block::instance($editid);
        if (!has_capability('block/eledia_aitutor:manage', $blockcontext)) {
            // Not manageable by this user: leave the core flow to handle access control.
            return;
        }

        // The per-instance shell reflects this specific block (admins reach the site-wide
        // settings via a link inside it).
        redirect(new moodle_url('/blocks/eledia_aitutor/edit_instance.php', ['blockid' => $editid]));
    }

    /**
     * In editing mode, load the client-side helper that sends this block's "Configure"
     * control to the per-instance Plugin Shell (the suitable settings UI, reflecting the
     * specific block) instead of opening Moodle's block-config modal. Site admins reach
     * the site-wide settings via a link inside that shell.
     * {@see self::redirect_block_config()} covers the no-JavaScript fallback.
     *
     * @param before_standard_top_of_body_html_generation $hook Unused.
     */
    public static function configure_block_in_shell(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isloggedin() || isguestuser() || !$PAGE->user_is_editing()) {
            // The block action controls (Configure) only appear in editing mode.
            return;
        }

        $config = [
            // Placeholder __ID__ is substituted with the clicked block's instance id in JS.
            'instanceUrl' => (new moodle_url(
                '/blocks/eledia_aitutor/edit_instance.php',
                ['blockid' => '__ID__']
            ))->out(false),
        ];
        $PAGE->requires->js_call_amd('block_eledia_aitutor/configure_redirect', 'init', [$config]);
    }
}
