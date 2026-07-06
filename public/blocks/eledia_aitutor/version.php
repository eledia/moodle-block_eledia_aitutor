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
 * eLeDia.ai Tutor block version information.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026070500;
$plugin->requires = 2024100700;
$plugin->supported = [405, 502];
$plugin->component = 'block_eledia_aitutor';
$plugin->maturity = MATURITY_BETA;
$plugin->release = '0.18.6';
// The MCP web service plugin is a mandatory runtime integration but is enforced
// at runtime (admin config error + user-facing unavailable message) rather than
// as an install-time dependency, so the block still installs standalone. See
// security::mcp_enabled() and token_provider::is_connector_available().
