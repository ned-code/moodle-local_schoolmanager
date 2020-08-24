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
use local_schoolmanager\output\school_manager_render as SMR;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');


class edit_users_form extends \moodleform {
    protected $_schoolid;

    public function definition()
    {
        $mform = $this->_form;
        $schoolid = $this->_customdata['schoolid'] ?? 0;
        $add_html = $this->_customdata['html'] ?? '';
        $SM = SM\school_manager::get_school_manager();

        $mform->addElement('html', $add_html);

        $mform->addElement('hidden', 'schoolid', $schoolid);
        $mform->setType('schoolid', PARAM_INT);
        $mform->addElement('hidden', 'userids_to_change', '');
        $mform->setType('userids_to_change', PARAM_TEXT);

        $crew_names = $SM->get_crew_names($schoolid);
        $choices = [0 => get_string('none')] + $crew_names;
        $mform->addElement('select', 'crewid', SM\str('newcrewforusers'), $choices);

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('save'));
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
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
        global $_POST;
        $data = parent::get_data();
        if ($data){
            $data->{SMR::FORM_USERS_TO_CHANGE} = $_POST[SMR::FORM_USERS_TO_CHANGE] ?? [];
        }
        return $data;
    }

    function set_prehtml($text){
        $mform = $this->_form;

        /** @var \HTML_QuickForm_group | \HTML_QuickForm_select | \HTML_QuickForm_html | \HTML_QuickForm_element  $elem */
        foreach ($mform->_elements as &$elem){
            $type = $elem->getType();
            $t = $elem->toHtml();
            if ($type == 'html' && $elem->toHtml() == ''){
                $elem->setText($text);
                break;
            }
        }
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
