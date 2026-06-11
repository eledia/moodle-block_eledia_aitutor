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

namespace block_elediaaitutor\external;

use context;
use moodle_exception;

/**
 * Shared helpers for the block's external functions.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Resolve a context id passed from the client into a context object.
     *
     * Only block and system contexts are accepted; anything else is rejected so
     * a caller cannot point the capability check at an unrelated context.
     *
     * @param int $contextid The context id.
     * @return context
     * @throws moodle_exception When the context is missing or of the wrong type.
     */
    public static function resolve_context(int $contextid): context {
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if ($context === false) {
            throw new moodle_exception('error_invalid_context', 'block_elediaaitutor');
        }
        if ($context->contextlevel !== CONTEXT_BLOCK && $context->contextlevel !== CONTEXT_SYSTEM) {
            throw new moodle_exception('error_invalid_context', 'block_elediaaitutor');
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
}
