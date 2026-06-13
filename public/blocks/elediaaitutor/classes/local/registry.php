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
 * The single source of truth for every "tutor-defining" setting.
 *
 * A *tutor* is the full presentation + persona of the widget: its visual design
 * (every `--eat-*` CSS custom property), its persona/system-prompt fields, the
 * launcher, footer and a handful of behaviour toggles, plus the logo and avatar
 * images. Infrastructure and security settings (RAG server URL, tokens, MCP
 * service, network/timeout/rate-limit/analytics) are deliberately NOT here —
 * they are not part of a tutor and stay hand-declared in settings.php.
 *
 * This registry drives, as data rather than duplicated code:
 *   1. the admin settings page (settings.php loops these),
 *   2. the per-setting "expose to instance" admin checkboxes (expose_<key>),
 *   3. the per-instance edit form (only exposed keys are offered),
 *   4. branding token resolution ({@see branding::resolve()}),
 *   5. tutor import/export (exactly these keys round-trip),
 *   6. the built-in presets and saved tutor profiles.
 *
 * Each entry is a descriptor:
 *   group        — UI grouping (also the order of headings)
 *   type         — colour|cssvalue|font|text|textarea|select|checkbox|file
 *   token        — the `--eat-*` custom property this maps to, or null
 *   default      — site default (empty string ⇒ "use the styles.css default")
 *   options      — value => lang-string key, for selects
 *   instanceable — whether this may ever be overridden per block instance
 *   sendtorag    — whether this value is sent to the RAG server (persona)
 *   sitekey      — the site config key when it differs from the registry key
 *   exposedefault— default value of the expose_<key> admin checkbox
 *
 * The registry key doubles as the per-instance config field name
 * (`config_<key>`); the site config key is the same unless `sitekey` overrides
 * it (used to preserve a couple of legacy admin keys).
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var string Config flag prefix marking a key as instance-overridable. */
    public const EXPOSE_PREFIX = 'expose_';

    /** @var string[] Group ids in display order. */
    private const GROUP_ORDER = [
        'persona', 'accent', 'surfaces', 'text', 'bubbles', 'states',
        'shape', 'effects', 'behaviour', 'launcher', 'footer', 'files',
    ];

    /**
     * The full registry, key => descriptor.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $entries = [];

        // --- Persona / system prompt (sent to the RAG server). --------------
        $entries['persona'] = self::entry('persona', 'text', [
            'sendtorag' => true, 'exposedefault' => true,
        ]);
        foreach (['role', 'tone', 'audience'] as $field) {
            $entries['persona_' . $field] = self::entry('persona', 'text', [
                'sendtorag' => true,
            ]);
        }
        $entries['persona_instructions'] = self::entry('persona', 'textarea', [
            'sendtorag' => true,
        ]);

        // --- Colour + value tokens. Each maps to a single --eat-* property. -
        // [registry key, --eat- token, group, type, exposedefault, sitekey].
        $tokens = [
            // Accent family.
            ['brandaccent', '--eat-accent', 'accent', 'colour', true],
            ['tok_accentdark', '--eat-accent-dark', 'accent', 'colour', false],
            ['tok_accentcontrast', '--eat-accent-contrast', 'accent', 'colour', false],
            ['tok_brand', '--eat-brand', 'accent', 'colour', false],
            ['tok_focusring', '--eat-focus-ring', 'accent', 'cssvalue', false],
            // Surfaces.
            ['brandsurface', '--eat-body-bg', 'surfaces', 'colour', false],
            ['tok_surface', '--eat-surface', 'surfaces', 'colour', false],
            ['tok_line', '--eat-line', 'surfaces', 'colour', false],
            ['tok_tint', '--eat-tint', 'surfaces', 'colour', false],
            ['tok_overlay', '--eat-overlay', 'surfaces', 'cssvalue', false],
            ['tok_overlaystrong', '--eat-overlay-strong', 'surfaces', 'cssvalue', false],
            ['tok_codebg', '--eat-code-bg', 'surfaces', 'cssvalue', false],
            ['tok_backdrop', '--eat-backdrop', 'surfaces', 'cssvalue', false],
            // Text.
            ['tok_ink', '--eat-ink', 'text', 'colour', false],
            ['tok_headerfg', '--eat-header-fg', 'text', 'colour', false],
            ['tok_muted', '--eat-muted', 'text', 'colour', false],
            ['tok_mutedsoft', '--eat-muted-soft', 'text', 'colour', false],
            ['brandfont', '--eat-font', 'text', 'font', false],
            // Bubbles.
            ['brandbubble', '--eat-user-bg', 'bubbles', 'colour', true],
            ['tok_userfg', '--eat-user-fg', 'bubbles', 'colour', false],
            ['brandbotbubble', '--eat-bot-bg', 'bubbles', 'colour', true],
            ['tok_botfg', '--eat-bot-fg', 'bubbles', 'colour', false],
            ['tok_assistantbg', '--eat-assistant-bg', 'bubbles', 'colour', false],
            ['tok_assistantfg', '--eat-assistant-fg', 'bubbles', 'colour', false],
            // States.
            ['tok_statusonline', '--eat-status-online', 'states', 'colour', false],
            ['brandiconhover', '--eat-icon-hover', 'states', 'cssvalue', false],
            ['tok_errorbg', '--eat-error-bg', 'states', 'colour', false],
            ['tok_errorfg', '--eat-error-fg', 'states', 'colour', false],
            ['tok_errorline', '--eat-error-line', 'states', 'colour', false],
            ['tok_groundedbg', '--eat-grounded-bg', 'states', 'colour', false],
            ['tok_groundedline', '--eat-grounded-line', 'states', 'colour', false],
            // Shape & spacing.
            ['tok_radius', '--eat-radius', 'shape', 'cssvalue', false],
            ['tok_bubbleradius', '--eat-bubble-radius', 'shape', 'cssvalue', false],
            ['tok_gap', '--eat-gap', 'shape', 'cssvalue', false],
            ['tok_z', '--eat-z', 'shape', 'cssvalue', false],
            ['tok_fabbottom', '--eat-fab-bottom', 'shape', 'cssvalue', false],
            ['tok_fabright', '--eat-fab-right', 'shape', 'cssvalue', false],
            // Effects.
            ['tok_shadowsm', '--eat-shadow-sm', 'effects', 'cssvalue', false],
            ['tok_shadowmd', '--eat-shadow-md', 'effects', 'cssvalue', false],
            ['tok_shadowlg', '--eat-shadow-lg', 'effects', 'cssvalue', false],
            ['tok_avatarglow', '--eat-avatar-glow', 'effects', 'cssvalue', false],
        ];
        // The 5th tuple element is retained for documentation of the original
        // intent, but every token is now exposed per instance by default (the
        // admin removes exposure where they want a setting kept site-wide).
        foreach ($tokens as [$key, $token, $group, $type]) {
            $entries[$key] = self::entry($group, $type, [
                'token' => $token,
            ]);
        }

        // --- Behaviour / presentation (instanceable). ----------------------
        $entries['welcomemessage'] = self::entry('behaviour', 'textarea', [
            'exposedefault' => true,
        ]);
        $entries['promptstarters'] = self::entry('behaviour', 'textarea', [
            'exposedefault' => true,
        ]);
        $entries['answerstyle'] = self::entry('behaviour', 'select', [
            'default' => 'explain',
            'options' => [
                'explain' => 'answerstyle_explain',
                'hint' => 'answerstyle_hint',
                'quiz' => 'answerstyle_quiz',
            ],
            'exposedefault' => true,
        ]);
        $entries['allowstylechange'] = self::entry('behaviour', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);
        $entries['displaymode'] = self::entry('behaviour', 'select', [
            'default' => 'embedded',
            'sitekey' => 'defaultdisplaymode',
            'options' => [
                'embedded' => 'displaymode_embedded',
                'docked' => 'displaymode_docked',
                'modal' => 'displaymode_modal',
                'fullscreen' => 'displaymode_fullscreen',
            ],
            'exposedefault' => true,
        ]);
        $entries['historyenabled'] = self::entry('behaviour', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);

        // --- Launcher. ------------------------------------------------------
        $entries['launcherstyle'] = self::entry('launcher', 'select', [
            'default' => 'pill',
            'options' => [
                'pill' => 'launcherstyle_pill',
                'solid' => 'launcherstyle_solid',
                'fab' => 'launcherstyle_fab',
            ],
            'exposedefault' => true,
        ]);
        $entries['launchlabel'] = self::entry('launcher', 'text', [
            'sitekey' => 'brandlaunchlabel',
            'exposedefault' => true,
        ]);

        // --- Footer / white-label. Instance-overridable only when the admin
        // opts in (off by default — white-label is usually institution-wide).
        $entries['footermode'] = self::entry('footer', 'select', [
            'default' => branding::FOOTER_DEFAULT,
            'options' => [
                branding::FOOTER_DEFAULT => 'footermode_default',
                branding::FOOTER_CUSTOM => 'footermode_custom',
                branding::FOOTER_NONE => 'footermode_none',
            ],
            'exposedefault' => false,
        ]);
        $entries['footertext'] = self::entry('footer', 'text', [
            'exposedefault' => false,
        ]);

        // --- Images. --------------------------------------------------------
        $entries['logo'] = self::entry('files', 'file', [
            'exposedefault' => true,
        ]);
        $entries['avatar'] = self::entry('files', 'file', [
            'exposedefault' => true,
        ]);

        $cache = $entries;
        return $cache;
    }

    /**
     * Build a descriptor with sensible defaults filled in.
     *
     * @param string $group Group id.
     * @param string $type Field type.
     * @param array<string, mixed> $overrides Any descriptor overrides.
     * @return array<string, mixed>
     */
    private static function entry(string $group, string $type, array $overrides = []): array {
        return $overrides + [
            'group' => $group,
            'type' => $type,
            'token' => null,
            'default' => self::type_default($type),
            'options' => null,
            'instanceable' => true,
            'sendtorag' => false,
            'sitekey' => null,
            // Every optical/persona/behaviour setting is overridable per instance
            // by default; the admin removes exposure where they want site-wide
            // control. Footer is the one opt-in exception (see below).
            'exposedefault' => true,
        ];
    }

    /**
     * The empty/neutral default for a field type.
     *
     * @param string $type Field type.
     * @return mixed
     */
    private static function type_default(string $type): mixed {
        return $type === 'checkbox' ? 0 : '';
    }

    /**
     * A descriptor by key, or null when unknown.
     *
     * @param string $key Registry key.
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array {
        return self::all()[$key] ?? null;
    }

    /**
     * Whether a key exists in the registry.
     *
     * @param string $key Registry key.
     * @return bool
     */
    public static function exists(string $key): bool {
        return isset(self::all()[$key]);
    }

    /**
     * Group ids in display order.
     *
     * @return string[]
     */
    public static function groups(): array {
        return self::GROUP_ORDER;
    }

    /**
     * Registry keys belonging to a group, in registry order.
     *
     * @param string $group Group id.
     * @return string[]
     */
    public static function group_keys(string $group): array {
        $keys = [];
        foreach (self::all() as $key => $entry) {
            if ($entry['group'] === $group) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * All keys that map to a `--eat-*` design token.
     *
     * @return array<string, string> registry key => token name.
     */
    public static function token_keys(): array {
        $out = [];
        foreach (self::all() as $key => $entry) {
            if ($entry['token'] !== null) {
                $out[$key] = $entry['token'];
            }
        }
        return $out;
    }

    /**
     * Keys whose value is sent to the RAG server (persona fields).
     *
     * @return string[]
     */
    public static function persona_keys(): array {
        $out = [];
        foreach (self::all() as $key => $entry) {
            if (!empty($entry['sendtorag'])) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * Keys that may ever be overridden per instance.
     *
     * @return string[]
     */
    public static function instanceable_keys(): array {
        $out = [];
        foreach (self::all() as $key => $entry) {
            if (!empty($entry['instanceable'])) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * The site config key for a registry key (honours the `sitekey` override).
     *
     * @param string $key Registry key.
     * @return string
     */
    public static function sitekey(string $key): string {
        $entry = self::get($key);
        return ($entry && $entry['sitekey']) ? $entry['sitekey'] : $key;
    }

    /**
     * The site default value for a key.
     *
     * @param string $key Registry key.
     * @return mixed
     */
    public static function default_for(string $key): mixed {
        $entry = self::get($key);
        return $entry['default'] ?? '';
    }

    /**
     * Whether the admin has exposed this key for per-instance override.
     *
     * Non-instanceable keys are never exposed. Otherwise the admin checkbox
     * `expose_<key>` governs it, defaulting to the descriptor's exposedefault.
     *
     * @param string $key Registry key.
     * @return bool
     */
    public static function is_exposed(string $key): bool {
        $entry = self::get($key);
        if (!$entry || empty($entry['instanceable'])) {
            return false;
        }
        $flag = get_config(security::CONFIG_COMPONENT, self::EXPOSE_PREFIX . $key);
        if ($flag === false) {
            return (bool) $entry['exposedefault'];
        }
        return (int) $flag === 1;
    }

    /**
     * The effective value of a key: an exposed, set instance override wins;
     * otherwise the site config value; otherwise the registry default.
     *
     * Shared by branding (tokens/persona), the widget (behaviour) and tutor
     * application, so instance-over-site precedence lives in exactly one place.
     *
     * @param string $key Registry key.
     * @param array<string, mixed> $instance Per-instance values keyed by registry key.
     * @return mixed
     */
    public static function effective(string $key, array $instance = []): mixed {
        if (self::is_exposed($key) && array_key_exists($key, $instance)) {
            $value = $instance[$key];
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return security::get_config(self::sitekey($key), self::default_for($key));
    }

    /**
     * Sanitise a raw value for a registry key according to its type.
     *
     * Delegates colour/CSS/font cleaning to {@see branding}; everything that
     * could land in an inline `style="…"` is filtered so it cannot break out of
     * the declaration. Returns null when the value is empty/invalid (caller then
     * stores nothing and the styles.css/site default stands).
     *
     * @param string $key Registry key.
     * @param mixed $value Raw value.
     * @return string|int|null Cleaned value, or null to store nothing.
     */
    public static function sanitise(string $key, mixed $value): string|int|null {
        $entry = self::get($key);
        if (!$entry) {
            return null;
        }
        switch ($entry['type']) {
            case 'colour':
                return branding::sanitise_colour((string) $value);
            case 'cssvalue':
                $clean = branding::sanitise_css_value((string) $value);
                return $clean === '' ? null : $clean;
            case 'font':
                $clean = branding::sanitise_font((string) $value);
                return $clean === '' ? null : $clean;
            case 'checkbox':
                return (int) ((int) $value === 1);
            case 'select':
                $value = (string) $value;
                return isset($entry['options'][$value]) ? $value : null;
            case 'text':
                $clean = trim((string) $value);
                return $clean === '' ? null : $clean;
            case 'textarea':
                $clean = trim((string) $value);
                return $clean === '' ? null : $clean;
            default:
                // 'file' and anything else are not plain config values.
                return null;
        }
    }
}
