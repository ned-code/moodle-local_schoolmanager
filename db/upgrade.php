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

function xmldb_local_schoolmanager_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2020083100) {

        $table = new xmldb_table('local_schoolmanager_crew');
        if ($dbman->table_exists($table)){
            $key = new xmldb_key('parentid', XMLDB_KEY_FOREIGN, ['id'], 'cohort', ['id']);
            $dbman->add_key($table, $key);

            $crew_count = [];
            $school_codes = $DB->get_records_menu('local_schoolmanager_school', null, '', 'id, code');
            $crews = $DB->get_records('local_schoolmanager_crew');
            foreach ($crews as $crew){
                $old_id = $crew->id;
                $crew_count[$crew->schoolid] = ($crew_count[$crew->schoolid] ?? 0) + 1;
                $code = '';
                $school_code = $school_codes[$crew->schoolid] ?? '';
                if (!empty($school_code) && !empty($crew->code)){
                    $code = $school_code . '-' . $crew->code;
                    if ($DB->record_exists('cohort', ['idnumber' => $code])){
                        $code = '';
                    }
                }

                if (empty($code)) {
                    $count = $crew_count[$crew->schoolid];
                    $code = $school_code . '-' . ($count > 9 ? $count : ('0' . $count));
                }
                $now = time();
                $new_crew = [
                    'contextid' => 1,
                    'name' => $crew->name,
                    'idnumber' => $code,
                    'description' => '',
                    'descriptionformat' => 1,
                    'visible' => 1,
                    'component' => 'local_schoolmanager',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];

                if ($new_id = $DB->insert_record('cohort', (object)$new_crew, true)){
                    $DB->set_field('local_schoolmanager_crew', 'id', $new_id, ['id' => $old_id]);
                    $DB->set_field('cohort_members', 'crewid', $new_id, ['crewid' => $old_id]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2020083100, 'local', 'schoolmanager');
    }
    return true;
}