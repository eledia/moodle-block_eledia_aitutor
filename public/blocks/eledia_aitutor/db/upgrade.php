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
 * Upgrade steps for the eLeDia.ai Tutor block.
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute upgrade steps between versions.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_block_eledia_aitutor_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061102) {
        // Question analytics log (opt-in; see classes/local/question_log.php).
        $table = new xmldb_table('block_eledia_aitutor_qlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('question', XMLDB_TYPE_CHAR, '1000', null, XMLDB_NOTNULL);
        $table->add_field('grounded', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('answerstyle', XMLDB_TYPE_CHAR, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('courseid-timecreated', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026061102, 'eledia_aitutor');
    }

    if ($oldversion < 2026061103) {
        // Analytics clustering anchors: server-supplied topic label plus the
        // primary cited source (title + resolved cmid).
        $table = new xmldb_table('block_eledia_aitutor_qlog');
        $fields = [
            new xmldb_field('topic', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'answerstyle'),
            new xmldb_field('sourcetitle', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'topic'),
            new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sourcetitle'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_block_savepoint(true, 2026061103, 'eledia_aitutor');
    }

    if ($oldversion < 2026061106) {
        // Documented first-use privacy consent (see classes/local/consent.php).
        $table = new xmldb_table('block_eledia_aitutor_consent');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026061106, 'eledia_aitutor');
    }

    if ($oldversion < 2026061114) {
        // Per-user daily message counters (see classes/local/usage.php).
        $table = new xmldb_table('block_eledia_aitutor_usage');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('daykey', XMLDB_TYPE_INTEGER, '8', null, XMLDB_NOTNULL);
        $table->add_field('messagecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userid-daykey', XMLDB_INDEX_UNIQUE, ['userid', 'daykey']);
        $table->add_index('daykey', XMLDB_INDEX_NOTUNIQUE, ['daykey']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026061114, 'eledia_aitutor');
    }

    if ($oldversion < 2026061320) {
        // Saved site-wide tutor profiles (see classes/local/tutor_profile.php).
        $table = new xmldb_table('block_eledia_aitutor_tutor');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT);
        $table->add_field('settings', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
        $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Themes are replaced by tutor profiles. Preserve any existing look by
        // snapshotting the old theme's palette into the individual token
        // settings (site + each instance), then dropping the obsolete 'theme'.
        $sitetheme = (string) get_config('block_eledia_aitutor', 'theme');
        if (
            $sitetheme !== '' && \block_eledia_aitutor\local\presets::exists($sitetheme)
                && $sitetheme !== \block_eledia_aitutor\local\presets::DEFAULT
        ) {
            foreach (\block_eledia_aitutor\local\presets::settings($sitetheme) as $key => $value) {
                $cfgkey = \block_eledia_aitutor\local\registry::sitekey($key);
                if ((string) get_config('block_eledia_aitutor', $cfgkey) === '') {
                    set_config($cfgkey, $value, 'block_eledia_aitutor');
                }
            }
        }
        unset_config('theme', 'block_eledia_aitutor');

        // Per-instance theme overrides → explicit token overrides in the config.
        $instances = $DB->get_records('block_instances', ['blockname' => 'eledia_aitutor']);
        foreach ($instances as $bi) {
            if (empty($bi->configdata)) {
                continue;
            }
            $config = unserialize_object(base64_decode($bi->configdata));
            if (!is_object($config) || empty($config->theme)) {
                continue;
            }
            $theme = (string) $config->theme;
            if (
                \block_eledia_aitutor\local\presets::exists($theme)
                    && $theme !== \block_eledia_aitutor\local\presets::DEFAULT
            ) {
                foreach (\block_eledia_aitutor\local\presets::settings($theme) as $key => $value) {
                    if (!isset($config->$key) || $config->$key === '') {
                        $config->$key = $value;
                    }
                }
            }
            unset($config->theme);
            $DB->set_field(
                'block_instances',
                'configdata',
                base64_encode(serialize($config)),
                ['id' => $bi->id]
            );
        }

        upgrade_block_savepoint(true, 2026061320, 'eledia_aitutor');
    }

    if ($oldversion < 2026061321) {
        // Every optical/persona setting is now overridable per instance by
        // default. The 0.13.0 install persisted the old (mostly off) expose_*
        // defaults to config, so re-sync each instanceable key's expose flag to
        // its new default. Safe one-off: 0.13.0 was never released, so no admin
        // could have chosen these yet.
        foreach (\block_eledia_aitutor\local\registry::all() as $key => $entry) {
            if (empty($entry['instanceable'])) {
                continue;
            }
            set_config(
                \block_eledia_aitutor\local\registry::EXPOSE_PREFIX . $key,
                !empty($entry['exposedefault']) ? 1 : 0,
                'block_eledia_aitutor'
            );
        }

        upgrade_block_savepoint(true, 2026061321, 'eledia_aitutor');
    }

    if ($oldversion < 2026061330) {
        // The default presentation is now the docked floating panel; migrate the
        // old 'embedded' default (the new default reaches fresh installs via
        // settings.php). An admin who deliberately chose another mode is left be.
        if ((string) get_config('block_eledia_aitutor', 'defaultdisplaymode') === 'embedded') {
            set_config('defaultdisplaymode', 'docked', 'block_eledia_aitutor');
        }

        upgrade_block_savepoint(true, 2026061330, 'eledia_aitutor');
    }

    if ($oldversion < 2026061602) {
        // MCP is now optional. Preserve existing installations that had already
        // selected a Moodle-MCP service by enabling the new switch for them.
        if ((int) get_config('block_eledia_aitutor', 'mcpserviceid') > 0) {
            set_config('enablemcp', 1, 'block_eledia_aitutor');
        }

        upgrade_block_savepoint(true, 2026061602, 'eledia_aitutor');
    }

    if ($oldversion < 2026062901) {
        // Lightweight admin diagnostics for failed tutor calls.
        $table = new xmldb_table('block_eledia_aitutor_diag');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('phase', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('detail', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('userid-timecreated', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026062901, 'eledia_aitutor');
    }

    return true;
}
