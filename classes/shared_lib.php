<?php
/**
 * @package    local_schoolmanager
 * @category   NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

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

        list($insql, $params) = $DB->get_in_or_equal($users);

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

        list($insql, $params) = $DB->get_in_or_equal($users);

        $params[] = time() - $lastdays * DAYSECS;

        $sql = "SELECT COUNT(u.id)
                  FROM {user} u
	             WHERE u.id {$insql}
	               AND (NOT u.lastlogin 
	               OR u.lastlogin < ?)";

        return $DB->count_records_sql($sql, $params);
    }

    static function count_dm_scheule($code) {
        global $DB;

        $filter = $DB->sql_like('g.name', ':code', false, false);
        $params['code'] = $DB->sql_like_escape($code).'%';

        $sql = "SELECT g.*
                  FROM {groups}  g
	              JOIN {course} c
                    ON g.courseid = c.id
                 WHERE $filter 
                   AND c.visible = 1";

        $complete = 0;
        $incomplete = 0;
        $classes = 0;
        $completeended = 0;
        $incompleteended = 0;

        if ($groups = $DB->get_records_sql($sql, $params)) {
            foreach ($groups as $group) {
                if ($group->schedule) {
                    $deadlinemanager = new \block_ned_teacher_tools\deadline_manager($group->courseid);
                    $classes++;
                    if ($missedschedule = $deadlinemanager->has_missed_schedule($group->id)) {
                        $incomplete++;
                        if ($group->enddate < time()) {
                            $incompleteended++;
                        }
                    } else {
                        $complete++;
                        if ($group->enddate < time()) {
                            $completeended++;
                        }
                    }
                }
            }
        }

        return [$complete, $incomplete, $classes, $completeended, $incompleteended];
    }
}

shared_lib::init();
