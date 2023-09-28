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
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @noinspection PhpUnnecessaryCurlyVarSyntaxInspection
 */

namespace local_schoolmanager\task;

use local_schoolmanager\shared_lib as NED;
use local_schoolmanager\school_manager;

defined('MOODLE_INTERNAL') || die();

NED::require_file('~/lib.php');
NED::require_lib('enrollib.php');
NED::require_lib('grouplib.php');
NED::require_lib('accesslib.php');

/**
 * Class sync_course_admins
 *
 * Auto enroll School Admins and District Admins to courses
 * - If user role = S-SA or S-DA
 * - And user belongs to any school
 * - Then enroll user in any course and course group that has matching school code
 *
 * @package local_schoolmanager\task
 */
class sync_course_admins extends \core\task\scheduled_task {
    use \local_ned_controller\task\base_task;

    /**
     * Do the job.
     *
     * @param array|object|static|null $task_or_data
     *
     * @return void
     */
    static public function do_job($task_or_data=[]) {
        $users = static::get_users_to_update(true);
        if (empty($users)){
            if (is_null($users)){
                static::print('There are no fields to sync users, pass');
            } else {
                static::print('There are no users-courses to update, pass');
            }
            return;
        }

        $c = count($users);
        static::print("There are $c users-courses to process...");
        list($add_enrol, $rem_enrol, $add_group, $rem_group) = static::process_users($users, true);
        if ($add_enrol){
            static::print("There are $add_enrol users-courses have been enrolled");
        }
        if ($rem_enrol){
            static::print("There are $rem_enrol users-courses have been suspended");
        }
        if ($add_group){
            static::print("There are $add_group users-groups have been added");
        }
        if ($rem_group){
            static::print("There are $rem_group users-groups have been removed");
        }
    }

    /**
     * Get users to enrol or unenrol to/from course by school groups
     *
     * @param bool $log
     *
     * @return array
     */
    static public function get_users_to_update($log=false){
        $params = [];
        $params['syscontextid'] = SYSCONTEXTID;
        $params['school_code_length'] = NED::SCHOOL_CODE_LENGTH;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = $params['enabled2'] = ENROL_INSTANCE_ENABLED;
        $params['manual'] = $params['manual2'] = 'manual';
        $NOW = NED::SQL_NOW;
        $all_params = [];

        $can_add = "(u.suspended = 0 AND real_member.id IS NOT NULL)";
        $can_rem = "real_member.id IS NULL";
        $sql_add_groupids = "($can_add AND gm.id IS NULL AND (e.id IS NOT NULL OR uee.any_enrolments IS NOT NULL))";
        $sql_rem_groupids = "($can_rem AND gm.id IS NOT NULL)";
        $sql_add_enrol = "($can_add AND uee.real_manual_enrols IS NULL AND e.id IS NOT NULL)";
        $sql_rem_enrol = "($sql_rem_groupids AND uee.real_manual_enrols IS NOT NULL)";

        $select = [
            "CONCAT(course.id, '_', u.id) AS uniqid",
            'course.id AS courseid',
            'u.id AS userid',
            'e.id AS manual_enrol_id',
            'uee.timestart AS enrolment_timestart',
            'uee.real_manual_enrols',
            'uee.any_enrolments',
            'GROUP_CONCAT(DISTINCT r.id) AS roleids',
            "GROUP_CONCAT(DISTINCT IF($sql_add_groupids, gr.id, NULL)) AS add_groupids",
            "GROUP_CONCAT(DISTINCT IF($sql_rem_groupids, gr.id, NULL)) AS rem_groupids",
            "SUM($sql_add_enrol) AS add_enrol",
            "SUM($sql_rem_enrol) AS rem_enrol",
        ];
        if ($log){
            $select[] = "CONCAT(u.firstname, ' ', u.lastname) AS username";
            $select[] = 'course.shortname as course_name';
        }
        $t_school = school_manager::TABLE_SCHOOL;
        $t_member = school_manager::TABLE_MEMBERS;

        list($rolename_where, $rolename_params) = NED::db()->get_in_or_equal([NED::ROLE_SSA, NED::ROLE_SDA], SQL_PARAMS_NAMED);
        $all_params[] = $rolename_params;

        $from = ["
            JOIN {user} u
                ON u.deleted = 0
            JOIN {role} r 
                ON r.shortname $rolename_where
            JOIN {role_assignments} ra
                ON ra.contextid = :syscontextid
                AND ra.userid = u.id
                AND ra.roleid = r.id
            JOIN {groups} gr 
                ON LEFT(gr.name, :school_code_length) LIKE BINARY school.code
            JOIN {course} course
                ON course.id = gr.courseid
            
            -- Get first manual enrol from course enrolment methods list
            JOIN {enrol} e
                ON e.courseid = course.id
                AND e.status = :enabled
                AND e.enrol = :manual
            LEFT JOIN {enrol} e2
                ON e2.courseid = e.courseid
                AND e2.status = e.status
                AND e2.enrol = e.enrol
                AND (e2.sortorder < e.sortorder 
                    OR (e2.sortorder = e.sortorder AND e2.id < e.id))
                
            -- Real join schools    
            LEFT JOIN {{$t_member}} real_member
                ON real_member.cohortid = school.id
                AND real_member.userid = u.id
            
            -- Real groups members   
            LEFT JOIN {groups_members} gm
                ON gm.groupid = gr.id
                AND gm.userid = u.id
                  
            -- Real user enrolments 
            LEFT JOIN (
                SELECT e.id, e.courseid, ue.userid,
                    GROUP_CONCAT(IF(e.enrol = :manual2 AND ue.timeend = 0, e.id, NULL)) AS real_manual_enrols,
                    GROUP_CONCAT(ue.id) AS any_enrolments,
                    MIN(ue.timestart) AS timestart
                FROM {user_enrolments} ue
                JOIN {enrol} e
                    ON e.id = ue.enrolid
                    AND e.status = :enabled2
                    -- get only active
                    AND ue.status = :active
                    AND ue.timestart <= $NOW
                    AND (ue.timeend = 0 OR ue.timeend > $NOW)
                GROUP BY e.courseid, ue.userid
            ) uee
                ON uee.courseid = course.id
                AND uee.userid = u.id
        "];

        $where = ["($sql_add_enrol OR $sql_rem_enrol OR $sql_add_groupids OR $sql_rem_groupids)"];
        $where[] = 'e2.id IS NULL';

        $sql = NED::sql_generate($select, $from, $t_school, 'school', $where, 'course.id, u.id');
        $params = array_merge($params, ...$all_params);

        return NED::db()->get_records_sql($sql, $params);
    }

    /**
     * Enrolled or suspended users to/on course, add/remove from course school groups
     * 'users' is list of special objects, getting from @see sync_school_admins::get_users_to_update()
     *
     * @param array $users
     * @param bool  $log
     *
     * @return array ($add_enrol, $rem_enrol, $add_group, $rem_group) - enrolled, suspended, added to group, removed from group users,
     */
    static public function process_users($users, $log=false){
        $add_enrol = 0;
        $rem_enrol = 0;
        $add_group = 0;
        $rem_group = 0;

        if (empty($users)){
            return [0, 0, 0, 0];
        }

        $now = time();
        $p = function($text) use (&$log) {
          if (!$log) return;

          static::print($text);
        };
        $enrol_plugin = null;
        if (enrol_is_enabled('manual')) {
            $enrol_plugin = enrol_get_plugin('manual');
        }

        $enrol_component = NED::$PLUGIN_NAME;
        $role_names = [NED::ROLE_SDA, NED::ROLE_SSA, NED::ROLE_RDA, NED::ROLE_RSA];
        list($role_sql, $params) = NED::db()->get_in_or_equal($role_names, SQL_PARAMS_NAMED);
        $roles = NED::db()->get_records_select_menu('role', "shortname $role_sql", $params, '', 'shortname, id');
        $set_roles = [];
        if (!empty($roles[NED::ROLE_SDA]) && !empty($roles[NED::ROLE_RDA])){
            $set_roles[$roles[NED::ROLE_SDA]] = $roles[NED::ROLE_RDA];
        }
        if (!empty($roles[NED::ROLE_SSA]) && !empty($roles[NED::ROLE_RSA])){
            $set_roles[$roles[NED::ROLE_SSA]] = $roles[NED::ROLE_RSA];
        }

        foreach ($users as $user){
            $dont_unenrol = false;

            if ($enrol_plugin && ($user->add_enrol ?? false)){
                $e_instance = NED::enrol_get_manual_enrol_instances($user->courseid, $user->manual_enrol_id ?? 0);
                if ($e_instance){
                    $context = \context_course::instance($user->courseid, IGNORE_MISSING);
                    if (!$context){
                        continue;
                    }

                    $enrol_plugin->enrol_user($e_instance, $user->userid, null,
                        $user->enrolment_timestart ?? $now, 0, ENROL_USER_ACTIVE, false);

                    if (!empty($user->roleids)){
                        $have_roles = explode(',', $user->roleids);
                        foreach ($have_roles as $have_role){
                            $set_roleid = $set_roles[$have_role] ?? 0;
                            if (!$set_roleid) continue;

                            if ($enrol_plugin->roles_protected()) {
                                role_assign($set_roleid, $user->userid, $context->id, $enrol_component, $e_instance->id);
                            } else {
                                role_assign($set_roleid, $user->userid, $context->id);
                            }
                        }
                    }

                    $p("+ User {$user->username} ($user->userid) have been unlimited enrolled to course " .
                        "{$user->course_name} ($user->courseid)");
                    $add_enrol++;
                    $dont_unenrol = true;
                }
            }

            if (!empty($user->add_groupids)){
                $groupids = explode(',', $user->add_groupids);
                foreach ($groupids as $groupid){
                    if (groups_add_member($groupid, $user->userid, $enrol_component)){
                        $p("+ User {$user->username} ($user->userid) have been added to group $groupid");
                        $add_group++;
                        $dont_unenrol = true;
                    }
                }
            }

            if (!empty($user->rem_groupids)){
                $groupids = explode(',', $user->rem_groupids);
                foreach ($groupids as $groupid){
                    if (groups_remove_member($groupid, $user->userid)){
                        $p("- User {$user->username} ($user->userid) have been removed from group $groupid");
                        $rem_group++;
                    }
                }
            }

            if (!$dont_unenrol){
                $user_groupings = groups_get_user_groups($user->courseid, $user->userid);
                $dont_unenrol = !empty($user_groupings[0]);
            }

            if (($user->rem_enrol ?? false) && $enrol_plugin && !empty($user->real_manual_enrols) && !$dont_unenrol){
                $real_manual_enrols = explode(',', $user->real_manual_enrols);
                foreach ($real_manual_enrols as $enrolid){
                    $e_instance = NED::enrol_get_manual_enrol_instances($user->courseid, $enrolid);
                    if (!$e_instance) continue;

                    $enrol_plugin->update_user_enrol($e_instance, $user->userid, ENROL_USER_SUSPENDED,
                        $user->enrolment_timestart ?? $now, $now+1);

                    $p("- User {$user->username} ($user->userid) have been suspended on course " .
                        "{$user->course_name} ($user->courseid)");
                    $rem_enrol++;
                }
            }
        }

        return [$add_enrol, $rem_enrol, $add_group, $rem_group];
    }
}
