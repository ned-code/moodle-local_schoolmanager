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
use local_schoolmanager\shared_lib as NED;
use local_schoolmanager as SM;

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
    const VIEW_CLASSES = 'classes';
    const VIEW_EPC = 'epc';
    /** @var static */
    static $self = null;

    protected $user;
    protected $ctx;
    protected $capability;
    protected $schools;

    /**
     * Use {@see static::get_school_handler()} instead of raw __construct() method
     */
    public function __construct() {
        global $USER;

        $this->user = $USER;
        $this->ctx = \context_system::instance();
        $this->capability = static::get_capability($this->ctx);
        $this->set_schools();
        static::$self = $this;
    }

    /**
     * Get school_handler object
     * @constructor
     *
     * @return static
     */
    static public function get_school_handler(){
        if (empty(static::$self)){
            static::$self = new static();
        }

        return static::$self;
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
        if (NED::has_capability('viewallschooldashboards', $ctx)){
            return static::CAP_SEE_ALL_SCHOOLS;
        }
        if (NED::has_capability('viewownschooldashboard', $ctx)){
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

        [$sql_cohorts, $params] = $DB->get_in_or_equal(array_keys($cohorts), SQL_PARAMS_NAMED);
        return $DB->get_records_select('local_schoolmanager_school', 'id '.$sql_cohorts, $params, 'name');
    }

    /**
     * Section control form for render method
     *
     * @return string
     */
    public function get_control_form($schoolid = 0, $url = null, $hidetemdisabled = false, $hideschoolswithoutstudent = false) {
        if (empty($this->schools) || $this->capability <= static::CAP_SEE_OWN_SCHOOL) {
            return '';
        }

        $form = [];
        if (!$url) {
            $url = static::get_url();
        }

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

        if ($hideschoolswithoutstudent) {
            $SM = new SM\school_manager();
        }

        foreach ($this->schools as $school){
            if ($hidetemdisabled && !$school->enabletem) {
                continue;
            }
            if ($hideschoolswithoutstudent && !$students = $SM->get_school_students($school->id, true, $SM::DEF_MEMBER_ROLE, false)) {
                continue;
            }
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

    /**
     * Get student's count deadline extensions for all courses
     *
     * @param array      $userids
     * @param int        $startime - UNIX time
     * @param int        $endtime  - UNIX time
     * @param int[]|null $courseids
     *
     * @return int|null
     */
    public static function get_user_number_of_dl_extensions($userids, $startime=0, $endtime=0, $courseids=null){
        if (!NED::is_tt_exists()) return null;

        $obj = DM::get_extensions_raw($userids, $courseids, null, null, null, $startime, $endtime, true);
        return $obj->{NED::PERIOD_TOTAL} ?? 0;
    }

    /**
     * Get count of students with 20+ deadline extensions
     *
     * @param array $userids
     * @param int $startime - UNIX time
     * @param int $endtime - UNIX time
     *
     * @return int
     */
    public static function get_user_number_with_extensions20($userids, $startime=0, $endtime=0){
        if (!NED::is_tt_exists()) return null;

        $count = 0;
        $records = DM::get_extensions_raw($userids, null, null, null, null, $startime, $endtime, true, null, 'userid');
        foreach ($records as $record){
            if (($record->{NED::PERIOD_TOTAL} ?? 0) >= 20) $count++;
        }
        return $count;
    }

    /**
     * @param numeric|object     $user_or_id
     * @param array|null         $courses
     *
     * @return float|int|null
     */
    public static function get_user_gpa($user_or_id, $courses=null) {
        $userid = NED::get_id($user_or_id);
        if (is_null($courses)) {
            $courses = enrol_get_users_courses($userid);
        } elseif (empty($courses)){
            return null;
        } else {
            $courses = NED::val2arr($courses);
        }

        $courseaverages = [];
        foreach ($courses as $course) {
            $courseaverage = NED::get_course_grade($course->id, $userid, false, false, null);
            if (!is_null($courseaverage)) {
                $courseaverages[] = $courseaverage;
            }
        }

        if ($courseaverages) {
            return round(array_sum($courseaverages) / count($courseaverages), 2);
        }

        return 0;
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

    /**
     * Get count of student AIVs
     * Alias {@see \local_academic_integrity\infraction::get_user_aiv_count()}
     *
     * @param object|numeric|array $users_or_ids - student (id) or list of students ids
     * @param numeric|null         $startdate    - count only after some date (UNIX time)
     * @param numeric|null         $enddate      - count only before some date (UNIX time)
     * @param numeric|null         $lastdays     - count only for some last days (num of days)
     * @param object|numeric|null  $courseid     - filter by some course (otherwise count for all site)
     * @param bool                 $count_hidden - if true, count all AIVs, otherwise count only shown AIVs
     *
     * @return int|bool|null - count of the AIVs, or null, if AI plugin doesn't exist
     */
    public static function get_users_aiv($users_or_ids, $startdate, $enddate, $lastdays=0, $courseid=0, $count_hidden=false){
        if (empty($users_or_ids)) return false;
        return NED::ai_get_users_aiv_count($users_or_ids, $courseid, $startdate, $enddate, $lastdays, $count_hidden);
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

        [$coursewhere, $courseparams] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'cor');
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
            INNER JOIN (SELECT gm2.id, gm2.groupid, g2.courseid FROM {groups_members} gm2
                          JOIN {groups} g2 ON gm2.groupid = g2.id
                         WHERE gm2.userid = :staffid) sg
                    ON g.id = sg.groupid AND g.courseid = sg.courseid             
                 WHERE $filtersql 
                   AND u.deleted = 0 
                   AND gm.userid IN (SELECT cm.userid FROM {cohort_members} cm WHERE cm.cohortid = :cohortid)";

        $courseparams['staffid'] = $user->id;
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
     * @param numeric $userid
     * @param string $type For example 'general' or 'advanced'
     *
     * @return bool
     */
    public static function has_certificate_badge($userid, $type) {
        $config = get_config('local_schoolmanager');
        $badgeid = $config->{$type."_cert_badge"} ?? 0;
        if ($badgeid) {
            if ($badges = badges_get_user_badges($userid)) {
                foreach ($badges as $badge) {
                    if ($badge->id == $badgeid) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}