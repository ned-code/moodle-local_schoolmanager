<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\output;

use report_ghs\helper;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');


class reports_header implements \renderable, \templatable {
    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        global $PAGE;

        $contextsystem = \context_system::instance();

        $data = new \stdClass();
        $data->showheader = true;

        $data->btn_academicintegrity_url = new \moodle_url('/local/academic_integrity/infractions.php');
        if ($PAGE->url->get_path() == $data->btn_academicintegrity_url->get_path()) {
            $data->show_academicintegrity = true;
        }

        /*$data->btn_deadlinenotifications_url = new \moodle_url('/my/?pagetype=dn');
        if ($PAGE->url->get_path() == $data->btn_deadlinenotifications_url->get_path()) {
            $data->show_deadlinenotifications = true;
        }*/

        if (has_capability('report/ghs:viewactivityextensions', $contextsystem) || helper::has_capability_in_any_course('report/ghs:viewactivityextensions')) {
            $data->btn_deadlineextensions_url = new \moodle_url('/report/ghs/ghs_activity_extension.php');
            if ($PAGE->url->get_path() == $data->btn_deadlineextensions_url->get_path()) {
                $data->show_deadlineextensions = true;
            }
        }

        if (has_capability('report/ghs:viewenddates', $contextsystem) || helper::has_capability_in_any_course('report/ghs:viewenddates')) {
            $data->btn_classdateextensions_url = new \moodle_url('/report/ghs/ghs_enddate_extension.php');
            if ($PAGE->url->get_path() == $data->btn_classdateextensions_url->get_path()) {
                $data->show_classdateextensions = true;
            }
        }

        return $data;
    }
}