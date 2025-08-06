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

function xmldb_local_schoolmanager_uninstall(){

    global $DB;

    $dbman = $DB->get_manager();

    // subcohort members
    $table = new xmldb_table('cohort_members');
    $field = new xmldb_field('crewid');
    if ($dbman->field_exists($table, $field)){
        $key = new xmldb_key('crewid', XMLDB_KEY_FOREIGN, ['crewid'], 'local_schoolmanager_crew', ['id']);
        $dbman->drop_key($table, $key);
        $dbman->drop_field($table, $field);
    }

    return true;
}
