<?php

/**
 * @package    local_schoolmanager
 * @subpackage forms
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\forms;

use local_schoolmanager\shared_lib as NED;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');


/**
 * choose_potential_school_form
 */
class choose_potential_school_form extends \moodleform {
    /**
     * Form definition
     * @noinspection PhpOverridingMethodVisibilityInspection
     */
    public function definition(){
        $mform = $this->_form;
        $cancel_link = $this->_customdata['cancel'] ?? false;
        $SM = NED::$SM::get_school_manager();
        $ps = $SM->get_potential_schools();
        if (empty($ps)){
            NED::print_module_error('nopermissions', 'error', '', 'There are no potential schools for the form!');
        }

        $ps_list = array_map(function($cohort){ return $cohort->name.' - '.$cohort->idnumber; }, $ps);
        asort($ps_list);
        $mform->addElement('autocomplete', 'cohortid', NED::str('selectcohort'), $ps_list);

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('continue'));
        if ($cancel_link){
            $buttonarray[] = $mform->createElement('html',
                NED::link([$cancel_link], get_string('cancel'), 'btn btn-default'));
        } else {
            $buttonarray[] = $mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    /**
     * Render & return form as html
     * @return string
     */
    public function draw(){
        //finalize the form definition if not yet done
        if (!$this->_definition_finalized){
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        return $this->_form->toHtml();
    }
}
