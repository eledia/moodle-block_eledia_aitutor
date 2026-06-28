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

namespace block_eledia_aitutor\event;

use core\event\base;

/**
 * Event fired when a user requests deletion of all their tutor data.
 *
 * Records how many local conversation pointers were removed and whether/how the
 * deletion was propagated to the external RAG/Tutor server. No message content
 * is included.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_deletion_requested extends base {
    /**
     * Initialise event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_data_deletion_requested', 'block_eledia_aitutor');
    }

    /**
     * Return a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $local = (int) ($this->other['localdeleted'] ?? 0);
        $supported = !empty($this->other['externalsupported']);
        $ext = (int) ($this->other['externaldeleted'] ?? 0);
        $failed = (int) ($this->other['externalfailed'] ?? 0);
        return "The user with id '{$this->userid}' requested deletion of all their tutor data "
            . "($local local conversation(s) deleted; external deletion "
            . ($supported ? "requested: $ext ok, $failed failed" : 'not supported') . ").";
    }

    /**
     * Validate the event data before dispatch.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->relateduserid)) {
            throw new \coding_exception('data_deletion_requested event requires relateduserid');
        }
    }
}
