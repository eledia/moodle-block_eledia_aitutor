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

/**
 * LernHive AI Suite feature provider for the eLeDia.ai Tutor block.
 *
 * @package     block_eledia_aitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_eledia_aitutor\lernhive_ai;

use local_lernhive_ai\feature\descriptor;
use local_lernhive_ai\feature\feature_provider as feature_provider_contract;
use moodle_url;

/**
 * Registers the installed tutor block with the central AI Suite launcher.
 */
final class feature_provider implements feature_provider_contract {
    #[\Override]
    public static function get_descriptors(): array {
        return [
            new descriptor(
                id: 'tutor',
                component: 'block_eledia_aitutor',
                name: get_string('pluginname', 'block_eledia_aitutor'),
                description: get_string('suite_feature_desc', 'block_eledia_aitutor'),
                launchurl: new moodle_url('/blocks/eledia_aitutor/view.php'),
                icon: 'graduation-cap',
                capability: null,
                configurl: new moodle_url('/blocks/eledia_aitutor/configuration.php'),
                comingsoon: false,
                detaildescription: get_string('suite_feature_detail', 'block_eledia_aitutor'),
            ),
        ];
    }
}
