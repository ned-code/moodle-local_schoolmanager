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
NED::require_file('/cohort/lib.php');

/**
 * Class sync_school_admins
 *
 * For users with roles {@see NED::ROLE_SSA}, {@see NED::ROLE_SDA}
 *
 * Task A1: Sync School Admins to schools
 *  - If user profile field “School” has selected school
 *  - Then add user to site cohort with matching name
 *
 * Task A2: Sync District Admins to school
 * - If user profile field “District Administration” has selected schools
 * - Then add user to all site cohorts with matching name
 *
 * @package local_schoolmanager\task
 */
class sync_school_admins extends \core\task\scheduled_task {
    use \local_ned_controller\task\base_task;

    /**
     * Do the job.
     *
     * @param array|object|static|null $task_or_data
     *
     * @return void
     */
    public static function do_job($task_or_data=[]){
        $users = static::get_users_to_update(true);
        if (empty($users)){
            if (is_null($users)){
                static::print('There are no fields to sync users, pass');
            } else {
                static::print('There are no users-schools to update, pass');
            }
            return;
        }

        $c = count($users);
        static::print("There are $c users-schools to process...");
        [$add, $rem] = static::process_users($users, true);
        if ($add){
            static::print("There are $add users-schools have been added");
        }
        if ($rem){
            static::print("There are $rem users-schools have been removed");
        }
    }

    /**
     * Get users to add or remove to/from schools
     * Return null, if there are no special settings
     *
     * @param bool $log
     *
     * @return array|null
     */
    public static function get_users_to_update($log=false){
        $config = NED::get_config();
        $school_field = $config->school_field_to_sync ?? 0;
        $schools_multi_field = $config->schools_field_to_sync ?? 0;

        $school_add_join = [];
        $school_check_fields = [];
        if ($school_field && is_numeric($school_field)){
            $school_check_fields[] = $school_field;
            $school_code_length = NED::SCHOOL_CODE_LENGTH;
            $school_add_join[] = "uid.fieldid = $school_field AND LEFT(uid.data, $school_code_length) LIKE BINARY school.code";
        }
        if ($schools_multi_field && is_numeric($schools_multi_field)){
            $school_check_fields[] = $schools_multi_field;
            /**
             * Don't use "[[.space.]]" here, as it doesn't work in the new MariaDB versions
             * Don't use "[[:space:]]" here, as it is identified by Moodle as parameter placeholders
             * @var $space
             */
            $space = ' ';
            $school_add_join[] = "uid.fieldid = $schools_multi_field AND uid.data REGEXP BINARY CONCAT('(^|\\n)', school.code, '$space.*')";
        }

        if (empty($school_check_fields)){
            return null;
        }

        $params = [];
        $params['syscontextid'] = SYSCONTEXTID;
        $all_params = [];

        $sql_add_school = '(u.suspended = 0 AND real_member.id IS NULL AND uid.id IS NOT NULL)';
        $sql_rem_school = '(real_member.id IS NOT NULL AND uid.id IS NULL)';
        $select = [
            "CONCAT(school.id, '_', u.id) AS uniqid",
            'school.id AS schoolid',
            'u.id AS userid',
            "$sql_add_school AS add_school",
            "$sql_rem_school AS rem_school",
        ];
        if ($log){
            $select[] = "CONCAT(u.firstname, ' ', u.lastname) AS username";
            $select[] = 'school.code as school_code';
        }
        $t_school = school_manager::TABLE_SCHOOL;
        $t_member = school_manager::TABLE_MEMBERS;
        $school_add_join = NED::sql_where($school_add_join, "OR", true);

        [$rolename_where, $rolename_params] = NED::db()->get_in_or_equal([NED::ROLE_SSA, NED::ROLE_SDA], SQL_PARAMS_NAMED);
        $all_params[] = $rolename_params;
        [$school_field_where, $school_field_params] = NED::db()->get_in_or_equal($school_check_fields, SQL_PARAMS_NAMED);
        $all_params[] = $school_field_params;
        [$school_none_where, $school_none_params] = NED::db()->get_in_or_equal(NED::SCHOOL_EMPTY_LIST, SQL_PARAMS_NAMED, 'param', false);
        $all_params[] = $school_none_params;

        $from = ["
            JOIN {user} u
                ON u.deleted = 0
            JOIN {role} r 
                ON r.shortname $rolename_where
            JOIN {role_assignments} ra
                ON ra.contextid = :syscontextid
                AND ra.userid = u.id
                AND ra.roleid = r.id
                
            -- Real join schools    
            LEFT JOIN {{$t_member}} real_member
                ON real_member.cohortid = school.id
                AND real_member.userid = u.id

            -- Should join schools
            LEFT JOIN {user_info_data} uid 
                ON uid.userid = u.id
                AND uid.fieldid $school_field_where
                AND uid.data $school_none_where 
                AND $school_add_join 
        "];

        $where = ["$sql_add_school OR $sql_rem_school"];

        $sql = NED::sql_generate($select, $from, $t_school, 'school', $where, 'school.id, u.id');
        $params = array_merge($params, ...$all_params);

        return NED::db()->get_records_sql($sql, $params);
    }

    /**
     * Add or remove users
     * 'users' is list of special objects, getting from @see sync_school_admins::get_users_to_update()
     *
     * @param array $users
     * @param bool  $log
     *
     * @return array ($add, $rem) - added and removed users
     */
    public static function process_users($users, $log=false){
        $add = 0;
        $rem = 0;

        if (empty($users)){
            return [0, 0];
        }

        $p = function($text) use (&$log){
            if (!$log) return;

            static::print($text);
        };

        foreach ($users as $user){
            try {
                if ($user->add_school ?? false){
                    cohort_add_member($user->schoolid, $user->userid);
                    $p("+ User {$user->username} ($user->userid) have been added to school {$user->school_code} ($user->schoolid)");
                    $add++;
                } elseif ($user->rem_school ?? false){
                    cohort_remove_member($user->schoolid, $user->userid);
                    $p("- User {$user->username} ($user->userid) have been removed from school {$user->school_code} ($user->schoolid)");
                    $rem++;
                }
            } catch (\Exception $e){
                $e_info = get_exception_info($e);
                $logerrmsg = "!!! Error !!!" .
                    "\nException handler: ".$e_info->message.
                    "\nDebug: ".$e_info->debuginfo."\n".format_backtrace($e_info->backtrace, true);
                static::print($logerrmsg, true);
                continue;
            }
        }

        return [$add, $rem];
    }
}
