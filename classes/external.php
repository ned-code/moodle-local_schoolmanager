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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once("{$CFG->libdir}/completionlib.php");

use block_ned_teacher_tools\deadline_manager;
use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;
use core_course\external\course_summary_exporter;
use context_user;
use context_course;
use context_helper;
use external_description;
use moodle_url;
use local_kica as KICA;
use completion_info;
use core_tag_tag;

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
     * @return external_function_parameters
     */
    public static function get_classes_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'), 'An array of course IDs', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    public static function get_classes($courseids) {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::get_classes_parameters(),
            ['courseids' => $courseids]
        );

        $filter = [];
        $courseparams = [];
        $filtersql = '0=0';

        if ($params['courseids']) {
            list($coursewhere, $courseparams) = $DB->get_in_or_equal($params['courseids'], SQL_PARAMS_NAMED, 'cor');
            $filter[] = "g.courseid {$coursewhere}";
        }

        if (!empty($filter)) {
            $filtersql = implode(' AND ', $filter);
        }

        $schools = cohort_get_user_cohorts($USER->id);
        $school = reset($schools);

        if (empty($school)) {
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
        foreach ($rs as $data) {
            if (!isset($classdata[$data->id])) {
                $classurl = new \moodle_url('/blocks/ned_teacher_tools/progress_report.php', [
                    'courseid' => $data->courseid,
                    'group' => $data->id,
                ]);

                $coursefinalgrade = '';
                $coursefinalgrademax = '';
                if ($coursegrade = $DB->get_record_sql($sqlgrade, [$data->courseid, $data->userid])) {
                    if (!is_null($coursegrade->finalgrade)) {
                        $coursefinalgrade = round($coursegrade->finalgrade);
                    }
                    if (!is_null($coursegrade->rawgrademax)) {
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

        $context = context_user::instance($USER->id);
        self::validate_context($context);


        return array_values($classdata);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_classes_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid'    => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'coursename'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'classid'    => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'classurl'    => new external_value(PARAM_URL, '', VALUE_OPTIONAL),
                'startdate'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'enddate'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'dmduedate'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'firstname' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'lastname' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'coursegrade' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'coursegrademax' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                            'roles' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'id' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                                        'name' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                                        'shortname' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                                    )
                                )
                            )
                        )
                    ), '', VALUE_OPTIONAL
                ),
            ])
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_student_activities_parameters() {
        return new external_function_parameters(
            [
                'studentid' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course ID'), 'An array of Course IDs', VALUE_DEFAULT, array()
                ),
            ]
        );
    }

    public static function get_student_activities($studentid, $courseids) {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::get_student_activities_parameters(),
            ['studentid' => $studentid, 'courseids' => $courseids]
        );

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        if (!$params['studentid']) {
            return [];
        }

        if (!$user = $DB->get_record('user', ['id' => $params['studentid'], 'deleted' => 0])) {
            return [];
        }

        if ($params['courseids']) {
            list($coursefilter, $courseparams) = $DB->get_in_or_equal($params['courseids']);
            $courses = $DB->get_records_select('course', "id {$coursefilter}", $courseparams);
        } else {
            $courses = enrol_get_users_courses($params['studentid'], true, null, null);
        }

        if (!$courses) {
            return [];
        }

        $return = [];

        foreach ($courses as $course) {
            // Retrieve course_module data for all modules in the course
            $modinfo = get_fast_modinfo($course, $user->id);
            $activities = $modinfo->get_cms();

            $deadlinemanager = new deadline_manager($course->id);

            foreach ($activities as $mod) {
                $data = [];
                $data['studentid'] = $user->id;

                if (!$mod->uservisible) {
                    continue;
                }

                if (!$tags = core_tag_tag::get_item_tags_array('core', 'course_modules', $mod->id)) {
                    continue;
                }

                $issummative = false;
                $isformative = false;

                if (in_array('Summative', $tags) || in_array('summative', $tags)) {
                    $issummative = true;
                }

                if (in_array('Formative', $tags) || in_array('formative', $tags)) {
                    $isformative = true;
                }

                if (!$issummative && !$isformative) {
                    continue;
                }

                if (!$instance = $DB->get_record($mod->modname, ['id' => $mod->instance])) {
                    continue;
                }

                $duedate =0;
                if ($deadlinemanager->is_enabled_activity($mod->id)) {
                    $classname = '\block_ned_teacher_tools\mod\deadline_manager_' . $mod->modname;
                    if (class_exists($classname)) {
                        $module = new $classname($mod);
                        $duedate = $module->get_user_effective_access($user->id);
                    }
                }

                if ($kica = $DB->get_record('local_kica', array('courseid' => $course->id))) {
                    $kicaavg = \local_kica\helper::get_course_average($user->id, $course->id, 5);
                }
                $itemparams = [
                    'courseid' => $course->id,
                    'itemtype' => 'mod',
                    'itemmodule' => $mod->modname,
                    'iteminstance' => $mod->instance,
                ];
                $kicaitem = new KICA\kica_item($itemparams);
                $finalgrade = '';
                $activitymaxgrade = '';
                if ($kica && $kicaitem->id) {
                    $kicagrade = new KICA\grade($user->id, $kicaitem->id, $kica->pullfromgradebook);
                    $grade = $kicagrade->get_grade();
                    $default[4] = $gradetimecreated = $kicagrade->timecreated;
                    $default[5] = $gradetime = $kicagrade->timemodified;
                    $finalgrade = (is_null($grade->finalgrade)) ? '' : $grade->finalgrade;
                    $activitymaxgrade = $kicaitem->get_grademax();
                }

                // Completion.
                $sqlcompletion = "SELECT cmc.* 
                                FROM {course_modules_completion} cmc
                          INNER JOIN {course_modules} cm 
                                 ON cmc.coursemoduleid = cm.id
                               WHERE cmc.coursemoduleid = ? 
                                 AND cmc.userid = ? 
                                 AND cm.deletioninprogress = 0";
                $completion = $DB->get_record_sql($sqlcompletion, [$mod->id, $user->id]);
                $submissionstatus = 'notcompleted';
                if (!empty($completion) && $completion->completionstate > 1) {
                    $submissionstatus = 'completed';
                }

                if (empty($duedate)) {
                    if ($mod->modname == 'assign') {
                        $duedate = $instance->cutoffdate;
                    } else if ($mod->modname == 'quiz') {
                        $duedate = $instance->timeclose;
                    }
                }

                $data['courseid'] = $course->id;
                $data['activityid'] = $mod->id;
                $data['activityname'] = $mod->get_formatted_name();
                $data['activiturl'] = (new moodle_url('/mod/' . $mod->modname . '/view.php', ['id' => $mod->id]))->out();
                $data['activitytype'] = $mod->modname;
                $data['duedate'] = (!empty($duedate)) ? userdate($duedate) : '';
                $data['submissionstatus'] = $submissionstatus;
                $data['activitygrade'] = $finalgrade;
                $data['activitymaxgrade'] = $activitymaxgrade;
                $data['coursegrade'] = $kicaavg;
                if ($isformative) {
                    $data['tags'][] = 'Formative';
                }
                if ($issummative) {
                    $data['tags'][] = 'Summative';
                }

                $return[] = $data;
            }
        }


        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_student_activities_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'studentid'    => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'courseid'    => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'activityid'    => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'activityname'    => new external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'activiturl'    => new external_value(PARAM_URL, '', VALUE_OPTIONAL),
                'activitytype'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'duedate'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'submissionstatus'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'activitygrade'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'activitymaxgrade'    => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'tags' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Tag name'), 'An array of tagname', VALUE_DEFAULT, array()
                ),
            ])
        );
    }
}