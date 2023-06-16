<?php
/**
 * @package    local_schoolmanager
 * @subpackage output
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\output;

use local_schoolmanager\forms as sm_forms;
use local_schoolmanager\shared_lib as NED;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');

/**
 * @property-read \core_renderer $o;
 * @property-read \local_schoolmanager\school_manager $SM;
 * @property-read int $schoolid;
 * @property-read int $crewid;
 * @property-read int $page;
 * @property-read bool $act;
 */
class school_manager_render implements \renderable, \templatable{
    use \local_ned_controller\base_empty_class;

    const URL = '~/index.php';
    const PAR_SCHOOL = 'schoolid';
    const PAR_CREW = 'crewid';
    const PAR_PAGE = 'page';

    const PAGE_SCHOOL = 0;
    const PAGE_CREW = 1;
    const PAGE_USER = 2;

    const PAGES = [self::PAGE_SCHOOL, self::PAGE_CREW, self::PAGE_USER];

    const FORM_USERS_TO_CHANGE = 'users_to_change';

    /** @var \core_renderer $_o */
    protected $_o;
    /** @var \local_schoolmanager\school_manager $_SM */
    protected $_SM;
    protected $_schoolid;
    protected $_crewid;
    protected $_page;
    protected $_act;

    /** @var \local_schoolmanager\support\school_manager_content $c */
    public $c;

    /**
     * @construct school_manager_render
     *
     * @param bool $load_page_data
     */
    public function __construct($load_page_data=true){
        global $OUTPUT;
        $this->_o = $OUTPUT;
        $this->_SM = NED::$SM::get_school_manager();
        $this->_act = $this->_SM->view != NED::$SM::CAP_CANT_VIEW_SM;
        $this->c = new \local_schoolmanager\support\school_manager_content();

        if ($this->_act && $load_page_data){
            $this->load_page_data();
        }
    }

    protected function _check_params(){
        if (!$this->_act){
            $this->_schoolid = null;
            $this->_crewid = null;
            $this->_page = null;
            return;
        }

        $SM = $this->SM;
        $school_names = $SM->school_names;

        if ($this->_schoolid){
            if (!isset($school_names[$this->_schoolid])){
                // check is it a new school
                if (!$SM->get_potential_schools($this->_schoolid)){
                    // some data error
                    $this->_schoolid = null;
                }
            }
        } elseif (!is_null($this->_schoolid) && $this->_schoolid == 0){
            $ps = $SM->potential_schools;
            if (empty($ps)){
                $this->_schoolid = null;
            }
        }
        // don't connect to upper block with else or elseif here
        if (is_null($this->_schoolid)){
            if (count($school_names) == 1 && !$SM->can_manage_schools()){
                $this->_schoolid = key($school_names);
            }
            $this->_crewid = null;
            $this->_page = self::PAGE_SCHOOL;
        } else {
            $this->_page = NED::isset_in_list(self::PAGES, $this->_page, self::PAGE_SCHOOL);
        }

        if ($this->_crewid){
            if (!$SM->get_crew_by_id($this->_crewid, $this->_schoolid)){
                $this->_crewid = 0;
            }
        }
    }

    public function load_page_data(){
        $this->_schoolid = optional_param(self::PAR_SCHOOL, null, PARAM_INT);
        $this->_crewid = optional_param(self::PAR_CREW, null, PARAM_INT);
        $this->_page = optional_param(self::PAR_PAGE, self::PAGE_SCHOOL, PARAM_INT);
        $this->_check_params();
    }

    /**
     * Add a notification (that is, a status message about something that has just happened).
     *
     * @param string $message_or_key The message to print out, can be key to string translate
     * @param string $type    The type of notification. See constants as NED::NOTIFY_*.
     *
     * @return void
     */
    public function add_notification($message_or_key='', $type=NED::NOTIFY_INFO){
        $this->c->messages[] = NED::notification($message_or_key, $type);
    }

    /**
     * @param int $schoolid
     * @param int $crewid
     * @param int $page - one of the {@see static::PAGES}
     *
     * @return void
     * @noinspection PhpUnused
     */
    public function set_params($schoolid=null, $crewid=null, $page=null){
        $this->_schoolid = $schoolid;
        $this->_crewid = $crewid;
        $this->_page= $page;
        $this->_check_params();
    }

    /**
     * Return page arguments for current page
     *  you can rewrite arguments, null - use default ($this) value
     *
     * @param null $schoolid
     * @param null $crewid
     * @param null $page
     *
     * @return \moodle_url
     */
    public function get_my_url($schoolid=null, $crewid=null, $page=null){
        return self::get_url($schoolid ?? $this->_schoolid, $crewid ?? $this->_crewid, $page ?? $this->_page);
    }

    /**
     * Return page url for this class
     *
     * @param null $schoolid
     * @param null $crewid
     * @param null $page
     *
     * @return \moodle_url
     */
    static public function get_url($schoolid=null, $crewid=null, $page=null, $params=[]){
        $args = [self::PAR_SCHOOL => $schoolid, self::PAR_CREW => $crewid, self::PAR_PAGE => $page];
        $params = $params ?: [];
        foreach ($args as $key => $arg){
            if (!is_null($arg) && $arg !== false){
                $params[$key] = $arg;
            }
        }
        return NED::url(static::URL, $params);
    }

    /**
     * @return string
     */
    static public function get_title(){
        return NED::str('pluginname');
    }

    protected function _page_main(){
        $SM = $this->_SM;
        $school_names = $SM->school_names;
        if (empty($school_names)){
            if ($SM->view == NED::$SM::CAP_SEE_ALL_SM){
                $text = NED::str('noschools');
            } else {
                $text = NED::str('nomyschools');
            }
            $this->add_notification($text, NED::NOTIFY_INFO);
        } else {
            foreach ($school_names as $id => $school_name){
                $this->c->schools[] = ['link' => static::get_url($id), 'name' => $school_name];
            }
        }

        $ps = $SM->potential_schools;
        if (!empty($ps)){
            $this->c->manage = true;
            $this->c->buttons[] = ['link' => self::get_url(0), 'name' => get_string('add'), 'primary' => true];
        }
    }

    protected function _page_choose_new_school(){
        $SM = $this->_SM;
        $form = new sm_forms\choose_potential_school_form($this->get_my_url(), ['cancel' => self::get_url()]);
        if ($data = $form->get_data()){
            $cohortid = $data->cohortid ?? null;
            $ps = $SM->potential_schools;
            if (isset($ps[$cohortid])){
                $this->_schoolid = $cohortid;
                $this->_page_edit_create_school();
                //@return;
            }
        } else {
            $this->c->forms[] = $form->draw();
        }
    }

    protected function _page_edit_create_school(){
        $SM = $this->_SM;
        if ($this->_schoolid){
            $form = new sm_forms\edit_school_form($this->get_my_url(), ['cancel' => self::get_url(), 'schoolid' => $this->_schoolid]);
            if ($data = $form->get_data()){
                if ($data->deletebutton ?? false){
                    if ($SM->delete_school($data->id)){
                        $this->add_notification('schooldeletedsuccessfully', NED::NOTIFY_SUCCESS);
                    }
                    $this->_schoolid = null;
                    $this->_page_main();
                    return;
                } elseif ($data->submitbutton ?? false){
                    if ($SM->save_school($data)){
                        NED::redirect(NED::url('~/view.php', ['schoolid' => $this->_schoolid, 'view' => 'school']),
                            NED::str('schoolsavedsuccessfully'), null, NED::NOTIFY_SUCCESS);
                        return;
                    }
                }
            }
            $this->c->forms[] = $form->draw();
        }
    }

    protected function _page_edit_create_crew(){
        $SM = $this->_SM;

        if(is_null($this->_crewid)){
            $crew_names = $SM->get_crew_names($this->_schoolid);
            if (empty($crew_names)){
                $this->add_notification('nocrew', NED::NOTIFY_INFO);
            } else {
                foreach ($crew_names as $id => $crew_name){
                    $this->c->crews[] = ['link' => $this->get_my_url(null, $id), 'name' => $crew_name];
                }
            }
            if ($SM->can_manage_schools()){
                $this->c->manage = true;
                $this->c->buttons[] =
                    ['link' => $this->get_my_url(null, 0), 'name' => get_string('add'), 'primary' => true];
            }
        } else {
            $form = new sm_forms\edit_crew_form($this->get_my_url(),
                ['cancel' => $this->get_my_url(null, false, self::PAGE_CREW),
                    'schoolid' => $this->_schoolid, 'crewid' => $this->_crewid]);
            if ($data = $form->get_data()){
                if ($data->deletebutton ?? false){
                    if ($SM->delete_crew($data->id, $this->_schoolid)){
                        $this->add_notification('crewdeletedsuccessfully', NED::NOTIFY_SUCCESS);
                    }
                    $this->_crewid = null;
                    $this->_page_edit_create_crew();
                    return;
                } elseif ($data->submitbutton ?? false){
                    if ($this->_crewid = $SM->save_crew($data)){
                        $this->add_notification('crewsavedsuccessfully', NED::NOTIFY_SUCCESS);
                        $form->set_crewid($this->_crewid);
                    }
                }
            }
            $this->c->forms[] = $form->draw();
        }
    }

    protected function _page_edit_users(){
        $SM = $this->_SM;
        $users = $SM->get_school_students($this->_schoolid, true);
        if (empty($users)){
            $this->add_notification('nousersatschool', NED::NOTIFY_INFO);
            return;
        }

        $school = $SM->get_school_by_ids($this->_schoolid, true);
        $crews = $SM->get_crews($this->_schoolid);
        $can_manage = $SM->can_manage_members();
        $user_table = static::user_edit_table($school, $crews, $users, $this->o, $can_manage);

        if ($can_manage){
            $form = new sm_forms\edit_users_form($this->get_my_url(), ['schoolid' => $this->_schoolid]);
            if($data = $form->get_data()){
                if ($SM->change_users_crew($this->_schoolid, $data->{self::FORM_USERS_TO_CHANGE}, $data->crewid)){
                    $this->add_notification('usersupdatedsuccessfully', NED::NOTIFY_SUCCESS);
                }
            }

            $form->set_prehtml($user_table ? NED::render_table($user_table) : '');
            $this->c->forms[] = $form->draw();
        } else {
            $this->c->tables[] = NED::render_table($user_table);
        }
    }

    /**
     * Get user html table
     *
     * @param \stdClass         $school
     * @param \stdClass[]       $crews
     * @param \stdClass[]       $users
     * @param \core_renderer    $output
     * @param bool              $can_manage
     *
     * @return \html_table
     */
    static public function user_edit_table($school, $crews, $users, $output, $can_manage=false){
        global $PAGE;
        $table = NED::html_table();
        $schoolcode = $school->code ?? '';
        $togglegroup = self::FORM_USERS_TO_CHANGE;

        if ($can_manage){
            $mastercheckbox = new \core\output\checkbox_toggleall($togglegroup, true, [
                'id' => $togglegroup,
                'name' => $togglegroup,
                'value' => 0,
                'label' => '',
                'selectall' => '',
                'deselectall' => '',
                'labelclasses' => '',
            ]);
            $table->head[] = $output->render($mastercheckbox);
        }
        array_push($table->head, get_string('username'), NED::str('crewname'), NED::str('crewcode'));

        foreach ($users as $user){
            $cells = [];
            if ($can_manage){
                $checkbox = new \core\output\checkbox_toggleall($togglegroup, false, [
                    'id' => $togglegroup . $user->id,
                    'name' => $togglegroup.'[]',
                    'classes' => 'user-checkbox',
                    'value' => $user->id,
                    'label' => '',
                ]);
                $cells[] = NED::cell($output->render($checkbox), 'select');
            }

            $cells[] = NED::cell(NED::q_user_link($user), 'username');
            $crewname = $crews[$user->crewid]->name ?? '';
            if ($PAGE->theme->name == 'ned_boost'){
                $crewname = NED::link(['/my', ['schoolid' => $school->id]], $crewname);
            }
            $cells[] = NED::cell($crewname, 'crewname');
            $crewcode = $crews[$user->crewid]->code ?? '';
            if ($schoolcode && $crewcode){
                $code = $schoolcode.'-'.$crewcode;
            } else {
                $code = $schoolcode || $crewcode;
            }
            $cells[] = NED::cell($code, 'crewcode');
            $row = NED::row($cells, 'userid-'.$user->id);
            $table->data[] = $row;
        }

        return $table;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     *
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output){
        global $PAGE;
        $this->c->output = $output;
        $SM = $this->_SM;
        $SM->show_error_if_necessary();
        if (!$this->_act){
            return $this->c->export();
        }

        if (is_null($this->_schoolid) && is_null($this->_crewid)){
            // Main page
            $this->_page_main();
        } elseif (!is_null($this->_schoolid)){
            if ($this->_schoolid){
                if ($PAGE->theme->name == 'ned_boost'){
                    $this->c->links[] = ['link' => new \moodle_url('/my', ['schoolid' => $this->_schoolid]),
                        'name' => NED::str('schoolinfo')];
                }
            }
            if ($this->_page == self::PAGE_SCHOOL){
                // School page
                if ($this->_schoolid == 0){
                    $this->_page_choose_new_school();
                } else {
                    $this->_page_edit_create_school();
                }

                if ($SM->school_names[$this->_schoolid] ?? false){
                    $this->c->links[] =
                        ['link' => $this->get_my_url(null, false, self::PAGE_CREW), 'name' => NED::str('crews')];
                    $this->c->links[] =
                        ['link' => $this->get_my_url(null, false, self::PAGE_USER), 'name' => NED::str('users')];
                }
            } else {
                $this->c->school_name = $SM->school_names[$this->schoolid];
                $this->c->links[] = ['link' => self::get_url(), 'name' => NED::str('schools')];

                if ($this->_page == self::PAGE_CREW){
                    $this->_page_edit_create_crew();
                    $this->c->links[] =
                        ['link' => $this->get_my_url(null, false, self::PAGE_USER), 'name' => NED::str('users')];
                } elseif($this->_page == self::PAGE_USER){
                    $this->_page_edit_users();
                    $this->c->links[] =
                        ['link' => $this->get_my_url(null, false, self::PAGE_CREW), 'name' => NED::str('crews')];
                }
            }

        }
        if ($this->SM->get_school_by_ids($this->_schoolid)){
            $header = new school_header($this->_schoolid ?? 0, $this->_page);
            $headerdata = $header->export_for_template($output);
        }
        $data = $this->c->export();
        if (!empty($headerdata)){
            foreach ($headerdata as $key => $item){
                $data->$key = $item;
            }
        }

        return $data;
    }

    /**
     * Render this class element
     * @param bool $return
     *
     * @return string
     */
    public function render($return=false){
        $res = NED::render($this);
        if (!$return){
            echo $res;
        }
        return $res;
    }
}
