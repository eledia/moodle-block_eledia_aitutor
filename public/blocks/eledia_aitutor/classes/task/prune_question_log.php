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

namespace block_eledia_aitutor\task;

use block_eledia_aitutor\local\question_log;
use core\task\scheduled_task;

/**
 * Scheduled task that prunes question analytics rows past the retention window.
 *
 * Data minimisation for the opt-in question log: the retention is configurable
 * (analyticsretentiondays, default 180); 0 disables pruning.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prune_question_log extends scheduled_task {
    /**
     * Return the task name shown in the scheduled tasks UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_prune_question_log', 'block_eledia_aitutor');
    }

    /**
     * Delete logged questions and stale usage counters past their retention.
     *
     * @return void
     */
    public function execute(): void {
        // Daily quota counters only matter for the current day; keep a short
        // tail for support questions, then drop them.
        $usagedeleted = \block_eledia_aitutor\local\usage::prune();
        mtrace("block_eledia_aitutor: pruned {$usagedeleted} stale usage counter(s).");

        $configured = get_config('block_eledia_aitutor', 'analyticsretentiondays');
        $days = ($configured === false || $configured === '') ? 180 : (int) $configured;

        if ($days <= 0) {
            mtrace('block_eledia_aitutor: question-log pruning is disabled (retention = 0).');
            return;
        }

        $deleted = question_log::prune($days);
        mtrace("block_eledia_aitutor: pruned {$deleted} logged question(s) older than {$days} day(s).");
    }
}
