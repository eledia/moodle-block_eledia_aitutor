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
 * Teacher-Copilot: AI analysis of a course's logged tutor questions.
 *
 * Builds one structured prompt from the question-analytics data (hotspots and
 * a sample of recent questions) and sends it through the regular chat tool,
 * grounded in the course knowledge base and authenticated with the teacher's
 * own user-scoped token. The result is a Markdown analysis ("what do learners
 * not understand + recommended actions") rendered for the report page.
 *
 * Deliberately bypasses {@see chat_service::send()}: a copilot run is not a
 * learner conversation, so it must not appear in the question log, in the
 * teacher's conversation list or in the learner-chat events. The consent gate
 * and the rate limit are enforced the same way; the daily message quota is
 * not consumed. The server-issued conversation id is discarded.
 *
 * @package     block_eledia_aitutor
 * @author      Johannes Moskaliuk
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copilot_service {
    /** @var int Max recent questions sampled into the prompt. */
    private const SAMPLE_SIZE = 40;

    /** @var int Per-question character cap inside the prompt. */
    private const QUESTION_MAXLEN = 200;

    /** @var int Rough overall prompt budget in characters. */
    private const PROMPT_BUDGET = 6000;

    /**
     * Generate the analysis for a course.
     *
     * @param int $courseid The course id.
     * @param int $userid The acting teacher's user id.
     * @param \core\context\course $context Course context (for safe rendering).
     * @param rag_client|null $client Optional injected client (tests).
     * @return array{analysishtml: string, iserror: bool}
     * @throws \moodle_exception On consent, rate-limit, data or RAG failure.
     */
    public static function analyse(
        int $courseid,
        int $userid,
        \core\context\course $context,
        ?rag_client $client = null
    ): array {
        consent::require_consent($userid);
        security::enforce_rate_limit($userid);

        $hotspots = question_log::hotspots($courseid, 30, 10);
        $recent = question_log::recent($courseid, self::SAMPLE_SIZE, 0);
        if (empty($hotspots) && empty($recent)) {
            throw new \moodle_exception('copilot_nodata', 'block_eledia_aitutor');
        }

        $prompt = self::build_prompt($hotspots, $recent);

        try {
            $client ??= rag_client::create();
        } catch (\moodle_exception $e) {
            self::log_failure($userid, $context, $e, $courseid);
            throw $e;
        }

        try {
            $result = self::call($client, $prompt, $courseid, $userid);
        } catch (rag_exception $e) {
            // The cached token may have been revoked/expired server-side: drop
            // it, mint a fresh one and retry exactly once (chat_service pattern).
            token_provider::forget_cached_token($userid);
            try {
                $result = self::call($client, $prompt, $courseid, $userid);
            } catch (\moodle_exception $retry) {
                self::log_failure($userid, $context, $retry, $courseid);
                throw $retry;
            }
        } catch (\moodle_exception $e) {
            self::log_failure($userid, $context, $e, $courseid);
            throw $e;
        }

        return [
            'analysishtml' => markdown_renderer::render((string) $result['answer'], $context),
            'iserror' => !empty($result['iserror']),
        ];
    }

    /**
     * One grounded chat call with the teacher's own token.
     *
     * The returned server conversation id is intentionally ignored — the run
     * never enters the local conversation list.
     *
     * @param rag_client $client The client.
     * @param string $prompt The assembled analysis prompt.
     * @param int $courseid The course id.
     * @param int $userid The teacher's user id.
     * @return array The raw chat tool result.
     */
    private static function call(rag_client $client, string $prompt, int $courseid, int $userid): array {
        global $CFG;

        return $client->chat(
            $CFG->wwwroot,
            token_provider::get_token($userid),
            $prompt,
            (string) $courseid,
            null,
            security::chat_tool_name(),
            null,
            null,
            current_language(),
            true,
            null
        );
    }

    /**
     * Assemble the localised analysis prompt from hotspots + question sample.
     *
     * @param \stdClass[] $hotspots Hotspot buckets {label, count, cmid}.
     * @param \stdClass[] $recent Recent question rows {question, ...}.
     * @return string
     */
    private static function build_prompt(array $hotspots, array $recent): string {
        $hotspotlines = [];
        foreach ($hotspots as $hotspot) {
            $hotspotlines[] = $hotspot->label . ': ' . $hotspot->count;
        }
        $hotspotstext = $hotspotlines !== [] ? implode('; ', $hotspotlines) : '-';

        // Sample questions until the rough prompt budget is spent, each one
        // truncated so a single essay-length question cannot eat the budget.
        $questionlines = [];
        $spent = 0;
        foreach ($recent as $index => $row) {
            $question = \core_text::substr(trim((string) $row->question), 0, self::QUESTION_MAXLEN);
            if ($question === '') {
                continue;
            }
            $line = ($index + 1) . '. ' . $question;
            if ($spent + \core_text::strlen($line) > self::PROMPT_BUDGET) {
                break;
            }
            $spent += \core_text::strlen($line);
            $questionlines[] = $line;
        }
        $questionstext = $questionlines !== [] ? implode(' | ', $questionlines) : '-';

        return get_string('copilot_prompt', 'block_eledia_aitutor', (object) [
            'hotspots' => $hotspotstext,
            'questions' => $questionstext,
        ]);
    }

    /**
     * Record a failed copilot run (event + admin diagnostics, no content).
     *
     * @param int $userid The acting user id.
     * @param \core\context\course $context The course context.
     * @param \Throwable $exception The failure.
     * @param int $courseid The course id.
     * @return void
     */
    private static function log_failure(
        int $userid,
        \core\context\course $context,
        \Throwable $exception,
        int $courseid
    ): void {
        \block_eledia_aitutor\event\rag_request_failed::create([
            'context' => $context,
            'userid' => $userid,
            'other' => ['reason' => 'copilot_error'],
        ])->trigger();

        diagnostics::record($userid, $context, 'copilot_error', $exception, $courseid);
    }
}
