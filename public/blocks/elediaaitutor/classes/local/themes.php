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
 * Curated visual themes for the tutor widget.
 *
 * A theme is a named bundle of `--eat-*` design-token values that provides a
 * complete palette (so dark themes stay readable). It forms the BASE layer of
 * {@see branding::resolve()}; explicit institutional colour overrides (accent,
 * user-bubble, surface) and the font then layer on top, so a site can pick a
 * theme and still nudge individual colours.
 *
 * All token values here are trusted literals authored in-plugin, so they need
 * no sanitisation before injection.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class themes {
    /** @var string The built-in eLeDia look (no overrides). */
    public const DEFAULT = 'default';

    /**
     * All themes: id => ['label' => lang-string key, 'tokens' => --eat-* map].
     *
     * @return array<string, array{label: string, tokens: array<string, string>}>
     */
    public static function all(): array {
        return [
            self::DEFAULT => [
                'label' => 'theme_default',
                'tokens' => [],
            ],
            // Calm light theme — teal accent, soft sage bubbles.
            'forest' => [
                'label' => 'theme_forest',
                'tokens' => self::light([
                    '--eat-accent' => '#1f6f54',
                    '--eat-accent-dark' => '#16523e',
                    '--eat-ink' => '#1b3b32',
                    '--eat-header-fg' => '#1b3b32',
                    '--eat-bot-fg' => '#1b3b32',
                    '--eat-body-bg' => '#f1f5f1',
                    '--eat-user-bg' => '#e3efd6',
                    '--eat-user-fg' => '#1b3b32',
                    '--eat-line' => '#dbe5d8',
                    '--eat-tint' => '#eef4ec',
                    '--eat-grounded-bg' => '#e3efd6',
                    '--eat-grounded-line' => '#c4d8b4',
                    '--eat-muted' => '#5f7068',
                    '--eat-muted-soft' => '#4c5d55',
                    '--eat-status-online' => '#2faa6a',
                ]),
            ],
            // Dark slate — light text on deep blue-grey, cyan accent.
            'midnight' => [
                'label' => 'theme_midnight',
                'tokens' => self::dark([
                    '--eat-accent' => '#5cc8ff',
                    '--eat-accent-dark' => '#3aa6e0',
                    // Bright accent → dark text on accent-filled controls.
                    '--eat-accent-contrast' => '#0b1722',
                    '--eat-surface' => '#1b2330',
                    '--eat-body-bg' => '#121821',
                    '--eat-bot-bg' => '#232d3c',
                    '--eat-user-bg' => '#2d3a4e',
                    '--eat-line' => '#2c3848',
                    '--eat-status-online' => '#46d18a',
                ]),
            ],
            // High-contrast — strong black/white for accessibility.
            'contrast' => [
                'label' => 'theme_contrast',
                'tokens' => self::light([
                    '--eat-accent' => '#0b3d91',
                    '--eat-accent-dark' => '#082c69',
                    '--eat-ink' => '#000000',
                    '--eat-header-fg' => '#000000',
                    '--eat-bot-fg' => '#000000',
                    '--eat-muted' => '#3a3a3a',
                    '--eat-muted-soft' => '#3a3a3a',
                    '--eat-line' => '#000000',
                    '--eat-tint' => '#eef2fb',
                    '--eat-grounded-bg' => '#e6ecf7',
                    '--eat-grounded-line' => '#000000',
                    '--eat-user-bg' => '#e6ecf7',
                    '--eat-user-fg' => '#000000',
                ]),
            ],
            // HAL 9000 — black panel, glowing red eye. For the fun of it.
            'hal' => [
                'label' => 'theme_hal',
                'tokens' => self::dark([
                    '--eat-accent' => '#ff1a1a',
                    '--eat-accent-dark' => '#c20000',
                    '--eat-surface' => '#161618',
                    '--eat-body-bg' => '#0c0c0e',
                    '--eat-bot-bg' => '#1f1f23',
                    '--eat-user-bg' => '#2a1416',
                    '--eat-user-fg' => '#f4e3e3',
                    '--eat-line' => '#2a2a2e',
                    '--eat-status-online' => '#ff2b2b',
                    // The avatar becomes the glowing red eye.
                    '--eat-avatar-glow' => '0 0 14px 2px rgba(255, 24, 24, 0.75)',
                ]),
            ],
        ];
    }

    /**
     * Tokens every (non-default) theme must define for full legibility. The
     * light()/dark() baselines guarantee these; the unit test enforces it for
     * any future theme.
     *
     * @return string[]
     */
    public static function required_tokens(): array {
        return [
            '--eat-accent', '--eat-accent-dark', '--eat-accent-contrast',
            '--eat-ink', '--eat-header-fg', '--eat-surface', '--eat-body-bg',
            '--eat-bot-bg', '--eat-bot-fg', '--eat-user-bg', '--eat-user-fg',
            '--eat-muted', '--eat-muted-soft', '--eat-line', '--eat-tint',
            '--eat-overlay', '--eat-icon-hover', '--eat-grounded-bg',
            '--eat-grounded-line', '--eat-status-online',
        ];
    }

    /**
     * The `--eat-*` token overrides for a theme id (empty for default/unknown).
     *
     * @param string $id Theme id.
     * @return array<string, string>
     */
    public static function tokens(string $id): array {
        return self::all()[$id]['tokens'] ?? [];
    }

    /**
     * Whether the id is a real, non-default theme.
     *
     * @param string $id Theme id.
     * @return bool
     */
    public static function is_themed(string $id): bool {
        return $id !== '' && $id !== self::DEFAULT && isset(self::all()[$id]);
    }

    /**
     * Theme id => translated label, for settings/form menus.
     *
     * @return array<string, string>
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::all() as $id => $theme) {
            $menu[$id] = get_string($theme['label'], 'block_elediaaitutor');
        }
        return $menu;
    }

    /**
     * Complete light-theme baseline (dark text on light surfaces). Callers
     * merge their accent/tint specifics on top; every required token has a
     * sensible default here so a theme can override only what differs.
     *
     * @param array<string, string> $overrides Theme-specific tokens.
     * @return array<string, string>
     */
    private static function light(array $overrides): array {
        return array_merge([
            '--eat-accent' => '#1e3f59',
            '--eat-accent-dark' => '#16314a',
            '--eat-accent-contrast' => '#ffffff',
            '--eat-ink' => '#1e3f59',
            '--eat-header-fg' => '#1e3f59',
            '--eat-surface' => '#ffffff',
            '--eat-body-bg' => '#f4f6f8',
            '--eat-bot-bg' => '#ffffff',
            '--eat-bot-fg' => '#1e3f59',
            '--eat-user-bg' => '#fce9db',
            '--eat-user-fg' => '#1e3f59',
            '--eat-muted' => '#748495',
            '--eat-muted-soft' => '#5b6677',
            '--eat-line' => '#e7ebef',
            '--eat-tint' => '#f4f7fa',
            '--eat-overlay' => 'rgba(16, 24, 40, 0.06)',
            '--eat-overlay-strong' => 'rgba(0, 0, 0, 0.04)',
            '--eat-code-bg' => 'rgba(0, 0, 0, 0.06)',
            '--eat-icon-hover' => 'rgba(16, 24, 40, 0.08)',
            '--eat-grounded-bg' => '#eaf1f3',
            '--eat-grounded-line' => '#c9d8dd',
            '--eat-status-online' => '#22c55e',
        ], $overrides);
    }

    /**
     * Complete dark-theme baseline (light text on dark surfaces). Callers merge
     * their accent/surface specifics on top.
     *
     * @param array<string, string> $overrides Theme-specific tokens.
     * @return array<string, string>
     */
    private static function dark(array $overrides): array {
        return array_merge([
            '--eat-accent' => '#5cc8ff',
            '--eat-accent-dark' => '#3aa6e0',
            '--eat-accent-contrast' => '#0b1722',
            '--eat-ink' => '#e8e8ea',
            '--eat-header-fg' => '#f2f2f4',
            '--eat-surface' => '#1b1d21',
            '--eat-body-bg' => '#121317',
            '--eat-bot-bg' => '#1f1f23',
            '--eat-bot-fg' => '#e8e8ea',
            '--eat-user-bg' => '#2d3340',
            '--eat-user-fg' => '#e8e8ea',
            '--eat-muted' => '#9aa0a6',
            '--eat-muted-soft' => '#b4b9bf',
            '--eat-line' => '#2c3036',
            '--eat-assistant-bg' => '#1f1f23',
            '--eat-assistant-fg' => '#e8e8ea',
            '--eat-tint' => '#1f1f22',
            '--eat-grounded-bg' => '#1f2a2e',
            '--eat-grounded-line' => '#33484f',
            // Light translucent hovers so they show on dark surfaces.
            '--eat-overlay' => 'rgba(255, 255, 255, 0.10)',
            '--eat-overlay-strong' => 'rgba(255, 255, 255, 0.06)',
            '--eat-code-bg' => 'rgba(255, 255, 255, 0.08)',
            '--eat-icon-hover' => 'rgba(255, 255, 255, 0.14)',
            '--eat-status-online' => '#46d18a',
        ], $overrides);
    }
}
