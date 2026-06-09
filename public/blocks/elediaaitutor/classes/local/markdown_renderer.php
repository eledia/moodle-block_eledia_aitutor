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
 * Renders assistant Markdown to safe HTML.
 *
 * Assistant output is untrusted (it originates from an LLM/RAG pipeline), so it
 * is converted from Markdown and then run through Moodle's HTML purifier via
 * {@see format_text()}. The result is sanitised server-side and is safe to
 * inject into the DOM, so the client never has to trust raw model output.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class markdown_renderer {
    /**
     * Convert assistant Markdown into sanitised HTML.
     *
     * @param string $markdown Raw assistant Markdown.
     * @param \context $context Context used for filtering/cleaning.
     * @return string Safe HTML.
     */
    public static function render(string $markdown, \context $context): string {
        if (trim($markdown) === '') {
            return '';
        }

        // FORMAT_MARKDOWN parses Markdown then HTMLPurifier cleans the result.
        // filter => false avoids running content filters (e.g. multimedia) on
        // model output; clean (the default) strips dangerous markup.
        return format_text($markdown, FORMAT_MARKDOWN, [
            'context' => $context,
            'filter' => false,
            'para' => true,
        ]);
    }
}
