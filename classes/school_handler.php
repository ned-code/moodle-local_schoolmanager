<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

use block_ned_teacher_tools\deadline_manager as DM;
use block_ned_teacher_tools\utils;
use theme_ned_boost\output\core_renderer;
use theme_ned_boost\output\course;
use theme_ned_boost\output\dashboard_content;
use theme_ned_boost\shared_lib as NED;
use local_kica as KICA;
use function block_ned_teacher_tools\is_kica_exists;

defined('MOODLE_INTERNAL') || die();

/** @var \stdClass $CFG */
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/theme/ned_boost/classes/output/frontdashboard.php');

class school_handler {
    const CAP_CANT_SEE_SCHOOL = 0;
    const CAP_SEE_OWN_SCHOOL = 1;
    const CAP_SEE_ALL_SCHOOLS = 2;
    const VIEW_STUDENTS = 'students';
    const VIEW_STAFF = 'staff';
    const VIEW_SCHOOL = 'school';
    const VIEW_SCHOOLS = 'schools';

    protected $user;
    protected $ctx;
    protected $capability;
    protected $schools;

    public function __construct() {
        global $USER;

        $this->user = $USER;
        $this->ctx = \context_system::instance();
        $this->capability = static::get_capability($this->ctx);
        $this->set_schools();
    }

    /**
     * Return capability to see own or other school.
     *  All upper capability includes others, so you can check by using ">" or "<"
     *
     * @param null $ctx - context
     *
     * @return int
     */
    static public function get_capability($ctx=null){
        $ctx = $ctx ?? \context_system::instance();
        if (has_capability('local/schoolmanager:viewallschooldashboards', $ctx)){
            return static::CAP_SEE_ALL_SCHOOLS;
        }
        if (has_capability('local/schoolmanager:viewownschooldashboard', $ctx)){
            return static::CAP_SEE_OWN_SCHOOL;
        }
        return static::CAP_CANT_SEE_SCHOOL;
    }

    /**
     * @return mixed
     */
    public function get_schools() {
        return $this->schools;
    }

    /**
     * load all possible fot current user schools from cohorts
     */
    protected function set_schools() {
        if ($this->capability >= static::CAP_SEE_ALL_SCHOOLS) {
            $cohort_data = cohort_get_all_cohorts(0, 0);
            $cohorts = $cohort_data['cohorts'] ?? [];
        } else {
            $cohorts = cohort_get_user_cohorts($this->user->id);
        }

        $this->schools = static::get_schools_by_cohorts($cohorts);
    }

    /**
     * @param array $cohorts - should be [cohortid => cohort]
     *
     * @return array
     */
    static public function get_schools_by_cohorts($cohorts=[]){
        global $DB;

        list($sql_cohorts, $params) = $DB->get_in_or_equal(array_keys($cohorts), SQL_PARAMS_NAMED);
        return $DB->get_records_select('local_schoolmanager_school', 'id '.$sql_cohorts, $params, 'name');
    }

    /**
     * Section control form for render method
     *
     * @return string
     */
    public function get_control_form($schoolid = 0) {
        if (empty($this->schools) || $this->capability <= static::CAP_SEE_OWN_SCHOOL) {
            return '';
        }

        $form = [];
        $url = static::get_url();

        // choose school
        $school_opts = [];
        $attr = [];
        $count = count($this->schools);
        if ($count != 1){
            $school_opts[0] = NED::str('allschools');
        }
        if ($count <= 1){
            $attr['disabled'] = 'disabled';
        }
        foreach ($this->schools as $school){
            $school_opts[$school->id] = $school->name;
        }
        $form[] = NED::single_autocomplete($url, 'schoolid', $school_opts, $schoolid, NED::fa('fa-university'), '', $attr);

        return implode('', $form);
    }

    /**
     * Return url to the dashboard page
     *
     * @return \moodle_url
     */
    public static function get_url() {
        $params = [];
        $params['schoolid'] = optional_param('schoolid', 0, PARAM_INT);;
        $params['view'] = optional_param('view', static::VIEW_STUDENTS, PARAM_ALPHA);
        return new \moodle_url('/local/schoolmanager/view.php', $params);
    }

    public static function get_user_lastaccess($user) {
        if ($user->lastaccess ?? false) {
            $t = time() - $user->lastaccess;
            if ($t > 0){
                $lastlogin = get_string('ago', 'message', format_time($t));
            } else {
                $lastlogin = get_string('now');
            }
        } else {
            $lastlogin = get_string('never');
        }
        return $lastlogin;
    }

    public static function get_user_number_of_dl_extensions($user, $courses = null) {
        if (empty($courses)) {
            $courses = enrol_get_users_courses($user->id);
        }

        $deadlineextentions = 0;

        if ($courses) {
            foreach ($courses as $course) {
                $deadlineextentions += DM::get_number_of_extensions_in_course($user->id, $course->id);
            }
            return $deadlineextentions;
        }
        return null;
    }

    public static function get_user_gpa($user, $courses = null) {
        if (empty($courses)) {
            $courses = enrol_get_users_courses($user->id);
        }
        if ($courses) {
            $courseaverages = [];
            foreach ($courses as $course) {
                //$courseaverage = \block_ned_teacher_tools\get_course_grade($course->id, $user->id, 5);
                $courseaverage = self::get_course_grade($course->id, $user->id, 5);
                if ($courseaverage != '-') {
                    $courseaverages[] = $courseaverage;
                }
            }

            if ($courseaverages) {
                return round(array_sum($courseaverages) / count($courseaverages), 2);
            }
        }
        return null;
    }

    public static function get_user_ppa($user, $courses = null) {
        if (empty($courses)) {
            $courses = enrol_get_users_courses($user->id);
        }
        if ($courses) {
            $pps = [];
            foreach ($courses as $course) {
                $_course = new \theme_ned_boost\output\course($course);
                if ($pp = $_course->get_participation_power($user->id)) {
                    $pps[] = $pp;
                }
            }
            if ($pps) {
                return  round(array_sum($pps) / count($pps), 2);
            }
        }
        return null;
    }

    public static function get_user_aiv($user, $startdate, $enddate, $lastdays = 0) {
        global $DB;

        $dayfilter = '';
        $params['student'] = $user->id;
        $params['startdate'] = $startdate;
        $params['enddate'] = $enddate;

        if ($lastdays) {
            $dayfilter = 'AND aiv.infractiondate >= :lastdays';
            $params['lastdays'] = time() - $lastdays * DAYSECS;
        }

        $sql = "SELECT COUNT(1)
                  FROM {local_academic_integrity_inf} aiv 
                 WHERE aiv.student = :student
                   AND aiv.approved = 1 
                   AND aiv.infractiondate >= :startdate
                   AND aiv.infractiondate < :enddate
                       $dayfilter";
        return $DB->count_records_sql($sql, $params);
    }

    public static function get_classes($user, $schoolid, $courses = null) {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        $filter = [];
        $courseparams = [];
        $filtersql = '0=0';

        if (empty($courses)) {
            $courses = enrol_get_users_courses($user->id);
        }

        if (!$courses) {
            return null;
        }

        list($coursewhere, $courseparams) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'cor');
        $filter[] = "g.courseid {$coursewhere}";


        if (!empty($filter)) {
            $filtersql = implode(' AND ', $filter);
        }

        $schools = cohort_get_user_cohorts($user->id);
        $school = $schools[$schoolid] ?? null;

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

                $coursefinalgrade = null;
                $coursefinalgrademax = null;
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

        return array_values($classdata);
    }

    public static function get_timezone() {}

    /**
     * @param $cohort
     * @return bool
     * @throws \dml_exception
     */
    public static function has_different_timezone_users_in_school($cohort) {
        global $DB;

        $sql = "SELECT cm.id, cm.userid, u.firstname, u.lastname,u.timezone
                  FROM {cohort_members} cm
                  JOIN {user} u ON cm.userid = u.id
                 WHERE cm.cohortid = ?
                   AND u.timezone != ?";

        return $DB->record_exists_sql($sql, [$cohort->id, $cohort->timezone]);
    }

    /**
     * @param $courseid
     * @param $userid
     * @param $precision
     *
     * @return float|string
     */
    // TODO load (count) all of them, if we need all user grades form course
    public static function get_course_grade($courseid, $userid, $precision=2){
        if (is_kica_exists() && utils::kica_gradebook_enabled($courseid)){
            $finalgrade = KICA\helper::get_course_average($userid, $courseid, $precision, false, true);
        } else {
            $courseitem = \grade_item::fetch_course_item($courseid);
            $coursegrade = new \grade_grade(array('itemid' => $courseitem->id, 'userid' => $userid));
            $coursegrade->grade_item =& $courseitem;
            $finalgrade = $coursegrade->finalgrade;
        }
        return is_null($finalgrade) ? '-' : round($finalgrade, $precision);
    }
}