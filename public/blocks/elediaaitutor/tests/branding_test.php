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
