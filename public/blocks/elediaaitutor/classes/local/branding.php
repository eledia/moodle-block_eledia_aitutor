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

use context_system;
use moodle_url;

/**
 * Resolves the effective institutional branding for the tutor widget.
 *
 * Branding has two layers: institution-wide **site** defaults (admin settings)
 * and optional **per-instance** overrides set on a block. An instance value
 * wins when present; otherwise the site value applies; otherwise the built-in
 * design defaults in styles.css remain (we simply emit no override for that
 * token). The colour/font parts are turned into scoped CSS custom-property
 * overrides ({@see css_variables()}) injected under the widget's unique id, so
 * two differently-branded instances can coexist on one page.
 *
 * White-label and custom CSS are institution-level (admin) only; the per-answer
 * visual identity (accent, user-bubble colour, launcher label, logo) may also
 * be overridden per instance.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class branding {
    /** @var string Footer shows the default "Powered by eLeDia.ai" credit + link. */
    public const FOOTER_DEFAULT = 'default';

    /** @var string Footer shows institution-defined text. */
    public const FOOTER_CUSTOM = 'custom';

    /** @var string Footer hidden entirely (full white-label). */
    public const FOOTER_NONE = 'none';

    /** @var string File area for the site-level brand logo (tutor logo). */
    public const LOGO_FILEAREA = 'brandlogo';

    /** @var string File area for the site-level conversation avatar. */
    public const AVATAR_FILEAREA = 'brandavatar';

    /** @var string Block-instance file area for the tutor logo override. */
    public const INSTANCE_LOGO_FILEAREA = 'instancelogo';

    /** @var string Block-instance file area for the conversation avatar override. */
    public const INSTANCE_AVATAR_FILEAREA = 'instanceavatar';

    /**
     * The effective branding kit, merging per-instance overrides over the site
     * defaults.
     *
     * @param array<string, mixed> $instance Per-instance overrides. Recognised:
     *        theme, brandaccent, brandbubble, launchlabel.
     * @return array{accent: string, bubble: string, theme: string,
     *         tokens: array<string, string>, launchlabel: string,
     *         footermode: string, footertext: string}
     */
    public static function resolve(array $instance = []): array {
        // 1. Theme provides the base palette (instance theme wins over site).
        $themeid = trim((string) ($instance['theme'] ?? ''));
        if ($themeid === '') {
            $themeid = trim((string) security::get_config('theme', themes::DEFAULT));
        }
        if (!isset(themes::all()[$themeid])) {
            $themeid = themes::DEFAULT;
        }
        $tokens = themes::tokens($themeid);
        $themed = themes::is_themed($themeid);

        // 2. Explicit institutional colour/font overrides layer on top.
        $accent = self::sanitise_colour((string) ($instance['brandaccent'] ?? ''))
            ?? self::sanitise_colour(security::brand_accent());
        if ($accent !== null) {
            $tokens['--eat-accent'] = $accent;
            $tokens['--eat-accent-dark'] = self::darken($accent, 0.12);
            // In the default (light) palette the ink equals the accent; only
            // map it there, so a dark theme's light text stays intact.
            if (!$themed) {
                $tokens['--eat-ink'] = $accent;
                $tokens['--eat-header-fg'] = $accent;
            }
        }
        $bubble = self::sanitise_colour((string) ($instance['brandbubble'] ?? ''))
            ?? self::sanitise_colour(security::brand_bubble());
        if ($bubble !== null) {
            $tokens['--eat-user-bg'] = $bubble;
        }
        $botbubble = self::sanitise_colour((string) ($instance['brandbotbubble'] ?? ''))
            ?? self::sanitise_colour(security::brand_bot_bubble());
        if ($botbubble !== null) {
            $tokens['--eat-bot-bg'] = $botbubble;
        }
        $surface = self::sanitise_colour(security::brand_surface());
        if ($surface !== null) {
            $tokens['--eat-body-bg'] = $surface;
        }
        $font = self::sanitise_font(security::brand_font());
        if ($font !== '') {
            $tokens['--eat-font'] = $font;
        }

        $launchlabel = trim((string) ($instance['launchlabel'] ?? ''));
        if ($launchlabel === '') {
            $launchlabel = security::brand_launch_label();
        }
        if ($launchlabel === '') {
            $launchlabel = get_string('launch', 'block_elediaaitutor');
        }

        // Launcher style: per-instance ('' = follow site) over the site default.
        $launcherstyle = trim((string) ($instance['launcherstyle'] ?? ''));
        if (!in_array($launcherstyle, ['pill', 'solid', 'fab'], true)) {
            $launcherstyle = security::launcher_style();
        }

        return [
            'theme' => $themeid,
            'tokens' => $tokens,
            'accent' => (string) ($accent ?? ''),
            'bubble' => (string) ($bubble ?? ''),
            'botbubble' => (string) ($botbubble ?? ''),
            'launchlabel' => $launchlabel,
            'launcherstyle' => $launcherstyle,
            'footermode' => self::footer_mode(),
            'footertext' => self::footer_text(),
        ];
    }

    /**
     * The site-level conversation avatar URL (assistant message avatar), or ''.
     *
     * @return string
     */
    public static function site_avatar_url(): string {
        $filename = (string) get_config('block_elediaaitutor', self::AVATAR_FILEAREA);
        if ($filename === '') {
            return '';
        }
        return moodle_url::make_pluginfile_url(
            context_system::instance()->id,
            'block_elediaaitutor',
            self::AVATAR_FILEAREA,
            0,
            '/',
            ltrim($filename, '/')
        )->out(false);
    }

    /**
     * Serialise a kit's resolved `--eat-*` token map into CSS declarations.
     *
     * Empty when nothing is themed/branded, so the styles.css defaults stand.
     * The caller wraps these in a selector keyed on the widget's unique id.
     *
     * @param array<string, mixed> $brand A kit from {@see resolve()}.
     * @return string CSS declarations (may be empty).
     */
    public static function css_variables(array $brand): string {
        $out = '';
        foreach (($brand['tokens'] ?? []) as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }
        return $out;
    }

    /**
     * The site-level brand logo URL, or '' when none is uploaded.
     *
     * @return string
     */
    public static function site_logo_url(): string {
        $filename = (string) get_config('block_elediaaitutor', self::LOGO_FILEAREA);
        if ($filename === '') {
            return '';
        }
        // admin_setting_configstoredfile stores the file in the system context.
        return moodle_url::make_pluginfile_url(
            context_system::instance()->id,
            'block_elediaaitutor',
            self::LOGO_FILEAREA,
            0,
            '/',
            ltrim($filename, '/')
        )->out(false);
    }

    /**
     * URL of a per-instance uploaded file (logo/avatar), or '' when none.
     *
     * Files live in the block context; only block contexts can carry them
     * (the standalone view.php uses the system context and has none).
     *
     * @param \context $context The widget context.
     * @param string $filearea INSTANCE_LOGO_FILEAREA or INSTANCE_AVATAR_FILEAREA.
     * @return string
     */
    public static function instance_file_url(\context $context, string $filearea): string {
        if ($context->contextlevel !== CONTEXT_BLOCK) {
            return '';
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_elediaaitutor', $filearea,
            0, 'itemid, filepath, filename', false);
        if (empty($files)) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url($context->id, 'block_elediaaitutor', $filearea,
            0, $file->get_filepath(), $file->get_filename())->out(false);
    }

    /**
     * The configured footer mode.
     *
     * @return string One of the FOOTER_* constants.
     */
    public static function footer_mode(): string {
        $mode = (string) security::get_config('footermode', self::FOOTER_DEFAULT);
        return in_array($mode, [self::FOOTER_DEFAULT, self::FOOTER_CUSTOM, self::FOOTER_NONE], true)
            ? $mode : self::FOOTER_DEFAULT;
    }

    /**
     * The effective footer text for the current mode ('' when hidden).
     *
     * @return string
     */
    public static function footer_text(): string {
        switch (self::footer_mode()) {
            case self::FOOTER_NONE:
                return '';
            case self::FOOTER_CUSTOM:
                $text = trim((string) security::get_config('footertext', ''));
                return $text !== '' ? $text : get_string('poweredby', 'block_elediaaitutor');
            default:
                return get_string('poweredby', 'block_elediaaitutor');
        }
    }

    /**
     * Validate a colour to a safe CSS hex value, or null if invalid/empty.
     *
     * @param string $value Candidate colour.
     * @return string|null A `#rgb`/`#rrggbb`(/aa) value, or null.
     */
    public static function sanitise_colour(string $value): ?string {
        $value = trim($value);
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : null;
    }

    /**
     * Reduce a font specification to a safe `font-family` value.
     *
     * Allows letters, digits, spaces, commas, hyphens and quotes only — enough
     * for a font stack, nothing that could break out of the declaration.
     *
     * @param string $value Candidate font stack.
     * @return string Sanitised value, or '' when nothing usable remains.
     */
    public static function sanitise_font(string $value): string {
        $value = trim((string) preg_replace('/[^A-Za-z0-9 ,\'"\-]/', '', $value));
        return $value;
    }

    /**
     * Darken a hex colour by a fraction (0..1) for the accent-hover token.
     *
     * @param string $hex A `#rgb` or `#rrggbb` colour.
     * @param float $fraction Amount to darken (0..1).
     * @return string A `#rrggbb` colour.
     */
    private static function darken(string $hex, float $fraction): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) < 6) {
            return '#' . $hex;
        }
        $rgb = [];
        foreach ([0, 2, 4] as $i) {
            $channel = (int) hexdec(substr($hex, $i, 2));
            $rgb[] = max(0, min(255, (int) round($channel * (1 - $fraction))));
        }
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }
}
