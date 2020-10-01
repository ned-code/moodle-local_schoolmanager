<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

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
class school_manager{
    const TABLE_SCHOOL = 'local_schoolmanager_school';
    const TABLE_CREW = 'local_schoolmanager_crew';
    const TABLE_COHORT = 'cohort';
    const TABLE_MEMBERS = 'cohort_members';

    const CAP_CANT_VIEW_SM = 0;
    const CAP_SEE_OWN_SM = 1;
    const CAP_SEE_ALL_SM = 2;

    const CAP_CANT_EDIT = 0;
    const CAP_CAN_EDIT = 1;

    const FIELD_ROLE = 'default_role';
    const DEF_MEMBER_ROLE = 'Student';

    static protected $_school_managers = [];
    static protected $_schools_data = [];
    static protected $_all_schools_data_was_loaded = false;
    static protected $_called_crews = [];
    static protected $_called_crew_schoolids = [];

    static protected $_default_role_field_id = null;
    static protected $_default_role_default_value = null;

    protected $_view = self::CAP_CANT_VIEW_SM;
    protected $_manage_schools = self::CAP_CANT_EDIT;
    protected $_manage_crews = self::CAP_CANT_EDIT;
    protected $_manage_members = self::CAP_CANT_EDIT;

    /** @var \context $_ctx */
    protected $_ctx;
    /** @var \stdClass $_user */
    protected $_user;
    protected $_userid = 0;

    /** @var array $_school_names */
    protected $_school_names = null;
    /** @var array $_schools */
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
        if (has_capability(PLUGIN_CAPABILITY.'viewallschooldashboards', $this->_ctx)){
            $this->_view = self::CAP_SEE_ALL_SM;
        } elseif (has_capability(PLUGIN_CAPABILITY.'viewownschooldashboard', $this->_ctx)){
            $this->_view = self::CAP_SEE_OWN_SM;
        } else {
            $this->_view = self::CAP_CANT_VIEW_SM;
        }

        if ($this->_view == self::CAP_CANT_VIEW_SM){
            return;
        }

        foreach (['manage_schools', 'manage_crews', 'manage_members'] as $cap){
            $this->{'_'.$cap} = (int)has_capability(PLUGIN_CAPABILITY.$cap, $this->_ctx);
        }

        $this->_user = $USER;
        $this->_userid = $USER->id;

        self::$_school_managers[$this->_userid] = $this;

        if (is_null(self::$_default_role_field_id)){
            $record = $DB->get_record('user_info_field', ['shortname' => self::FIELD_ROLE], 'id, defaultdata');
            self::$_default_role_field_id = $record->id ?? false;
            self::$_default_role_default_value = $record->defaultdata ?? '';
        }
    }

    /**
     * @return school_manager
     */
    static public function get_school_manager(){
        global $USER;
        $userid = $USER->id;

        if (!isset(self::$_school_managers[$userid])){
            self::$_school_managers[$userid] = new school_manager();
        }

        return self::$_school_managers[$userid];
    }

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
     * @return array|\stdClass|false
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
            if (isset(self::$_schools_data[$id])){
                $data[$id] = self::$_schools_data[$id];
            } else {
                $ids_to_load[] = $id;
            }
        }

        if (!self::$_all_schools_data_was_loaded && !empty($ids_to_load)){
            list($sql, $params) = $DB->get_in_or_equal($ids_to_load, SQL_PARAMS_NAMED);
            $add_data = $DB->get_records_sql("SELECT * FROM {".self::TABLE_SCHOOL."} AS school WHERE school.id $sql", $params) ?: [];
            foreach ($add_data as $id => $add_datum){
                self::$_schools_data[$id] = $add_datum;
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
     * @return array|\stdClass|false
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

        return self::_get_school_by_ids($check_ids, $only_one);
    }

    /**
     * Check capabilities an show error if necessary
     *
     * @param array $check_other_capabilities_all - all of this capabilities should be
     * @param array $check_other_capabilities_any - any of this capabilities should be
     * @param bool  $ignore_base_capability       - check or not base capability
     */
    public function show_error_if_necessary($check_other_capabilities_all=[], $check_other_capabilities_any=[], $ignore_base_capability=false){
        $pr_error = function(){
            print_error('nopermissions', 'error', '', get_string('checkpermissions', 'core_role'));
        };
        $ctx = $this->_ctx;

        if (!$ignore_base_capability && $this->_view == self::CAP_CANT_VIEW_SM){
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
            if ($this->_view == self::CAP_CANT_VIEW_SM){
                break;
            }

            if ($this->_view == self::CAP_SEE_ALL_SM){
              if ($this::$_all_schools_data_was_loaded){
                  foreach ($this::$_schools_data as $school){
                      $this->_school_names[$school->id] = $school->name;
                  }
              } else {
                  $this->_school_names = $DB->get_records_menu(self::TABLE_SCHOOL, [], false, 'id, name');
              }
            } elseif ($this->_view == self::CAP_SEE_OWN_SM){
                $this->_school_names = $DB->get_records_sql_menu("
                    SELECT school.id, school.name 
                    FROM {".self::TABLE_SCHOOL."} AS school
                    JOIN {".self::TABLE_MEMBERS."} AS members
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
        global $DB;
        do{
            if (!is_null($this->_schools)){
                break;
            }

            $this->_schools = [];
            if ($this->_view == self::CAP_CANT_VIEW_SM){
                break;
            }

            if ($this->_view == self::CAP_SEE_ALL_SM){
                if (!$this::$_all_schools_data_was_loaded){
                    $this::$_schools_data = $DB->get_records(self::TABLE_SCHOOL);
                    $this::$_all_schools_data_was_loaded = true;
                }
                $this->_schools = $this::$_schools_data;
            } elseif ($this->_view == self::CAP_SEE_OWN_SM){
                $this->_schools = self::_get_school_by_ids($this->get_school_names());
            }

            $this->_schools = $this->_schools ?: [];
        } while(false);

        return $this->_schools;
    }

    /**
     * Return moodle cohort, which can become schools
     *
     * @param null $cohortid
     *
     * @return array
     */
    public function get_potential_schools($cohortid=null){
        global $DB;
        do{
            if (!is_null($this->_potential_schools)){
                break;
            }

            if ($this->_view == self::CAP_CANT_VIEW_SM || !$this->can_manage_schools()){
                $this->_potential_schools = [];
                break;
            }

            $sql = ["SELECT cohort.* 
                    FROM {".self::TABLE_COHORT."} AS cohort
                    LEFT JOIN {".self::TABLE_SCHOOL."} AS school
                        ON school.id = cohort.id"];
            $where = ["school.id IS NULL"];
            $params = [];

            if ($this->_view == self::CAP_SEE_OWN_SM){
                $sql[] = "JOIN {".self::TABLE_MEMBERS."} AS members
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
                if (strlen($code) == 4){
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

            list($sql_param, $params) = $DB->get_in_or_equal($schoolids, SQL_PARAMS_NAMED);
            $select = $get_only_names ? "crew.id, crew.name, crew.schoolid" : "crew.*";
            $sql = "SELECT $select
                    FROM {".self::TABLE_CREW."} AS crew
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
                self::$_called_crew_schoolids[$crew->id] = $crew->schoolid;
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
        list($this->_crew_names, $res_data) = $this->_get_crew_data($this->_crew_names, $schoolid, true);
        return $res_data;
    }

    /**
     * @param int|array $schoolid
     *
     * @return array
     */
    public function get_crews($schoolid=null){
        list($this->_crews, $res_data) = $this->_get_crew_data($this->_crews, $schoolid, false);
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

        $crew = self::$_called_crews[$crewid] ?? $DB->get_record(self::TABLE_CREW, ['id' => $crewid]);
        if ($crew){
            if (isset($this->school_names[$crew->schoolid])){
                self::$_called_crews[$crewid] = $crew;
                self::$_called_crew_schoolids[$crewid] = $crew->schoolid;
                return $crew;
            }
        }
        return null;
    }

    public function get_schoolid_by_crewids($crewids, $return_one=false){
        global $DB;
        $res = [];
        $to_load = [];
        $crewids = is_array($crewids) ? $crewids : [$crewids];
        foreach ($crewids as $crewid){
            if (isset(self::$_called_crew_schoolids[$crewid])){
                $schoolid = self::$_called_crew_schoolids[$crewid];
                if (isset($this->school_names[$schoolid])){
                    $res[$crewid] = $schoolid;
                }
            } else {
                $to_load[] = $crewid;
            }
        }

        if (!empty($to_load) && (!$return_one || empty($res))){
            list($sql_param, $params) = $DB->get_in_or_equal($to_load, SQL_PARAMS_NAMED);
            $sql = "SELECT crew.id, crew.schoolid
                    FROM {".self::TABLE_CREW."} AS crew
                    WHERE crew.id $sql_param";
            $crews = $DB->get_records_sql($sql, $params);
            foreach ($crews as $crew){
                self::$_called_crew_schoolids[$crew->id] = $crew->schoolid;
                if (isset($this->school_names[$crew->schoolid])){
                    $res[$crew->id] = $crew->schoolid;
                }
            }
        }

        return $return_one ? (reset($res) ?: null) : $res;
    }

    /**
     * Load users by schoolid for this course
     * Note: this fill crew_users too
     *
     * @param null   $schoolid
     * @param bool   $return_one - return onl first value, if true
     * @param string|array $role_names - "Student" by default
     * @param bool   $use_cache
     *
     * @return array|null
     */
    public function get_school_students($schoolid=null, $return_one=true, $role_names=self::DEF_MEMBER_ROLE, $use_cache=true){
        global $DB;
        $def_role = $role_names == self::DEF_MEMBER_ROLE;
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
            list($sql_param, $school_params) = $DB->get_in_or_equal($to_load, SQL_PARAMS_NAMED);
            $params = array_merge($params, $school_params);
            $where = ["m.cohortid $sql_param"];
            $groupby = ['u.id'];
            $sql = "SELECT u.*, m.cohortid AS schoolid, COALESCE(m.crewid, 0) AS crewid
                    FROM {user} AS u
                    JOIN {".self::TABLE_MEMBERS."} AS m
                        ON m.userid = u.id
                    ";
            if (self::$_default_role_field_id && !empty($role_names)){
                $field_id = self::$_default_role_field_id;
                $sql .= "LEFT JOIN {user_info_data} uid
                            ON uid.userid = u.id AND uid.fieldid = '$field_id'
                ";

                list($where_role, $rn_params) = $DB->get_in_or_equal($role_names, SQL_PARAMS_NAMED, 'rolename');
                $params = array_merge($params, $rn_params);

                if (in_array(self::$_default_role_default_value, $role_names)){
                    $where_role .= ' OR uid.data IS NULL';
                }

                $where[] = 'uid.data ' . $where_role;
            }
            $where = empty($where) ? '' : ('WHERE (' . join(') AND (', $where) . ')');
            $groupby = empty($groupby) ? '' : ("\nGROUP BY " . join(', ', $groupby));
            $members = $DB->get_records_sql("$sql $where $groupby", $params);
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
        $def_role = $role_names == self::DEF_MEMBER_ROLE;
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
            list($sql_param, $crew_params) = $DB->get_in_or_equal($to_load, SQL_PARAMS_NAMED);
            $params = array_merge($params, $crew_params);
            $where = ["m.crewid $sql_param"];
            if (in_array(0, $to_load)){
                $where[0] .= ' OR m.crewid IS NULL';
            }
            $sql = "SELECT u.*, m.cohortid AS schoolid, COALESCE(m.crewid, 0) AS crewid
                    FROM {user} AS u
                    JOIN {".self::TABLE_MEMBERS."} AS m
                        ON m.userid = u.id";
            if (self::$_default_role_field_id && !empty($role_names)){
                $field_id = self::$_default_role_field_id;
                $sql .= "LEFT JOIN {user_info_data} uid
                            ON uid.userid = u.id AND uid.fieldid = '$field_id'
                ";

                list($where_role, $rn_params) = $DB->get_in_or_equal($role_names, SQL_PARAMS_NAMED, 'rolename');
                $params = array_merge($params, $rn_params);

                if (in_array(self::$_default_role_default_value, $role_names)){
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
     * @return bool
     */
    public function can_manage_schools(){
        return $this->_manage_schools == self::CAP_CAN_EDIT;
    }

    /**
     * @return bool
     */
    public function can_manage_crews(){
        return $this->_manage_crews == self::CAP_CAN_EDIT;
    }

    /**
     * @return bool
     */
    public function can_manage_members(){
        return $this->_manage_members == self::CAP_CAN_EDIT;
    }

    /**
     * Save school object in the table
     *
     * @param $data
     *
     * @return bool|int
     */
    public function save_school($data){
        global $DB;
        $data = (object)$data;
        if (!($data->id ?? false) || !$this->can_manage_schools()){
            return false;
        }

        if ($school = $this->get_school_by_ids($data->id, true)){
            $new = false;
        } elseif ($school = $this->get_potential_schools($data->id)){
            $new = true;
        } else {
            return false;
        }

        $up_data = new \stdClass();
        $up_data->id = $school->id;
        $up_data->name = $school->name;
        $up_data->code = $school->code ?? $school->idnumber;
        $up_data->url = $data->url ?? '';
        $up_data->city = $data->city ?? '';
        $up_data->country = $data->country ?? $this->_user->country ?? '';
        $up_data->logo = $data->logo ?? 0;
        $up_data->startdate = $data->startdate ?? time();
        $up_data->enddate = $data->enddate ?? (time() + 365*24*3600);
        $up_data->note = $data->note ?? '';
        // save logo
        $data = file_postupdate_standard_filemanager($data, 'logo', ['subdirs' => 0, 'maxfiles' => 1], $this->ctx,
            PLUGIN_NAME, 'logo', $up_data->id);
        $up_data->logo = !empty($data->logo) ? $data->logo_filemanager : 0;

        if ($new){
            self::$_schools_data[$school->id] = $up_data;
            $this->_schools[$school->id] = $up_data;
            $this->_school_names[$school->id] = $school->name;
            return $DB->insert_record_raw(self::TABLE_SCHOOL, $up_data, false, false, true);
        } else {
            self::$_schools_data[$school->id] = $up_data;
            $this->_schools[$school->id] = $up_data;
            return $DB->update_record(self::TABLE_SCHOOL, $up_data);
        }
    }

    /**
     * Delete school object from the table
     *
     * @param $id
     *
     * @return bool|int
     */
    public function delete_school($id){
        global $DB;
        if (!$school = $this->get_school_by_ids($id, true)){
            return false;
        }

        $DB->delete_records(self::TABLE_SCHOOL, ['id' => $id]);
        $DB->delete_records(self::TABLE_CREW, ['schoolid' => $id]);
        $DB->set_field(self::TABLE_MEMBERS, 'crewid', null, ['cohortid' => $id]);
        // remove logo
        $fs = get_file_storage();
        $fs->delete_area_files($this->ctx->id, PLUGIN_NAME, 'logo', $id);

        $school->idnumber = $school->code;
        $this->_potential_schools[$id] = $school;
        unset(self::$_schools_data[$id]);
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

        $school_code = $DB->get_field(self::TABLE_SCHOOL, 'code', ['id' => $data->schoolid]);
        $code = '';
        if (!empty($school_code) && !empty($up_data->code)){
            $code = $school_code . '-' . $up_data->code;
            if (!$data->id && $DB->record_exists('cohort', ['idnumber' => $code])){
                $code = '';
            }
        }

        if (empty($code)) {
            $count = $DB->count_records(self::TABLE_CREW, ['schoolid' => $up_data->schoolid]) + 1;
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
            'component' => PLUGIN_NAME,
            'timemodified' => time(),
        ];

        if (!$data->id){
            $cohort->timecreated = $cohort->timemodified;
            if ($new_id = $DB->insert_record(self::TABLE_COHORT, $cohort, true)){
                $up_data->id = $new_id;
            }
            $DB->insert_record_raw(self::TABLE_CREW, $up_data,  false, false, true);
            if (isset($this->_crews[$data->schoolid])){
                $this->_crews[$data->schoolid][$up_data->id] = $up_data;
            }
            return $up_data->id;
        } else {
            $cohort->id = $data->id;
            $DB->update_record(self::TABLE_COHORT, $cohort);
            return $DB->update_record(self::TABLE_CREW, $up_data);
        }
    }

    /**
     * Delete crew object from the table
     *
     * @param      $id
     * @param null $schoolid
     *
     * @return bool|int
     */
    public function delete_crew($id, $schoolid=null){
        global $DB;
        if (!$crew = $this->get_crew_by_id($id, $schoolid)){
            return false;
        }

        $DB->delete_records(self::TABLE_CREW, ['id' => $id]);
        $DB->delete_records(self::TABLE_COHORT, ['id' => $id]);
        $DB->set_field(self::TABLE_MEMBERS, 'crewid', null, ['crewid' => $id]);

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

        list($sql_param, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['schoolid'] = $schoolid;
        $sql = "cohortid = :schoolid AND userid $sql_param";

        if ($DB->set_field_select(self::TABLE_MEMBERS, 'crewid', $crewid, $sql, $params)){
            if (isset($this->_school_users[$schoolid])){
                foreach ($this->_school_users[$schoolid] as $userid => &$user){
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
            $files = $fs->get_area_files(\context_system::instance()->id, PLUGIN_NAME, 'logo', $schoolid,
                "itemid, filepath, filename", false);
            $logourl = false;

            foreach ($files as $file) {
                $logourl = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
                break;
            }

            $_data[$schoolid] = $logourl;
        }

        return $_data[$schoolid];
    }
}