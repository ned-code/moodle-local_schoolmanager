<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

use core\persistent;
use cacheable_object;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class school extends persistent implements cacheable_object  {

    /** Table name for the persistent. */
    const TABLE = 'local_schoolmanager_school';

    /**
     * @var
     */
    protected $cohort;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param stdClass $record If set will be passed to {@see self::from_record()}.
     */
    public function __construct(int $id = 0, stdClass $record = null) {
        parent::__construct($id, $record);
        $this->set_cohort();
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'name' => array(
                'type' => PARAM_RAW,
            ),
            'code' => array(
                'type' => PARAM_RAW,
            ),
            'url' => array(
                'type' => PARAM_RAW,
            ),
            'city' => array(
                'type' => PARAM_RAW,
            ),
            'country' => array(
                'type' => PARAM_RAW,
            ),
            'logo' => array(
                'type' => PARAM_INT,
            ),
            'compact_logo' => array(
                'type' => PARAM_INT,
            ),
            'startdate' => array(
                'type' => PARAM_INT,
            ),
            'enddate' => array(
                'type' => PARAM_INT,
            ),
            'note' => array(
                'type' => PARAM_RAW,
            ),
            'synctimezone' => array(
                'type' => PARAM_INT,
            ),
        );
    }

    /**
     * Get all records from a user's username.
     *
     * @param string $username The username.
     * @return status[]
     */
    public static function get_records_by_username($username) {
        global $DB;

        $sql = 'SELECT s.*
                  FROM {' . static::TABLE . '} s
                  JOIN {user} u
                    ON u.id = s.userid
                 WHERE u.username = :username';

        $persistents = [];

        $recordset = $DB->get_recordset_sql($sql, ['username' => $username]);
        foreach ($recordset as $record) {
            $persistents[] = new static(0, $record);
        }
        $recordset->close();

        return $persistents;
    }

    /**
     * Prepares the object for caching. Works like the __sleep method.
     *
     * @return mixed The data to cache, can be anything except a class that implements the cacheable_object... that would
     *      be dumb.
     */
    public function prepare_to_cache() {
        return $this->to_record();
    }

    /**
     * Takes the data provided by prepare_to_cache and reinitialises an instance of the associated from it.
     *
     * @param mixed $data
     * @return object The instance for the given data.
     */
    public static function wake_from_cache($data) {
        return new self(0, $data);
    }

    /**
     * Get cohort
     *
     * @return object|false
     */
    public function get_cohort() {
        return $this->cohort;
    }

    /**
     * @return mixed
     */
    public function get_timezone() {
        return $this->cohort->timezone;
    }

    /**
     * @return string
     */
    public function get_localtime() {
        return userdate(time(), '%I:%M %p', $this->cohort->timezone);
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    public function get_schoolyear() {
        return date('j M Y', $this->get('startdate')) . ' - ' .  date('j M Y', $this->get('enddate'));
    }

    /**
     * Sets the cohort to use in get_cohort()
     */
    public function set_cohort() {
        global $DB;
        $this->cohort = $DB->get_record('cohort', ['id' => $this->get('id')]);
    }

    /**
     * @throws \dml_exception
     */
    public function reset_time_zone() {
        global $DB;
        $sql = "SELECT u.id, u.timezone
                  FROM {cohort_members} cm
                  JOIN {user} u ON cm.userid = u.id
                 WHERE cm.cohortid = ?";
        if ($users = $DB->get_records_sql($sql, [$this->cohort->id])) {
            foreach ($users as $user) {
                $user->timezone = $this->cohort->timezone;
                $DB->update_record('user', $user);
            }
        }
    }
}
