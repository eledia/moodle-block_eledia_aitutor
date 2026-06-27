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
use block_elediaaitutor\local\registry;
use block_elediaaitutor\local\tutor_profile;

/**
 * Unit tests for the setting registry.
 *
 * @package     block_elediaaitutor
 * @covers      \block_elediaaitutor\local\registry
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry_test extends \advanced_testcase {
    /**
     * Every descriptor is well-formed: known type and group, tokens look like
     * `--eat-*`, and selects carry options.
     */
    public function test_descriptors_wellformed(): void {
        $types = ['colour', 'cssvalue', 'font', 'text', 'textarea', 'select', 'checkbox', 'file'];
        $groups = registry::groups();
        foreach (registry::all() as $key => $entry) {
            $this->assertContains($entry['type'], $types, "Bad type for {$key}");
            $this->assertContains($entry['group'], $groups, "Bad group for {$key}");
            if ($entry['token'] !== null) {
                $this->assertStringStartsWith('--eat-', $entry['token'], "Bad token for {$key}");
            }
            if ($entry['type'] === 'select') {
                $this->assertNotEmpty($entry['options'], "Select {$key} has no options");
            }
        }
    }

    /**
     * Token keys map to distinct `--eat-*` properties.
     */
    public function test_token_keys_distinct(): void {
        $tokens = array_values(registry::token_keys());
        $this->assertSame(
            count($tokens),
            count(array_unique($tokens)),
            'Two registry keys map to the same --eat-* token'
        );
        $this->assertGreaterThan(30, count($tokens), 'Expected the full token set');
    }

    /**
     * The persona keys are exactly the sendtorag set, and the name maps cleanly.
     */
    public function test_persona_keys(): void {
        $this->assertContains('persona', registry::persona_keys());
        $this->assertContains('persona_instructions', registry::persona_keys());
        $this->assertNotContains('brandaccent', registry::persona_keys());
    }

    /**
     * The two legacy site keys keep their historical config names.
     */
    public function test_sitekey_overrides(): void {
        $this->assertSame('defaultdisplaymode', registry::sitekey('displaymode'));
        $this->assertSame('brandlaunchlabel', registry::sitekey('launchlabel'));
        $this->assertSame('brandaccent', registry::sitekey('brandaccent'));
    }

    /**
     * Every optical/persona setting is exposed by default; footer is premium-only
     * and unavailable in the free block.
     */
    public function test_is_exposed(): void {
        $this->resetAfterTest();

        // Optical tokens (incl. text/surface) are now overridable by default.
        $this->assertTrue(registry::is_exposed('brandaccent'));
        $this->assertTrue(registry::is_exposed('tok_surface'));
        $this->assertTrue(registry::is_exposed('tok_ink'));
        // Footer is instanceable in the registry but locked without premium.
        $this->assertTrue(registry::get('footermode')['instanceable']);
        $this->assertFalse(registry::is_available('footermode'));
        $this->assertFalse(registry::is_exposed('footermode'));

        // Admin checkboxes still work for available keys, but cannot unlock premium keys.
        set_config('expose_tok_surface', 0, 'block_elediaaitutor');
        set_config('expose_footermode', 1, 'block_elediaaitutor');
        $this->assertFalse(registry::is_exposed('tok_surface'));
        $this->assertFalse(registry::is_exposed('footermode'));
    }

    /**
     * effective() resolves an exposed instance value over the site, and falls
     * back to the site (then the default) otherwise.
     */
    public function test_effective_precedence(): void {
        $this->resetAfterTest();
        set_config('brandaccent', '#111111', 'block_elediaaitutor');

        $this->assertSame('#111111', registry::effective('brandaccent', []));
        $this->assertSame('#222222', registry::effective('brandaccent', ['brandaccent' => '#222222']));

        // When the key is not exposed, the instance value is ignored.
        set_config('expose_brandaccent', 0, 'block_elediaaitutor');
        $this->assertSame('#111111', registry::effective('brandaccent', ['brandaccent' => '#222222']));
    }

    /**
     * Every non-colour token offers friendly named options (so the forms render
     * a dropdown rather than a raw-CSS text box), and each option value survives
     * the token's own sanitiser. Colour tokens stay free pickers (no choices).
     */
    public function test_non_colour_tokens_have_choices(): void {
        foreach (registry::all() as $key => $entry) {
            if ($entry['token'] === null) {
                continue; // Not a design token.
            }
            if ($entry['type'] === 'colour') {
                $this->assertNull($entry['choices'], "Colour token {$key} should not have choices");
                continue;
            }
            // Cssvalue / font tokens must have choices keyed by valid CSS values.
            $this->assertNotEmpty($entry['choices'], "Token {$key} is missing friendly choices");
            $this->assertArrayHasKey('', $entry['choices'], "Token {$key} needs a default option");
            foreach ($entry['choices'] as $value => $langkey) {
                if ((string) $value === '') {
                    continue;
                }
                $this->assertNotNull(
                    registry::sanitise($key, $value),
                    "Choice '{$value}' for {$key} is rejected by its sanitiser"
                );
            }
        }
    }

    /**
     * sanitise() cleans by type and rejects the unsafe.
     */
    public function test_sanitise(): void {
        $this->assertSame('#abc', registry::sanitise('brandaccent', '#abc'));
        $this->assertNull(registry::sanitise('brandaccent', 'notacolour'));
        $this->assertSame('explain', registry::sanitise('answerstyle', 'explain'));
        $this->assertNull(registry::sanitise('answerstyle', 'bogus'));
        $this->assertSame(1, registry::sanitise('historyenabled', '1'));
        $this->assertSame(0, registry::sanitise('historyenabled', '0'));
        $this->assertNull(registry::sanitise('unknown_key', 'x'));
    }

    /**
     * Tutor profile settings drop premium-only footer values in the free block.
     */
    public function test_clean_settings_drops_locked_footer_values(): void {
        $settings = tutor_profile::clean_settings([
            'brandaccent' => '#abcdef',
            'footermode' => branding::FOOTER_NONE,
            'footertext' => 'Hidden credit',
        ]);

        $this->assertSame('#abcdef', $settings['brandaccent']);
        $this->assertArrayNotHasKey('footermode', $settings);
        $this->assertArrayNotHasKey('footertext', $settings);
    }
}
