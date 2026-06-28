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

namespace block_eledia_aitutor;

use block_eledia_aitutor\local\branding;
use block_eledia_aitutor\local\presets;
use block_eledia_aitutor\local\registry;

/**
 * Unit tests for registry-driven branding + persona resolution.
 *
 * @package     block_eledia_aitutor
 * @covers      \block_eledia_aitutor\local\branding
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class branding_test extends \advanced_testcase {
    /**
     * With nothing configured, no token overrides are emitted (defaults stand)
     * and the footer is the eLeDia credit.
     */
    public function test_defaults_emit_no_overrides(): void {
        $this->resetAfterTest();

        $brand = branding::resolve([]);
        $this->assertSame('', $brand['accent']);
        $this->assertSame('', $brand['bubble']);
        $this->assertSame('', branding::css_variables($brand));
        $this->assertSame(branding::FOOTER_DEFAULT, $brand['footermode']);
        $this->assertSame(get_string('poweredby', 'block_eledia_aitutor'), $brand['footertext']);
    }

    /**
     * A per-instance accent overrides the site accent; the site value applies
     * where the instance is empty. The accent drives a darkened hover token.
     */
    public function test_instance_overrides_site(): void {
        $this->resetAfterTest();
        set_config('brandaccent', '#112233', 'block_eledia_aitutor');
        set_config('brandbubble', '#445566', 'block_eledia_aitutor');

        // No instance override → site values.
        $brand = branding::resolve([]);
        $this->assertSame('#112233', $brand['accent']);
        $this->assertSame('#445566', $brand['bubble']);

        // Instance accent wins (it is exposed by default); bubble still from site.
        $brand = branding::resolve(['brandaccent' => '#aabbcc']);
        $this->assertSame('#aabbcc', $brand['accent']);
        $this->assertSame('#445566', $brand['bubble']);

        $css = branding::css_variables($brand);
        $this->assertStringContainsString('--eat-accent:#aabbcc;', $css);
        $this->assertStringContainsString('--eat-user-bg:#445566;', $css);
        $this->assertStringContainsString('--eat-accent-dark:#', $css);
    }

    /**
     * Invalid colours and unsafe CSS/font values are rejected, never injected.
     */
    public function test_sanitisation(): void {
        $this->resetAfterTest();

        // A CSS-injection attempt in a colour is dropped (not a valid hex).
        $brand = branding::resolve(['brandaccent' => 'red;}#x{color:red']);
        $this->assertSame('', $brand['accent']);
        $this->assertSame('', branding::css_variables($brand));

        $this->assertNull(branding::sanitise_colour('blue'));
        $this->assertSame('#1e3f59', branding::sanitise_colour('#1e3f59'));

        // The free-text token sanitiser allows safe values but blocks break-outs.
        $this->assertSame('rgba(0, 0, 0, 0.5)', branding::sanitise_css_value('rgba(0, 0, 0, 0.5)'));
        $this->assertSame('18px', branding::sanitise_css_value('18px'));
        $this->assertSame('', branding::sanitise_css_value('red; } body{display:none'));
        $this->assertSame('', branding::sanitise_css_value('url(http://evil/x.png)'));
        $this->assertSame('', branding::sanitise_css_value('</style><script>'));

        $this->assertSame('Inter, sans-serif', branding::sanitise_font('Inter, sans-serif }'));
        $this->assertStringNotContainsString('{', branding::sanitise_font('a{}<b>'));
    }

    /**
     * Applying a built-in preset's settings (as an instance would receive on a
     * snapshot) yields the preset's palette; HAL is a dark red look.
     */
    public function test_preset_palette(): void {
        $this->resetAfterTest();

        // HAL preset, written into the site config (as tutor_apply would).
        foreach (presets::settings('hal') as $key => $value) {
            set_config(registry::sitekey($key), $value, 'block_eledia_aitutor');
        }
        $css = branding::css_variables(branding::resolve([]));
        $this->assertStringContainsString('--eat-accent:#ff1a1a;', $css);
        $this->assertStringContainsString('--eat-body-bg:#0c0c0e;', $css);
        $this->assertStringContainsString('--eat-avatar-glow:', $css);   // The glowing eye.
    }

    /**
     * Every non-default preset defines a complete palette (the required tokens),
     * so no component falls back to a clashing default on any preset.
     */
    public function test_presets_define_required_tokens(): void {
        $required = ['--eat-accent', '--eat-ink', '--eat-surface', '--eat-body-bg',
            '--eat-bot-bg', '--eat-user-bg', '--eat-line'];
        $tokentokey = array_flip(registry::token_keys());
        foreach (presets::all() as $id => $preset) {
            if ($id === presets::DEFAULT) {
                continue;
            }
            $settings = presets::settings($id);
            foreach ($required as $token) {
                $key = $tokentokey[$token] ?? null;
                $this->assertNotNull($key, "No registry key for {$token}");
                $this->assertArrayHasKey(
                    $key,
                    $settings,
                    "Preset '{$id}' is missing {$token}"
                );
            }
        }
    }

    /**
     * The tutor (assistant) bubble colour is settable, instance over site.
     */
    public function test_bot_bubble_colour(): void {
        $this->resetAfterTest();
        set_config('brandbotbubble', '#101820', 'block_eledia_aitutor');
        $this->assertStringContainsString(
            '--eat-bot-bg:#101820;',
            branding::css_variables(branding::resolve([]))
        );
        $this->assertStringContainsString(
            '--eat-bot-bg:#abcdef;',
            branding::css_variables(branding::resolve(['brandbotbubble' => '#abcdef']))
        );
    }

    /**
     * The launcher style resolves instance-over-site; an empty instance value
     * follows the site, and an invalid one falls back to the safe default.
     */
    public function test_launcher_style(): void {
        $this->resetAfterTest();
        $this->assertSame('pill', branding::resolve([])['launcherstyle']);

        set_config('launcherstyle', 'fab', 'block_eledia_aitutor');
        $this->assertSame('fab', branding::resolve([])['launcherstyle']);
        // Empty instance value follows the site setting.
        $this->assertSame('fab', branding::resolve(['launcherstyle' => ''])['launcherstyle']);
        // A valid instance value wins.
        $this->assertSame('solid', branding::resolve(['launcherstyle' => 'solid'])['launcherstyle']);
        // A tampered/invalid value falls back to the safe default.
        $this->assertSame('pill', branding::resolve(['launcherstyle' => 'bogus'])['launcherstyle']);
    }

    /**
     * Footer customisation is ignored in the free block.
     */
    public function test_footer_modes_require_premium(): void {
        $this->resetAfterTest();

        set_config('footermode', branding::FOOTER_CUSTOM, 'block_eledia_aitutor');
        set_config('footertext', 'A University', 'block_eledia_aitutor');
        $this->assertSame(branding::FOOTER_DEFAULT, branding::resolve([])['footermode']);
        $this->assertSame(get_string('poweredby', 'block_eledia_aitutor'), branding::resolve([])['footertext']);

        set_config('footermode', branding::FOOTER_NONE, 'block_eledia_aitutor');
        $this->assertSame(get_string('poweredby', 'block_eledia_aitutor'), branding::resolve([])['footertext']);
    }

    /**
     * Footer text is instance-overridable only when the admin exposes it.
     */
    public function test_footer_instance_override(): void {
        $this->resetAfterTest();
        set_config('footermode', branding::FOOTER_CUSTOM, 'block_eledia_aitutor');
        set_config('footertext', 'Site footer', 'block_eledia_aitutor');

        // Not exposed by default → instance value ignored.
        $this->assertSame(
            get_string('poweredby', 'block_eledia_aitutor'),
            branding::resolve(['footertext' => 'Course footer'])['footertext']
        );

        // Admin opt-in is still ignored without the premium add-on.
        set_config('expose_footertext', 1, 'block_eledia_aitutor');
        $this->assertSame(
            get_string('poweredby', 'block_eledia_aitutor'),
            branding::resolve(['footertext' => 'Course footer'])['footertext']
        );
    }

    /**
     * The structured persona is assembled instance-over-site, only populated
     * sub-fields, with the name mapped from the 'persona' key.
     */
    public function test_persona_assembly(): void {
        $this->resetAfterTest();
        set_config('persona', 'Site Tutor', 'block_eledia_aitutor');
        set_config('persona_tone', 'friendly', 'block_eledia_aitutor');

        $persona = branding::persona([]);
        $this->assertSame('Site Tutor', $persona['name']);
        $this->assertSame('friendly', $persona['tone']);
        $this->assertArrayNotHasKey('role', $persona);

        // Instance overrides the name (persona is exposed by default).
        $persona = branding::persona(['persona' => 'Course Tutor']);
        $this->assertSame('Course Tutor', $persona['name']);
    }
}
