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
    private $schoolid;

    public function __construct($schoolid = 0) {
        $this->schoolid = $schoolid;
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $dataa) {
        global $PAGE;

        $contextsystem = \context_system::instance();

        $data = new \stdClass();
        $data->showheader = true;

        $data->btn_academicintegrity_url = new \moodle_url('/local/academic_integrity/infractions.php');
        if ($this->schoolid) {
            $data->btn_academicintegrity_url->param('school', $this->schoolid);
        }
        if ($PAGE->url->get_path() == $data->btn_academicintegrity_url->get_path()) {
            $data->show_academicintegrity = true;
        }

        if (has_capability('report/ghs:viewactivityextensions', $contextsystem) || helper::has_capability_in_any_course('report/ghs:viewactivityextensions')) {
            $data->btn_deadlineextensions_url = new \moodle_url('/report/ghs/ghs_activity_extension.php');
            if ($this->schoolid) {
                $data->btn_deadlineextensions_url->param('schoolid', $this->schoolid);
            }
            if ($PAGE->url->get_path() == $data->btn_deadlineextensions_url->get_path()) {
                $data->show_deadlineextensions = true;
            }
        }

        if (has_capability('report/ghs:viewenddates', $contextsystem) || helper::has_capability_in_any_course('report/ghs:viewenddates')) {
            $data->btn_classdateextensions_url = new \moodle_url('/report/ghs/ghs_enddate_extension.php');
            if ($this->schoolid) {
                $data->btn_classdateextensions_url->param('schoolid', $this->schoolid);
            }
            if ($PAGE->url->get_path() == $data->btn_classdateextensions_url->get_path()) {
                $data->show_classdateextensions = true;
            }
        }

        if (has_any_capability(['report/ghs:viewclassdeadlinesallschools', 'report/ghs:viewclassdeadlinesownschool'], $contextsystem)) {
            $data->btn_classdeadlines_url = new \moodle_url('/report/ghs/ghs_class_deadlines.php');
            if ($this->schoolid) {
                $data->btn_classdeadlines_url->param('schoolid', $this->schoolid);
            }
            if ($PAGE->url->get_path() == $data->btn_classdeadlines_url->get_path()) {
                $data->show_classdeadlines = true;
            }
        }

        if (has_any_capability(['report/ghs:viewenglishproficiencyallschools', 'report/ghs:viewenglishproficiencyownschool'], $contextsystem)) {
            $data->btn_englishproficiency_url = new \moodle_url('/report/ghs/ghs_english_proficiency.php');
            if ($this->schoolid) {
                $data->btn_englishproficiency_url->param('schoolid', $this->schoolid);
            }
            if ($PAGE->url->get_path() == $data->btn_englishproficiency_url->get_path()) {
                $data->show_englishproficiency = true;
            }
        }

        if (has_any_capability(['report/ghs:viewschoolreportsallschools', 'report/ghs:viewschoolreportsownschool'], $contextsystem)) {
            $data->btn_schoolreports_url = new \moodle_url('/report/ghs/ghs_school_reports.php');
            if ($this->schoolid) {
                $data->btn_schoolreports_url->param('schoolid', $this->schoolid);
            }
            if ($PAGE->url->get_path() == $data->btn_schoolreports_url->get_path()) {
                $data->show_schoolreports = true;
            }
        }

        if (has_capability('report/ghs:viewfrozenaccountsallschools', $contextsystem)) {
            $data->btn_rfrozenaccounts_url = new \moodle_url('/report/ghs/ghs_frozen_accounts.php');
            if ($this->schoolid) {
                $data->btn_rfrozenaccounts_url->param('schoolid', $this->schoolid);
            }
            if ($PAGE->url->get_path() == $data->btn_rfrozenaccounts_url->get_path()) {
                $data->show_frozenaccounts = true;
            }
        }

        return $data;
    }
}