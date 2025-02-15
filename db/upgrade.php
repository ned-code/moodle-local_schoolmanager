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
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use local_schoolmanager\shared_lib as NED;

require_once(__DIR__ . '/upgradelib.php');

function xmldb_local_schoolmanager_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024073000){
        local_schoolmanager_moodle3_upgrades($oldversion);
    }

    if ($oldversion < 2024100400) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('cohortname', XMLDB_TYPE_CHAR, '255', null, null, null, NULL, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            /** @noinspection SqlWithoutWhere */
            $DB->execute('UPDATE {local_schoolmanager_school} SET `cohortname` = `name`');
        }

        NED::upgrade_plugin_savepoint(2024100400);
    }

    if ($oldversion < 2024121100) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('deadlinesdata', XMLDB_TYPE_TEXT, null,null, null, null, null, 'forceproxysubmissionwindow');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        NED::upgrade_plugin_savepoint(2024121100);
    }

    if ($oldversion < 2024121600) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('region', XMLDB_TYPE_CHAR, '120', null, null, null, null, 'country');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        NED::upgrade_plugin_savepoint(2024121600);
    }

    if ($oldversion < 2024121900) {
        $DB->execute("UPDATE {local_schoolmanager_school} SET region = 'CN' WHERE region = 'China'");
        NED::upgrade_plugin_savepoint(2024121900);
    }

    if ($oldversion < 2025011301) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('schoolgroup', XMLDB_TYPE_CHAR, '120', null, null, null, null, 'region');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->execute("UPDATE {local_schoolmanager_school} SET schoolgroup = 'None'");

        NED::upgrade_plugin_savepoint(2025011301);
    }

    if ($oldversion < 2025012801) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('videosubmissionrequired', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'enabletem');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        NED::upgrade_plugin_savepoint(2025012801);
    }

    if ($oldversion < 2025021500) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('showipchange', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'iptype');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        NED::upgrade_plugin_savepoint(2025021500);
    }

    return true;
}
