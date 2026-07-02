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
 * AI-Home: a full-page, Claude-style start page built around the tutor.
 *
 * Renders the hero variant of the tutor widget (greeting, composer, audience
 * pills, briefing) at full page width. Intended as the site's landing
 * experience: point the admin setting "Start page for users"
 * (defaulthomepage → custom URL, Moodle 4.2+) at this page and logging in
 * lands directly in the AI home. The Dashboard block remains available for
 * sites that keep their classic dashboard; both share the same widget,
 * settings and chat services.
 *
 * @package     block_eledia_aitutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use block_eledia_aitutor\local\security;
use block_eledia_aitutor\local\widget;

require(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$context = \core\context\system::instance();
require_capability('block/eledia_aitutor:use', $context);

$PAGE->set_url(new moodle_url('/blocks/eledia_aitutor/home.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('home_title', 'block_eledia_aitutor'));
$PAGE->set_heading('');
$PAGE->add_body_class('eledia_aitutor-pagebody');
$PAGE->add_body_class('eledia_aitutor-homebody');

echo $OUTPUT->header();

if (!security::global_chat_enabled()) {
    echo $OUTPUT->notification(get_string('error_global_chat_disabled', 'block_eledia_aitutor'), 'info');
    echo $OUTPUT->footer();
    die;
}

// The home page is the hero experience: greeting, composer and pills at page
// width, always in global chat. The 'dashboard' flag selects the hero variant
// inside the shared widget (same flag the Dashboard block sets on /my/).
$instancecfg = ['displaymode' => 'embedded', 'dashboard' => true, 'dashboardenabled' => 1];

$configerror = widget::config_error(0, $instancecfg);
if ($configerror !== null) {
    $canmanage = has_capability('block/eledia_aitutor:manage', $context);
    echo $OUTPUT->render_from_template('block_eledia_aitutor/unavailable', [
        'isadmin' => $canmanage,
        'message' => $canmanage ? $configerror : get_string('unavailable_user', 'block_eledia_aitutor'),
    ]);
    echo $OUTPUT->footer();
    die;
}

echo html_writer::div(
    widget::render($context, 0, $instancecfg),
    'eledia_aitutor-page eledia_aitutor-home'
);

echo $OUTPUT->footer();
