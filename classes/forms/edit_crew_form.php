<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\forms;
use local_schoolmanager as SM;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');


class edit_crew_form extends \moodleform {
    protected $_can_manage = false;
    protected $_schoolid;
    protected $_school;
    protected $_crewid;
    protected $_crew;


    public function definition()
    {
        $mform = $this->_form;
        $cancel_link = $this->_customdata['cancel'] ?? false;
        $schoolid = $this->_customdata['schoolid'] ?? 0;
        $this->_crewid = $this->_customdata['crewid'] ?? 0;
        $SM = SM\school_manager::get_school_manager();
        $config = get_config(SM\PLUGIN_NAME);

        $this->_school = $SM->get_school_by_ids($schoolid, true);
        $this->_crew = $this->_crewid ? $SM->get_crew_by_id($this->_crewid, $schoolid) : null;
        $this->_can_manage = $SM->can_manage_crews();

        if (!$this->_school || (!$this->_crew && !$this->_can_manage)){
            print_error('nopermissions', 'error', '', 'There is no such crew!');
        }

        $mform->addElement('hidden', 'schoolid', $schoolid);
        $mform->setType('schoolid', PARAM_INT);
        $mform->addElement('hidden', 'form_crewid', $this->_crewid);
        $mform->setType('form_crewid', PARAM_INT);

        $mform->addElement('text', 'name', SM\str('crewname'), []);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');

        $mform->addElement('text', 'code', SM\str('crewcode'), ['size'=>'2']);
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', null, 'maxlength', '2');
        $mform->addRule('code', null, 'minlength', '2');

        $choices = explode("\n", $config->academic_program ?? '');
        $mform->addElement('select', 'program', SM\str('academicprogram'), $choices);

        $year = time() + 365*24*3600;
        $mform->addElement('date_selector', 'admissiondate', SM\str('admissiondate'));
        $mform->setType('admissiondate', PARAM_INT);
        $mform->setDefault('admissiondate', $year);
        $mform->addElement('date_selector', 'graduationdate', SM\str('expectedgraduation'));
        $mform->setType('graduationdate', PARAM_INT);
        $mform->setDefault('graduationdate', $year);

        $mform->addElement('text', 'courses', SM\str('coursesperyear'), []);
        $mform->setType('courses', PARAM_TEXT);
        $mform->setDefault('courses', 0);
        $mform->addRule('courses', null, 'required');
        $mform->addRule('courses', null, 'maxlength', 5);
        $mform->addRule('courses', null, 'numeric');

        $mform->addElement('editor', 'note', SM\str('note'));
        $mform->setType('note', PARAM_RAW);

        $buttonarray = [];
        if ($this->_can_manage){
            $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('save'));
            $buttonarray[] = $mform->createElement('submit', 'deletebutton', get_string('delete'));
            $mform->hideIf('deletebutton', 'form_crewid', 'eq', 0);
        }
        if ($cancel_link){
            $buttonarray[] = $mform->createElement('html',
                SM\link([$cancel_link], get_string('cancel'), 'btn btn-default'));
        } else {
            $buttonarray[] = $mform->createElement('cancel');
        }

        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data() {
        $mform = $this->_form;

        if (!$this->_can_manage){
            /** @var \HTML_QuickForm_group | \HTML_QuickForm_select | \HTML_QuickForm_element  $elem */
            foreach ($mform->_elements as $elem){
                $type = $elem->getType();
                if ($type != 'html' && $type != 'cancel'){
                    $mform->hardFreeze($elem->getName());
                }
            }
        }

        if ($this->_crew){
            $this->_crew->id = $this->_crewid;
            $this->set_data($this->_crew);
        }
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * note: $slashed param removed
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data(){
        $data = parent::get_data();
        if ($data){
            $data->id = $data->form_crewid ?? 0;
            $data->note = $data->note['text'] ?? '';
        }
        return $data;
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * @param \stdClass|array $default_values object or array of default values
     */
    public function set_data($default_values){
        $default_values->form_crewid = $default_values->id ?? 0;
        $default_values->note = ['text' => $default_values->note ?? '', 'format' => 1];
        parent::set_data($default_values);
    }

    /**
     * @param $id
     */
    public function set_crewid($id){
        $mform = $this->_form;
        $mform->getElement('form_crewid')->setValue($id);
        $this->_crewid = $id;
    }

    /**
     * Render & return form as html
     *
     * @param null $def_data
     * @return string
     */
    public function draw($def_data=null) {
        //finalize the form definition if not yet done
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        if (!is_null($def_data)){
            $this->set_data($def_data);
        }

        return $this->_form->toHtml();
    }
}
