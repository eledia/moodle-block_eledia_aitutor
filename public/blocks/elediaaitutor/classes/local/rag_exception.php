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

use moodle_exception;

/**
 * Raised when a call to the external RAG/Tutor server fails.
 *
 * The user-facing message is always a generic, localised string; any detail
 * useful for debugging is carried only in the (developer-mode) debug info and
 * is scrubbed of secrets before being attached.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rag_exception extends moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode Language string key in block_elediaaitutor.
     * @param string|null $debuginfo Optional developer-only detail (no secrets).
     */
    public function __construct(string $errorcode = 'error_rag_unavailable', ?string $debuginfo = null) {
        parent::__construct($errorcode, 'block_elediaaitutor', '', null, $debuginfo);
    }
}
