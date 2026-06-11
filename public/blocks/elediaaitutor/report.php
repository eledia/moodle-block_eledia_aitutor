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
 * themselves. Access requires block/elediaaitutor:viewreports in the course.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_elediaaitutor\local\question_log;

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$course = get_course($courseid);

require_login($course, false);
$context = context_course::instance($course->id);
require_capability('block/elediaaitutor:viewreports', $context);

$PAGE->set_url(new moodle_url('/blocks/elediaaitutor/report.php',
    ['courseid' => $course->id, 'page' => $page]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report_title', 'block_elediaaitutor'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_title', 'block_elediaaitutor'));

if (!question_log::is_enabled()) {
    echo $OUTPUT->notification(get_string('report_disabled', 'block_elediaaitutor'), 'info');
    echo $OUTPUT->footer();
    die;
}

echo html_writer::div(get_string('report_intro', 'block_elediaaitutor'), 'mb-3 text-muted');

$summary = question_log::summary($course->id);

// Summary cards.
$groundedpct = $summary->total > 0 ? round($summary->grounded * 100 / $summary->total) : 0;
$cards = [
    [get_string('report_total', 'block_elediaaitutor'), (string) $summary->total],
    [get_string('report_last7', 'block_elediaaitutor'), (string) $summary->last7],
    [get_string('report_grounded', 'block_elediaaitutor'), $groundedpct . '%'],
];
$cardshtml = '';
foreach ($cards as [$label, $value]) {
    $cardshtml .= html_writer::div(
        html_writer::div($value, 'h2 mb-0') . html_writer::div($label, 'text-muted small'),
        'card p-3 me-3 mb-3 d-inline-block text-center'
    );
}
echo html_writer::div($cardshtml, 'mb-2');

if ($summary->total === 0) {
    echo $OUTPUT->notification(get_string('report_none', 'block_elediaaitutor'), 'info');
    echo $OUTPUT->footer();
    die;
}

// Hotspots: questions clustered by canonical topic / primary source.
$hotspots = question_log::hotspots($course->id, 30, 10);
if (!empty($hotspots)) {
    echo $OUTPUT->heading(get_string('report_hotspots', 'block_elediaaitutor'), 3);
    $maxcount = max(array_map(static fn($h) => $h->count, $hotspots));
    $rows = '';
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
                // Module gone: plain label.
                $label = s($hotspot->label);
            }
        }
        $pct = (int) round($hotspot->count * 100 / max(1, $maxcount));
        $bar = html_writer::div(
            html_writer::div(s((string) $hotspot->count), 'progress-bar', ['role' => 'progressbar',
                'style' => 'width: ' . max($pct, 4) . '%',
                'aria-valuenow' => $hotspot->count, 'aria-valuemin' => 0, 'aria-valuemax' => $maxcount]),
            'progress', ['style' => 'height: 1.4rem; min-width: 200px;']
        );
        $rows .= html_writer::tag('tr',
            html_writer::tag('td', $label, ['class' => 'text-nowrap'])
            . html_writer::tag('td', $bar, ['class' => 'w-100']));
    }
    echo html_writer::tag('table', html_writer::tag('tbody', $rows), ['class' => 'table table-sm mb-4']);
}

// Questions per day (last 14 days, newest first) as a simple bar list.
echo $OUTPUT->heading(get_string('report_byday', 'block_elediaaitutor'), 3);
$perday = array_reverse(question_log::per_day($course->id, 14), true);
$max = max(1, max($perday));
$rows = '';
foreach ($perday as $day => $count) {
    $pct = (int) round($count * 100 / $max);
    $bar = html_writer::div(
        html_writer::div(s((string) $count), 'progress-bar', ['role' => 'progressbar',
            'style' => 'width: ' . max($pct, $count > 0 ? 4 : 0) . '%',
            'aria-valuenow' => $count, 'aria-valuemin' => 0, 'aria-valuemax' => $max]),
        'progress', ['style' => 'height: 1.4rem; min-width: 200px;']
    );
    $rows .= html_writer::tag('tr',
        html_writer::tag('td', s($day), ['class' => 'text-nowrap'])
        . html_writer::tag('td', $bar, ['class' => 'w-100']));
}
echo html_writer::tag('table', html_writer::tag('tbody', $rows), ['class' => 'table table-sm mb-4']);

// Recent questions (no asker identities, by design), paginated.
echo $OUTPUT->heading(get_string('report_recent', 'block_elediaaitutor'), 3);
$perpage = 20;
$lastpage = max(0, (int) ceil($summary->total / $perpage) - 1);
$page = min($page, $lastpage);
echo $OUTPUT->paging_bar($summary->total, $page, $perpage, $PAGE->url);
$table = new html_table();
$table->head = [
    get_string('report_when', 'block_elediaaitutor'),
    get_string('report_question', 'block_elediaaitutor'),
    get_string('report_groundedcol', 'block_elediaaitutor'),
    get_string('report_style', 'block_elediaaitutor'),
];
$table->attributes['class'] = 'table generaltable';
foreach (question_log::recent($course->id, $perpage, $page * $perpage) as $row) {
    $stylelabel = '';
    if (!empty($row->answerstyle) && in_array($row->answerstyle, ['explain', 'hint', 'quiz'], true)) {
        $stylelabel = get_string('answerstyle_' . $row->answerstyle, 'block_elediaaitutor');
    }
    $table->data[] = [
        userdate((int) $row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        s($row->question),
        $row->grounded
            ? html_writer::span(get_string('groundedbadge', 'block_elediaaitutor'), 'badge bg-success text-white')
            : html_writer::span(get_string('ungroundedbadge', 'block_elediaaitutor'), 'badge bg-secondary text-white'),
        s($stylelabel),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($summary->total, $page, $perpage, $PAGE->url);

echo $OUTPUT->footer();
