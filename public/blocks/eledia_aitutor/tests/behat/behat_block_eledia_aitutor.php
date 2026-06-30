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

// NOTE: no MOODLE_INTERNAL guard here; Behat may require this file before config.php.

/**
 * Behat page resolvers for the eLeDia.ai Tutor block.
 *
 * @package     block_eledia_aitutor
 * @category    test
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_eledia_aitutor extends behat_base {
    /**
     * Resolve plugin page URLs whose ids are only known at runtime.
     *
     * Use as: `I am on the "<identifier>" "block_eledia_aitutor > <type>" page`.
     * Recognised types:
     * - `coursechat` — the course-scoped standalone chat page; the identifier is the
     *   course shortname (resolved to a course id, so scenarios never hard-code ids,
     *   which are not deterministic between Behat scenarios).
     *
     * @param string $type Page type within the plugin.
     * @param string $identifier Disambiguating identifier (course shortname for `coursechat`).
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'coursechat':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/blocks/eledia_aitutor/view.php', ['courseid' => $courseid]);
            default:
                throw new Exception("Unrecognised block_eledia_aitutor page type '{$type}'.");
        }
    }
}
