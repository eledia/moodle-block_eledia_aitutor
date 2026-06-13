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

namespace block_elediaaitutor;

use block_elediaaitutor\local\branding;
use block_elediaaitutor\local\themes;

/**
 * Unit tests for institutional branding resolution.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\branding
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class branding_test extends \advanced_testcase {
    /**
     * With nothing configured, no colour overrides are emitted (defaults stand)
     * and the footer is the eLeDia credit.
     */
    public function test_defaults_emit_no_overrides(): void {
        $this->resetAfterTest();

        $brand = branding::resolve([]);
        $this->assertSame('', $brand['accent']);
        $this->assertSame('', $brand['bubble']);
        $this->assertSame('', branding::css_variables($brand));
        $this->assertSame(branding::FOOTER_DEFAULT, $brand['footermode']);
        $this->assertSame(get_string('poweredby', 'block_elediaaitutor'), $brand['footertext']);
    }

    /**
     * A per-instance accent overrides the site accent; the site value applies
     * where the instance is empty.
     */
    public function test_instance_overrides_site(): void {
        $this->resetAfterTest();
        set_config('brandaccent', '#112233', 'block_elediaaitutor');
        set_config('brandbubble', '#445566', 'block_elediaaitutor');

        // No instance override → site values.
        $brand = branding::resolve([]);
        $this->assertSame('#112233', $brand['accent']);
        $this->assertSame('#445566', $brand['bubble']);

        // Instance accent wins; bubble still from site.
        $brand = branding::resolve(['brandaccent' => '#aabbcc']);
        $this->assertSame('#aabbcc', $brand['accent']);
        $this->assertSame('#445566', $brand['bubble']);

        $css = branding::css_variables($brand);
        $this->assertStringContainsString('--eat-accent:#aabbcc;', $css);
        $this->assertStringContainsString('--eat-user-bg:#445566;', $css);
        // Accent also drives a darkened hover token.
        $this->assertStringContainsString('--eat-accent-dark:#', $css);
    }

    /**
     * Invalid colours and unsafe font/CSS values are rejected, never injected.
     */
    public function test_sanitisation(): void {
        $this->resetAfterTest();

        // A CSS-injection attempt in the colour is dropped (not a valid hex).
        $brand = branding::resolve(['brandaccent' => 'red;}#x{color:red']);
        $this->assertSame('', $brand['accent']);
        $this->assertSame('', branding::css_variables($brand));

        $this->assertNull(branding::sanitise_colour('blue'));
        $this->assertSame('#1e3f59', branding::sanitise_colour('#1e3f59'));
        // Font keeps only safe characters.
        $this->assertSame('Inter, sans-serif',
            branding::sanitise_font('Inter, sans-serif }')); // brace stripped.
        $this->assertStringNotContainsString('{', branding::sanitise_font('a{}<b>'));
    }

    /**
     * A theme supplies a full base palette; an explicit accent layers on top
     * without clobbering the theme's (light) text colour.
     */
    public function test_theme_base_and_override_layering(): void {
        $this->resetAfterTest();

        // HAL: dark palette with a red accent and the glowing-eye avatar.
        set_config('theme', 'hal', 'block_elediaaitutor');
        $css = branding::css_variables(branding::resolve([]));
        $this->assertStringContainsString('--eat-accent:#ff1a1a;', $css);
        $this->assertStringContainsString('--eat-body-bg:#0c0c0e;', $css);
        $this->assertStringContainsString('--eat-ink:#e8e8ea;', $css);   // light text
        $this->assertStringContainsString('--eat-avatar-glow:', $css);   // the eye

        // An explicit accent overrides the theme's accent but the theme's light
        // ink survives (accent must NOT be mapped onto --eat-ink when themed).
        $brand = branding::resolve(['brandaccent' => '#00ddff']);
        $css = branding::css_variables($brand);
        $this->assertStringContainsString('--eat-accent:#00ddff;', $css);
        $this->assertStringContainsString('--eat-ink:#e8e8ea;', $css);

        // A per-instance theme overrides the site theme.
        $brand = branding::resolve(['theme' => themes::DEFAULT]);
        $this->assertSame(themes::DEFAULT, $brand['theme']);
        $this->assertSame('', branding::css_variables($brand));
    }

    /**
     * Every non-default theme defines the full required token set, so no
     * component falls back to a clashing/illegible default on any theme.
     */
    public function test_all_themes_define_required_tokens(): void {
        $required = themes::required_tokens();
        foreach (themes::all() as $id => $theme) {
            if ($id === themes::DEFAULT) {
                continue;
            }
            $missing = array_diff($required, array_keys($theme['tokens']));
            $this->assertSame([], $missing,
                "Theme '{$id}' is missing tokens: " . implode(', ', $missing));
        }
    }

    /**
     * The tutor (assistant) bubble colour is settable, instance over site.
     */
    public function test_bot_bubble_colour(): void {
        $this->resetAfterTest();
        set_config('brandbotbubble', '#101820', 'block_elediaaitutor');

        $css = branding::css_variables(branding::resolve([]));
        $this->assertStringContainsString('--eat-bot-bg:#101820;', $css);

        $css = branding::css_variables(branding::resolve(['brandbotbubble' => '#abcdef']));
        $this->assertStringContainsString('--eat-bot-bg:#abcdef;', $css);
    }

    /**
     * Launcher style resolves instance-over-site with a safe fallback to pill;
     * the removed 'fab' value is rejected.
     */
    public function test_launcher_style(): void {
        $this->resetAfterTest();
        $this->assertSame('pill', branding::resolve([])['launcherstyle']);

        set_config('launcherstyle', 'solid', 'block_elediaaitutor');
        $this->assertSame('solid', branding::resolve([])['launcherstyle']);
        // Instance override wins; a removed/bogus value falls back to the site setting.
        $this->assertSame('pill', branding::resolve(['launcherstyle' => 'pill'])['launcherstyle']);
        $this->assertSame('solid', branding::resolve(['launcherstyle' => 'fab'])['launcherstyle']);
    }

    /**
     * The navbar launcher toggle is read from the site setting.
     */
    public function test_navbar_launcher_setting(): void {
        $this->resetAfterTest();
        $this->assertFalse(\block_elediaaitutor\local\security::navbar_launcher_enabled());
        set_config('navbarlauncher', 1, 'block_elediaaitutor');
        $this->assertTrue(\block_elediaaitutor\local\security::navbar_launcher_enabled());
    }

    /**
     * Footer modes: custom text, and white-label removal.
     */
    public function test_footer_modes(): void {
        $this->resetAfterTest();

        set_config('footermode', branding::FOOTER_CUSTOM, 'block_elediaaitutor');
        set_config('footertext', 'A University', 'block_elediaaitutor');
        $this->assertSame('A University', branding::resolve([])['footertext']);

        set_config('footermode', branding::FOOTER_NONE, 'block_elediaaitutor');
        $this->assertSame('', branding::resolve([])['footertext']);
    }
}
