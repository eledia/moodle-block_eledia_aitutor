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

/**
 * Per-user daily message counters (cost/quota governance).
 *
 * One row per user per day, incremented on every successful chat turn. The
 * daily limit is enforced before any RAG call: the site-wide default
 * (dailymessagelimit, 0 = unlimited) can be overridden per block instance.
 * Counters are intentionally NOT removed by the self-service "delete all my
 * tutor data" control — otherwise a user could reset their own quota — but
 * they are covered by the Privacy API and erased when the account is deleted.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /** @var string Backing table. */
    public const TABLE = 'block_elediaaitutor_usage';

    /** @var int Days of counters kept by the prune task (quota only needs today). */
    public const RETENTION_DAYS = 60;

    /**
     * Today's key (server timezone), e.g. 20260612.
     *
     * @return int
     */
    public static function today_key(): int {
        return (int) date('Ymd');
    }

    /**
     * The user's message count for today.
     *
     * @param int $userid The user id.
     * @return int
     */
    public static function count_today(int $userid): int {
        global $DB;
        return (int) $DB->get_field(
            self::TABLE,
            'messagecount',
            ['userid' => $userid, 'daykey' => self::today_key()]
        );
    }

    /**
     * Enforce a daily limit. Call before the RAG request.
     *
     * @param int $userid The user id.
     * @param int $limit Maximum messages per day (must be > 0).
     * @return void
     * @throws \moodle_exception When the user has reached the limit.
     */
    public static function assert_within_limit(int $userid, int $limit): void {
        if (self::count_today($userid) >= $limit) {
            throw new \moodle_exception('error_quota_exceeded', 'block_elediaaitutor', '', $limit);
        }
    }

    /**
     * Count one successful chat turn for today.
     *
     * @param int $userid The user id.
     * @return void
     */
    public static function increment(int $userid): void {
        global $DB;

        $daykey = self::today_key();
        $DB->execute(
            'UPDATE {' . self::TABLE . '} SET messagecount = messagecount + 1
              WHERE userid = :userid AND daykey = :daykey',
            ['userid' => $userid, 'daykey' => $daykey]
        );
        if ($DB->record_exists(self::TABLE, ['userid' => $userid, 'daykey' => $daykey])) {
            return;
        }

        try {
            $DB->insert_record(self::TABLE, (object) [
                'userid' => $userid,
                'daykey' => $daykey,
                'messagecount' => 1,
            ]);
        } catch (\dml_exception $e) {
            // Lost a race against a concurrent turn: count it via update instead.
            $DB->execute(
                'UPDATE {' . self::TABLE . '} SET messagecount = messagecount + 1
                  WHERE userid = :userid AND daykey = :daykey',
                ['userid' => $userid, 'daykey' => $daykey]
            );
        }
    }

    /**
     * Delete counters older than the retention window.
     *
     * @param int $days Days to keep.
     * @return int Number of rows deleted.
     */
    public static function prune(int $days = self::RETENTION_DAYS): int {
        global $DB;
        $cutoff = (int) date('Ymd', time() - $days * DAYSECS);
        $count = $DB->count_records_select(self::TABLE, 'daykey < :cutoff', ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select(self::TABLE, 'daykey < :cutoff', ['cutoff' => $cutoff]);
        }
        return $count;
    }

    /**
     * Erase a user's counters (user deletion / Privacy API).
     *
     * @param int $userid The user id.
     * @return int Number of rows deleted.
     */
    public static function delete_for_user(int $userid): int {
        global $DB;
        $count = $DB->count_records(self::TABLE, ['userid' => $userid]);
        if ($count > 0) {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
        }
        return $count;
    }
}
