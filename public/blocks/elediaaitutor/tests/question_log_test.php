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

declare(strict_types=1);

namespace block_elediaaitutor;

use block_elediaaitutor\local\question_log;

/**
 * Unit tests for the opt-in question analytics log.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\question_log
 * @covers      \block_elediaaitutor\task\prune_question_log
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_log_test extends \advanced_testcase {
    /**
     * Nothing is logged while analytics is disabled (the default).
     */
    public function test_disabled_by_default(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        question_log::log((int) $user->id, 5, 'What is photosynthesis?', true, 'explain');

        $this->assertSame(0, $DB->count_records(question_log::TABLE));
    }

    /**
     * Enabled logging stores the truncated question with its metadata.
     */
    public function test_log_and_truncate(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaaitutor');
        $user = $this->getDataGenerator()->create_user();

        question_log::log((int) $user->id, 5, str_repeat('x', 1500), true, 'hint');
        question_log::log((int) $user->id, null, 'global question', false, null);

        $rows = array_values($DB->get_records(question_log::TABLE, [], 'id ASC'));
        $this->assertCount(2, $rows);
        $this->assertSame(1000, \core_text::strlen($rows[0]->question));
        $this->assertEquals(5, $rows[0]->courseid);
        $this->assertEquals(1, $rows[0]->grounded);
        $this->assertSame('hint', $rows[0]->answerstyle);
        $this->assertEquals(0, $rows[1]->courseid);
        $this->assertEquals(0, $rows[1]->grounded);
        $this->assertNull($rows[1]->answerstyle);
    }

    /**
     * Summary and recent listing aggregate per course.
     */
    public function test_summary_and_recent(): void {
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaaitutor');
        $user = $this->getDataGenerator()->create_user();

        question_log::log((int) $user->id, 7, 'q1', true, 'explain');
        question_log::log((int) $user->id, 7, 'q2', false, 'quiz');
        question_log::log((int) $user->id, 8, 'other course', true, null);

        $summary = question_log::summary(7);
        $this->assertSame(2, $summary->total);
        $this->assertSame(2, $summary->last7);
        $this->assertSame(1, $summary->grounded);

        $recent = question_log::recent(7);
        $this->assertCount(2, $recent);
        $this->assertSame('q2', $recent[0]->question);
    }

    /**
     * Pruning removes only rows past the retention window; the task wires the
     * configured retention through.
     */
    public function test_prune_and_task(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaaitutor');
        $user = $this->getDataGenerator()->create_user();

        question_log::log((int) $user->id, 7, 'fresh', true, null);
        $oldid = $DB->insert_record(question_log::TABLE, (object) [
            'userid' => $user->id, 'courseid' => 7, 'question' => 'old', 'grounded' => 0,
            'answerstyle' => null, 'timecreated' => time() - 200 * DAYSECS,
        ]);

        $this->assertSame(0, question_log::prune(0), 'Retention 0 must disable pruning.');
        $this->assertSame(1, question_log::prune(180));
        $this->assertFalse($DB->record_exists(question_log::TABLE, ['id' => $oldid]));
        $this->assertSame(1, $DB->count_records(question_log::TABLE));

        // The scheduled task runs without error against the configured retention.
        set_config('analyticsretentiondays', 180, 'block_elediaaitutor');
        $task = new \block_elediaaitutor\task\prune_question_log();
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertSame(1, $DB->count_records(question_log::TABLE));
    }

    /**
     * Hotspots cluster by topic, fall back to the primary source title, skip
     * unclassifiable rows, and sort by frequency.
     */
    public function test_hotspots_clustering(): void {
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaaitutor');
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;

        // Three phrasings of the same concept share one topic label.
        question_log::log($uid, 7, 'When is the essay due?', true, null, 'Assignments & deadlines', 'Essay 2', 99);
        question_log::log($uid, 7, 'essay deadline?', true, null, 'Assignments & deadlines', 'Essay 2', 99);
        question_log::log($uid, 7, 'wann ist der essay fällig', true, null, 'Assignments & deadlines', null, null);
        // No topic: falls back to the source title.
        question_log::log($uid, 7, 'what is in week 1', true, null, null, 'Week 1 notes', 42);
        // Neither: skipped.
        question_log::log($uid, 7, 'hello', false, null, null, null, null);

        $hotspots = question_log::hotspots(7);

        $this->assertCount(2, $hotspots);
        $this->assertSame('Assignments & deadlines', $hotspots[0]->label);
        $this->assertSame(3, $hotspots[0]->count);
        $this->assertSame(99, $hotspots[0]->cmid);
        $this->assertSame('Week 1 notes', $hotspots[1]->label);
        $this->assertSame(1, $hotspots[1]->count);
    }

    /**
     * apply_topics only relabels ids that were actually sent, scoped to the course.
     */
    public function test_apply_topics_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaaitutor');
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;

        question_log::log($uid, 7, 'q-a', true, null);
        question_log::log($uid, 7, 'q-b', true, null);
        question_log::log($uid, 8, 'other course', true, null);
        [$a, $b, $other] = array_values($DB->get_records(question_log::TABLE, [], 'id ASC', 'id'));

        $updated = question_log::apply_topics([
            (int) $a->id => 'Topic A',
            (int) $other->id => 'Should not apply',   // Not in the sent batch.
            999999 => 'Ghost',                          // Unknown id.
        ], 7, [(int) $a->id, (int) $b->id]);

        $this->assertSame(1, $updated);
        $this->assertSame('Topic A', $DB->get_field(question_log::TABLE, 'topic', ['id' => $a->id]));
        $this->assertNull($DB->get_field(question_log::TABLE, 'topic', ['id' => $other->id]));

        $this->assertSame(['Topic A'], question_log::distinct_topics(7));
        $this->assertEqualsCanonicalizing([7, 8], question_log::active_courses(30));
    }

    /**
     * delete_all_for_user erases only that user's rows.
     */
    public function test_delete_all_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableanalytics', 1, 'block_elediaaitutor');
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        question_log::log((int) $alice->id, 7, 'a1', true, null);
        question_log::log((int) $alice->id, 7, 'a2', true, null);
        question_log::log((int) $bob->id, 7, 'b1', true, null);

        $this->assertSame(2, question_log::delete_all_for_user((int) $alice->id));
        $this->assertSame(1, $DB->count_records(question_log::TABLE));
    }
}
