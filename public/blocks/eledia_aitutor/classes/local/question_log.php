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

namespace block_eledia_aitutor\local;

use stdClass;

/**
 * Opt-in question analytics log.
 *
 * When the administrator enables question analytics, every successful chat turn
 * records the learner's QUESTION (truncated; never the answer) together with the
 * course, the asker, whether the answer cited course materials and the answer
 * style. Teachers with block/eledia_aitutor:viewreports see aggregated,
 * name-free views in the course report — the data exists to surface confusion
 * hotspots, not to monitor individuals.
 *
 * Privacy posture: collection is OFF by default; the asker id is stored so the
 * Privacy API can export and erase the rows (full data-subject rights), but the
 * report never displays it; rows are pruned by a daily scheduled task after the
 * configured retention; "delete all my tutor data" wipes a user's rows too.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_log {
    /** @var string Backing table. */
    public const TABLE = 'block_eledia_aitutor_qlog';

    /** @var int Max stored question length (characters). */
    private const QUESTION_LENGTH = 1000;

    /**
     * Whether question analytics collection is enabled site-wide.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (int) get_config('block_eledia_aitutor', 'enableanalytics') === 1;
    }

    /**
     * Record one asked question. No-op while analytics is disabled.
     *
     * @param int $userid The asker.
     * @param int|null $courseid Course context, or null/0 for global chat.
     * @param string $question The question text (truncated on store).
     * @param bool $grounded Whether the answer cited course materials.
     * @param string|null $answerstyle The effective answer style, if any.
     * @param string|null $topic Canonical topic label from the RAG server, if any.
     * @param string|null $sourcetitle Title of the primary cited source, if any.
     * @param int|null $cmid Course module id of the primary source, if resolvable.
     * @return void
     */
    public static function log(
        int $userid,
        ?int $courseid,
        string $question,
        bool $grounded,
        ?string $answerstyle = null,
        ?string $topic = null,
        ?string $sourcetitle = null,
        ?int $cmid = null
    ): void {
        global $DB;

        if (!self::is_enabled()) {
            return;
        }

        $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'courseid' => (int) ($courseid ?: 0),
            'question' => \core_text::substr(trim($question), 0, self::QUESTION_LENGTH),
            'grounded' => $grounded ? 1 : 0,
            'answerstyle' => $answerstyle !== null && $answerstyle !== '' ? $answerstyle : null,
            'topic' => $topic !== null && trim($topic) !== ''
                ? \core_text::substr(trim($topic), 0, 100) : null,
            'sourcetitle' => $sourcetitle !== null && trim($sourcetitle) !== ''
                ? \core_text::substr(trim($sourcetitle), 0, 255) : null,
            'cmid' => $cmid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Cluster recent questions into hotspots.
     *
     * Groups by the server-supplied canonical topic, falling back to the
     * primary source title — the two aggregation anchors that stay stable
     * across rephrasings of the same question. Unclassifiable rows (neither
     * label) are reported as one residual bucket by the caller if desired;
     * here they are simply skipped.
     *
     * @param int $courseid The course id.
     * @param int $days Look-back window in days.
     * @param int $limit Max hotspots returned.
     * @return array<int,stdClass> Sorted by count desc: {label, count, cmid}.
     */
    public static function hotspots(int $courseid, int $days = 30, int $limit = 10): array {
        global $DB;

        $rows = $DB->get_records_select(
            self::TABLE,
            'courseid = :courseid AND timecreated >= :since',
            ['courseid' => $courseid, 'since' => time() - $days * DAYSECS],
            '',
            'id, topic, sourcetitle, cmid'
        );

        $buckets = [];
        foreach ($rows as $row) {
            $label = $row->topic ?? $row->sourcetitle;
            if ($label === null || $label === '') {
                continue;
            }
            if (!isset($buckets[$label])) {
                $buckets[$label] = (object) ['label' => $label, 'count' => 0, 'cmid' => null];
            }
            $buckets[$label]->count++;
            if ($buckets[$label]->cmid === null && !empty($row->cmid)) {
                $buckets[$label]->cmid = (int) $row->cmid;
            }
        }

        // Sorting with usort() already reindexes $buckets to a 0-based list (no array_values needed).
        usort($buckets, static fn($a, $b) => $b->count <=> $a->count);
        return array_slice($buckets, 0, $limit);
    }

    /**
     * Aggregate summary for a course.
     *
     * @param int $courseid The course id.
     * @return stdClass {total: int, last7: int, grounded: int}
     */
    public static function summary(int $courseid): stdClass {
        global $DB;

        $total = $DB->count_records(self::TABLE, ['courseid' => $courseid]);
        $last7 = $DB->count_records_select(
            self::TABLE,
            'courseid = :courseid AND timecreated >= :since',
            ['courseid' => $courseid, 'since' => time() - 7 * DAYSECS]
        );
        $grounded = $DB->count_records(self::TABLE, ['courseid' => $courseid, 'grounded' => 1]);

        return (object) ['total' => $total, 'last7' => $last7, 'grounded' => $grounded];
    }

    /**
     * Question counts per day for the last N days (oldest first).
     *
     * @param int $courseid The course id.
     * @param int $days Number of days to cover.
     * @return array<string,int> Map of "YYYY-MM-DD" (user timezone) => count.
     */
    public static function per_day(int $courseid, int $days = 14): array {
        global $DB;

        $since = time() - $days * DAYSECS;
        $rows = $DB->get_records_select(
            self::TABLE,
            'courseid = :courseid AND timecreated >= :since',
            ['courseid' => $courseid, 'since' => $since],
            'timecreated ASC',
            'id, timecreated'
        );

        $counts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $counts[userdate(time() - $i * DAYSECS, '%Y-%m-%d')] = 0;
        }
        foreach ($rows as $row) {
            $key = userdate((int) $row->timecreated, '%Y-%m-%d');
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }
        return $counts;
    }

    /**
     * The most recent questions in a course, without asker identities.
     *
     * @param int $courseid The course id.
     * @param int $limit Max rows (page size).
     * @param int $offset Number of rows to skip (page offset).
     * @return stdClass[] Rows with question, grounded, answerstyle, timecreated.
     */
    public static function recent(int $courseid, int $limit = 50, int $offset = 0): array {
        global $DB;
        return array_values($DB->get_records(
            self::TABLE,
            ['courseid' => $courseid],
            'timecreated DESC, id DESC',
            'id, question, grounded, answerstyle, timecreated',
            $offset,
            $limit
        ));
    }

    /**
     * Courses with logged questions inside the window.
     *
     * @param int $days Look-back window in days.
     * @return int[] Course ids (excluding global chat).
     */
    public static function active_courses(int $days): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT DISTINCT courseid FROM {' . self::TABLE . '}
              WHERE timecreated >= :since AND courseid > 0',
            ['since' => time() - $days * DAYSECS]
        );
        return array_map('intval', array_keys($rows));
    }

    /**
     * The distinct topic labels currently used in a course (the label registry
     * snapshot supplied to the recluster tool).
     *
     * @param int $courseid The course id.
     * @param int $limit Max labels.
     * @return string[]
     */
    public static function distinct_topics(int $courseid, int $limit = 100): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT DISTINCT topic FROM {' . self::TABLE . '}
              WHERE courseid = :courseid AND topic IS NOT NULL',
            ['courseid' => $courseid],
            0,
            $limit
        );
        return array_values(array_map('strval', array_keys($rows)));
    }

    /**
     * Fetch a batch of questions for reclustering (id + text only).
     *
     * @param int $courseid The course id.
     * @param int $days Look-back window in days.
     * @param int $limit Batch size.
     * @param int $offset Batch offset.
     * @return stdClass[] Rows with id and question, oldest first.
     */
    public static function fetch_for_recluster(int $courseid, int $days, int $limit, int $offset): array {
        global $DB;
        return array_values($DB->get_records_select(
            self::TABLE,
            'courseid = :courseid AND timecreated >= :since',
            ['courseid' => $courseid, 'since' => time() - $days * DAYSECS],
            'id ASC',
            'id, question',
            $offset,
            $limit
        ));
    }

    /**
     * Apply reclustered topic labels.
     *
     * Defensive by construction: only ids that were actually sent in the batch
     * (and belong to the course) are updatable — a misbehaving server cannot
     * relabel arbitrary rows.
     *
     * @param array $map Question id => topic label.
     * @param int $courseid The course the batch belongs to.
     * @param int[] $allowedids The ids that were sent in the batch.
     * @return int Number of rows updated.
     */
    public static function apply_topics(array $map, int $courseid, array $allowedids): int {
        global $DB;

        $allowed = array_flip(array_map('intval', $allowedids));
        $updated = 0;
        foreach ($map as $id => $topic) {
            $id = (int) $id;
            $topic = \core_text::substr(trim((string) $topic), 0, 100);
            if (!isset($allowed[$id]) || $topic === '') {
                continue;
            }
            $DB->set_field(self::TABLE, 'topic', $topic, ['id' => $id, 'courseid' => $courseid]);
            $updated++;
        }
        return $updated;
    }

    /**
     * Delete every logged question of a user (privacy / delete-my-data).
     *
     * @param int $userid The asker.
     * @return int Number of rows deleted.
     */
    public static function delete_all_for_user(int $userid): int {
        global $DB;
        $count = $DB->count_records(self::TABLE, ['userid' => $userid]);
        if ($count > 0) {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
        }
        return $count;
    }

    /**
     * Delete logged questions older than the retention window.
     *
     * @param int $retentiondays Days to keep. 0 (or less) disables pruning.
     * @return int Number of rows deleted.
     */
    public static function prune(int $retentiondays): int {
        global $DB;

        if ($retentiondays <= 0) {
            return 0;
        }
        $cutoff = time() - $retentiondays * DAYSECS;
        $count = $DB->count_records_select(self::TABLE, 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select(self::TABLE, 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        }
        return $count;
    }
}
