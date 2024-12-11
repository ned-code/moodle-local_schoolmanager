<?php

/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @noinspection PhpUnused
 */

namespace local_schoolmanager;
use block_ned_teacher_tools\deadline_manager;
use local_schoolmanager\shared_lib as NED;
use block_ned_teacher_tools\shared_lib as TT;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');

/**
 * @property-read int $view = self::CAP_CANT_VIEW_SM;
 * @property-read int $manage_schools = self::CAP_CANT_EDIT;
 * @property-read int $manage_crews = self::CAP_CANT_EDIT;
 * @property-read int $manage_members = self::CAP_CANT_EDIT;
 *
 * @property-read \context $ctx;
 * @property-read \stdClass $user;
 * @property-read int $userid
 * @property-read array $school_names
 * @property-read array $schools
 * @property-read array $potential_schools
 * @property-read array $crew_names
 * @property-read array $crews
 * @property-read array $school_users = [];
 * @property-read array $crew_users = [];
 */
class school_manager {
    const TABLE_SCHOOL = school::TABLE;
    const TABLE_CREW = 'local_schoolmanager_crew';
    const TABLE_COHORT = school::TABLE_COHORT;
    const TABLE_MEMBERS = school::TABLE_COHORT_MEMBERS;

    const CAP_CANT_VIEW_SM = 0;
    const CAP_SEE_OWN_SM = 1;
    const CAP_SEE_ALL_SM = 2;

    const CAP_CANT_EDIT = 0;
    const CAP_CAN_EDIT = 1;

    const FIELD_ROLE = NED::FIELD_DEFAULT_ROLE;
    const DEF_MEMBER_ROLE = NED::DEFAULT_ROLE_STUDENT;
    const STAFF_ROLES = [
        NED::DEFAULT_ROLE_CT, NED::DEFAULT_ROLE_OT,
        NED::DEFAULT_ROLE_SCHOOL_ADMINISTRATOR, NED::DEFAULT_ROLE_CA, NED::DEFAULT_ROLE_GC, NED::DEFAULT_ROLE_DA,
    ];
    const SCHOOL_ADMINISTRATOR_ROLE = NED::DEFAULT_ROLE_SCHOOL_ADMINISTRATOR;
    const SCHOOL_CT_ROLE = NED::DEFAULT_ROLE_CT;

    static protected $_school_managers = [];
    /**@var school[] */
    static protected $_schools_data = [];
    static protected $_all_schools_data_was_loaded = false;
    static protected $_called_crews = [];
    static protected $_called_crew_schoolids = [];

    static protected $_default_role_field_id = null;
    static protected $_default_role_default_value = null;

    protected $_view = self::CAP_CANT_VIEW_SM;
    protected $_manage_schools = self::CAP_CANT_EDIT;
    protected $_manage_schools_extra = self::CAP_CANT_EDIT;
    protected $_delete_schools = self::CAP_CANT_EDIT;
    protected $_manage_crews = self::CAP_CANT_EDIT;
    protected $_manage_members = self::CAP_CANT_EDIT;

    /** @var \context $_ctx */
    protected $_ctx;
    /** @var \stdClass $_user */
    protected $_user;
    protected $_userid = 0;

    /** @var array $_school_names */
    protected $_school_names = null;
    /** @var array|school[] $_schools */
    protected $_schools = null;
    /** @var array $_cohorts */
    protected $_potential_schools = null;
    /** @var array array $_crew_names - [schoolid => [crewid => crew_name]]*/
    protected $_crew_names = [];
    /** @var array array $_crews - [schoolid => [crewid => crew]]*/
    protected $_crews = [];
    /** @var array array $_school_users - [schoolid => [userid => user]]*/
    protected $_school_users = [];
    /** @var array array $_crew_users - [cohortid => [userid => user]]*/
    protected $_crew_users = [];
    /**
     * school_manager constructor.
     */
    public function __construct(){
        global $USER, $DB;

        $this->_ctx = \context_system::instance();
        if (NED::has_capability('viewallschooldashboards', $this->_ctx)){
            $this->_view = static::CAP_SEE_ALL_SM;
        } elseif (NED::has_capability('viewownschooldashboard', $this->_ctx)){
            $this->_view = static::CAP_SEE_OWN_SM;
        } else {
            $this->_view = static::CAP_CANT_VIEW_SM;
        }

        if ($this->_view == static::CAP_CANT_VIEW_SM){
            return;
        }

        /**
         * Set school capabilities for the current user
         * @see static::$_manage_schools
         * @see static::$_manage_schools_extra
         * @see static::$_delete_schools
         * @see static::$_manage_crews
         * @see static::$_manage_members
         */
        foreach (['manage_schools', 'manage_schools_extra', 'delete_schools', 'manage_crews', 'manage_members'] as $cap){
            $this->{'_'.$cap} = (int)NED::has_capability($cap, $this->_ctx);
        }

        $this->_user = $USER;
        $this->_userid = $USER->id;

        static::$_school_managers[$this->_userid] = $this;

        if (is_null(static::$_default_role_field_id)){
            $record = $DB->get_record('user_info_field', ['shortname' => static::FIELD_ROLE], 'id, defaultdata');
            static::$_default_role_field_id = $record->id ?? false;
            static::$_default_role_default_value = $record->defaultdata ?? '';
        }
    }

    /**
     * @return school_manager
     */
    static public function get_school_manager(){
        global $USER;
        $userid = $USER->id;

        if (!isset(static::$_school_managers[$userid])){
            static::$_school_managers[$userid] = new school_manager();
        }

        return static::$_school_managers[$userid];
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @noinspection DuplicatedCode
     */
    public function __get($name){
        $pr_name = '_' . $name;
        $method = 'get_' . $name;
        $res = null;

        if (method_exists($this, $method)){
            return $this->$method();
        }

        if (property_exists($this, $pr_name)){
            $res = ($this::${$pr_name} ?? $this->$pr_name) ?? null;
        } elseif(property_exists($this, $name)){
            $res = ($this::${$name} ?? $this->$name) ?? null;
        }

        if (is_object($res)){
            $res = clone($res);
        }

        return $res;
    }

    /**
     * Return school(s) by its id(s)
     * Warning: This function check none capabilities!
     *
     * @param array|int $ids
     * @param bool      $only_one - return \stdClass of one school by first id if true
     *
     * @return array|school[]|school|object|false
     */
    static protected function _get_school_by_ids($ids, $only_one=false){
        global $DB;
        if (empty($ids)){
            return $only_one ? false : [];
        }

        $ids = is_array($ids) ? $ids : [$ids];
        if ($only_one){
            $ids = [reset($ids)];
        }
        $ids_to_load = [];
        $data = [];
        foreach ($ids as $id){
            if (isset(static::$_schools_data[$id])){
                $data[$id] = static::$_schools_data[$id];
            } else {
                $ids_to_load[] = $id;
            }
        }

        if (!static::$_all_schools_data_was_loaded && !empty($ids_to_load)){
            [$sql, $params] = $DB->get_in_or_equal($ids_to_load, SQL_PARAMS_NAMED);
            $add_data = school::get_records_select("id $sql", $params);
            foreach ($add_data as $id => $add_datum){
                static::$_schools_data[$id] = $add_datum;
                $data[$id] = $add_datum;
            }
        }

        return $only_one ? reset($data) : $data;
    }

    /**
     * Return school(s) by its id(s)
     * Check only id, which user can see
     *
     * @param array|int $ids
     * @param bool      $only_one - return \stdClass of one school by first id if true
     *
     * @return array|school|school[]|object|false
     */
    public function get_school_by_ids($ids, $only_one=false){
        $ids = is_array($ids) ? $ids : [$ids];
        $possible_ids = $this->get_school_names();
        $check_ids = [];
        foreach ($ids as $id){
            if (isset($possible_ids[$id])){
                $check_ids[] = $id;
            }
        }

        return static::_get_school_by_ids($check_ids, $only_one);
    }

    /**
     * Check capabilities and show error if necessary
     *
     * @param array $check_other_capabilities_all - all of this capabilities should be
     * @param array $check_other_capabilities_any - any of this capabilities should be
     * @param bool  $ignore_base_capability       - check or not base capability
     */
    public function show_error_if_necessary($check_other_capabilities_all=[], $check_other_capabilities_any=[], $ignore_base_capability=false){
        $pr_error = function(){
            NED::print_module_error('nopermissions', 'error', '', get_string('checkpermissions', 'core_role'));
        };
        $ctx = $this->_ctx;

        if (!$ignore_base_capability && $this->_view == static::CAP_CANT_VIEW_SM){
            $pr_error();
        }
        if (!empty($check_other_capabilities_all) && !has_all_capabilities($check_other_capabilities_all, $ctx)){
            $pr_error();
        }
        if (!empty($check_other_capabilities_any) && !has_any_capability($check_other_capabilities_any, $ctx)){
            $pr_error();
        }
    }

    /**
     * @return array
     */
    public function get_school_names(){
        global $DB;
        do{
            if (!is_null($this->_school_names)){
                break;
            }

            $this->_school_names = [];
            if ($this->_view == static::CAP_CANT_VIEW_SM){
                break;
            }

            if ($this->_view == static::CAP_SEE_ALL_SM){
              if ($this::$_all_schools_data_was_loaded){
                  foreach ($this::$_schools_data as $school){
                      $this->_school_names[$school->id] = $school->name;
                  }
              } else {
                  $this->_school_names = $DB->get_records_menu(static::TABLE_SCHOOL, [], false, 'id, name');
              }
            } elseif ($this->_view == static::CAP_SEE_OWN_SM){
                $this->_school_names = $DB->get_records_sql_menu("
                    SELECT school.id, school.name 
                    FROM {".static::TABLE_SCHOOL."} AS school
                    JOIN {".static::TABLE_MEMBERS."} AS members
                        ON members.cohortid = school.id
                    WHERE members.userid = :userid",
                    ['userid' => $this->userid]);
            }

            $this->_school_names = $this->_school_names ?: [];
        } while(false);

        return $this->_school_names;
    }

    /**
     * @return array
     */
    public function get_schools(){
        do{
            if (!is_null($this->_schools)){
                break;
            }

            $this->_schools = [];
            if ($this->_view == static::CAP_CANT_VIEW_SM){
                break;
            }

            if ($this->_view == static::CAP_SEE_ALL_SM){
                if (!$this::$_all_schools_data_was_loaded){
                    $this::$_schools_data = school::get_records();
                    $this::$_all_schools_data_was_loaded = true;
                }
                $this->_schools = $this::$_schools_data;
            } elseif ($this->_view == static::CAP_SEE_OWN_SM){
                $this->_schools = static::_get_school_by_ids($this->get_school_names());
            }

            $this->_schools = $this->_schools ?: [];
        } while(false);

        return $this->_schools;
    }

    /**
     * Return moodle cohort, which can become schools
     *
     * @param int|null $cohortid
     *
     * @return array|object - array of cohorts or single object, if id provided
     */
    public function get_potential_schools($cohortid=null){
        global $DB;
        do{
            if (!is_null($this->_potential_schools)){
                break;
            }

            if ($this->_view == static::CAP_CANT_VIEW_SM || !$this->can_manage_schools()){
                $this->_potential_schools = [];
                break;
            }

            $sql = ["SELECT cohort.* 
                    FROM {".static::TABLE_COHORT."} AS cohort
                    LEFT JOIN {".static::TABLE_SCHOOL."} AS school
                        ON school.id = cohort.id"];
            $where = ["school.id IS NULL"];
            $params = [];

            if ($this->_view == static::CAP_SEE_OWN_SM){
                $sql[] = "JOIN {".static::TABLE_MEMBERS."} AS members
                        ON members.cohortid = school.id";
                $where[] = 'members.userid = :userid';
                $params['userid'] = $this->_userid;
            }

            if (!is_null($cohortid)){
                $where[] = 'cohort.id = :cohortid';
                $params['cohortid'] = $cohortid;
            }

            $sql = join("\n", $sql);
            $where = empty($where) ? '' : ("\nWHERE (" . join(') AND (', $where) . ')');
            $cohorts = $DB->get_records_sql($sql.$where, $params) ?: [];

            $potential_schools = [];
            foreach ($cohorts as $id => $cohort){
                $code = trim($cohort->idnumber ?? '');
                // Schools have 4-digits code
                if (strlen($code) == NED::SCHOOL_CODE_LENGTH){
                    $potential_schools[$id] = $cohort;
                }
            }

            if (!is_null($cohortid)){
                return $potential_schools[$cohortid] ?? null;
            }

            $this->_potential_schools = $potential_schools ?: [];
        } while(false);

        return !is_null($cohortid) ? ($this->_potential_schools[$cohortid] ?? null) : $this->_potential_schools;
    }

    /**
     * Function for get_crew_names and get_crews
     *
     * @param      $check_data
     * @param null $schoolid
     * @param bool $get_only_names
     *
     * @return array
     */
    protected function _get_crew_data($check_data, $schoolid=null, $get_only_names=false){
        global $DB;
        $data = [];
        do{

            if ($schoolid && !is_array($schoolid) && isset($check_data[$schoolid])){
                $data = $check_data;
                break;
            }

            if (!$schoolid){
                $schoolids = array_keys($this->school_names);
            } else {
                $schoolids = is_array($schoolid) ? $schoolid : [$schoolid];
                $schoolids = array_intersect(array_keys($this->school_names), $schoolids);
            }

            $schoolids = array_diff($schoolids, array_keys($check_data));
            if (empty($schoolids)){
                break;
            }

            [$sql_param, $params] = $DB->get_in_or_equal($schoolids, SQL_PARAMS_NAMED);
            $select = $get_only_names ? "crew.id, crew.name, crew.schoolid" : "crew.*";
            $sql = "SELECT $select
                    FROM {".static::TABLE_CREW."} AS crew
                    WHERE crew.schoolid $sql_param";
            $crews = $DB->get_records_sql($sql, $params);
            if (empty($crews)){
                break;
            }

            foreach ($crews as $crew){
                $data[$crew->schoolid][$crew->id] = $get_only_names ? $crew->name : $crew;
                if (!$get_only_names && !isset($this->_crew_names[$crew->schoolid][$crew->id])){
                    $this->_crew_names[$crew->schoolid][$crew->id] = $crew->name;
                }
                static::$_called_crew_schoolids[$crew->id] = $crew->schoolid;
            }

        } while(false);

        if (!$schoolid){
            $res_data = $data;
        } elseif (is_array($schoolid)){
            $res_data = [];
            foreach ($schoolid as $id){
                if (isset($data[$id])){
                    $res_data[$id] = $data[$id];
                }
            }
        } else {
            $res_data = $data[$schoolid] ?? [];
        }

        return [$data, $res_data];
    }

    /**
     * @param int|array $schoolid
     *
     * @return array
     */
    public function get_crew_names($schoolid=null){
        [$this->_crew_names, $res_data] = $this->_get_crew_data($this->_crew_names, $schoolid, true);
        return $res_data;
    }

    /**
     * @param int|array $schoolid
     *
     * @return array
     */
    public function get_crews($schoolid=null){
        [$this->_crews, $res_data] = $this->_get_crew_data($this->_crews, $schoolid, false);
        return $res_data;
    }

    /**
     * Be careful with using this function in circle, it loads data from DB by one row
     *
     * @param      $crewid
     * @param null $schoolid
     *
     * @return \stdClass|null
     */
    public function get_crew_by_id($crewid, $schoolid=null){
        global $DB;
        if (!is_null($schoolid)){
            if (isset($this->_crews[$schoolid][$crewid])){
                return $this->_crews[$schoolid][$crewid];
            } elseif(isset($this->_crews[$schoolid])){
                return null;
            }
        }

        $crew = static::$_called_crews[$crewid] ?? $DB->get_record(static::TABLE_CREW, ['id' => $crewid]);
        if ($crew){
            if (isset($this->school_names[$crew->schoolid])){
                static::$_called_crews[$crewid] = $crew;
                static::$_called_crew_schoolids[$crewid] = $crew->schoolid;
                return $crew;
            }
        }
        return null;
    }

    /**
     * @param array $crewids
     * @param bool  $return_one
     *
     * @return array|false|mixed|null
     */
    public function get_schoolid_by_crewids($crewids, $return_one=false){
        global $DB;
        $res = [];
        $to_load = [];
        $crewids = is_array($crewids) ? $crewids : [$crewids];
        foreach ($crewids as $crewid){
            if (isset(static::$_called_crew_schoolids[$crewid])){
                $schoolid = static::$_called_crew_schoolids[$crewid];
                if (isset($this->school_names[$schoolid])){
                    $res[$crewid] = $schoolid;
                }
            } else {
                $to_load[] = $crewid;
            }
        }

        if (!empty($to_load) && (!$return_one || empty($res))){
            [$sql_param, $params] = $DB->get_in_or_equal($to_load, SQL_PARAMS_NAMED);
            $sql = "SELECT crew.id, crew.schoolid
                    FROM {".static::TABLE_CREW."} AS crew
                    WHERE crew.id $sql_param";
            $crews = $DB->get_records_sql($sql, $params);
            foreach ($crews as $crew){
                static::$_called_crew_schoolids[$crew->id] = $crew->schoolid;
                if (isset($this->school_names[$crew->schoolid])){
                    $res[$crew->id] = $crew->schoolid;
                }
            }
        }

        return $return_one ? (reset($res) ?: null) : $res;
    }

    /**
     * Load users by schoolid for this course
     * Returned records contain user.* fields, + schoolid, crewid, def_role
     *  - where def_role - string data from user profile (example: "Student"), don't mix up with role (and its id) table
     * Note: this fill crew_users too
     *
     * @param null   $schoolid
     * @param bool   $return_one - return only first value (for one school), if true
     * @param string|array $role_names - "Student" by default
     * @param bool   $use_cache
     *
     * @return array|null
     */
    public function get_school_students($schoolid=null, $return_one=true, $role_names=self::DEF_MEMBER_ROLE, $use_cache=true){
        global $DB;
        $def_role = $role_names == static::DEF_MEMBER_ROLE;
        $role_names = empty($role_names) ? [] : (is_array($role_names) ? $role_names : [$role_names]);
        $use_cache = $use_cache && $def_role;
        if (!$schoolid){
            if (count($this->_school_users) == count($this->school_names)){
                return $this->_school_users;
            }
            $schoolids = array_keys($this->school_names);
        } else {
            $schoolids = is_array($schoolid) ? $schoolid : [$schoolid];
            $schoolids = array_intersect(array_keys($this->school_names), $schoolids);
        }

        $res = [];
        $to_load = [];
        foreach ($schoolids as $schoolid){
            if (isset($this->_school_users[$schoolid]) && $use_cache){
                $res[$schoolid] = $this->_school_users[$schoolid];
            } else {
                $to_load[] = $schoolid;
            }
        }

        if (!empty($to_load) && (!$return_one || empty($res))){
            $params = [];
            $where = [];
            NED::sql_add_get_in_or_equal_options('m.cohortid', $to_load, $where, $params);

            $where[] = 'u.suspended = 0';
            $groupby = ['u.id'];
            $select = ["u.*", "m.cohortid AS schoolid", "COALESCE(m.crewid, 0) AS crewid"];
            $joins = ["JOIN {".static::TABLE_MEMBERS."} AS m ON m.userid = u.id"];
            /** @noinspection DuplicatedCode */
            if (static::$_default_role_field_id && !empty($role_names)){
                $field_id = static::$_default_role_field_id;
                $joins[] = "LEFT JOIN {user_info_data} uid
                            ON uid.userid = u.id AND uid.fieldid = '$field_id'
                ";
                $select[] = "COALESCE(uid.data, '".static::$_default_role_default_value."') AS def_role";
                $where_role = [];
                NED::sql_add_get_in_or_equal_options('uid.data', $role_names, $where_role, $params);
                if (in_array(static::$_default_role_default_value, $role_names)){
                    $where_role []= 'uid.data IS NULL';
                }

                $where[] = NED::sql_condition($where_role, "OR");
            }

            $sql = NED::sql_generate($select, $joins, 'user', 'u', $where, $groupby);
            $members = $DB->get_records_sql($sql, $params);
            foreach ($members as $member){
                $this->_school_users[$member->schoolid][$member->id] = $member;
                $this->_crew_users[$member->crewid][$member->id] = $member;
                $res[$member->schoolid][$member->id] = $member;
            }
        }

        return $return_one ? (reset($res) ?: null) : $res;
    }

    /**
     * Load users by $crewid for this course
     *
     * @param null   $crewid
     * @param bool   $return_one - return onl first value, if true
     * @param string|array $role_names - "Student by default
     * @param bool   $use_cache
     *
     * @return array|null
     */
    public function get_crew_students($crewid=null, $return_one=true, $role_names=self::DEF_MEMBER_ROLE, $use_cache=true){
        global $DB;
        $def_role = $role_names == static::DEF_MEMBER_ROLE;
        $role_names = empty($role_names) ? [] : (is_array($role_names) ? $role_names : [$role_names]);
        $use_cache = $use_cache && $def_role;
        if (is_null($crewid)){
            $this->get_school_students(null, $return_one, $role_names, $use_cache);
            return $return_one ? reset($this->_crew_users) : $this->_crew_users;
        } else {
            $crewids = is_array($crewid) ? $crewid : [$crewid];
        }

        $res = [];
        $to_load = [];
        foreach ($crewids as $crewid){
            if (isset($this->_crew_users[$crewid]) && $use_cache){
                $res[$crewid] = $this->_crew_users[$crewid];
            } else {
                $to_load[] = $crewid;
            }
        }

        if (!empty($to_load) && (!$return_one || empty($res))){
            $params = [];
            [$sql_param, $crew_params] = $DB->get_in_or_equal($to_load, SQL_PARAMS_NAMED);
            $params = array_merge($params, $crew_params);
            $where = ["m.crewid $sql_param"];
            if (in_array(0, $to_load)){
                $where[0] .= ' OR m.crewid IS NULL';
            }
            $sql = "SELECT u.*, m.cohortid AS schoolid, COALESCE(m.crewid, 0) AS crewid
                    FROM {user} AS u
                    JOIN {".static::TABLE_MEMBERS."} AS m
                        ON m.userid = u.id";
            /** @noinspection DuplicatedCode */
            if (static::$_default_role_field_id && !empty($role_names)){
                $field_id = static::$_default_role_field_id;
                $sql .= "LEFT JOIN {user_info_data} uid
                            ON uid.userid = u.id AND uid.fieldid = '$field_id'
                ";

                [$where_role, $rn_params] = $DB->get_in_or_equal($role_names, SQL_PARAMS_NAMED, 'rolename');
                $params = array_merge($params, $rn_params);

                if (in_array(static::$_default_role_default_value, $role_names)){
                    $where_role .= ' OR uid.data IS NULL';
                }

                $where[] = 'uid.data ' . $where_role;
            }
            $where = empty($where) ? '' : ('WHERE (' . join(') AND (', $where) . ')');
            $members = $DB->get_records_sql("$sql\n$where", $params);
            foreach ($members as $member){
                if (isset($this->school_names[$member->schoolid])){
                    $this->_crew_users[$member->crewid][$member->id] = $member;
                    $res[$member->crewid][$member->id] = $member;
                }
            }
        }

        return $return_one ? (reset($res) ?: null) : $res;
    }

    /**
     * @param int $school_id
     *
     * @return object|null
     */
    public function get_school_admin($school_id){
        if (empty($school_id)) return null;

        $admins = $this->get_school_students($school_id, true, static::SCHOOL_ADMINISTRATOR_ROLE, false);

        if (empty($admins)) return null;
        return reset($admins);
    }

    /**
     * Permission to manage schools in general
     *
     * @return bool
     */
    public function can_manage_schools(){
        return $this->_manage_schools == static::CAP_CAN_EDIT;
    }

    /**
     * Permission to manage (and sometimes view) some extra fields of school
     *
     * @return bool
     */
    public function can_manage_schools_extra(){
        return $this->_manage_schools_extra == static::CAP_CAN_EDIT;
    }

    /**
     * Permission to manage (and sometimes view) some extra fields of school
     *
     * @return bool
     */
    public function can_delete_schools(){
        return $this->_delete_schools == static::CAP_CAN_EDIT;
    }

    /**
     * @return bool
     */
    public function can_manage_crews(){
        return $this->_manage_crews == static::CAP_CAN_EDIT;
    }

    /**
     * @return bool
     */
    public function can_manage_members(){
        return $this->_manage_members == static::CAP_CAN_EDIT;
    }

    /**
     * Save school object in the table
     *
     * @param $data
     *
     * @return bool|int
     */
    public function save_school($data){
        global $USER;
        $data = (object)$data;
        if (!($data->id ?? false) || !$this->can_manage_schools()){
            return false;
        }

        $can_manage_extra = $this->can_manage_schools_extra();
        $new_school = false;
        $school = null;
        $upd_school = school::get_school_by_id($data->id);
        if (!$upd_school && $school = $this->get_potential_schools($data->id)){
            $new_school = true;
            $upd_school = school::create_school_from_data();
            $upd_school->id = $school->id;
            $upd_school->cohortname = $school->name;
            $upd_school->name = $school->name;
            $upd_school->code = $school->code ?? $school->idnumber;
        }

        if (empty($upd_school)) return false;

        $administrator = $this->get_school_admin($school->id ?? null);
        $upd_school->url = $data->url ?? '';
        $upd_school->city = $data->city ?? '';
        $upd_school->country = $data->country ?? $this->_user->country ?? '';
        $upd_school->schoolyeartype = $data->schoolyeartype ?? 0;
        $upd_school->startdate = $data->startdate ?? time();
        $upd_school->enddate = $data->enddate ?? (time() + 365*24*3600);
        $upd_school->note = $data->note ?? '';
        $upd_school->iptype = $data->iptype ?? null;

        if (has_capability('local/schoolmanager:manage_extension_limit', $this->_ctx)) {
            $upd_school->extensionsallowed = $data->extensionsallowed ?? 3;
        }

        $deadlinesdata = $deadlinesdata_keys = [];

        if (NED::is_tt_exists()){
            $deadlinesdata_keys = [
                TT::X_DAYS_BETWEEN_DL_QUIZ,
                TT::X_DAYS_BETWEEN_DL_OTHER,
                TT::X_DAYS_APPLY_TO_ALL,
            ];
        }

        foreach ($deadlinesdata_keys as $deadlinesdata_key){
            if ($data->$deadlinesdata_key || $deadlinesdata_key === TT::X_DAYS_APPLY_TO_ALL){
                $deadlinesdata[$deadlinesdata_key] = $data->$deadlinesdata_key;
            }
        }

        $upd_school->deadlinesdata = (!empty($data->activatedeadlinesconfig) && !empty($deadlinesdata)) ? json_encode($deadlinesdata) : null;

        if ($can_manage_extra) {
            if (!empty($data->name)){
                $upd_school->name = $data->name;
            }
            $upd_school->synctimezone = $data->synctimezone ?? 0;
            $upd_school->forceproxysubmissionwindow = $data->forceproxysubmissionwindow ?? 0;
            $upd_school->enabletem = $data->enabletem ?? 0;
            $upd_school->extmanager = $data->extmanager ?? 0;
            $upd_school->esl = $data->esl;

            // save logo
            $data = file_postupdate_standard_filemanager($data, 'logo', ['subdirs' => 0, 'maxfiles' => 1], $this->ctx,
                NED::$PLUGIN_NAME, 'logo', $upd_school->id);
            $upd_school->logo = !empty($data->logo) ? $data->logo_filemanager : 0;

            // save compact logo
            $data = file_postupdate_standard_filemanager($data, 'compact_logo', ['subdirs' => 0, 'maxfiles' => 1], $this->ctx,
                NED::$PLUGIN_NAME, 'compact_logo', $upd_school->id);
            $upd_school->compact_logo = !empty($data->compact_logo) ? $data->compact_logo_filemanager : 0;
        }

        if ($can_manage_extra || ($administrator && $administrator->id == $USER->id)){
            $upd_school->proctormanager = !empty($data->proctormanager) ? $data->proctormanager : 0;
            $upd_school->academicintegritymanager = !empty($data->academicintegritymanager) ? $data->academicintegritymanager : 0;
        }

        if ($new_school){
            // don't use create() or save() method here, as it can't create object with ID
            $upd_school->create_with_id();
        } else {
            $upd_school->update();
        }

        static::$_schools_data[$upd_school->id] = $upd_school;
        $this->_schools[$upd_school->id] = $upd_school;
        $this->_school_names[$upd_school->id] = $upd_school->name;
        $upd_school->update_cohort_timezone($data->timezone);

        return $upd_school->id;
    }

    /**
     * Delete school object from the table
     *
     * @param $id
     *
     * @return bool
     */
    public function delete_school($id){
        global $DB;
        if (!$this->can_delete_schools()) return false;
        if (!$school = $this->get_school_by_ids($id, true)) return false;

        $DB->delete_records(static::TABLE_SCHOOL, ['id' => $id]);
        $DB->delete_records(static::TABLE_CREW, ['schoolid' => $id]);
        $DB->set_field(static::TABLE_MEMBERS, 'crewid', null, ['cohortid' => $id]);
        // remove logo
        $fs = get_file_storage();
        $fs->delete_area_files($this->ctx->id, NED::$PLUGIN_NAME, 'logo', $id);

        $school->idnumber = $school->code;
        $this->_potential_schools[$id] = $school;
        unset(static::$_schools_data[$id]);
        unset($this->_schools[$id]);
        unset($this->_school_names[$id]);
        unset($this->_crews[$id]);
        unset($this->_crew_names[$id]);
        return true;
    }

    /**
     * Save crew object in the table
     *
     * @param $data
     *
     * @return bool|int
     */
    public function save_crew($data){
        global $DB;
        $data = (object)$data;
        if (!($data->schoolid ?? false) || !$this->can_manage_crews()){
            return false;
        }

        if (!($this->school_names[$data->schoolid] ?? false)){
            return false;
        }

        $up_data = new \stdClass();
        $up_data->id = $data->id;
        $up_data->schoolid = $data->schoolid;
        $up_data->name = $data->name ?? '';
        $up_data->code = $data->code ?? '';
        $up_data->program = $data->program ?? 0;
        $year = time() + 365*24*3600;
        $up_data->admissiondate = $data->admissiondate ??  $year;
        $up_data->graduationdate = $data->graduationdate ?? $year;
        $up_data->courses = $data->courses ?? 0;
        $up_data->note = $data->note ?? '';

        $school_code = $DB->get_field(static::TABLE_SCHOOL, 'code', ['id' => $data->schoolid]);
        $code = '';
        if (!empty($school_code) && !empty($up_data->code)){
            $code = $school_code . '-' . $up_data->code;
            if (!$data->id && $DB->record_exists('cohort', ['idnumber' => $code])){
                $code = '';
            }
        }

        if (empty($code)) {
            $count = $DB->count_records(static::TABLE_CREW, ['schoolid' => $up_data->schoolid]) + 1;
            $count = $count > 9 ? $count : ('0' . $count);
            $code = $school_code . '-' . $count;
        }

        $cohort = (object)[
            'contextid' => 1,
            'name' => $up_data->name,
            'idnumber' => $code,
            'description' => '',
            'descriptionformat' => 1,
            'visible' => 1,
            'component' => NED::$PLUGIN_NAME,
            'timemodified' => time(),
        ];

        if (!$data->id){
            $cohort->timecreated = $cohort->timemodified;
            if ($new_id = $DB->insert_record(static::TABLE_COHORT, $cohort, true)){
                $up_data->id = $new_id;
            }
            $DB->insert_record_raw(static::TABLE_CREW, $up_data,  false, false, true);
            if (isset($this->_crews[$data->schoolid])){
                $this->_crews[$data->schoolid][$up_data->id] = $up_data;
            }
            return $up_data->id;
        } else {
            $cohort->id = $data->id;
            $DB->update_record(static::TABLE_COHORT, $cohort);
            return $DB->update_record(static::TABLE_CREW, $up_data);
        }
    }

    /**
     * Delete crew object from the table
     *
     * @param      $id
     * @param null $schoolid
     *
     * @return bool
     */
    public function delete_crew($id, $schoolid=null){
        global $DB;
        if (!$crew = $this->get_crew_by_id($id, $schoolid)){
            return false;
        }

        $DB->delete_records(static::TABLE_CREW, ['id' => $id]);
        $DB->delete_records(static::TABLE_COHORT, ['id' => $id]);
        $DB->set_field(static::TABLE_MEMBERS, 'crewid', null, ['crewid' => $id]);

        unset($this->_crews[$crew->schoolid][$id]);
        unset($this->_crew_names[$crew->schoolid][$id]);
        return true;
    }

    /**
     * @param int $schoolid
     * @param int|array $userids
     * @param int $crewid
     *
     * @return bool
     */
    public function change_users_crew($schoolid, $userids, $crewid){
        global $DB;

        $userids = is_array($userids) ? $userids : [$userids];
        if (!$this->can_manage_members() || empty($userids)){
            return false;
        }

        if ($crewid){
            $crew_names = $this->get_crew_names($schoolid);
            if (!isset($crew_names[$crewid])){
                return false;
            }
        } else {
            $crewid = null;
        }

        [$sql_param, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['schoolid'] = $schoolid;
        $sql = "cohortid = :schoolid AND userid $sql_param";

        if ($DB->set_field_select(static::TABLE_MEMBERS, 'crewid', $crewid, $sql, $params)){
            if (isset($this->_school_users[$schoolid])){
                foreach ($this->_school_users[$schoolid] as $userid => $user){
                    if (in_array($userid, $userids)){
                        $old_crewid = $user->crewid;
                        unset($this->_crew_users[$old_crewid][$userid]);
                        $user->crewid = $crewid;
                        $this->_crew_users[$crewid][$userid] = $user;
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Get logo of the school by its id
     *
     * @param $schoolid
     *
     * @return \moodle_url|false
     */
    static public function get_logo_url($schoolid){
        static $_data = [];
        if (!$schoolid){
            return false;
        }

        if (!isset($_data[$schoolid])){
            $fs = get_file_storage();
            $files = $fs->get_area_files(NED::ctx()->id, NED::$PLUGIN_NAME, 'logo', $schoolid,
                "itemid, filepath, filename", false);
            $logourl = false;

            foreach ($files as $file) {
                $logourl = NED::file_get_pluginfile_url($file);
                break;
            }

            $_data[$schoolid] = $logourl;
        }

        return $_data[$schoolid];
    }

    /**
     * Get compact logo of the school by its id
     *
     * @param $schoolid
     *
     * @return \moodle_url|false
     */
    static public function get_compact_logo_url($schoolid){
        static $_data = [];
        if (!$schoolid){
            return false;
        }

        if (!isset($_data[$schoolid])){
            $fs = get_file_storage();
            $files = $fs->get_area_files(NED::ctx()->id, NED::$PLUGIN_NAME, 'compact_logo', $schoolid,
                "itemid, filepath, filename", false);
            $logourl = false;

            foreach ($files as $file) {
                $logourl = NED::file_get_pluginfile_url($file);
                break;
            }

            $_data[$schoolid] = $logourl;
        }

        return $_data[$schoolid];
    }

    /**
     * @param $schoolid
     * @return array
     * @throws \dml_exception
     */
    public static function get_school_classes($schoolid) {
        global $DB;

        $school = school::get_school_by_id($schoolid);

        $codefilter = $DB->sql_like('g.name', ':code', false, false);
        $params['code'] = $DB->sql_like_escape($school->code).'%';
        $params['schedule'] = deadline_manager::SCHEDULE_FULL;
        $params['enddate'] = time();


        $sql = "SELECT g.id, g.courseid, g.name
                  FROM {groups} g
                 WHERE {$codefilter}
                   AND g.schedule = :schedule
                   AND g.enddate > :enddate
              ORDER BY g.name";

        return $DB->get_records_sql($sql, $params);
    }
}
