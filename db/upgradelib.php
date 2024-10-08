<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2024 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * All upgrades checks from Moodle 3
 *
 * @param int $oldversion
 */
function local_schoolmanager_moodle3_upgrades($oldversion): void{
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

    if ($oldversion < 2021073100) {

        // Define field usermodified to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'note');

        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timecreated to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'usermodified');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timemodified to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key usermodified (foreign) to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Launch add key usermodified.
        $dbman->add_key($table, $key);

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2021073100, 'local', 'schoolmanager');
    }

    if ($oldversion < 2021110800) {

        // Define field synctimezone to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('synctimezone', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'usermodified');

        // Conditionally launch add field synctimezone.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2021110800, 'local', 'schoolmanager');
    }

    if($oldversion < 2022041700){
        // Define field compact_logo to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('compact_logo', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'logo');

        // Conditionally launch add field compact_logo.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2022041700, 'local', 'schoolmanager');
    }

    if ($oldversion < 2023061500){
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('extmanager', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'synctimezone');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2023061500, 'local', 'schoolmanager');
    }

    if ($oldversion < 2023082001) {
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('iptype', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'extmanager');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('proctormanager', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'iptype');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('academicintegritymanager', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'proctormanager');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2023082001, 'local', 'schoolmanager');
    }

    if ($oldversion < 2023082900) {

        // Define field enabletem to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('enabletem', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'academicintegritymanager');

        // Conditionally launch add field enabletem.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2023082900, 'local', 'schoolmanager');
    }

    if ($oldversion < 2023121100) {

        // Define field forceproxysubmissionwindow to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('forceproxysubmissionwindow', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enabletem');

        // Conditionally launch add field forceproxysubmissionwindow.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2023121100, 'local', 'schoolmanager');
    }

    if ($oldversion < 2024031300) {

        // Define index code (not unique) to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $index = new xmldb_index('code', XMLDB_INDEX_NOTUNIQUE, ['code']);

        // Conditionally launch add index code.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2024031300, 'local', 'schoolmanager');
    }

    if ($oldversion < 2024042600) {

        // Define field esl to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('esl', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'enabletem');

        // Conditionally launch add field esl.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2024042600, 'local', 'schoolmanager');
    }

    if ($oldversion < 2024061700) {

        // Define field schoolyeartype to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('schoolyeartype', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'compact_logo');

        // Conditionally launch add field schoolyeartype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2024061700, 'local', 'schoolmanager');
    }

    if ($oldversion < 2024061801) {
        $DB->set_field('local_schoolmanager_school', 'schoolyeartype', 1);

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2024061801, 'local', 'schoolmanager');
    }

    if ($oldversion < 2024073000) {
        // Define field extensionsallowed to be added to local_schoolmanager_school.
        $table = new xmldb_table('local_schoolmanager_school');
        $field = new xmldb_field('extensionsallowed', XMLDB_TYPE_INTEGER, '11', null, null, null, '3', 'schoolyeartype');

        // Conditionally launch add field extensionsallowed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Schoolmanager savepoint reached.
        upgrade_plugin_savepoint(true, 2024073000, 'local', 'schoolmanager');
    }
}
