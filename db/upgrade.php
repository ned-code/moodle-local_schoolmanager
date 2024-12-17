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

    if ($oldversion < 2024112200) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('deadlinesdata', XMLDB_TYPE_TEXT, null,null, null, null, null, 'forceproxysubmissionwindow');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        NED::upgrade_plugin_savepoint(2024112200);
    }

    if ($oldversion < 2024121600) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('region', XMLDB_TYPE_CHAR, '120', null, null, null, null, 'country');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        NED::upgrade_plugin_savepoint(2024121600);
    }

    return true;
}
