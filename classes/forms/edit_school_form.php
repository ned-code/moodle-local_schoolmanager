<?php

/**
 * @package    local_schoolmanager
 * @subpackage forms
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\forms;

use context_system;
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
    protected $_can_manage_extra = false;
    protected $_can_delete = false;
    protected $_schoolid;
    protected $_school;

    public function definition(){
        global $CFG, $USER;

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
            NED::print_module_error('nopermissions', 'error', '', 'There is no school or potential school for edit!');
        }

        $this->_school = $school;
        $this->_can_manage = $SM->can_manage_schools();
        $this->_can_manage_extra = $SM->can_manage_schools_extra();
        $this->_can_delete = $SM->can_delete_schools();

        $mform->addElement('hidden', 'schoolid', $this->_schoolid);
        $mform->setType('schoolid', PARAM_INT);
        $mform->addElement('hidden', 'new', (int)$this->_new);
        $mform->setType('new', PARAM_INT);

        $mform->addElement('text', 'name', NED::str('schoolname'), []);
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $this->_school->name ?? '');

        if ($this->_can_manage_extra){
            $mform->addElement('text', 'cohortname', NED::str('schoolcohortname'), []);
            $mform->setType('cohortname', PARAM_TEXT);
            $mform->setDefault('cohortname', $this->_school->name ?? '');
            $mform->hardFreeze('cohortname');
        } else {
            $mform->hardFreeze('name');
        }

        $mform->addElement('text', 'code', NED::str('schoolid'), []);
        $mform->setType('code', PARAM_TEXT);
        $mform->hardFreeze('code');

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

        if (!$this->_can_manage_extra){
            $mform->hardFreeze(['city', 'country']);
        }

        $context = context_system::instance();
        if (has_capability('local/schoolmanager:manage_extension_limit', $context)) {
            $mform->addElement('select', 'extensionsallowed', NED::str('extensionsallowed'),
                [0 => 0, 1 => 1, 2 => 2, 3 => 3]);
            $mform->setDefault('extensionsallowed', 3);
        }

        // Timezone
        $choices = \core_date::get_list_of_timezones($CFG->timezone, true);
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', $CFG->timezone);

        if ($this->_can_manage_extra) {
            // Sync timezone
            $mform->addElement('selectyesno', 'synctimezone', NED::str('synctimezone'));
            $mform->setDefault('synctimezone', 0);

            // Force proxy submission window
            $mform->addElement('select', 'forceproxysubmissionwindow', NED::str('forceproxysubmissionwindow'),
                [0 => NED::str('activitysetting')] + NED::strings2menu(school::PROXY_SUBMISSION_WINDOWS));
            $mform->setDefault('forceproxysubmissionwindow', 0);

            // Enable TEM
            $mform->addElement('selectyesno', 'enabletem', NED::str('enabletem'));
            $mform->setDefault('enabletem', 0);
        }

        // IP type
        $mform->addElement('select', 'iptype', NED::str('iptype'), ['' => get_string('choose')] + NED::strings2menu(school::IP_TYPES));
        $mform->addRule('iptype', null, 'required');
        $mform->addHelpButton('iptype', 'iptype', NED::$PLUGIN_NAME);

        // Staff options.
        $staffoptions = [];
        if ($staffs = $SM->get_school_students($this->_schoolid, true, $SM::STAFF_ROLES, false)) {
            foreach ($staffs as $staff) {
                $staffoptions[$staff->id] = fullname($staff);
            }
            $staffoptions = [0 => get_string('choose')] + $staffoptions;
        }

        // School Administrator.
        $administrator = $SM->get_school_admin($this->_schoolid);

        // it doesn't save anywhere, just info
        $mform->addElement('select', 'schooladministrator', NED::str('schooladministrator'), $staffoptions);
        $mform->setDefault('schooladministrator', $administrator->id ?? 0);
        $mform->hardFreeze('schooladministrator');

        if ($this->_can_manage_extra || ($administrator && $administrator->id == $USER->id)) {
            // Proctor Manager for tests/Exams.
            $mform->addElement('select', 'proctormanager', NED::str('proctormanager'), $staffoptions);
            $mform->setDefault('proctormanager', $administrator->id ?? 0);
            // Academic Integrity Manager.
            $mform->addElement('select', 'academicintegritymanager', NED::str('academicintegritymanager'), $staffoptions);
            $mform->setDefault('academicintegritymanager', $administrator->id ?? 0);
        }

        if ($this->_can_manage_extra){
            $mform->addElement('select', 'extmanager', NED::str('extmanager'), NED::strings2menu(school::EXTENSION_MANAGER));
            $mform->addHelpButton('extmanager', 'extmanager', NED::$PLUGIN_NAME);
            $mform->setDefault('extmanager', school::EXT_MANAGE_CT);
        }

        // ESL.
        if ($this->_can_manage_extra){
            $mform->addElement('selectyesno', 'esl', NED::str('esl'));
            $mform->setDefault('esl', 0);
        }

        // Logo
        if ($this->_can_manage_extra){
            $mform->addElement('filemanager', 'logo_filemanager', NED::str('logo'), null,
                array('accepted_types' => array('.png','.jpg'), 'maxfiles' => 1));
            //Compact Logo
            $mform->addElement('filemanager', 'compact_logo_filemanager', NED::$C::str('compactlogo'), null,
                array('accepted_types' => array('.png','.jpg'), 'maxfiles' => 1));
        }

        // School year.
        $schoolyearoptions = [
            NED::str('custom'),
            NED::str('rosedaledefault', NED::get_format_school_year()),
        ];
        $mform->addElement('select', 'schoolyeartype', NED::str('schoolyear'), $schoolyearoptions);
        $mform->setDefault('schoolyeartype', 0);

        $mform->addElement('date_selector', 'startdate', NED::str('schoolyearstartdate'));
        $mform->setType('startdate', PARAM_INT);
        $mform->setDefault('startdate', time());
        $mform->hideIf('startdate', 'schoolyeartype', 'eq', 1);

        $mform->addElement('date_selector', 'enddate', NED::str('schoolyearenddate'));
        $mform->setType('enddate', PARAM_INT);
        $mform->setDefault('enddate', time() + YEARSECS);
        $mform->hideIf('enddate', 'schoolyeartype', 'eq', 1);

        $mform->addElement('editor', 'note', NED::str('aboutschool'));
        $mform->setType('note', PARAM_RAW);

        $buttonarray = [];
        if ($this->_can_manage){
            $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('save'));
        }
        if ($this->_can_delete){
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
        }

        if ($this->_school->idnumber ?? false){
            $this->_school->code = trim($this->_school->idnumber);
        }

        $SM = SM::get_school_manager();
        if ($school = $SM->get_school_by_ids($this->_schoolid, true)) {
            $this->_school->timezone = $school->get_cohort()->timezone ?? 99;
        }

        $this->_school->proctormanager = $this->_school->proctormanager ?? null;
        $this->_school->academicintegritymanager = $this->_school->academicintegritymanager ?? null;

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
