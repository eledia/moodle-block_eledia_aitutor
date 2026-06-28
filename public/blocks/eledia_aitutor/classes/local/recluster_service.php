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

/**
 * Batch topic reclustering against the RAG server.
 *
 * Converges the question-analytics hotspot labels: for every course with
 * recent activity, the last 30 days of logged questions are sent to the
 * configured recluster tool (in batches, together with the course's current
 * label registry) and the returned canonical labels are written back. The
 * server contract is idempotent, so repeated runs are safe and progressively
 * converge labels that drifted across model or prompt changes.
 *
 * This is a SITE-LEVEL service operation: requests carry the MCP token of the
 * auto-provisioned maintenance account ({@see service_user}) — never a real
 * person's token — so the RAG server can authenticate the call exactly like
 * every other tool, without any shared transport secret (see the spec,
 * section A.6). Question texts re-sent here already transited the same server
 * at chat time.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recluster_service {
    /** @var int Look-back window: matches the hotspot report window. */
    public const WINDOW_DAYS = 30;

    /** @var int Questions per tools/call batch (spec maximum: 200). */
    public const BATCH_SIZE = 200;

    /** @var int Safety cap of questions processed per course per run. */
    public const MAX_PER_COURSE = 1000;

    /**
     * Whether reclustering is configured and may run.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return question_log::is_enabled() && security::recluster_tool_name() !== '';
    }

    /**
     * Recluster recent questions for every active course.
     *
     * Failures are per-course best-effort: a failing batch aborts that course
     * (the next nightly run retries) but never the whole run.
     *
     * @param rag_client|null $client Optional injected client (tests).
     * @param string|null $moodletoken Optional injected maintenance token (tests);
     *                                 by default one is minted for the service account.
     * @return array{courses: int, batches: int, updated: int, failed: int}
     */
    public static function run(?rag_client $client = null, ?string $moodletoken = null): array {
        global $CFG;

        $stats = ['courses' => 0, 'batches' => 0, 'updated' => 0, 'failed' => 0];
        if (!self::is_configured()) {
            return $stats;
        }

        $toolname = security::recluster_tool_name();
        try {
            $client ??= rag_client::create();
            if ($moodletoken === null) {
                $moodletoken = '';
                if (security::mcp_enabled()) {
                    // Authenticate like every other tool call: mint a component
                    // token for the maintenance account (auto-created on first run).
                    token_provider::require_available();
                    $moodletoken = token_provider::get_token((int) service_user::get_or_create()->id);
                }
            }
        } catch (\moodle_exception $e) {
            debugging(
                'block_eledia_aitutor: recluster aborted (client/token): ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            $stats['failed']++;
            return $stats;
        }

        foreach (question_log::active_courses(self::WINDOW_DAYS) as $courseid) {
            $stats['courses']++;
            $labels = question_log::distinct_topics($courseid);
            $offset = 0;

            while ($offset < self::MAX_PER_COURSE) {
                $rows = question_log::fetch_for_recluster(
                    $courseid,
                    self::WINDOW_DAYS,
                    self::BATCH_SIZE,
                    $offset
                );
                if (empty($rows)) {
                    break;
                }
                $offset += count($rows);

                $questions = [];
                $sentids = [];
                foreach ($rows as $row) {
                    $questions[] = ['id' => (int) $row->id, 'text' => (string) $row->question];
                    $sentids[] = (int) $row->id;
                }

                try {
                    $map = $client->recluster_questions(
                        $CFG->wwwroot,
                        (string) $courseid,
                        $labels,
                        $questions,
                        $toolname,
                        $moodletoken
                    );
                } catch (rag_exception $e) {
                    $stats['failed']++;
                    debugging("block_eledia_aitutor: recluster batch failed (course $courseid): "
                        . $e->getMessage(), DEBUG_DEVELOPER);
                    break;
                }

                $stats['batches']++;
                $stats['updated'] += question_log::apply_topics($map, $courseid, $sentids);

                // Newly minted labels join the registry for the next batch.
                $labels = array_slice(array_values(array_unique(array_merge(
                    $labels,
                    array_values($map)
                ))), 0, 100);

                if (count($rows) < self::BATCH_SIZE) {
                    break;
                }
            }
        }

        return $stats;
    }
}
