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
 * @package     block_eledia_aitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_eledia_aitutor\output;

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

    /** @var string Per-instance "Settings" tab (this block's config; teacher-facing). */
    public const ACTIVE_INSTANCE_SETTINGS = 'instance-settings';

    /** @var string Per-instance "Tutor" tab (apply/import a tutor to this block). */
    public const ACTIVE_INSTANCE_TUTOR = 'instance-tutor';

    /** @var string Per-instance "Preview" tab (the tutor chat for this block's course). */
    public const ACTIVE_INSTANCE_PREVIEW = 'instance-preview';

    /** @var string Per-instance "MCP tokens" tab (the user's own MCP token preferences). */
    public const ACTIVE_INSTANCE_MCP = 'instance-mcp';

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
        global $PAGE, $CFG;

        // Cache-bust on the theme revision. The bare static include carried no version, so
        // browsers served a heuristically-cached copy (and, loading after the theme-aggregated
        // CSS, that stale copy won the cascade) — edits to styles.css appeared not to take.
        $rev = isset($CFG->themerev) ? (int) $CFG->themerev : -1;
        $params = ['rev' => $rev > 0 ? $rev : time()];
        $PAGE->requires->css(new moodle_url('/blocks/eledia_aitutor/styles.css', $params));
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

        $url = new moodle_url('/blocks/eledia_aitutor/configuration.php');
        $canconfigure = has_capability('moodle/site:config', \core\context\system::instance());
        $tagline = match ($active) {
            self::ACTIVE_SETTINGS => get_string('nav_settings', 'block_eledia_aitutor'),
            self::ACTIVE_TUTORS => get_string('managetutors', 'block_eledia_aitutor'),
            self::ACTIVE_PREVIEW => get_string('nav_preview', 'block_eledia_aitutor'),
            default => get_string('nav_configuration', 'block_eledia_aitutor'),
        };
        $subtitle = '';

        return [
            'name' => get_string('pluginname', 'block_eledia_aitutor'),
            'tagline' => $tagline,
            'subtitle' => $subtitle,
            'sectionnav' => self::sectionnav($active),
        ] + plugin_shell::action_slots(
            'block_eledia_aitutor',
            $canconfigure,
            $url,
            get_string('shell_help_label', 'block_eledia_aitutor'),
            get_string('shell_settings_label', 'block_eledia_aitutor'),
            $settingsiscurrent
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * The cross-plugin tabs link almost exclusively to site-admin pages (each
     * enforces moodle/site:config). It is therefore shown only to users who can
     * actually use it, so teacher-reachable shell pages (e.g. instance_tutor.php)
     * don't surface admin links a non-admin would only get "access denied" from.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell `sectionnav` slot (empty for non-admins).
     */
    public static function sectionnav(string $active): string {
        if (!has_capability('moodle/site:config', \core\context\system::instance())) {
            return '';
        }

        $items = [
            [
                'key' => self::ACTIVE_CONFIGURATION,
                'icon' => 'fa-th-large',
                'label' => get_string('nav_configuration', 'block_eledia_aitutor'),
                'url' => new moodle_url('/blocks/eledia_aitutor/configuration.php'),
            ],
            [
                'key' => self::ACTIVE_SETTINGS,
                'icon' => 'fa-sliders',
                'label' => get_string('nav_settings', 'block_eledia_aitutor'),
                'url' => new moodle_url('/admin/settings.php', ['section' => 'blocksettingeledia_aitutor']),
            ],
            [
                'key' => self::ACTIVE_TUTORS,
                'icon' => 'fa-comments',
                'label' => get_string('nav_tutors', 'block_eledia_aitutor'),
                'url' => new moodle_url('/blocks/eledia_aitutor/manage_tutors.php'),
            ],
            [
                'key' => self::ACTIVE_PREVIEW,
                'icon' => 'fa-eye',
                'label' => get_string('nav_preview', 'block_eledia_aitutor'),
                'url' => new moodle_url('/blocks/eledia_aitutor/view.php'),
            ],
        ];
        $integrations = [
            'local_literag' => [
                'key' => 'literag',
                'icon' => 'fa-database',
                'label' => get_string('nav_literag', 'block_eledia_aitutor'),
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_literag']),
            ],
            'local_ragingest' => [
                'key' => 'ragingest',
                'icon' => 'fa-upload',
                'label' => get_string('nav_ragingest', 'block_eledia_aitutor'),
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_ragingest_settings']),
            ],
            'webservice_elediamcp' => [
                'key' => 'elediamcp',
                'icon' => 'fa-plug',
                'label' => get_string('nav_elediamcp', 'block_eledia_aitutor'),
                'url' => new moodle_url('/webservice/elediamcp/configuration.php'),
            ],
        ];
        foreach ($integrations as $plugin => $item) {
            if (\core_component::get_plugin_directory(...explode('_', $plugin, 2)) !== null) {
                $items[] = $item;
            }
        }

        return self::render_nav($items, $active);
    }

    /**
     * Open the shell for a per-instance block page (Settings / Tutor / Preview tabs
     * scoped to one block instance). Available to anyone with manage rights on the
     * instance; it carries no site-admin tabs and no settings cog.
     *
     * @param int $blockid The block_instances.id.
     * @param string $active One of the ACTIVE_INSTANCE_* keys.
     */
    public static function open_instance(int $blockid, string $active): void {
        if (!self::is_available()) {
            return;
        }

        $parent = \core\context\block::instance($blockid)->get_parent_context();
        $coursename = ($parent && $parent->contextlevel == CONTEXT_COURSE)
            ? format_string(get_course($parent->instanceid)->fullname)
            : ($parent ? $parent->get_context_name(false) : '');

        // Mirror the site shell ("eLeDia.ai Tutor | <section>"), keeping the course in
        // parentheses so the instance is still clear, e.g. "Settings (Demo course)".
        $section = match ($active) {
            self::ACTIVE_INSTANCE_SETTINGS => get_string('nav_settings', 'block_eledia_aitutor'),
            self::ACTIVE_INSTANCE_TUTOR => get_string('nav_instance_tutor', 'block_eledia_aitutor'),
            self::ACTIVE_INSTANCE_PREVIEW => get_string('nav_preview', 'block_eledia_aitutor'),
            self::ACTIVE_INSTANCE_MCP => get_string('nav_instance_mcp', 'block_eledia_aitutor'),
            default => get_string('pluginname', 'block_eledia_aitutor'),
        };
        $tagline = $coursename !== ''
            ? get_string('instance_shell_tagline', 'block_eledia_aitutor', (object) [
                'section' => $section,
                'course' => $coursename,
            ])
            : $section;

        $context = [
            'name' => get_string('pluginname', 'block_eledia_aitutor'),
            'tagline' => $tagline,
            'subtitle' => '',
            'sectionnav' => self::instance_sectionnav($blockid, $active),
        ] + plugin_shell::action_slots('block_eledia_aitutor', false);

        // Easy return to the host course via the header's back-arrow slot.
        $courseid = self::instance_courseid($blockid);
        if ($courseid > 0) {
            $context['backurl'] = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            $context['backlabel'] = get_string('nav_back_to_course', 'block_eledia_aitutor');
        }

        plugin_page::open($context, plugin_page::MODIFIER_READING);
        plugin_shell::content_open();
    }

    /**
     * Build the per-instance section navigation (Settings / Tutor / Preview),
     * scoped to a single block instance.
     *
     * @param int $blockid The block_instances.id.
     * @param string $active One of the ACTIVE_INSTANCE_* keys.
     * @return string Raw nav HTML.
     */
    public static function instance_sectionnav(int $blockid, string $active): string {
        $courseid = self::instance_courseid($blockid);
        // Preview keeps the blockid so view.php re-renders this instance shell (menu persists).
        $previewparams = ['blockid' => $blockid] + ($courseid > 0 ? ['courseid' => $courseid] : []);
        $items = [
            [
                'key' => self::ACTIVE_INSTANCE_SETTINGS,
                'icon' => 'fa-sliders',
                'label' => get_string('nav_settings', 'block_eledia_aitutor'),
                'url' => new moodle_url('/blocks/eledia_aitutor/edit_instance.php', ['blockid' => $blockid]),
            ],
            [
                'key' => self::ACTIVE_INSTANCE_TUTOR,
                'icon' => 'fa-comments',
                'label' => get_string('nav_instance_tutor', 'block_eledia_aitutor'),
                'url' => new moodle_url('/blocks/eledia_aitutor/instance_tutor.php', ['blockid' => $blockid]),
            ],
            [
                'key' => self::ACTIVE_INSTANCE_PREVIEW,
                'icon' => 'fa-eye',
                'label' => get_string('nav_preview', 'block_eledia_aitutor'),
                'url' => new moodle_url('/blocks/eledia_aitutor/view.php', $previewparams),
            ],
        ];
        // The learner's own MCP token preferences, when the MCP webservice is installed.
        if (\core_component::get_plugin_directory('webservice', 'elediamcp') !== null) {
            $items[] = [
                'key' => self::ACTIVE_INSTANCE_MCP,
                'icon' => 'fa-plug',
                'label' => get_string('nav_instance_mcp', 'block_eledia_aitutor'),
                // Carry the blockid so the token page re-renders this instance shell.
                'url' => new moodle_url('/webservice/elediamcp/token/index.php', ['blockid' => $blockid]),
            ];
        }
        return self::render_nav($items, $active);
    }

    /**
     * The course id a block instance belongs to, or 0 when it is not in a course
     * (e.g. a Dashboard block).
     *
     * @param int $blockid The block_instances.id.
     * @return int
     */
    private static function instance_courseid(int $blockid): int {
        $parent = \core\context\block::instance($blockid)->get_parent_context();
        return ($parent && $parent->contextlevel == CONTEXT_COURSE) ? (int) $parent->instanceid : 0;
    }

    /**
     * Render a list of nav items as the shared section-nav markup.
     *
     * @param array<int, array{key: string, icon: string, label: string, url: \moodle_url}> $items
     * @param string $active Active item key.
     * @return string Raw nav HTML.
     */
    private static function render_nav(array $items, string $active): string {
        $html = html_writer::start_tag('nav', [
            'class' => 'lh-plugin-section-nav',
            'aria-label' => get_string('nav_label', 'block_eledia_aitutor'),
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
