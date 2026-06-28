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

namespace block_eledia_aitutor\external;

use context;
use block_eledia_aitutor\local\widget;
use moodle_exception;

/**
 * Shared helpers for the block's external functions.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Resolve a context id passed from the client into a context object.
     *
     * Only block, course and system contexts are accepted — the three places
     * the widget renders (block instance, the standalone view.php page in a
     * course, and global chat); anything else is rejected so a caller cannot
     * point the capability check at an unrelated context. For course contexts
     * the external functions' validate_context() additionally enforces course
     * access for the calling user.
     *
     * @param int $contextid The context id.
     * @return context
     * @throws moodle_exception When the context is missing or of the wrong type.
     */
    public static function resolve_context(int $contextid): context {
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if ($context === false) {
            throw new moodle_exception('error_invalid_context', 'block_eledia_aitutor');
        }
        $allowed = [CONTEXT_BLOCK, CONTEXT_COURSE, CONTEXT_SYSTEM];
        if (!in_array($context->contextlevel, $allowed, true)) {
            throw new moodle_exception('error_invalid_context', 'block_eledia_aitutor');
        }
        return $context;
    }

    /**
     * Load the per-instance block configuration for a block context.
     *
     * Used to enforce instance settings server-side (e.g. the answer-style
     * lock) — client-supplied values are never trusted. Returns an empty
     * object for non-block contexts or unconfigured instances.
     *
     * @param context $context The context the request was made in.
     * @return \stdClass The instance configuration (possibly empty).
     */
    public static function block_config(context $context): \stdClass {
        global $DB;

        if ($context->contextlevel !== CONTEXT_BLOCK) {
            return new \stdClass();
        }
        $instance = $DB->get_record('block_instances', ['id' => $context->instanceid]);
        if (!$instance || $instance->configdata === null || $instance->configdata === '') {
            return new \stdClass();
        }
        $config = unserialize_object(base64_decode($instance->configdata));
        return $config instanceof \stdClass ? $config : new \stdClass();
    }

    /**
     * Enforce the course-level tutor opt-in signal.
     *
     * A teacher opts a course into the tutor by adding the block to the course.
     * Standalone UI entry points already honour that rule; external functions
     * use this helper so direct AJAX calls follow the same contract.
     *
     * @param int $courseid Course id, or 0 for non-course/global chat.
     * @return void
     * @throws moodle_exception When the course has no tutor block.
     */
    public static function require_course_tutor_enabled(int $courseid): void {
        if ($courseid > 0 && !widget::course_has_tutor($courseid)) {
            throw new moodle_exception('notenabledincourse', 'block_eledia_aitutor');
        }
    }
}
