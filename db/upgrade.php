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
 * Upgrade steps.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_theme_lmslabs_upgrade($oldversion) {
    if ($oldversion < 2026110101) {
        \theme_lmslabs\local\siteowner::ensure_role();
        upgrade_plugin_savepoint(true, 2026110101, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110102) {
        \theme_lmslabs\local\siteowner::ensure_role();
        upgrade_plugin_savepoint(true, 2026110102, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110103) {
        global $DB;
        $dbman = $DB->get_manager();

        $table = new xmldb_table('theme_lmslabs_pgroup');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('theme_lmslabs_pcourse');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('hidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('delay', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('requireprev', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        \theme_lmslabs\local\siteowner::ensure_role();
        upgrade_plugin_savepoint(true, 2026110103, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110104) {
        global $DB;
        $dbman = $DB->get_manager();

        $table = new xmldb_table('theme_lmslabs_program');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('audience', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Headings belong to a program.
        $table = new xmldb_table('theme_lmslabs_pgroup');
        $field = new xmldb_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('programid', XMLDB_INDEX_NOTUNIQUE, ['programid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // A course can sit in several programs.
        $table = new xmldb_table('theme_lmslabs_pcourse');
        $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        $dbman->drop_key($table, $key);
        $field = new xmldb_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $dbman->add_key($table, $key);
        $index = new xmldb_index('programcourse', XMLDB_INDEX_UNIQUE, ['programid', 'courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Anything set up already becomes one program for everyone.
        if ($DB->record_exists('theme_lmslabs_pcourse', [])) {
            $pid = $DB->insert_record(
                'theme_lmslabs_program', (object)[
                'name' => get_string('mycourses'), 'description' => '',
                'audience' => json_encode(['everyone' => true]), 'sortorder' => 1, 'timemodified' => time(),
            ]
            );
            $DB->set_field('theme_lmslabs_pcourse', 'programid', $pid);
            $DB->set_field('theme_lmslabs_pgroup', 'programid', $pid);
        }
        upgrade_plugin_savepoint(true, 2026110104, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110108) {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new xmldb_table('theme_lmslabs_view');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('page', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('params', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('isdefault', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userpage', XMLDB_INDEX_NOTUNIQUE, ['userid', 'page']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110108, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110109) {
        // Site Owners may open the Speed Up tool (read-only) when it is installed.
        \theme_lmslabs\local\siteowner::ensure_role();
        upgrade_plugin_savepoint(true, 2026110109, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110300) {
        // Every course uses the AI course format with its player course index.
        \theme_lmslabs\local\aiformat::apply();
        upgrade_plugin_savepoint(true, 2026110300, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110400) {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new xmldb_table('theme_lmslabs_tenant');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('color', XMLDB_TYPE_CHAR, '7', null, null, null, null);
        $table->add_field('sitename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('logo', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idnumber', XMLDB_INDEX_UNIQUE, ['idnumber']);
        $table->add_index('cohortid', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110400, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026110600) {
        // Tenants and branding moved to the LMS Labs Cohort branding plugin.
        global $DB;
        $dbman = $DB->get_manager();
        $table = new xmldb_table('theme_lmslabs_tenant');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $fs = get_file_storage();
        $fs->delete_area_files(context_system::instance()->id, 'theme_lmslabs', 'tenantlogo');
        upgrade_plugin_savepoint(true, 2026110600, 'theme', 'lmslabs');
    }
    if ($oldversion < 2026111300) {
        // Site Owners may set the default top bar shortcuts for teachers and students.
        \theme_lmslabs\local\siteowner::ensure_role();
        upgrade_plugin_savepoint(true, 2026111300, 'theme', 'lmslabs');
    }
    return true;
}
