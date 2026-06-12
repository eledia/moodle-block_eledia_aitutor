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

use block_elediaaitutor\local\usage;

/**
 * Unit tests for the daily message counters (quota governance).
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\usage
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usage_test extends \advanced_testcase {
    /**
     * Counting starts at zero, increments per call and stays per-user.
     */
    public function test_increment_and_count(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        $this->assertSame(0, usage::count_today((int) $alice->id));

        usage::increment((int) $alice->id);
        usage::increment((int) $alice->id);
        usage::increment((int) $bob->id);

        $this->assertSame(2, usage::count_today((int) $alice->id));
        $this->assertSame(1, usage::count_today((int) $bob->id));
    }

    /**
     * The limit gate passes below the cap and throws at it.
     */
    public function test_assert_within_limit(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;

        usage::assert_within_limit($uid, 2);
        usage::increment($uid);
        usage::assert_within_limit($uid, 2);
        usage::increment($uid);

        try {
            usage::assert_within_limit($uid, 2);
            $this->fail('Expected the quota gate to throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_quota_exceeded', $e->errorcode);
        }
    }

    /**
     * Pruning removes only counters past the retention window.
     */
    public function test_prune(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        usage::increment((int) $user->id);
        $DB->insert_record(usage::TABLE, (object) [
            'userid' => $user->id,
            'daykey' => (int) date('Ymd', time() - 90 * DAYSECS),
            'messagecount' => 5,
        ]);

        $this->assertSame(1, usage::prune(60));
        $this->assertSame(1, $DB->count_records(usage::TABLE));
        $this->assertSame(1, usage::count_today((int) $user->id));
    }

    /**
     * delete_for_user erases only that user's counters.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        usage::increment((int) $alice->id);
        usage::increment((int) $bob->id);

        $this->assertSame(1, usage::delete_for_user((int) $alice->id));
        $this->assertSame(1, $DB->count_records(usage::TABLE));
        $this->assertSame(1, usage::count_today((int) $bob->id));
    }
}
