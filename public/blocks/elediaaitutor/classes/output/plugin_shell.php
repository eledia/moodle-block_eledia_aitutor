<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

declare(strict_types=1);

namespace block_elediaaitutor\output;

use coding_exception;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared Plugin Shell helpers owned by the eLeDia.ai Tutor block.
 *
 * @package     block_elediaaitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugin_shell {
    /**
     * Build trailing header action slots.
     *
     * @param string $component Frankenstyle component.
     * @param bool $cansiteconfig Whether the settings slot may be rendered.
     * @param moodle_url|string|null $settingsurl Plugin-owned settings URL.
     * @param string|null $helplabel Help label.
     * @param string|null $settingslabel Settings label.
     * @param bool $settingsiscurrent Mark settings as current.
     * @return array<string, mixed>
     */
    public static function action_slots(
        string $component,
        bool $cansiteconfig = false,
        moodle_url|string|null $settingsurl = null,
        ?string $helplabel = null,
        ?string $settingslabel = null,
        bool $settingsiscurrent = false
    ): array {
        $resolvedsettingsurl = '';
        if ($cansiteconfig && $settingsurl !== null && $settingsurl !== '') {
            $resolvedsettingsurl = self::url_to_string($settingsurl);
            if (str_contains($resolvedsettingsurl, '/admin/settings.php')) {
                throw new coding_exception(
                    'Plugin Shell settingsurl must point to a plugin-owned in-shell page, not admin/settings.php.'
                );
            }
        }

        $helpurl = new moodle_url('/local/lernhive/support.php', ['component' => $component]);

        return [
            'hasactions' => $resolvedsettingsurl !== '',
            'helpurl' => self::url_to_string($helpurl),
            'helplabel' => $helplabel ?? get_string('help', 'core'),
            'settingsurl' => $resolvedsettingsurl,
            'settingslabel' => $settingslabel ?? get_string('settings', 'core'),
            'settingsiscurrent' => $settingsiscurrent && $resolvedsettingsurl !== '',
        ];
    }

    /**
     * Open a shell content area.
     *
     * @param string $classes CSS classes.
     */
    public static function content_open(string $classes = 'lh-plugin-content-area'): void {
        echo html_writer::start_div($classes);
    }

    /**
     * Close a shell content area.
     */
    public static function content_close(): void {
        echo html_writer::end_div();
    }

    /**
     * Convert URL object or string to a template-safe URL string.
     *
     * @param moodle_url|string $url URL value.
     * @return string
     */
    private static function url_to_string(moodle_url|string $url): string {
        return $url instanceof moodle_url ? $url->out(false) : $url;
    }
}
