<?php
/**
 * @package    local_schoolmanager
 * @category   NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

use local_tem\helper as tem_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Class shared_lib
 *
 * @package local_schoolmanager
 */
class shared_lib extends \local_ned_controller\shared\base_class {
    use \local_ned_controller\shared\base_trait;

    /**
     * @var string|\local_schoolmanager\school_manager
     */
    static $SM = '\\local_schoolmanager\\school_manager';

    /**
     * @param $users
     * @param $lastdays
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    static function count_logged_user($users, $lastdays) {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($users);

        $params[] = time() - $lastdays * DAYSECS;

        $sql = "SELECT COUNT(u.id)
                  FROM {user} u
	             WHERE u.id {$insql}
	               AND u.lastlogin 
	               AND u.lastlogin >= ?";

        return $DB->count_records_sql($sql, $params);
    }

    static function count_not_logged_user($users, $lastdays) {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($users);

        $params[] = time() - $lastdays * DAYSECS;

        $sql = "SELECT COUNT(u.id)
                  FROM {user} u
	             WHERE u.id {$insql}
	               AND (NOT u.lastlogin 
	               OR u.lastlogin < ?)";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Get $complete/$incomplete statistics by school groups
     *
     * @param string $school_code
     * @param int $startdate - UNIX time
     * @param int $enddate - UNIX time
     *
     * @return int[] - [$complete, $incomplete, $classes, $completeended, $incompleteended]
     */
    static function count_dm_schedule($school_code, $startdate=0, $enddate=0) {
        $complete = 0;
        $incomplete = 0;
        $classes = 0;
        $completeended = 0;
        $incompleteended = 0;
        if (!static::is_tt_exists() || empty($school_code)){
            return [$complete, $incomplete, $classes, $completeended, $incompleteended];
        }

        $joins = ["JOIN {course} c ON g.courseid = c.id"];
        $where = ["c.visible = 1", static::db()->sql_like('g.name', ':code', false, false)];
        $params = ['code' => static::db()->sql_like_escape($school_code).'%'];

        if (!empty($startdate) && !empty($enddate)){
            $time_conds = [];
            static::sql_add_between('g.startdate', $startdate, $enddate, $time_conds, $params);
            static::sql_add_between('g.enddate', $startdate, $enddate, $time_conds, $params);

            $gr_inside_year = [];
            static::sql_add_condition("g.startdate", $startdate, $gr_inside_year, $params, static::SQL_COND_LTE);
            static::sql_add_condition("g.enddate", $enddate, $gr_inside_year, $params, static::SQL_COND_GTE);
            $time_conds[] = static::sql_condition($gr_inside_year);

            $where[] = static::sql_condition($time_conds, "OR");
        } elseif (!empty($startdate)){
            static::sql_add_condition("g.enddate", $startdate, $where, $params, static::SQL_COND_GT);
        } elseif (!empty($enddate)){
            static::sql_add_condition("g.startdate", $enddate, $where, $params, static::SQL_COND_LT);
        }

        $sql = static::sql_generate("g.*", $joins, "groups", "g", $where);
        $groups = static::db()->get_records_sql($sql, $params);
        $now = time();
        foreach ($groups as $group) {
            if (!$group->schedule) continue;

            $deadlinemanager = new \block_ned_teacher_tools\deadline_manager($group->courseid);
            $classes++;
            if ($deadlinemanager->has_missed_schedule($group->id)){
                $incomplete++;
                if ($group->enddate < $now) {
                    $incompleteended++;
                }
            } else {
                $complete++;
                if ($group->enddate < $now) {
                    $completeended++;
                }
            }
        }

        return [$complete, $incomplete, $classes, $completeended, $incompleteended];
    }

    /**
     * @return int - UNIX timestamp
     */
    static public function get_default_school_year_start(){
        return strtotime(get_config('local_schoolmanager', 'defaultschoolyearstart'));
    }

    /**
     * @return int - UNIX timestamp
     */
    static public function get_default_school_year_end(){
        return strtotime(get_config('local_schoolmanager', 'defaultschoolyearend'));
    }

    /**
     * Return formatted school year dates
     *
     * @param int|null $school_year_start - UNIX time or null (uses default school year start)
     * @param int|null $school_year_end - UNIX time or null (uses default school year end)
     * @param string $format - string format for dates
     *
     * @return string
     */
    static public function get_format_school_year($school_year_start=null, $school_year_end=null, $format=self::DT_FORMAT_DATE) {
        $school_year_start = $school_year_start ?? static::get_default_school_year_start();
        $school_year_end = $school_year_end ?? static::get_default_school_year_end();
        return
            static::ned_date($school_year_start, '', null, $format).
            ' – '.
            static::ned_date($school_year_end, '', null, $format);
    }

    /**
     * @param string  $school_code
     * @param int     $startdate - UNIX time
     * @param int     $enddate   - UNIX time
     * @param numeric $last_days
     *
     * @return int
     */
    public static function count_school_classes_enddate_extensions($school_code, $startdate=0, $enddate=0, $last_days=0) {
        if (!static::is_tt_exists() || empty($school_code)) return 0;

        $where = [static::db()->sql_like('g.name', ':code', false, false)];
        $params = ['code' => static::db()->sql_like_escape($school_code).'%'];
        static::sql_add_equal("r.changetype", 'classenddate', $where, $params);

        if ($last_days){
            $startdate2 = time() - $last_days * DAYSECS;
            $startdate = empty($startdate) ? $startdate2 : max($startdate, $startdate2);
        }

        static::sql_add_between("r.timecreated", $startdate, $enddate, $where, $params, true);
        $where = static::sql_where($where);

        $sql = "SELECT COUNT(1)
                    FROM {block_ned_teacher_tools_cued} r
                    INNER JOIN {groups} g ON r.groupid = g.id
                    $where";

        return static::db()->count_records_sql($sql, $params);
    }

    /**
     *
     * @param int $schoolid
     * @param array $args
     *
     * @return array|{sql: string, params: array} - can be empty, if user can see nothing
     */
    public static function get_school_proctoring_sqlquery(int $schoolid, array $args): array {
        $where = [];
        $params = [];

        if (!static::is_tem_exists() || empty($args['filter']) || !$schoolid) return [];
        if (!tem_helper::get_view_filter(null, $schoolid, $where, $params)) return [];

        $select = empty($args['fields']) ? 'COUNT(1)' : implode(',', $args['fields']);

        if (!empty($args['overdue'])){
            static::sql_add_condition("s.timefinish", time() - $args['overdue'], $where, $params, static::SQL_COND_LT);
        }
        static::sql_add_between("s.timestart", $args['starttime'] ?? 0, $args['endtime'] ?? 0,
            $where, $params, true);

        $where[] = match($args['filter']){
            tem_helper::FILTER_ACTION_REQUIRED => "(s.timescheduled  = 0 OR s.proctor = 0 OR r.id IS NULL)",
            tem_helper::FILTER_COMPLETED => "(s.timescheduled  != 0 AND s.proctor <> 0 AND r.id IS NOT NULL)",
            default => ''
        };
        if (empty($args['show_disabled'])){
            $where[] = "sc.enabletem = 1";
        }

        $where[] = "
            sg.quizid NOT IN (
                   SELECT cm.instance 
                   FROM {tag_instance} ti
                   JOIN {tag} t ON  ti.tagid = t.id
                   JOIN {course_modules} cm ON ti.itemid = cm.id
                   JOIN {modules} m ON cm.module= m.id
                  WHERE ti.itemtype = 'course_modules' AND t.name = 'tem excluded' AND m.name = 'quiz'
            )
        ";

        $ss = \local_tem\tem::TABLE_SESSION;
        $sg = \local_tem\tem::TABLE_SESSGROUP;
        $tr = \local_tem\tem::TABLE_REPORT;
        $t_school = \local_schoolmanager\school::TABLE;
        $where_sql = static::sql_where($where);
        $sql = "
            SELECT $select
            FROM {{$ss}} AS s
            JOIN {{$sg}} sg ON sg.id = s.sessgroupid
            LEFT JOIN {{$tr}} AS r ON s.id = r.sessionid
            LEFT JOIN {user} AS u ON s.proctor = u.id
            LEFT JOIN {{$t_school}} AS sc ON sg.schoolid = sc.id
            JOIN {course} AS c ON sg.courseid = c.id
            JOIN {groups} AS g ON sg.groupid = g.id
            JOIN {quiz} AS q ON sg.quizid = q.id
            $where_sql
        ";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @param numeric $schoolid
     * @param int     $starttime - UNIX time
     * @param int     $endtime   - UNIX time
     * @param int     $overdue
     *
     * @return int
     */
    public static function count_school_proctoring_reports($schoolid, int $starttime = 0, int $endtime = 0, int $overdue = 0){
        if (!static::is_tem_exists() || empty($schoolid)) return 0;

        $args = [
            'filter' => tem_helper::FILTER_ACTION_REQUIRED,
            'overdue' => $overdue,
            'starttime' => $starttime,
            'endtime' => $endtime
        ];

        $query = static::get_school_proctoring_sqlquery($schoolid, $args);
        if (empty($query)) return 0;

        return static::db()->count_records_sql($query['sql'], $query['params']);
    }

    /**
     * Check if the user can view the class enrollment report
     *
     * @return bool True if the user has the capability to view the report, false otherwise
     */
    public static function can_view_class_enrollment_report() {
        $contextsystem = \context_system::instance();
        return has_capability('report/ghs:viewgroupenrollment', $contextsystem) ||
            \report_ghs\helper::has_capability_in_any_course('report/ghs:viewgroupenrollment');

    }

    /**
     * Retrieve a list of regions with their respective codes.
     *
     * @return string[] - An associative array where keys and values represent region codes and names.
     */
    public static function get_region_list() {
        return [
            'SM' => 'SM',
            'CN' =>'CN',
            'NA' => 'NA'
        ];
    }

    /**
     * Retrieves a list of school groups.
     *
     * @return array Associative array where keys are the school group codes and values are their corresponding names.
     */
    public static function get_schoolgroup_list() {
        return [
            'None' => 'None',
            'New Oriental' => 'New Oriental',
            'DEOU Group' =>'DEOU Group',
            'Oakwood' => 'Oakwood',
            'YesEdu' => 'YesEdu',
            'Walton' => 'Walton'
        ];
    }

    /**
     * Getting showall parameter from url or from user cache
     *
     * @return bool
     */
    public static function get_showallschools_param_value(){
        $userscache = static::get_user_cache();
        $showallschools = optional_param(static::PAR_SHOWALL, null, PARAM_BOOL);

        if (!is_null($showallschools)){
            $userscache->set(static::$C::CACHE_USERS_KEY_SHOWALLSCHOOLS, $showallschools);
        } else {
            $showallschools = false;
            $prev_url = static::urL_get_prev_url();
            if (static::is_schm_page($prev_url) || static::is_frontdashboard_page($prev_url)){
                $showallschools = $userscache->get(static::$C::CACHE_USERS_KEY_SHOWALLSCHOOLS);
            } else {
                $userscache->delete(static::$C::CACHE_USERS_KEY_SHOWALLSCHOOLS);
            }
        }

        return $showallschools;
    }
}

shared_lib::init();
