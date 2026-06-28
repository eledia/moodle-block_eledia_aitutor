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
 * Plugin-owned help page.
 *
 * @package     block_eledia_aitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_eledia_aitutor\output\shell;

$context = \core\context\system::instance();
$url = new moodle_url('/blocks/eledia_aitutor/help.php');

require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('shell_help_label', 'block_eledia_aitutor'));
$PAGE->set_heading(shell::is_available() ? '' : get_string('shell_help_label', 'block_eledia_aitutor'));
shell::require_css();

$docfile = str_starts_with(current_language(), 'de')
    ? __DIR__ . '/docs/02-user-doc.de.md'
    : __DIR__ . '/docs/02-user-doc.md';
if (!is_readable($docfile)) {
    $docfile = __DIR__ . '/docs/02-user-doc.md';
}

$markdown = is_readable($docfile) ? (string) file_get_contents($docfile) : '';
$html = $markdown !== ''
    ? format_text($markdown, FORMAT_MARKDOWN, ['context' => $context])
    : html_writer::tag('p', get_string('error'));

echo $OUTPUT->header();

$header = shell::context(shell::ACTIVE_CONFIGURATION);
if ($header) {
    $header['tagline'] = get_string('help', 'core');
    $header['sectionnav'] = shell::sectionnav('help');
    \block_eledia_aitutor\output\plugin_page::open($header, \block_eledia_aitutor\output\plugin_page::MODIFIER_READING);
    \block_eledia_aitutor\output\plugin_shell::content_open();
} else {
    echo $OUTPUT->heading(get_string('shell_help_label', 'block_eledia_aitutor'));
}

echo html_writer::start_div('path-block-eledia_aitutor');
echo html_writer::tag('article', $html, ['class' => 'lh-plugin-card eat-help-doc']);
echo html_writer::end_div();

if ($header) {
    \block_eledia_aitutor\output\plugin_shell::content_close();
    \block_eledia_aitutor\output\plugin_page::close();
}

echo $OUTPUT->footer();
