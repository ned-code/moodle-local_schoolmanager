<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\output;
use local_schoolmanager as SM;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');

/**
 * @property-read \core_renderer $o;
 * @property-read SM\school_manager $SM;
 * @property-read int $schoolid;
 * @property-read int $crewid;
 * @property-read int $page;
 * @property-read bool $act;
 */
class school_manager_render implements \renderable, \templatable{

    const URL = SM\PLUGIN_URL.'/index.php';
    const PAR_SCHOOL = 'schoolid';
    const PAR_CREW = 'crewid';
    const PAR_PAGE = 'page';

    const PAGE_SCHOOL = 0;
    const PAGE_CREW = 1;

    const PAGES = [self::PAGE_SCHOOL, self::PAGE_CREW];

    /** @var \core_renderer $_o */
    protected $_o;
    /** @var SM\school_manager $_SM */
    protected $_SM;
    protected $_schoolid;
    protected $_crewid;
    protected $_page;
    protected $_act;

    /** @var school_manager_content $c */
    public $c;

    public function __construct($load_page_data=true){
        global $OUTPUT;
        $this->_o = $OUTPUT;
        $this->_SM = SM\school_manager::get_school_manager();
        $this->_act = $this->_SM->view != SM\school_manager::CAP_CANT_VIEW_SM;
        $this->c = new school_manager_content();

        if ($this->_act && $load_page_data){
            $this->load_page_data();
        }
    }

    public function __get($name){
        $pr_name = '_' . $name;
        $res = null;

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
                // may be save new school
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
            $this->_page = SM\isset_in_list(self::PAGES, $this->_page, self::PAGE_SCHOOL);
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
    static public function get_url($schoolid=null, $crewid=null, $page=null){
        $args = [self::PAR_SCHOOL => $schoolid, self::PAR_CREW => $crewid, self::PAR_PAGE => $page];
        $params = [];
        foreach ($args as $key => $arg){
            if (!is_null($arg) && $arg !== false){
                $params[$key] = $arg;
            }
        }
        return new \moodle_url(self::URL, $params);
    }

    /**
     * @return string
     */
    static public function get_title(){
        return SM\str('pluginname');
    }

    protected function _page_main(){
        $SM = $this->_SM;
        $school_names = $SM->school_names;
        if (empty($school_names)){
            if ($SM->view == $SM::CAP_SEE_ALL_SM){
                $text = SM\str('noschools');
            } else {
                $text = SM\str('nomyschools');
            }
            $this->c->messages[] = $this->o->notification($text, \core\output\notification::NOTIFY_INFO);
        } else {
            foreach ($school_names as $id => $school_name){
                $this->c->schools[] = ['link' => self::get_url($id), 'name' => $school_name];
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
        $form = new SM\forms\choose_potential_school_form($this->get_my_url(), ['cancel' => self::get_url()]);
        if ($data = $form->get_data()){
            $cohortid = $data->cohortid ?? null;
            $ps = $SM->potential_schools;
            if (isset($ps[$cohortid])){
                $this->_schoolid = $cohortid;
                $this->_page_edit_create_school();
                return;
            }
        } else {
            $this->c->forms[] = $form->draw();
        }
    }

    protected function _page_edit_create_school(){
        $SM = $this->_SM;
        if ($this->_schoolid){
            $form = new SM\forms\edit_school_form($this->get_my_url(), ['cancel' => self::get_url(), 'schoolid' => $this->_schoolid]);
            if ($data = $form->get_data()){
                if ($data->deletebutton ?? false){
                    if($SM->delete_school($data->id)){
                        $this->c->messages[] =
                            $this->o->notification(SM\str('schooldeletedsuccessfully'), \core\output\notification::NOTIFY_SUCCESS);
                    }
                    $this->_schoolid = null;
                    $this->_page_main();
                    return;
                } elseif ($data->submitbutton ?? false){
                    if($SM->save_school($data)){
                        $this->c->messages[] =
                            $this->o->notification(SM\str('schoolsavedsuccessfully'), \core\output\notification::NOTIFY_SUCCESS);
                        $form->set_new_status(false);
                    }
                }
            }
            if ($SM->school_names[$this->_schoolid] ?? false){
                $this->c->links[] =
                    ['link' => $this->get_my_url(null, false, self::PAGE_CREW), 'name' => SM\str('crews')];
            }
            $this->c->forms[] = $form->draw();
        }
    }

    protected function _page_edit_create_crew(){
        $SM = $this->_SM;

        if(is_null($this->_crewid)){
            $crew_names = $SM->get_crew_names($this->_schoolid);
            if (empty($crew_names)){
                $this->c->messages[] = $this->o->notification(SM\str('nocrew'), \core\output\notification::NOTIFY_INFO);
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
            $form = new SM\forms\edit_crew_form($this->get_my_url(),
                ['cancel' => $this->get_my_url(null, false, self::PAGE_CREW),
                    'schoolid' => $this->_schoolid, 'crewid' => $this->_crewid]);
            if ($data = $form->get_data()){
                if ($data->deletebutton ?? false){
                    if($SM->delete_crew($data->id, $this->_schoolid)){
                        $this->c->messages[] =
                            $this->o->notification(SM\str('crewdeletedsuccessfully'), \core\output\notification::NOTIFY_SUCCESS);
                    }
                    $this->_crewid = null;
                    $this->_page_edit_create_crew();
                    return;
                } elseif ($data->submitbutton ?? false){
                    if($this->_crewid = $SM->save_crew($data)){
                        $this->c->messages[] =
                            $this->o->notification(SM\str('crewsavedsuccessfully'), \core\output\notification::NOTIFY_SUCCESS);
                        $form->set_crewid($this->_crewid);
                    }
                }
            }
            $this->c->forms[] = $form->draw();
        }
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     *
     * @return \stdClass|array
     */
    public function export_for_template(\renderer_base $output){
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
            if ($this->_page == self::PAGE_SCHOOL){
                // School page
                if ($this->_schoolid == 0){
                    $this->_page_choose_new_school();
                } else {
                    $this->_page_edit_create_school();
                }
            } elseif ($this->_page == self::PAGE_CREW){
                // Crew page
                $this->_page_edit_create_crew();
                $this->c->school_name = $SM->school_names[$this->schoolid];
                $this->c->links[] = ['link' => self::get_url(), 'name' => SM\str('schools')];
            }

        }

        return $this->c->export();
    }

    /**
     * Render this class element
     * @param bool $return
     *
     * @return string
     */
    public function render($return=false){
        global $PAGE;
        $renderer = $PAGE->get_renderer(SM\PLUGIN_NAME);
        $res = $renderer->render($this);
        if (!$return){
            echo $res;
        }
        return $res;
    }

}

class school_manager_content extends \stdClass{
    /** @var \renderer_base $output */
    public $output;
    public $messages = [];
    public $schools = [];
    public $crews = [];
    public $forms = [];
    public $links = [];
    public $manage = false;
    public $buttons = [];
    public $school_name = '';


    public function __construct($var=null){
        if (!is_null($var)){
            $this->import($var);
        }
    }

    public function __get($name){
        return null;
    }

    /**
     * Import $obj data into $this
     *
     * @param $obj
     */
    public function import($obj){
        foreach ($obj as $key => $item){
            $this->$key = $item;
        }
    }

    /**
     * Export $this data as \stdClass object
     *
     * @return \stdClass
     */
    public function export(){
        $res = new \stdClass();
        foreach ($this as $key => $item){
            $res->$key = $item;
        }

        return $res;
    }
}
