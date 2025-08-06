<?php

/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

use local_schoolmanager\shared_lib as NED;

defined('MOODLE_INTERNAL') || die();

/**
 * School object
 *
 * For properties @see school::define_properties()
 * @property int    id
 * @property string cohortname
 * @property string name
 * @property string code
 * @property string url
 * @property string city
 * @property string country
 * @property string region
 * @property string schoolgroup
 * @property int    logo
 * @property int    compact_logo
 * @property int    schoolyeartype
 * @property int    startdate
 * @property int    enddate
 * @property string note - HTML data
 * @property bool   synctimezone
 * @property bool   enabletem
 * @property bool   videosubmissionrequired
 * @property bool   esl
 * @property int    extmanager
 * @property int    iptype
 * @property int    reportipchange
 * @property int    showipchange
 * @property int    reportiptem
 * @property int    proctormanager
 * @property int    academicintegritymanager
 * @property int    forceproxysubmissionwindow
 * @property int    extensionsallowed
 * @property string deadlinesdata
 * @property int    hidecompliancereport
 * @property int    timecreated
 * @property int    timemodified
 */
class school extends \core\persistent implements \cacheable_object  {

    /** Table name for the \core\persistent. */
    public const TABLE = 'local_schoolmanager_school';
    public const TABLE_COHORT = 'cohort';
    public const TABLE_COHORT_MEMBERS = 'cohort_members';

    public const EXT_MANAGE_CT = 0;
    public const EXT_MANAGE_SA = 1;
    public const EXTENSION_MANAGER = [
        self::EXT_MANAGE_CT => 'ct',
        self::EXT_MANAGE_SA => 'sa',
    ];
    public const IP_TYPE_STATIC = 1;
    public const IP_TYPE_DYNAMIC = 2;
    public const IP_TYPES = [
        self::IP_TYPE_STATIC  => 'static',
        self::IP_TYPE_DYNAMIC => 'dynamic',
    ];
    public const PROXY_SUBMISSION_WINDOW_5HOURS = 18000;
    public const PROXY_SUBMISSION_WINDOW_12HOURS = 43200;
    public const PROXY_SUBMISSION_WINDOW_24HOURS = 86400;
    public const PROXY_SUBMISSION_WINDOWS = [
        self::PROXY_SUBMISSION_WINDOW_5HOURS  => 'fivehours',
        self::PROXY_SUBMISSION_WINDOW_12HOURS => 'twelvehours',
        self::PROXY_SUBMISSION_WINDOW_24HOURS => 'twentyfourhours',
    ];
    /**
     * @var object|false - do not use directly, {@see get_cohort()}
     */
    protected $_cohort;

    /**
     * Create an instance of this class.
     *
     * @param int            $id     If set, this is the id of an existing record, used to load the data.
     * @param \stdClass|null $record If set will be passed to {@see self::from_record()}.
     */
    public function __construct(int $id = 0, \stdClass $record = null){
        parent::__construct($id, $record);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name){
        if (!static::has_property($name)) return null;

        return $this->get($name);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     */
    public function __set($name, $value){
        if (!static::has_property($name)) return null;

        $this->set($name, $value);

        return $value;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name){
        if (!static::has_property($name)) return false;

        return !is_null($this->get($name));
    }

    /**
     * Return the definition of the properties of this model.
     * Properties usermodified, timemodified, timecreated are defined by default, {@see static::properties_definition()}
     *
     * @return array
     */
    protected static function define_properties(){
        return [
            'cohortname' => [
                'type' => PARAM_RAW,
            ],
            'name' => [
                'type' => PARAM_RAW,
            ],
            'code' => [
                'type' => PARAM_RAW,
            ],
            'url' => [
                'type' => PARAM_RAW,
            ],
            'city' => [
                'type' => PARAM_RAW,
            ],
            'country' => [
                'type' => PARAM_RAW,
            ],
            'region' => [
                'type' => PARAM_RAW,
            ],
            'schoolgroup' => [
                'type' => PARAM_RAW,
            ],
            'logo' => [
                'type' => PARAM_INT,
            ],
            'compact_logo' => [
                'type' => PARAM_INT,
            ],
            'schoolyeartype' => [
                'type' => PARAM_INT,
            ],
            'startdate' => [
                'type' => PARAM_INT,
            ],
            'enddate' => [
                'type' => PARAM_INT,
            ],
            'note' => [
                'type' => PARAM_RAW,
            ],
            'synctimezone' => [
                'type' => PARAM_INT,
            ],
            'extmanager' => [
                'type' => PARAM_INT,
            ],
            'iptype' => [
                'type' => PARAM_INT,
            ],
            'reportipchange' => [
                'type' => PARAM_INT,
            ],
            'showipchange' => [
                'type' => PARAM_INT,
            ],
            'reportiptem' => [
                'type' => PARAM_INT,
            ],
            'proctormanager' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'enabletem' => [
                'type' => PARAM_INT,
            ],
            'videosubmissionrequired' => [
                'type' => PARAM_INT,
            ],
            'esl' => [
                'type' => PARAM_INT,
            ],
            'academicintegritymanager' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'forceproxysubmissionwindow' => [
                'type' => PARAM_INT,
            ],
            'extensionsallowed' => [
                'type' => PARAM_INT,
            ],
            'deadlinesdata' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'hidecompliancereport' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * Prepares the object for caching. Works like the __sleep method.
     *
     * @return object The data to cache, can be anything except a class that implements the \cacheable_object... that would
     *      be dumb.
     */
    public function prepare_to_cache(){
        return $this->to_record();
    }

    /**
     * Takes the data provided by prepare_to_cache and reinitialises an instance of the associated from it.
     *
     * @param mixed $data
     * @return object The instance for the given data.
     */
    public static function wake_from_cache($data){
        return new static(0, $data);
    }

    /**
     * Like {@see static::create()}, but allow to set custom ID
     *
     * @param int|null $save_id - (optional) ID to save, if null - uses id from the object
     *
     * @return static
     * @throws \coding_exception
     */
    public function create_with_id($save_id=null){
        $id = $save_id ?? $this->raw_get('id');
        if (!$id){
            throw new \coding_exception('Cannot save an object with ID without ID.');
        }

        $this->raw_set('id', 0);
        $this->create();
        NED::db()->set_field(static::TABLE, 'id', $id, ['id' => $this->raw_get('id')]);

        return $this;
    }

    /**
     * Get cohort
     *
     * @return object|false
     */
    public function get_cohort(){
        if (is_null($this->_cohort)){
            $this->_cohort = NED::db()->get_record('cohort', ['id' => $this->id]);
        }

        return $this->_cohort;
    }

    /**
     * @return mixed
     */
    public function get_timezone(){
        return $this->get_cohort()->timezone ?? 99;
    }

    /**
     * @return string
     */
    public function get_localtime(){
        return NED::ned_date(time(), '-', null, NED::DT_FORMAT_TIME12, $this->get_timezone());
    }

    /**
     * @return string
     */
    public function _get_startdate(){
        if ($this->get('schoolyeartype')){
            return NED::get_default_school_year_start();
        } else {
            return $this->get('startdate') ?? 0;
        }
    }

    /**
     * @return string
     */
    public function _get_enddate(){
        if ($this->get('schoolyeartype')){
            return NED::get_default_school_year_end();
        } else {
            return $this->get('enddate') ?? 0;
        }
    }

    /**
     * Return formatted school year dates
     *
     * @return string
     */
    public function get_schoolyear(){
        return NED::get_format_school_year($this->_get_startdate(), $this->_get_enddate());
    }

    /**
     * Update timezone in moodle cohort
     *
     * @param int|string $timezone
     *
     * @return void
     */
    public function update_cohort_timezone($timezone=99){
        NED::db()->set_field(static::TABLE_COHORT, 'timezone', $timezone, ['id' => $this->id]);
    }

    /**
     * @throws \dml_exception
     */
    public function reset_time_zone(){
        $sql = "SELECT u.id, u.timezone
                  FROM {".static::TABLE_COHORT_MEMBERS."} cm
                  JOIN {user} u ON cm.userid = u.id
                 WHERE cm.cohortid = ?";
        if ($users = NED::db()->get_records_sql($sql, [$this->id])){
            foreach ($users as $user){
                $user->timezone = $this->get_timezone();
                NED::db()->update_record('user', $user);
            }
        }
    }

    /**
     * @param int $id
     *
     * @return static|false
     */
    public static function get_school_by_id($id){
        if (!static::record_exists($id)) return false;
        return new static($id);
    }

    /**
     * @param object|null $data
     *
     * @return static
     */
    public static function create_school_from_data($data=null){
        return new static(0, $data);
    }
}
