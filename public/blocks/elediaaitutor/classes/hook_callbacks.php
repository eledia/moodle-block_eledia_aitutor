<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Global output hook callbacks for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaaitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_elediaaitutor;

defined('MOODLE_INTERNAL') || die();

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

        $url = (new moodle_url('/blocks/elediaaitutor/configuration.php'))->out(false);
        $label = get_string('navbar_dashboard_label', 'block_elediaaitutor');
        $PAGE->requires->js_call_amd('block_elediaaitutor/admin_launcher', 'init');
        $hook->add_html(
            '<div id="eat-admin-launcher-host" class="eat-admin-launcher-host" hidden>' .
                '<a class="eat-admin-launcher-host__link" href="' . s($url) . '" title="' . s($label) . '" ' .
                    'aria-label="' . s($label) . '">' .
                    '<span class="eat-admin-launcher-host__icon" aria-hidden="true">' .
                        '<svg viewBox="0 0 24 24" focusable="false">' .
                            '<path d="M12 4v2"/>' .
                            '<path d="M8.5 4h7"/>' .
                            '<path d="M5 9.5c0-1.4 1.1-2.5 2.5-2.5h9c1.4 0 2.5 1.1 2.5 2.5v4.7' .
                                'c0 1.4-1.1 2.5-2.5 2.5H11l-4 3v-3H7.5c-1.4 0-2.5-1.1-2.5-2.5V9.5z"/>' .
                            '<path d="M9 12h.01"/>' .
                            '<path d="M15 12h.01"/>' .
                            '<path d="M10 14.5c1.2.7 2.8.7 4 0"/>' .
                        '</svg>' .
                    '</span>' .
                    '<span class="sr-only">' . s($label) . '</span>' .
                '</a>' .
            '</div>'
        );
    }
}
