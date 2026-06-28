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
}
