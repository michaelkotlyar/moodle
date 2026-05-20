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
 * Upgrade script for plugin.
 *
 * @package    quizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot  . '/mod/quiz/accessrule/seb/lib.php');

/**
 * Function to upgrade quizaccess_seb plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Result.
 */
function xmldb_quizaccess_seb_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.5.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2024121801) {

        // Define field allowcapturecamera to be added to quizaccess_seb_quizsettings.
        $table = new xmldb_table('quizaccess_seb_quizsettings');
        $field = new xmldb_field('allowcapturecamera', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'muteonstartup');

        // Conditionally launch add field allowcapturecamera.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field allowcapturemicrophone to be added to quizaccess_seb_quizsettings.
        $field = new xmldb_field('allowcapturemicrophone', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'allowcapturecamera');

        // Conditionally launch add field allowcapturemicrophone.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Seb savepoint reached.
        upgrade_plugin_savepoint(true, 2024121801, 'quizaccess', 'seb');
    }

    // Automatically generated Moodle v5.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v5.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v5.2.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2026042201) {
        // Define table quizaccess_seb_quizsettings to be updated.
        $table = new xmldb_table('quizaccess_seb_quizsettings');

        // Adding fields to table quizaccess_seb_quizsettings.
        $field = new xmldb_field('overrideid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'cmid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('overrideenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'overrideid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The quizid key can be no longer unique as there can be records relating to the same quiz but represent
        // SEB settings for a quiz override. Therefore we must swap the unique quizid key for a non-unique key.
        $quizidkeyforeignunique = new xmldb_key('quizid', XMLDB_KEY_FOREIGN_UNIQUE, ['quizid'], 'quiz', ['id']);
        $quizidkeyforeign = new xmldb_key('quizid', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);

        $dbman->drop_key($table, $quizidkeyforeignunique);
        $dbman->add_key($table, $quizidkeyforeign);

        // ... The same goes for the cmid key - it can no longer be unique.
        $cmididkeyforeignunique = new xmldb_key('cmid', XMLDB_KEY_FOREIGN_UNIQUE, ['cmid'], 'course_modules', ['id']);
        $cmididkeyforeign = new xmldb_key('cmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);

        $dbman->drop_key($table, $cmididkeyforeignunique);
        $dbman->add_key($table, $cmididkeyforeign);

        // Define key overrideid (foreign) to be added to quizaccess_seb_quizsettings.
        $overrideidkey = new xmldb_key('overrideid', XMLDB_KEY_FOREIGN, ['overrideid'], 'quiz_overrides', ['id']);

        $dbman->add_key($table, $overrideidkey);

        // Define index quizoverride (unique) to be added to quizaccess_seb_quizsettings.
        $index = new xmldb_index('quizoverride', XMLDB_INDEX_UNIQUE, ['quizid', 'overrideid']);

        // Conditionally launch add index quizoverride.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_plugin_savepoint(true, 2026042201, 'quizaccess', 'seb');
    }

    return true;
}
