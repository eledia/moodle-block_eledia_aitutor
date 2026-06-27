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
 * @package     block_elediaaitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_elediaaitutor;

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
                            '<path d="M12 3.4v2.1"/>' .
                            '<path d="M9.2 3.4h5.6"/>' .
                            '<path d="M5.4 10.2c0-2.1 1.7-3.8 3.8-3.8h5.6c2.1 0 3.8 1.7 3.8 3.8v3.2' .
                                'c0 2.1-1.7 3.8-3.8 3.8h-3.1l-3.8 2.8v-3.1c-1.5-.5-2.5-1.9-2.5-3.5v-3.2z"/>' .
                            '<path d="M9.4 11.7h.01"/>' .
                            '<path d="M14.6 11.7h.01"/>' .
                            '<path d="M9.7 14.2h4.6"/>' .
                        '</svg>' .
                    '</span>' .
                    '<span class="sr-only">' . s($label) . '</span>' .
                '</a>' .
            '</div>'
        );
    }
}
