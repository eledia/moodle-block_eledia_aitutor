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

/**
 * External (AJAX) function definitions for the eLeDia.ai Tutor block.
 *
 * These functions are only ever called from the plugin's own AMD module over
 * Moodle's authenticated core/ajax channel (ajax => true, loginrequired honoured
 * by the calling page). They are deliberately not added to any external service,
 * so they cannot be invoked through the public Web Services API.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_elediaaitutor_send_message' => [
        'classname' => 'block_elediaaitutor\external\send_message',
        'description' => 'Send a chat message to the RAG/Tutor server and return the assistant response.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:use',
        'loginrequired' => true,
    ],
    'block_elediaaitutor_get_history' => [
        'classname' => 'block_elediaaitutor\external\get_history',
        'description' => 'Load previous messages for one of the current user\'s conversations.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:viewhistory',
        'loginrequired' => true,
    ],
    'block_elediaaitutor_get_conversations' => [
        'classname' => 'block_elediaaitutor\external\get_conversations',
        'description' => 'List the current user\'s stored conversations.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:viewhistory',
        'loginrequired' => true,
    ],
    'block_elediaaitutor_clear_conversation' => [
        'classname' => 'block_elediaaitutor\external\clear_conversation',
        'description' => 'Clear/delete one of the current user\'s own conversations.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:deleteownhistory',
        'loginrequired' => true,
    ],
    'block_elediaaitutor_give_consent' => [
        'classname' => 'block_elediaaitutor\external\give_consent',
        'description' => 'Record the current user\'s acknowledgement of the privacy guidelines.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:use',
        'loginrequired' => true,
    ],
    'block_elediaaitutor_set_ltm' => [
        'classname' => 'block_elediaaitutor\external\set_ltm',
        'description' => 'Set the current user\'s long-term memory opt-in preference.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:use',
        'loginrequired' => true,
    ],
    'block_elediaaitutor_delete_my_data' => [
        'classname' => 'block_elediaaitutor\external\delete_my_data',
        'description' => 'Delete all of the current user\'s own tutor data.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/elediaaitutor:deleteownhistory',
        'loginrequired' => true,
    ],
];
