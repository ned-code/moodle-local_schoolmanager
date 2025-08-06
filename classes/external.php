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
 * External API
 *
 * @package     local_schoolmanager
 * @copyright   2021 Michael Gardener <mgardener@cissq.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

use local_schoolmanager\shared_lib as NED;

defined('MOODLE_INTERNAL') || die;

/** @var \stdClass $CFG */
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once("{$CFG->libdir}/completionlib.php");

/**
 * External functions
 *
 * @package     local_schoolmanager
 * @copyright   2019 Michael Gardener <mgardener@cissq.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {
    /**
     * Returns description of method parameters
     *
     * @return \external_function_parameters
     */
    public static function get_classes_parameters(){
        return new \external_function_parameters(
            [
                'courseids' => new \external_multiple_structure(
                    new \external_value(PARAM_INT, 'user ID'), 'An array of course IDs', VALUE_DEFAULT, []
                ),
            ]
        );
    }

    public static function get_classes($courseids){
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::get_classes_parameters(),
            ['courseids' => $courseids]
        );

        $filter = [];
        $courseparams = [];
        $filtersql = '0=0';

        if ($params['courseids']){
            list($coursewhere, $courseparams) = $DB->get_in_or_equal($params['courseids'], SQL_PARAMS_NAMED, 'cor');
            $filter[] = "g.courseid {$coursewhere}";
        }

        if (!empty($filter)){
            $filtersql = implode(' AND ', $filter);
        }

        $schools = cohort_get_user_cohorts($USER->id);
        $school = reset($schools);

        if (empty($school)){
            return null;
        }

        $classdata = [];

        $sqlgrade = "SELECT gg.id, gg.userid, gg.finalgrade, gg.rawgrademax 
                       FROM {grade_items} gi 
                       JOIN {grade_grades} gg
                         ON gi.id = gg.itemid
                      WHERE gi.itemtype = 'course'
                        AND gi.iteminstance = ? 
                        AND gg.userid = ?";

        $sql = "SELECT g.id,
                       g.courseid, 
                       g.name classname, 
                       c.id courseid, 
                       c.fullname coursename, 
                       g.startdate, 
                       g.enddate,
                       gm.userid,
                       u.idnumber,
                       u.firstname,
                       u.lastname,
                       u.email       
                  FROM {groups_members} gm 
            INNER JOIN {groups} g 
                    ON gm.groupid = g.id
            INNER JOIN {course} c 
                    ON g.courseid = c.id
            INNER JOIN {user} u 
                    ON gm.userid = u.id
                 WHERE $filtersql 
                   AND u.deleted = 0 
                   AND gm.userid IN (SELECT cm.userid FROM {cohort_members} cm WHERE cm.cohortid = :cohortid)";

        $courseparams['cohortid'] = $school->id;

        $rs = $DB->get_recordset_sql($sql, $courseparams);
        foreach ($rs as $data){
            if (!isset($classdata[$data->id])){
                $classurl = new \moodle_url('/blocks/ned_teacher_tools/progress_report.php', [
                    'courseid' => $data->courseid,
                    'group' => $data->id,
                ]);

                $coursefinalgrade = '';
                $coursefinalgrademax = '';
                if ($coursegrade = $DB->get_record_sql($sqlgrade, [$data->courseid, $data->userid])){
                    if (!is_null($coursegrade->finalgrade)){
                        $coursefinalgrade = round($coursegrade->finalgrade);
                    }
                    if (!is_null($coursegrade->rawgrademax)){
                        $coursefinalgrademax = round($coursegrade->rawgrademax);
                    }
                }

                $classdata[$data->id] = [
                    'courseid' => $data->courseid,
                    'coursename' => $data->coursename,
                    'classid' => $data->id,
                    'classurl' => $classurl->out(false),
                    'users' => [],
                    'startdate' => (!empty($data->startdate)) ? userdate($data->startdate) : '',
                    'enddate' => (!empty($data->enddate)) ? userdate($data->enddate) : '',
                    'dmduedate' => '',
                ];
            }

            $sql = "SELECT ra.id, 
                           r.name, 
                           r.shortname 
                      FROM {role_assignments} ra 
                INNER JOIN {context} cx 
                        ON ra.contextid = cx.id 
                INNER JOIN {role} r 
                        ON ra.roleid = r.id
                     WHERE cx.contextlevel = ? 
                       AND cx.instanceid = ? 
                       AND ra.userid = ?";
            $roles = $DB->get_records_sql($sql, [CONTEXT_COURSE, $data->courseid, $data->userid]);

            $classdata[$data->id]['users'][] = [
                'id' => $data->userid,
                'firstname' => $data->firstname,
                'lastname' => $data->lastname,
                'coursegrade' => $coursefinalgrade,
                'coursegrademax' => $coursefinalgrademax,
                'roles' => $roles,
            ];
        }

        $rs->close();

        $context = \context_user::instance($USER->id);
        self::validate_context($context);


        return array_values($classdata);
    }

    /**
     * Returns description of method result value
     *
     * @return \external_description
     */
    public static function get_classes_returns(){
        return new \external_multiple_structure(
            new \external_single_structure([
                'courseid'    => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'coursename'  => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'classid'    => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'classurl'    => new \external_value(PARAM_URL, '', VALUE_OPTIONAL),
                'startdate'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'enddate'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'dmduedate'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'users' => new \external_multiple_structure(
                    new \external_single_structure(
                        [
                            'id' => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'firstname' => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'lastname' => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'coursegrade' => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'coursegrademax' => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'roles' => new \external_multiple_structure(
                                new \external_single_structure(
                                    [
                                        'id' => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                                        'name' => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                                        'shortname' => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                                    ]
                                )
                            )
                        ]
                    ), '', VALUE_OPTIONAL
                ),
            ])
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return \external_function_parameters
     */
    public static function get_student_activities_parameters(){
        return new \external_function_parameters(
            [
                'studentid' => new \external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
                'courseids' => new \external_multiple_structure(
                    new \external_value(PARAM_INT, 'Course ID'), 'An array of Course IDs', VALUE_DEFAULT, []
                ),
            ]
        );
    }

    public static function get_student_activities($studentid, $courseids){
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::get_student_activities_parameters(),
            ['studentid' => $studentid, 'courseids' => $courseids]
        );

        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        if (!$params['studentid']){
            return [];
        }

        if (!$user = $DB->get_record('user', ['id' => $params['studentid'], 'deleted' => 0])){
            return [];
        }

        if ($params['courseids']){
            list($coursefilter, $courseparams) = $DB->get_in_or_equal($params['courseids']);
            $courses = $DB->get_records_select('course', "id {$coursefilter}", $courseparams);
        } else {
            $courses = enrol_get_users_courses($params['studentid'], true, null, null);
        }

        if (!$courses){
            return [];
        }

        $return = [];

        foreach ($courses as $course){
            // Retrieve course_module data for all modules in the course
            $kica = NED::get_kica($course->id);
            $activities = NED::get_important_activities($course, $user->id);

            if ($kica && !empty($activities)){
                NED::kg_get_grades_by_course($courseid, $userid, true, true);
                $kicaavg = NED::kg_get_course_average($courseid, $userid, NED::FINALGRADE, 5);
            } else {
                $kicaavg = null;
            }
            foreach ($activities as $mod){
                $data = [];
                $data['studentid'] = $user->id;

                if (!$mod->uservisible){
                    continue;
                }

                if (!in_array($mod->modname, ['assign', 'quiz'])){
                    continue;
                }

                $issummative = false;
                $isformative = false;

                if ($tags = \core_tag_tag::get_item_tags_array('core', 'course_modules', $mod->id)){
                    if (in_array('Summative', $tags) || in_array('summative', $tags)){
                        $issummative = true;
                    }

                    if (in_array('Formative', $tags) || in_array('formative', $tags)){
                        $isformative = true;
                    }
                }

                $duedate = NED::get_deadline_by_cm($mod);

                if ($kica){
                    $kicaitem = NED::ki_get_by_cm($mod);
                    $kicagrade = NED::kg_get_by_userid_itemid($user, $kicaitem);
                } else {
                    $kicaavg = $kicaitem = null;
                }
                $finalgrade = '';
                $activitymaxgrade = '';
                if ($kica && !empty($kicaitem->id) && !empty($kicagrade->id)){
                    $finalgrade = $kicagrade->get_finalgrade(true);
                    $activitymaxgrade = $kicaitem->get_grademax();
                } else {
                    if ($gradeitem = NED::get_grade_item($mod)){
                        $activitymaxgrade = $gradeitem->grademax;
                        if ($grade = NED::get_grade_grade($mod, $user, false)){
                            $finalgrade = $grade->finalgrade ?? '';
                        }
                    }
                }

                // Completion.
                $completion = new \completion_info($course);
                $cm_completion = $completion->get_data($mod, true, $user->id, NED::get_fast_modinfo($course));
                $submissionstatus = 'notcompleted';
                if (($cm_completion->completionstate ?? 0) > 1){
                    $submissionstatus = 'completed';
                }

                if ($mod->modname == 'assign'){
                    $sql = "SELECT su.*,
                                       ag.grade,
                                       ac.commenttext, ac.commentformat
                                  FROM {assign_submission} su
                       LEFT OUTER JOIN {assign_grades} ag
                                    ON su.assignment = ag.assignment
                                   AND su.userid = ag.userid
                                   AND su.attemptnumber = ag.attemptnumber
                       LEFT OUTER JOIN {assignfeedback_comments} ac
                                    ON ag.id = ac.grade
                                 WHERE su.assignment = ?
                                   AND su.userid = ?
                                   AND su.latest = 1
                                   AND su.status = ?";

                    if ($submission = $DB->get_record_sql($sql, [$instance->id, $user->id, ASSIGN_SUBMISSION_STATUS_SUBMITTED], IGNORE_MULTIPLE)){
                        if ($submission->grade == -1 || is_null($submission->grade)){
                            $submissionstatus = 'waitingforgrade';
                        }
                    }
                } elseif ($mod->modname == 'quiz'){
                    $sql = "SELECT * FROM {quiz_attempts} qa WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = 'finished' AND qa.sumgrades IS NULL";
                    if ($DB->record_exists_sql($sql, [$instance->id, $user->id])){
                        $submissionstatus = 'waitingforgrade';
                    }
                }

                $data['courseid'] = $course->id;
                $data['activityid'] = $mod->id;
                $data['activityname'] = $mod->get_formatted_name();
                $data['activiturl'] = $mod->get_url()->out(false);
                $data['activitytype'] = $mod->modname;
                $data['duedate'] = NED::ned_date($duedate, '');
                $data['submissionstatus'] = $submissionstatus;
                $data['activitygrade'] = $finalgrade;
                $data['activitymaxgrade'] = $activitymaxgrade;
                $data['coursegrade'] = $kicaavg;
                $data['tags'] = [];
                if ($isformative){
                    $data['tags'][] = 'Formative';
                }
                if ($issummative){
                    $data['tags'][] = 'Summative';
                }

                $return[] = $data;
            }

            NED::purge_course_depended_caches();
        }

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return \external_description
     */
    public static function get_student_activities_returns(){
        return new \external_multiple_structure(
            new \external_single_structure([
                'studentid'    => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'courseid'    => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'activityid'    => new \external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'activityname'    => new \external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'activiturl'    => new \external_value(PARAM_URL, '', VALUE_OPTIONAL),
                'activitytype'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'duedate'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'submissionstatus'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'activitygrade'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'activitymaxgrade'    => new \external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'tags' => new \external_multiple_structure(
                    new \external_value(PARAM_TEXT, 'Tag name'), 'An array of tagname', VALUE_DEFAULT, []
                ),
            ])
        );
    }
}