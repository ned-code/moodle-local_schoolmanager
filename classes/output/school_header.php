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
use local_schoolmanager\school_handler as SH;
use local_schoolmanager\shared_lib as NED;

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
class school_header implements \renderable, \templatable {
    protected int $schoolid;
    protected SM\school $school;
    private $view;
    private $sm;
    private \moodle_url $url;

    public function __construct($schoolid, $view) {
        $this->schoolid = $schoolid;
        $this->school = new SM\school($schoolid);
        $this->sm = new SM\school_manager();
        $this->view = $view;
        $this->url = SH::get_url();
        if (!$this->schoolid) {
            $sh = SH::get_school_handler();
            $schools = $sh->get_schools();
            if (count($schools) == 1) {
                $this->url->param('schoolid', reset($schools)->id);
                redirect($this->url);
            }
        }
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $data = new \stdClass();

        if ($logourl = SM\school_manager::get_logo_url($this->schoolid)) {
            $data->logourl = $logourl->out();
        } else {
            $name = $this->school->get('name');
            if (($pos = strpos($name, '-')) !== false) {
                $data->shortname = trim(substr($name, $pos + 1 ));
            }
        }
        if ($this->school->get_cohort()) {
            $data->showheader = true;
            $data->timezone = $this->school->get_timezone();
            $data->localtime = $this->school->get_localtime();
        }
        $data->name = $this->school->get('name');
        $data->code = $this->school->get('code');
        $data->note = $this->school->get('note');
        $data->city = $this->school->get('city');
        $data->country = $this->school->get('country');
        $data->schoolwebsite = $this->school->get('url');
        $data->schoolwebsiteurl = $data->schoolwebsite;
        if (!preg_match("@^htt?ps?://@", $data->schoolwebsiteurl)) {
            $data->schoolwebsiteurl = "https://" . $data->schoolwebsiteurl;
        }
        $data->synctimezone = $this->school->get('synctimezone');


        $data->schoolyear = $this->school->get_schoolyear();
        if ($administrator = $this->sm->get_school_students($this->schoolid, true, $this->sm::SCHOOL_ADMINISTRATOR_ROLE, false)) {
            $administrator = reset($administrator);
            $data->administrator = fullname($administrator);
        }

        $data->{'btn'.$this->view.'csl'} = 'btn-primary';
        $data->{'show_'.$this->view} = 1;

        if ($this->view == SH::VIEW_CLASSES && has_capability('report/ghs:downloadgradesbulk', \context_system::instance())) {
            $name = NED::str('downloadallgrades', null, 'local_schoolmanager');
            $downloadallgradesurl = clone $this->url;
            $downloadallgradesurl->param('view', SH::VIEW_CLASSES);
            $downloadallgradesurl->param('download', 1);
            $data->downloadallgrades = NED::link($downloadallgradesurl, NED::fa('fa-download') . $name, 'float-right');
        }
        $ctx = NED::ctx();
        if ($viewstudentstaffsummary = NED::has_capability('viewstudentstaffsummary', $ctx)) {
            $data->{'canview_'.SH::VIEW_STUDENTS} = 1;
            $data->{'canview_'.SH::VIEW_STAFF} = 1;
        }
        if (NED::can_view_class_enrollment_report()) {
            $data->{'canview_'.SH::VIEW_CLASSES} = 1;
        }
        if (NED::has_capability('local/epctracker:viewownschool', $ctx)) {
            $data->{'canview_'.SH::VIEW_EPC} = 1;
        }

        $data->btn_students_url = clone $this->url;
        $data->btn_students_url->param('view', SH::VIEW_STUDENTS);
        $data->btn_students_url = $data->btn_students_url->out(false);

        $data->btn_staff_url = clone $this->url;
        $data->btn_staff_url->param('view', SH::VIEW_STAFF);
        $data->btn_staff_url = $data->btn_staff_url->out(false);

        $data->btn_school_url = clone $this->url;
        $data->btn_school_url->param('view', SH::VIEW_SCHOOL);
        $data->btn_school_url = $data->btn_school_url->out(false);

        $data->btn_classes_url = clone $this->url;
        $data->btn_classes_url->param('view', SH::VIEW_CLASSES);
        $data->btn_classes_url = $data->btn_classes_url->out(false);

        $data->btn_epc_url = clone $this->url;
        $data->btn_epc_url->param('view', SH::VIEW_EPC);
        $data->btn_epc_url = $data->btn_epc_url->out(false);

        $reportheader = (new \local_schoolmanager\output\reports_header($this->schoolid))->export_for_template($output);
        unset($reportheader->showheader);

        $data = NED::object_merge($data, $reportheader);

        return $data;
    }
}