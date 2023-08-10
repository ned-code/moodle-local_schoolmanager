<?php

/**
 * @package    local_schoolmanager
 * @subpackage forms
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\forms;

use local_schoolmanager\school;
use local_schoolmanager\school_manager as SM;
use local_schoolmanager\shared_lib as NED;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');


/**
 * edit_school_form
 */
class edit_school_form extends \moodleform {
    protected $_new = false;
    protected $_can_manage = false;
    protected $_schoolid;
    protected $_school;

    public function definition(){
        global $CFG;
        $mform = $this->_form;
        $cancel_link = $this->_customdata['cancel'] ?? false;
        $this->_schoolid = $this->_customdata['schoolid'] ?? 0;
        $SM = SM::get_school_manager();
        $user = $SM->user;

        if ($school = $SM->get_school_by_ids($this->_schoolid, true)){
            $this->_new = false;
            $school = $school->to_record();
        } elseif ($school = ($SM->get_potential_schools($this->_schoolid) ?? false)){
            $this->_new = true;
        } else {
            print_error('nopermissions', 'error', '', 'There is no school or potential school for edit!');
        }

        $this->_school = $school;
        $this->_can_manage = $SM->can_manage_schools();

        $mform->addElement('hidden', 'schoolid', $this->_schoolid);
        $mform->setType('schoolid', PARAM_INT);
        $mform->addElement('hidden', 'new', (int)$this->_new);
        $mform->setType('new', PARAM_INT);

        $mform->addElement('text', 'name', NED::str('schoolname'), []);
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('text', 'code', NED::str('schoolid'), []);
        $mform->setType('code', PARAM_TEXT);

        $mform->addElement('text', 'url', NED::str('schoolwebsite'), []);
        $mform->setType('url', PARAM_URL);
        $mform->addElement('text', 'city', get_string('city'), []);
        $mform->setType('city', PARAM_TEXT);

        $purpose = user_edit_map_field_purpose($user->id, 'country');
        $choices = get_string_manager()->get_list_of_countries();
        $choices = array('' => get_string('selectacountry') . '...') + $choices;
        $mform->addElement('select', 'country', get_string('selectacountry'), $choices, $purpose);
        if ($school->country ?? false){
            $mform->setDefault('country', $school->country);
        } elseif (!empty($CFG->country)){
            $mform->setDefault('country', $SM->user->country);
        }

        // Timezone
        $choices = \core_date::get_list_of_timezones($CFG->timezone, true);
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', $CFG->timezone);

        // Sync timezone
        $mform->addElement('selectyesno', 'synctimezone', NED::str('synctimezone'));
        $mform->setDefault('synctimezone', 0);

        if (is_siteadmin()){
            $mform->addElement('select', 'extmanager', NED::str('extmanager'), NED::strings2menu(school::EXTENSION_MANAGER));
            $mform->addHelpButton('extmanager', 'extmanager', NED::$PLUGIN_NAME);
            $mform->setDefault('extmanager', school::EXT_MANAGE_CT);
        } else {
            $mform->addElement('hidden', 'extmanager', school::EXT_MANAGE_CT);
            $mform->setType('extmanager', PARAM_INT);
        }

        // Logo
        if ($this->_can_manage){
            $mform->addElement('filemanager', 'logo_filemanager', NED::str('logo'), null,
                array('accepted_types' => array('.png','.jpg'), 'maxfiles' => 1));
            //Compact Logo
            $mform->addElement('filemanager', 'compact_logo_filemanager', NED::$C::str('compactlogo'), null,
                array('accepted_types' => array('.png','.jpg'), 'maxfiles' => 1));
        } else {
            $mform->addElement('static', 'currentpicture', NED::str('logo'));
            $mform->addElement('static', 'currentpicture_compact', NED::$C::str('compactlogo'));
        }

        $mform->addElement('date_selector', 'startdate', NED::str('schoolyearstartdate'));
        $mform->setType('startdate', PARAM_INT);
        $mform->setDefault('startdate', time());
        $mform->addElement('date_selector', 'enddate', NED::str('schoolyearenddate'));
        $mform->setType('enddate', PARAM_INT);
        $mform->setDefault('enddate', time() + YEARSECS);

        $mform->addElement('editor', 'note', NED::str('note'));
        $mform->setType('note', PARAM_RAW);

        $buttonarray = [];
        if ($this->_can_manage){
            $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('save'));
            $buttonarray[] = $mform->createElement('submit', 'deletebutton', get_string('delete'));
            $mform->hideIf('deletebutton', 'new', 'eq', 1);
        }
        if ($cancel_link){
            $buttonarray[] = $mform->createElement('html',
                NED::link([$cancel_link], get_string('cancel'), 'btn btn-default'));
        } else {
            $buttonarray[] = $mform->createElement('cancel');
        }

        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data(){
        $mform = $this->_form;

        if (!$this->_can_manage){
            /** @var \HTML_QuickForm_group | \HTML_QuickForm_select | \HTML_QuickForm_element  $elem */
            foreach ($mform->_elements as $elem){
                $type = $elem->getType();
                if ($type != 'html' && $type != 'cancel'){
                    $mform->hardFreeze($elem->getName());
                }
            }
            // set static logo image
            $imageelement = $mform->getElement('currentpicture');
            $logourl = SM::get_logo_url($this->_schoolid);
            if ($logourl){
                $imageelement->setValue(\html_writer::img($logourl, 'logo'));
            } else {
                $imageelement->setValue(get_string('none'));
            }

            // set static compact logo image
            $imageelement = $mform->getElement('currentpicture_compact');
            $logourl = SM::get_compact_logo_url($this->_schoolid);
            if ($logourl){
                $imageelement->setValue(\html_writer::img($logourl, 'compact_logo'));
            } else {
                $imageelement->setValue(get_string('none'));
            }
        } else {
            $mform->hardFreeze('name');
            $mform->hardFreeze('code');
        }

        if ($this->_school->idnumber ?? false){
            $this->_school->code = trim($this->_school->idnumber);
        }

        $SM = SM::get_school_manager();
        $school = $SM->get_school_by_ids($this->_schoolid, true);
        $this->_school->timezone = $school->get_cohort()->timezone ?? 99;

        $this->set_data($this->_school);
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * note: $slashed param removed
     *
     * @param \stdClass|array $default_values object or array of default values
     */
    public function set_data($default_values){
        $default_values->schoolid = $default_values->id ?? null;
        $default_values->note = ['text' => $default_values->note ?? '', 'format' => 1];
        if ($default_values->schoolid && $this->_can_manage){
            file_prepare_standard_filemanager($default_values, 'logo', ['subdirs' => 0],
                NED::ctx(), NED::$PLUGIN_NAME, 'logo', $default_values->schoolid);
            file_prepare_standard_filemanager($default_values, 'compact_logo', ['subdirs' => 0],
                NED::ctx(), NED::$PLUGIN_NAME, 'logo', $default_values->schoolid);
        }
        parent::set_data($default_values);
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * note: $slashed param removed
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    function get_data(){
        $data = parent::get_data();
        if ($data){
            $data->id = $data->schoolid ?? null;
            $data->note = $data->note['text'] ?? '';
        }
        return $data;
    }

    /**
     * @param $new
     */
    public function set_new_status($new){
        $mform = $this->_form;
        $el = $mform->getElement('new');
        $el->setValue((int)$new);
        $this->_new = $new;
    }

    /**
     * Render & return form as html
     *
     * @param null $def_data
     * @return string
     */
    public function draw($def_data=null){
        //finalize the form definition if not yet done
        if (!$this->_definition_finalized){
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        if (!is_null($def_data)){
            $this->set_data($def_data);
        }

        return $this->_form->toHtml();
    }
}
