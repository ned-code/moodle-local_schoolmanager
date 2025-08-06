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
 * Sync user profile
 *
 * @package    local_schoolmanager
 * @copyright  Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\task;

global $CFG;
require_once($CFG->dirroot.'/user/profile/lib.php');

use core_user;

defined('MOODLE_INTERNAL') || die();

class sync_user_profile extends \core\task\scheduled_task {

    public function get_name(){
        return get_string('syncuserprofile', 'local_schoolmanager');
    }

    public function execute(){
        global $DB;

        $rs = $DB->get_recordset('local_schoolmanager_school', ['synctimezone' => 1]);
        foreach ($rs as $school){
            if (!$cohort = $DB->get_record('cohort', ['id' => $school->id, 'visible' => 1])){
                continue;
            }

            if (!$cohortmembers = $DB->get_records('cohort_members', ['cohortid' => $school->id], '', 'userid')){
                continue;
            }

            foreach ($cohortmembers as $cohortmember){
                $user = core_user::get_user($cohortmember->userid, '*', MUST_EXIST);
                profile_load_data($user);
                if (!empty($school->region)){
                    $user->profile_field_region = $school->region;
                }
                if (!empty($school->schoolgroup)){
                    $user->profile_field_school_group = $school->schoolgroup;
                }
                $user->profile_field_ESL = $school->esl == 1 ? 'Yes' : 'No';
                profile_save_data($user);
            }
        }
        $rs->close();

        return true;
    }
}