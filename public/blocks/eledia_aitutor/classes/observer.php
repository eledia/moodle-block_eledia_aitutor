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

namespace block_eledia_aitutor;

use block_eledia_aitutor\local\consent;
use block_eledia_aitutor\local\usage;

/**
 * Core event observers.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * When a user account is deleted, erase their consent record and usage
     * counters immediately.
     *
     * Both rows exist solely for the (now deleted) account — documented
     * acknowledgement and quota counting — so they must not outlive it.
     * Conversation pointers and analytics rows are handled by the Privacy API
     * on data deletion requests.
     *
     * @param \core\event\user_deleted $event The deletion event.
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        consent::delete_for_user((int) $event->objectid);
        usage::delete_for_user((int) $event->objectid);
    }
}
