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
 * Plugin Shell adapter for eLeDia.ai Tutor pages.
 *
 * @package     block_elediaaitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_elediaaitutor\output;

use html_writer;
use moodle_url;

/**
 * Builds and renders the shared Plugin Shell context.
 */
final class shell {
    /** @var string Configuration section key. */
    public const ACTIVE_CONFIGURATION = 'configuration';

    /** @var string Operator settings section key. */
    public const ACTIVE_SETTINGS = 'settings';

    /** @var string Tutor library section key. */
    public const ACTIVE_TUTORS = 'tutors';

    /** @var string Standalone tutor section key. */
    public const ACTIVE_PREVIEW = 'preview';

    /**
     * Check whether the shell helper is installed and autoloadable.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists(plugin_page::class) && class_exists(plugin_shell::class);
    }

    /**
     * Require the shared shell CSS.
     */
    public static function require_css(): void {
        global $PAGE;

        $PAGE->requires->css('/blocks/elediaaitutor/styles.css');
    }

    /**
     * Build the Zone-A shell context.
     *
     * The Settings slot points to this plugin-owned in-shell page. Moodle's
     * generic admin settings remain an operator-facing target linked from the
     * page content, in line with ux-system.md §3.5.
     *
     * @param bool $settingsiscurrent Whether the settings slot represents the current page.
     * @return array<string, mixed>
     */
    public static function context(
        string $active = self::ACTIVE_CONFIGURATION,
        bool $settingsiscurrent = false
    ): array {
        if (!self::is_available()) {
            return [];
        }

        $url = new moodle_url('/blocks/elediaaitutor/configuration.php');
        $canconfigure = has_capability('moodle/site:config', \core\context\system::instance());
        $tagline = match ($active) {
            self::ACTIVE_SETTINGS => get_string('nav_settings', 'block_elediaaitutor'),
            self::ACTIVE_TUTORS => get_string('managetutors', 'block_elediaaitutor'),
            self::ACTIVE_PREVIEW => get_string('nav_preview', 'block_elediaaitutor'),
            default => get_string('nav_configuration', 'block_elediaaitutor'),
        };
        $subtitle = '';

        return [
            'name' => get_string('pluginname', 'block_elediaaitutor'),
            'tagline' => $tagline,
            'subtitle' => $subtitle,
            'sectionnav' => self::sectionnav($active),
        ] + plugin_shell::action_slots(
            'block_elediaaitutor',
            $canconfigure,
            $url,
            get_string('shell_help_label', 'block_elediaaitutor'),
            get_string('shell_settings_label', 'block_elediaaitutor'),
            $settingsiscurrent
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell `sectionnav` slot.
     */
    public static function sectionnav(string $active): string {
        $items = [
            [
                'key' => self::ACTIVE_CONFIGURATION,
                'icon' => 'fa-th-large',
                'label' => get_string('nav_configuration', 'block_elediaaitutor'),
                'url' => new moodle_url('/blocks/elediaaitutor/configuration.php'),
            ],
            [
                'key' => self::ACTIVE_SETTINGS,
                'icon' => 'fa-sliders',
                'label' => get_string('nav_settings', 'block_elediaaitutor'),
                'url' => new moodle_url('/admin/settings.php', ['section' => 'blocksettingelediaaitutor']),
            ],
            [
                'key' => self::ACTIVE_TUTORS,
                'icon' => 'fa-comments',
                'label' => get_string('nav_tutors', 'block_elediaaitutor'),
                'url' => new moodle_url('/blocks/elediaaitutor/manage_tutors.php'),
            ],
            [
                'key' => self::ACTIVE_PREVIEW,
                'icon' => 'fa-eye',
                'label' => get_string('nav_preview', 'block_elediaaitutor'),
                'url' => new moodle_url('/blocks/elediaaitutor/view.php'),
            ],
        ];
        $integrations = [
            'local_literag' => [
                'key' => 'literag',
                'icon' => 'fa-database',
                'label' => get_string('nav_literag', 'block_elediaaitutor'),
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_literag']),
            ],
            'local_ragingest' => [
                'key' => 'ragingest',
                'icon' => 'fa-upload',
                'label' => get_string('nav_ragingest', 'block_elediaaitutor'),
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_ragingest_settings']),
            ],
            'webservice_elediamcp' => [
                'key' => 'elediamcp',
                'icon' => 'fa-plug',
                'label' => get_string('nav_elediamcp', 'block_elediaaitutor'),
                'url' => new moodle_url('/webservice/elediamcp/configuration.php'),
            ],
        ];
        foreach ($integrations as $plugin => $item) {
            if (\core_component::get_plugin_directory(...explode('_', $plugin, 2)) !== null) {
                $items[] = $item;
            }
        }

        $html = html_writer::start_tag('nav', [
            'class' => 'lh-plugin-section-nav',
            'aria-label' => get_string('nav_label', 'block_elediaaitutor'),
        ]);

        foreach ($items as $item) {
            $attrs = [
                'class' => 'lh-plugin-section-nav__item',
                'href' => $item['url']->out(false),
            ];
            if ($item['key'] === $active) {
                $attrs['aria-current'] = 'page';
            }
            $label = html_writer::tag('i', '', [
                'class' => 'fa ' . $item['icon'],
                'aria-hidden' => 'true',
            ]) . ' ' . s($item['label']);
            $html .= html_writer::tag('a', $label, $attrs);
        }

        $html .= html_writer::end_tag('nav');
        return $html;
    }

    /**
     * Open the shell and its content wrapper.
     *
     * @param string $active Active section key.
     * @param bool $settingsiscurrent Whether the settings slot represents the current page.
     */
    public static function open(
        string $active = self::ACTIVE_CONFIGURATION,
        bool $settingsiscurrent = false
    ): void {
        if (!self::is_available()) {
            return;
        }

        plugin_page::open(
            self::context($active, $settingsiscurrent),
            plugin_page::MODIFIER_READING
        );
        plugin_shell::content_open();
    }

    /**
     * Close the shell content wrapper.
     */
    public static function close(): void {
        if (!self::is_available()) {
            return;
        }

        plugin_shell::content_close();
        plugin_page::close();
    }
}
