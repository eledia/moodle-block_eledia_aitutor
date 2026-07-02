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
 * Course-level question analytics report for the eLeDia.ai Tutor.
 *
 * Shows aggregated, name-free views of the questions learners asked the tutor
 * in one course: summary numbers, questions per day, and the recent questions
 * themselves. Access requires block/eledia_aitutor:viewreports in the course.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use block_eledia_aitutor\local\question_log;

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$course = get_course($courseid);

require_login($course, false);
$context = \core\context\course::instance($course->id);
require_capability('block/eledia_aitutor:viewreports', $context);

$PAGE->set_url(new moodle_url(
    '/blocks/eledia_aitutor/report.php',
    ['courseid' => $course->id, 'page' => $page]
));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report_title', 'block_eledia_aitutor'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_title', 'block_eledia_aitutor'));

if (!question_log::is_enabled()) {
    echo $OUTPUT->notification(get_string('report_disabled', 'block_eledia_aitutor'), 'info');
    echo $OUTPUT->footer();
    die;
}

// Render one labelled progress bar row for the report. Takes the already-escaped
// label HTML, the value, and the maximum across the set (for scaling), and
// returns the row HTML.
$barrow = function (string $labelhtml, int $count, int $max): string {
    $pct = $max > 0 && $count > 0 ? max(6, (int) round($count * 100 / $max)) : 0;
    $fill = html_writer::div(
        (string) $count,
        'eat-bar-fill' . ($count === 0 ? ' eat-bar-zero' : ''),
        ['style' => 'width: ' . $pct . '%', 'role' => 'progressbar',
        'aria-valuenow' => $count,
        'aria-valuemin' => 0,
        'aria-valuemax' => $max]
    );
    return html_writer::div(
        html_writer::div($labelhtml, 'eat-bar-label') . html_writer::div($fill, 'eat-bar-track'),
        'eat-bar-row'
    );
};

echo html_writer::start_div('eat-admin eat-report');
echo html_writer::div(
    html_writer::tag('i', '', ['class' => 'fa fa-chart-bar', 'aria-hidden' => 'true']) .
    html_writer::span(get_string('report_intro', 'block_eledia_aitutor')),
    'eat-admin-intro'
);

$summary = question_log::summary($course->id);

// Summary stat cards.
$groundedpct = $summary->total > 0 ? round($summary->grounded * 100 / $summary->total) : 0;
$stats = [
    ['fa-comments', (string) $summary->total, get_string('report_total', 'block_eledia_aitutor')],
    ['fa-calendar-week', (string) $summary->last7, get_string('report_last7', 'block_eledia_aitutor')],
    ['fa-book', $groundedpct . '%', get_string('report_grounded', 'block_eledia_aitutor')],
];
echo html_writer::start_div('eat-stat-grid');
foreach ($stats as [$icon, $value, $label]) {
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('i', '', ['class' => 'fa ' . $icon, 'aria-hidden' => 'true']),
            'eat-stat-icon'
        ) .
        html_writer::div(
            html_writer::div($value, 'eat-stat-num') . html_writer::div($label, 'eat-stat-label')
        ),
        'eat-stat'
    );
}
echo html_writer::end_div();

if ($summary->total === 0) {
    echo $OUTPUT->notification(get_string('report_none', 'block_eledia_aitutor'), 'info');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die;
}

// Hotspots: questions clustered by canonical topic / primary source.
$hotspots = question_log::hotspots($course->id, 30, 10);
if (!empty($hotspots)) {
    $maxcount = max(array_map(static fn($h) => $h->count, $hotspots));
    $bars = '';
    foreach ($hotspots as $hotspot) {
        $label = s($hotspot->label);
        if (!empty($hotspot->cmid)) {
            // Link the hotspot to its course module when it still exists.
            try {
                [, $cm] = get_course_and_cm_from_cmid($hotspot->cmid);
                if ($cm->url) {
                    $label = html_writer::link($cm->url, s($hotspot->label));
                }
            } catch (\moodle_exception $e) {
                $label = s($hotspot->label);
            }
        }
        $bars .= $barrow($label, (int) $hotspot->count, (int) $maxcount);
    }
    echo html_writer::start_div('eat-report-card');
    echo html_writer::tag(
        'h3',
        get_string('report_hotspots', 'block_eledia_aitutor'),
        ['class' => 'eat-report-title']
    );
    echo html_writer::div($bars, 'eat-bars');
    echo html_writer::end_div();
}

// Teacher-Copilot: on-demand AI analysis of the logged questions.
echo $OUTPUT->render_from_template('block_eledia_aitutor/copilot_section', ['courseid' => $course->id]);
$PAGE->requires->js_call_amd('block_eledia_aitutor/copilot_report', 'init', [$course->id]);

// Questions per day (last 14 days, newest first).
$perday = array_reverse(question_log::per_day($course->id, 14), true);
$max = max(1, max($perday));
$bars = '';
foreach ($perday as $day => $count) {
    $bars .= $barrow(s($day), (int) $count, (int) $max);
}
echo html_writer::start_div('eat-report-card');
echo html_writer::tag('h3', get_string('report_byday', 'block_eledia_aitutor'), ['class' => 'eat-report-title']);
echo html_writer::div($bars, 'eat-bars');
echo html_writer::end_div();

// Recent questions (no asker identities, by design), paginated.
echo html_writer::start_div('eat-report-card');
echo html_writer::tag('h3', get_string('report_recent', 'block_eledia_aitutor'), ['class' => 'eat-report-title']);
$perpage = 20;
$lastpage = max(0, (int) ceil($summary->total / $perpage) - 1);
$page = min($page, $lastpage);
echo $OUTPUT->paging_bar($summary->total, $page, $perpage, $PAGE->url);
$table = new html_table();
$table->head = [
    get_string('report_when', 'block_eledia_aitutor'),
    get_string('report_question', 'block_eledia_aitutor'),
    get_string('report_groundedcol', 'block_eledia_aitutor'),
    get_string('report_style', 'block_eledia_aitutor'),
];
$table->attributes['class'] = 'table generaltable';
foreach (question_log::recent($course->id, $perpage, $page * $perpage) as $row) {
    $stylelabel = '';
    if (!empty($row->answerstyle) && in_array($row->answerstyle, ['explain', 'hint', 'quiz'], true)) {
        $stylelabel = get_string('answerstyle_' . $row->answerstyle, 'block_eledia_aitutor');
    }
    $table->data[] = [
        userdate((int) $row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        s($row->question),
        $row->grounded
            ? html_writer::span(get_string('groundedbadge', 'block_eledia_aitutor'), 'badge bg-success text-white')
            : html_writer::span(get_string('ungroundedbadge', 'block_eledia_aitutor'), 'badge bg-secondary text-white'),
        s($stylelabel),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($summary->total, $page, $perpage, $PAGE->url);
echo html_writer::end_div(); // End of .eat-report-card.

echo html_writer::end_div(); // End of .eat-admin.eat-report.
echo $OUTPUT->footer();
