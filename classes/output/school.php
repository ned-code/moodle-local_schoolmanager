<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\output;
use block_ned_teacher_tools\deadline_manager as DM;
use local_schoolmanager as SM;
use local_schoolmanager\school_handler as SH;
use Matrix\Exception;
use theme_ned_boost\shared_lib as NED;
use tool_brickfield\local\areas\core_course\fullname;

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
class school implements \renderable, \templatable {
    protected int $schoolid;
    protected SM\school $persistent;
    protected $cohort;
    private $view;
    private $sm;
    private \moodle_url $url;

    public function __construct($schoolid, $view) {
        global $DB;
        $this->schoolid = $schoolid;
        $this->persistent = new SM\school($schoolid);
        $this->sm = new SM\school_manager();
        $this->view = $view;
        $this->url = SH::get_url();
        if (!$this->schoolid) {
            $sh = new SH();
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
        $contextsystem = \context_system::instance();

        $header = new school_header($this->schoolid, $this->view);
        $data = $header->export_for_template($output);

        if ($this->view == SH::VIEW_STUDENTS) {
            if ($data->students = $this->sm->get_school_students($this->schoolid, true, $this->sm::DEF_MEMBER_ROLE, false)) {
                $gpas = [];
                $ppas = [];
                $aivschoolyear = 0;
                $aiv30schoolyear = 0;
                $deadlineextensions = 0;
                foreach ($data->students as $student) {
                    $student->userlink = new \moodle_url('/user/profile.php', ['id' => $student->id]);
                    $ai_flag = "";
                    if (class_exists('\local_academic_integrity\ai_flag')) {
                        $ai_flag = \local_academic_integrity\ai_flag::flag($student->id, $contextsystem);
                    }
                    $student->name = $ai_flag . fullname($student);
                    $student->lastaccess = SH::get_user_lastaccess($student);
                    $courses = enrol_get_users_courses($student->id);
                    $student->deadlineextentions = SH::get_user_number_of_dl_extensions($student, $courses);
                    $deadlineextensions += $student->deadlineextentions;
                    $student->gpa = SH::get_user_gpa($student, $courses);
                    if (!is_null($student->gpa)) {
                        $gpas[] = $student->gpa;
                    }
                    /*$participationpower = SH::get_user_ppa($student, $courses); // TODO: It slows down the page loading.
                    $student->ppa = NED::str(\theme_ned_boost\output\course::get_participation_power_status_by_power($participationpower));
                    if (!is_null($participationpower)) {
                        $ppas[] = $participationpower;
                    }*/
                    $student->aiv = SH::get_user_aiv($student, $this->persistent->get('startdate'), $this->persistent->get('enddate'));
                    $aivschoolyear += $student->aiv;
                    $student->aiv30 = SH::get_user_aiv($student, $this->persistent->get('startdate'), $this->persistent->get('enddate'), 30);
                    $aiv30schoolyear += $student->aiv30;
                }
                $data->students = array_values($data->students);
                $data->activestudents = count($data->students);
                if ($gpas) {
                    $data->averagegrade = round(array_sum($gpas) / count($gpas), 0);
                }
                if ($ppas) {
                    $participationpower = array_sum($ppas) / count($ppas);
                    $data->averagepp = NED::str(\theme_ned_boost\output\course::get_participation_power_status_by_power($participationpower));
                }
                $data->aivschoolyear = $aivschoolyear;
                $data->aiv30schoolyear = $aiv30schoolyear;
                $data->misseddeadlines = '---';
                $data->deadlineextensions = $deadlineextensions;
            }
        }

        if ($this->view == SH::VIEW_STAFF) {
            $config = get_config('local_schoolmanager');
            if ($config->general_cert_course) {
                $course = get_course($config->general_cert_course);
                $completioncertgen = new \completion_info($course);
            }
            if ($config->advanced_cert_course) {
                $course = get_course($config->advanced_cert_course);
                $completioncertadv = new \completion_info($course);
            }


            $data->staffs = $this->sm->get_school_students($this->schoolid, true, $this->sm::STAFF_ROLES, false);
            $courses = [];
            $gpas = [];
            $activestudents = [];

            $data->activestudents = 0;
            $data->aivschoolyear = 0;
            $data->aiv30schoolyear = 0;
            $data->deadlineextentions = 0;
            $data->classroomteachers = 0;
            $data->generalcert = 0;
            $data->advancedcert = 0;

            foreach ($data->staffs as $staff) {
                profile_load_custom_fields($staff);
                $staff->name = fullname($staff);
                $staff->role = $staff->profile['default_role'];
                if ($staff->profile['default_role'] == 'Classroom Teacher') {
                    $data->classroomteachers++;
                }
                $classes = SH::get_classes($staff, $this->schoolid);
                $staff->classes = count($classes);
                $staff->students = 0;
                $staff->deadlineextentions = 0;
                $staff->aivreports = 0;
                $staff->aivreports30 = 0;
                if (isset($completioncertgen) && $completioncertgen->is_course_complete($staff->id)) {
                    $data->generalcert++;
                }
                if (isset($completioncertadv) && $completioncertadv->is_course_complete($staff->id)) {
                    $data->advancedcert++;
                }


                foreach ($classes as $index => $class) {
                    $staff->students += count($class['users']);
                    $courseid = $class['courseid'];
                    if (!isset($courses[$courseid])) {
                        $courses[$courseid] = get_course($courseid);
                    }
                    foreach ($class['users'] as $user) {
                        $activestudents[$user['id']] = $user['id'];
                        $staff->deadlineextentions += SH::get_user_number_of_dl_extensions((object)$user, [$courses[$courseid]]);
                        $staff->aivreports += SH::get_user_aiv((object)$user, $this->persistent->get('startdate'), $this->persistent->get('enddate'));
                        $staff->aivreports30 += SH::get_user_aiv((object)$user, $this->persistent->get('startdate'), $this->persistent->get('enddate'), 30);
                        if (!is_null($user['coursegrade'])) {
                            $gpas[] =  $user['coursegrade'];
                        }
                    }
                }
                $staff->lastaccess = SH::get_user_lastaccess($staff);
                $data->aivschoolyear += $staff->aivreports;
                $data->aiv30schoolyear += $staff->aivreports30;
                $data->deadlineextentions += $staff->deadlineextentions;
                $data->activestudents = count($activestudents);
            }

            $data->staffs = array_values($data->staffs);

            if ($gpas) {
                $data->averagegrade = round(array_sum($gpas) / count($gpas), 0);
            }
        }

        if ($this->view == SH::VIEW_SCHOOL) {
            try {
                $data->caneditschool = true;
                $data->editschoolurl = new \moodle_url('/local/schoolmanager/index.php', ['schoolid' => $this->schoolid]);
                $this->sm->show_error_if_necessary();
            } catch (Exception $e) {
               // Do nothing.
            }

        }

        if ($this->view == SH::VIEW_SCHOOLS) {
            $sh = new SH();
            $schools = $sh->get_schools();
            foreach ($schools as $school) {
                $school->persistent = new SM\school($school->id);
                $school->schoolurl = (new \moodle_url('/local/schoolmanager/view.php', ['view' => SH::VIEW_STUDENTS, 'schoolid' => $school->id]))->out(false);
                $school->timezone = $school->persistent->get_timezone();
                $school->schoolyear = $school->persistent->get_schoolyear();
                $school->numberofstudents = 0;
                if ($students = $this->sm->get_school_students($school->id, true, $this->sm::DEF_MEMBER_ROLE, false)) {
                    $school->numberofstudents = count($students);
                }

                $school->numberofcts = 0;
                if ($cts = $this->sm->get_school_students($school->id, true, $this->sm::SCHOOL_CT_ROLE, false)) {
                    $school->numberofcts = count($cts);
                }
            }
            $data->schools = array_values($schools);
        }
        return $data;
    }
}