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

namespace block_elediaaitutor\local;

use stdClass;

/**
 * Resolves the effective answer mode for a chat turn.
 *
 * A tutor instance can answer in one of two ways:
 *  - **grounded** — the RAG agent retrieves from the course knowledge base;
 *  - **llmonly** — the agent answers from the model alone (no retrieval).
 *
 * The mode is decided centrally so the block UI, the standalone page and the
 * server-side chat endpoint all agree, and so the client can never widen its
 * own permissions. Two inputs govern it:
 *
 *  - the site admin gate {@see is_llm_allowed()} ("allow LLM-only mode"); and
 *  - whether the course's content is actually ingested
 *    {@see ingestion_available()} (the local_ragingest per-course marking).
 *
 * When LLM-only is disallowed AND grounding is unavailable, the tutor cannot
 * function for that course and the mode is {@see MODE_UNAVAILABLE}, which the
 * UI surfaces as a friendly "no knowledge base" notice.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_mode {
    /** @var string Grounded (retrieval) answers. */
    public const MODE_GROUNDED = 'grounded';

    /** @var string LLM-only (no retrieval) answers. */
    public const MODE_LLMONLY = 'llmonly';

    /** @var string Tutor cannot answer here (no KB, LLM-only disallowed). */
    public const MODE_UNAVAILABLE = 'unavailable';

    /** @var string local_ragingest gate class, present only when that plugin is installed. */
    private const RAGINGEST_GATE = '\\local_ragingest\\course_gate';

    /** @var string local_ragingest state class, present only when that plugin is installed. */
    private const RAGINGEST_STATE = '\\local_ragingest\\course_state';

    /**
     * Whether the site admin permits LLM-only mode at all.
     *
     * @return bool
     */
    public static function is_llm_allowed(): bool {
        return (int) get_config('block_elediaaitutor', 'allowllmonly') === 1;
    }

    /**
     * Whether grounding (knowledge-base retrieval) is available for a course.
     *
     * Decoupled from local_ragingest: when that plugin is absent there is no
     * ingestion pipeline, so grounding is unavailable. For a course chat the
     * answer is the course's ingestion marking; for global chat (no course)
     * it is whether ingestion is configured at all.
     *
     * @param int $courseid The course id, or 0 for global chat.
     * @return bool
     */
    public static function ingestion_available(int $courseid): bool {
        if (!class_exists(self::RAGINGEST_GATE) || !class_exists(self::RAGINGEST_STATE)) {
            return false;
        }
        if ($courseid > 0) {
            return \local_ragingest\course_gate::should_ingest($courseid)
                && \local_ragingest\course_state::is_ingested($courseid);
        }
        // Global chat: no per-course signal — treat as available when the
        // ingestion endpoint is configured (site content may be indexed).
        return trim((string) get_config('local_ragingest', 'rag_endpoint_url')) !== '';
    }

    /**
     * Whether a course is marked for ingestion but has no recorded index yet.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function course_is_released_not_indexed(int $courseid): bool {
        if ($courseid <= 0 || !class_exists(self::RAGINGEST_GATE) || !class_exists(self::RAGINGEST_STATE)) {
            return false;
        }

        return \local_ragingest\course_gate::should_ingest($courseid)
            && !\local_ragingest\course_state::is_ingested($courseid);
    }

    /**
     * Resolve the effective mode for a chat turn.
     *
     * @param int $courseid The course id (0 for global chat).
     * @param stdClass $blockconfig The block instance configuration.
     * @return string One of the MODE_* constants.
     */
    public static function resolve(int $courseid, stdClass $blockconfig): string {
        $llmallowed = self::is_llm_allowed();
        $grounding = self::ingestion_available($courseid);

        if ($grounding) {
            // Honour the teacher's per-instance choice, but only if LLM-only is
            // permitted site-wide; otherwise always ground.
            $instancemode = $llmallowed ? ($blockconfig->ragmode ?? self::MODE_GROUNDED) : self::MODE_GROUNDED;
            return $instancemode === self::MODE_LLMONLY ? self::MODE_LLMONLY : self::MODE_GROUNDED;
        }

        // No knowledge base for this course.
        return $llmallowed ? self::MODE_LLMONLY : self::MODE_UNAVAILABLE;
    }

    /**
     * The `rag_enabled` flag to send for a resolved mode (null when N/A).
     *
     * @param string $mode A MODE_* constant.
     * @return bool|null True for grounded, false for llmonly, null for unavailable.
     */
    public static function rag_enabled_for(string $mode): ?bool {
        return match ($mode) {
            self::MODE_GROUNDED => true,
            self::MODE_LLMONLY => false,
            default => null,
        };
    }
}
