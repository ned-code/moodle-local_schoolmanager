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
require_once($CFG->dirroot . '/badges/renderer.php');

/**
 * @property-read \core_renderer $o;
 * @property-read SM\school_manager $SM;
 * @property-read int $schoolid;
 * @property-read int $crewid;
 * @property-read int $page;
 * @property-read bool $act;
 */
class school implements \renderable, \templatable {
    /** the same keys, as object properties from {@see \local_ned_controller\ned_grade_controller::get_students_ngc_records_count()}*/
    const NGC_KEYS = ['wrong_submissions', 'late_submissions', 'missed_deadlines'];

    protected int $_schoolid;
    protected SM\school $_persistent;
    protected $_cohort;
    protected $_view;
    /**
     * @var SM\school_manager
     */
    protected $_sm;
    protected \moodle_url $_url;

    /**
     * school constructor.
     *
     * @param $schoolid
     * @param $view
     */
    public function __construct($schoolid, $view) {
        $this->schoolid = $schoolid;
        $this->_persistent = new SM\school($schoolid);
        $this->_sm = new SM\school_manager();
        $this->_view = $view;
        $this->_url = SH::get_url();
        if (!$this->schoolid) {
            $sh = new SH();
            $schools = $sh->get_schools();
            if (count($schools) == 1) {
                $this->_url->param('schoolid', reset($schools)->id);
                redirect($this->_url);
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
        global $OUTPUT, $PAGE;

        $header = new school_header($this->schoolid, $this->_view);
        $data = $header->export_for_template($output);

        if ($this->_view == SH::VIEW_STUDENTS) {
            $data->students = $this->_sm->get_school_students($this->schoolid, true, $this->_sm::DEF_MEMBER_ROLE, false);
            if (empty($data->students)) return $data;

            $gpas = $ppas = [];
            $aivschoolyear = $aiv30schoolyear = $deadlineextensions = 0;
            $ngc_data = array_fill_keys(static::NGC_KEYS, 0);
            $students_ngc = NED::$ned_grade_controller::get_students_ngc_records_count(array_keys($data->students));

            $badgerenderer = new \core_badges_renderer($PAGE, '');

            foreach ($data->students as $sid => $student) {
                $user_link = NED::link(['/my/index.php', ['userid' => $sid]], fullname($student), 'student');
                $student->username = NED::get_profile_with_menu_flag($sid, null, $user_link, true);
                $student->lastaccess = SH::get_user_lastaccess($student);
                $courses = enrol_get_users_courses($student->id, true);
                $student->deadlineextentions = SH::get_user_number_of_dl_extensions($student, $courses);
                $deadlineextensions += $student->deadlineextentions;
                /*$student->gpa = SH::get_user_gpa($student, $courses);
                if (!is_null($student->gpa)) {
                    $gpas[] = $student->gpa;
                }*/
                /*$participationpower = SH::get_user_ppa($student, $courses); // TODO: It slows down the page loading.
                $student->ppa = NED::str('pp-'.\theme_ned_boost\output\course::get_participation_power_status_by_power($participationpower),null,'local_ned_controller');
                if (!is_null($participationpower)) {
                    $ppas[] = $participationpower;
                }*/
                $student->aiv = SH::get_user_aiv($student, $this->_persistent->get('startdate'), $this->_persistent->get('enddate'));
                $aivschoolyear += $student->aiv;
                $student->aiv30 = SH::get_user_aiv($student, $this->_persistent->get('startdate'), $this->_persistent->get('enddate'), 30);
                $aiv30schoolyear += $student->aiv30;

                if ($records = badges_get_user_badges($sid, 0, null, null, null, true)) {
                    $student->badges = $badgerenderer->print_badges_list($records, $sid, true);
                }

                foreach (static::NGC_KEYS as $ngc_key){
                    if (empty($students_ngc[$sid])){
                        $val = 0;
                    } else {
                        $val = $students_ngc[$sid]->$ngc_key ?? 0;
                    }

                    $student->$ngc_key = $val;
                    $ngc_data[$ngc_key] += $val;
                }
            }

            $data->students = array_values($data->students);
            $data->activestudents = count($data->students);
            /*if ($gpas) {
                $data->averagegrade = round(array_sum($gpas) / count($gpas), 0);
            }*/
            /*if ($ppas) {
                $participationpower = array_sum($ppas) / count($ppas);
                $data->averagepp = NED::str('pp-'.\theme_ned_boost\output\course::get_participation_power_status_by_power($participationpower),null,'local_ned_controller');
            }*/
            $data->aivschoolyear = $aivschoolyear;
            $data->aiv30schoolyear = $aiv30schoolyear;
            $data->deadlineextensions = $deadlineextensions;

            foreach (static::NGC_KEYS as $ngc_key){
                $data->$ngc_key = $ngc_data[$ngc_key];
            }

            if ($data->activestudents > 0) {
                $data->aivaverage = round(($aivschoolyear / $data->activestudents), 1);
            }
        } elseif ($this->_view == SH::VIEW_STAFF) {
            $data->staffs = $this->_sm->get_school_students($this->schoolid, true, SM\school_manager::STAFF_ROLES, false);
            $courses = $gpas = $activestudents = [];

            $data->activestudents = 0;
            $data->aivschoolyear = 0;
            $data->aiv30schoolyear = 0;
            $data->deadlineextentions = 0;
            $data->classroomteachers = 0;
            $data->generalcert = 0;
            $data->advancedcert = 0;

            $badgerenderer = new \core_badges_renderer($PAGE, '');

            if ($data->staffs) {
                foreach ($data->staffs as $staff) {
                    profile_load_custom_fields($staff);
                    $staff->username = NED::q_user_link($staff);
                    $staff->role = $staff->profile['default_role'];
                    $staff->deadlineextentions = '';
                    $staff->aivreports = '';
                    $staff->aivreports30 = '';
                    $staff->ctgc = 'N';
                    $staff->ctac = 'N';

                    if ($records = badges_get_user_badges($staff->id, 0, null, null, null, true)) {
                        $staff->badges = $badgerenderer->print_badges_list($records, $staff->id, true);
                    }

                    $data->classroomteachers++;

                    if (SH::has_certificate_badge($staff->id, 'general')) {
                        $data->generalcert++;
                        $staff->ctgc = 'Y';
                    }
                    if (SH::has_certificate_badge($staff->id, 'advanced')) {
                        $data->advancedcert++;
                        $staff->ctac = 'Y';
                    }

                    if ($staff->role === SM\school_manager::SCHOOL_CT_ROLE) {
                        $staffstudents = [];
                        $classes = SH::get_classes($staff, $this->schoolid);
                        $staff->students = 0;
                        $staff->deadlineextentions = 0;
                        $staff->aivreports = 0;
                        $staff->aivreports30 = 0;

                        foreach ($classes as $index => $class) {
                            $courseid = $class['courseid'];
                            if (!isset($courses[$courseid])) {
                                $courses[$courseid] = get_course($courseid);
                            }
                            foreach ($class['users'] as $user) {
                                $activestudents[$user['id']] = $user['id'];
                                if (isset($staffstudents[$courseid][$user['id']])) {
                                    continue;
                                }
                                $staffstudents[$courseid][$user['id']] = $user['id'];
                                $staff->deadlineextentions += SH::get_user_number_of_dl_extensions((object)$user, [$courses[$courseid]]);
                                $staff->aivreports += SH::get_user_aiv((object)$user, $this->_persistent->get('startdate'), $this->_persistent->get('enddate'), 0, $courseid);
                                $staff->aivreports30 += SH::get_user_aiv((object)$user, $this->_persistent->get('startdate'), $this->_persistent->get('enddate'), 30, $courseid);
                            }
                        }

                        $data->aivschoolyear += $staff->aivreports;
                        $data->aiv30schoolyear += $staff->aivreports30;
                        $data->deadlineextentions += $staff->deadlineextentions;
                        $data->activestudents = count($activestudents);
                    }
                    $staff->lastaccess = SH::get_user_lastaccess($staff);
                }
                $data->staffs = array_values($data->staffs);
            }
        } elseif ($this->_view == SH::VIEW_SCHOOL) {
            $data->caneditschool = true;
            $data->editschoolurl = NED::url('~/index.php', ['schoolid' => $this->schoolid]);
            $data->hasdifferenttimezone = SH::has_different_timezone_users_in_school($this->_persistent->get_cohort());
            $data->edittimezoneurl = (NED::url('~/view.php', [
                'view' => $this->_view,
                'schoolid' => $this->schoolid,
                'action' => 'resettimezone',
            ]))->out(false);
            $data->isadmin = is_siteadmin();
        }

        if ($this->_view == SH::VIEW_SCHOOLS) {
            $showall = optional_param('showall', false, PARAM_BOOL);

            $sh = new SH();
            $schools = $sh->get_schools();
            $data->showall = $showall;
            $data->totalstudents = 0;
            $data->totalcts = 0;
            $data->totalctgc = 0;
            $data->totalctac = 0;
            $data->totalaiv = 0;
            $data->totalaiv30 = 0;
            foreach ($schools as $index => $school) {
                $school->persistent = new SM\school($school->id);
                $school->schoolurl = (NED::url('~/view.php', ['view' => SH::VIEW_STUDENTS, 'schoolid' => $school->id]))->out(false);
                $school->timezone = $school->persistent->get_timezone();
                $school->schoolyear = $school->persistent->get_schoolyear();
                $school->numberofstudents = 0;
                $school->ctgc = 0;
                $school->ctac = 0;
                $school->aivreports = 0;
                $school->aiv = 0;
                $school->aiv30 = 0;
                if ($students = $this->_sm->get_school_students($school->id, true, $this->_sm::DEF_MEMBER_ROLE, false)) {
                    $school->numberofstudents = count($students);
                    $data->totalstudents += $school->numberofstudents;
                    foreach ($students as $student) {
                        $school->aiv += SH::get_user_aiv($student, $school->persistent->get('startdate'), $school->persistent->get('enddate'));
                        $school->aiv30 += SH::get_user_aiv($student, $school->persistent->get('startdate'), $school->persistent->get('enddate'), 30);
                    }
                    $data->totalaiv += $school->aiv;
                    $data->totalaiv30 += $school->aiv30;
                    $school->aivaverage = round(($school->aiv /  $school->numberofstudents), 1);
                } else {
                    if (!$showall) {
                        unset($schools[$index]);
                        continue;
                    }
                }

                $school->numberofcts = 0;
                if ($cts = $this->_sm->get_school_students($school->id, true, SM\school_manager::STAFF_ROLES, false)) {
                    $school->numberofcts = count($cts);
                    $data->totalcts += $school->numberofcts;
                    foreach ($cts as $ct) {
                        if (SH::has_certificate_badge($ct->id, 'general')) {
                            $school->ctgc++;
                        }
                        if (SH::has_certificate_badge($ct->id, 'advanced')) {
                            $school->ctac++;
                        }
                    }
                    $data->totalctgc += $school->ctgc;
                    $data->totalctac += $school->ctac;
                }
                $actions = [];
                $actions[] = array(
                    'url' =>  NED::url('~/index.php', ['schoolid' => $school->id]),
                    'icon' => new \pix_icon('i/edit', get_string('edit')),
                    'attributes' => array('class' => 'action-edit')
                );

                $actionshtml = array();
                foreach ($actions as $action) {
                    $action['attributes']['role'] = 'button';
                    $actionshtml[] = $OUTPUT->action_icon($action['url'], $action['icon'], null, $action['attributes']);
                }
                $school->actionlinks = NED::span($actionshtml, 'class-item-actions item-actions');
            }
            $data->schools = array_values($schools);
            $data->totalschools = count($schools);

            if ($data->totalcts > 0) {
                $data->ctgcrate = round((($data->totalctgc / $data->totalcts) * 100), 0);
                $data->ctacrate = round((($data->totalctac / $data->totalcts) * 100), 0);
            }
        }
        return $data;
    }
}
