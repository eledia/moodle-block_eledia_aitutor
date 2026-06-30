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
 * Hook callbacks for the eLeDia.ai Tutor block.
 *
 * @package     block_eledia_aitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \block_eledia_aitutor\hook_callbacks::class . '::inject_admin_launcher',
        'priority' => 480,
    ],
    [
        // "Configure <this tutor block>" opens the Plugin Shell (the suitable settings UI)
        // instead of Moodle's generic block edit form. No-JS fallback: the control falls
        // back to a bui_editid page load, which this intercepts before any output.
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \block_eledia_aitutor\hook_callbacks::class . '::redirect_block_config',
        'priority' => 500,
    ],
    [
        // With JS on, the "Configure" control opens a modal (core_block/edit) rather than
        // navigating; this loads the client-side helper that redirects it to the shell.
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \block_eledia_aitutor\hook_callbacks::class . '::configure_block_in_shell',
        'priority' => 470,
    ],
];
