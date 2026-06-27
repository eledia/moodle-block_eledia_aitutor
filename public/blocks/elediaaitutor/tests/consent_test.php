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

use block_elediaaitutor\external\give_consent;
use block_elediaaitutor\local\consent;

/**
 * Unit tests for the documented first-use privacy consent.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\consent
 * @covers      \block_elediaaitutor\external\give_consent
 * @covers      \block_elediaaitutor\observer
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class consent_test extends \advanced_testcase {
    /**
     * Consent starts absent, give() documents it once (idempotent) and fires
     * the audit event exactly once.
     */
    public function test_give_is_documented_and_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;

        $this->assertFalse(consent::has_consented($uid));
        $this->assertNull(consent::time_consented($uid));

        $sink = $this->redirectEvents();
        consent::give($uid, \core\context\system::instance());
        consent::give($uid, \core\context\system::instance());

        $this->assertTrue(consent::has_consented($uid));
        $this->assertIsInt(consent::time_consented($uid));
        $this->assertSame(1, $DB->count_records(consent::TABLE, ['userid' => $uid]));

        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \block_elediaaitutor\event\consent_given
        );
        $this->assertCount(1, $events);
    }

    /**
     * The gate throws for users without a consent record.
     */
    public function test_require_consent_throws(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_consentrequired', 'block_elediaaitutor'));
        consent::require_consent((int) $user->id);
    }

    /**
     * delete_for_user erases the record and re-arms the gate.
     */
    public function test_delete_for_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;
        consent::give($uid, \core\context\system::instance());

        $this->assertSame(1, consent::delete_for_user($uid));
        $this->assertFalse(consent::has_consented($uid));
        $this->assertSame(0, consent::delete_for_user($uid));
    }

    /**
     * Deleting the user account erases the consent record and the usage
     * counters via the observer.
     */
    public function test_user_deleted_observer(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        consent::give((int) $user->id, \core\context\system::instance());
        consent::give((int) $other->id, \core\context\system::instance());
        \block_elediaaitutor\local\usage::increment((int) $user->id);
        \block_elediaaitutor\local\usage::increment((int) $other->id);

        delete_user($user);

        $this->assertFalse($DB->record_exists(consent::TABLE, ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records(
            \block_elediaaitutor\local\usage::TABLE,
            ['userid' => $user->id]
        ));
        // Other users' records are untouched.
        $this->assertTrue(consent::has_consented((int) $other->id));
        $this->assertSame(1, \block_elediaaitutor\local\usage::count_today((int) $other->id));
    }

    /**
     * The external function records consent for the calling user only.
     */
    public function test_give_consent_external(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = give_consent::execute(\core\context\system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(give_consent::execute_returns(), $result);

        $this->assertTrue($result['consented']);
        $this->assertTrue(consent::has_consented((int) $user->id));

        // Repeated calls stay idempotent.
        $result = give_consent::execute(\core\context\system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(give_consent::execute_returns(), $result);
        $this->assertTrue($result['consented']);
    }
}
