<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\output;

use local_academic_integrity\infraction;
use local_schoolmanager as SM;
use local_schoolmanager\school_handler as SH;
use local_schoolmanager\shared_lib as NED;
use local_tem\helper as TEM;
use report_ghs\helper as GHS;

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
        global $OUTPUT, $PAGE, $CFG;

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
                $student->aiv = SH::get_user_aiv($student, $this->_persistent->_get_startdate(), $this->_persistent->_get_enddate());
                $aivschoolyear += $student->aiv;
                $student->aiv30 = SH::get_user_aiv($student, $this->_persistent->_get_startdate(), $this->_persistent->_get_enddate(), 30);
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
                                $staff->aivreports += SH::get_user_aiv((object)$user, $this->_persistent->_get_startdate(), $this->_persistent->_get_enddate(), 0, $courseid);
                                $staff->aivreports30 += SH::get_user_aiv((object)$user, $this->_persistent->_get_startdate(), $this->_persistent->_get_enddate(), 30, $courseid);
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
            $staffs = $this->_sm->get_school_students($this->schoolid, true, SM\school_manager::STAFF_ROLES, false);
            $data->students = $this->_sm->get_school_students($this->schoolid, true, $this->_sm::DEF_MEMBER_ROLE, false);
            $data->classroomteachers = count($this->_sm->get_school_students($this->schoolid, true, ['Classroom Teacher'], false) ?? []);
            $data->classroomassistants = count($this->_sm->get_school_students($this->schoolid, true, ['Classroom Assistant'], false) ?? []);
            $data->guidancecounsellors = count($this->_sm->get_school_students($this->schoolid, true, ['Guidance Counsellor'], false) ?? []);

            $data->activestudents = count($data->students);
            $data->classroomassistants = 0;
            $data->generalcert = 0;
            $data->advancedcert = 0;
            $data->certifiedproctors = 0;
            $data->staffs = 0;
            $data->viewpscompliancereport = has_capability('local/schoolmanager:view_ps_compliance_report', \context_system::instance());
            $aivschoolyear = $aiv30schoolyear = $deadlineextensions = $deadlineextensions20 = $deadlineextensions30 = 0;
            $ngc_data = array_fill_keys(static::NGC_KEYS, 0);
            $students_ngc = NED::$ned_grade_controller::get_students_ngc_records_count(array_keys($data->students));

            if ($administrator = $this->_sm->get_school_students($this->schoolid, true, $this->_sm::SCHOOL_ADMINISTRATOR_ROLE, false)) {
                $administrator = reset($administrator);
                $data->schooladministrator = fullname($administrator);

                $proctormanager = $this->_persistent->get('proctormanager');
                $data->proctormanager = ($proctormanager) ? fullname($staffs[$proctormanager]) : fullname($administrator);

                $academicintegritymanager = $this->_persistent->get('academicintegritymanager');
                $data->academicintegritymanager = ($academicintegritymanager) ? fullname($staffs[$academicintegritymanager]) : fullname($administrator);
            }

            $startdate = $this->_persistent->_get_startdate();
            $enddate = $this->_persistent->_get_enddate();

            foreach ($data->students as $sid => $student) {
                $student->aiv = SH::get_user_aiv($student, $startdate, $enddate);
                $aivschoolyear += $student->aiv;
                $student->aiv30 = SH::get_user_aiv($student, $startdate, $enddate, 30);
                $aiv30schoolyear += $student->aiv30;
                $courses = enrol_get_users_courses($student->id, true);
                $student->deadlineextentions = SH::get_user_number_of_dl_extensions($student, $courses);
                $student->deadlineextentions30 = SH::get_user_number_of_dl_extensions($student, $courses, 30);

                $deadlineextensions += $student->deadlineextentions;
                $deadlineextensions30 += $student->deadlineextentions30;

                if ($student->deadlineextentions > 20) {
                    $deadlineextensions20++;
                }

                foreach (static::NGC_KEYS as $ngc_key) {
                    if (empty($students_ngc[$sid])) {
                        $val = 0;
                    } else {
                        $val = $students_ngc[$sid]->$ngc_key ?? 0;
                    }

                    $student->$ngc_key = $val;
                    $ngc_data[$ngc_key] += $val;
                }
            }



            if ($staffs) {
                $data->staffs = count($staffs);
                foreach ($staffs as $staff) {
                    if (SH::has_certificate_badge($staff->id, 'general')) {
                        $data->generalcert++;
                    }
                    if (SH::has_certificate_badge($staff->id, 'advanced')) {
                        $data->advancedcert++;
                    }
                    if (SH::has_certificate_badge($staff->id, 'proctor')) {
                        $data->certifiedproctors++;
                    }
                }
            }

            $data->aivurl = (new \moodle_url('/local/academic_integrity/infractions.php', [
                'school' => $this->schoolid,
                'state' => -1
            ]))->out(false);

            $data->deadlineurl = (new \moodle_url('/local/ned_controller/grade_controller.php', [
                'school' => $this->schoolid,
                'reason' => 3
            ]))->out(false);

            $data->aivschoolyear = $aivschoolyear;
            $data->aiv30schoolyear = $aiv30schoolyear;
            $data->deadlineextensions = $deadlineextensions;
            if ($data->activestudents > 0) {
                $data->aivaverage = round(($aivschoolyear / $data->activestudents), 1);
            }

            foreach (static::NGC_KEYS as $ngc_key){
                $data->$ngc_key = $ngc_data[$ngc_key];
            }

            $data->caneditschool = true;
            $data->editschoolurl = NED::url('~/index.php', ['schoolid' => $this->schoolid]);
            $data->hasdifferenttimezone = SH::has_different_timezone_users_in_school($this->_persistent->get_cohort());
            $data->edittimezoneurl = (NED::url('~/view.php', [
                'view' => $this->_view,
                'schoolid' => $this->schoolid,
                'action' => 'resettimezone',
            ]))->out(false);
            $data->isadmin = is_siteadmin();

            // Academic Integrity Violations.
            $data->aivschoolyear = $aivschoolyear;
            $data->aivstartdate = NED::ned_date($startdate, '', null, NED::DT_FORMAT_DATE);
            $data->aivicon = $this->get_icon($aivschoolyear, 'A');

            $majorplagiarism = infraction::get_user_aiv_count(
                array_keys($data->students), null, $startdate,
                $enddate, null, false,
                infraction::PLAGIARISM_REASONS
            );
            $data->majorplagiarism = $this->percentage_format($majorplagiarism, $aivschoolyear);
            $data->majorplagiarismicon = $this->get_icon($majorplagiarism, 'A');

            $cheating = infraction::get_user_aiv_count(
                array_keys($data->students), null, $startdate,
                $enddate, null, false,
                infraction::CHEATING_REASONS
            );
            $data->cheating = $this->percentage_format($cheating, $aivschoolyear);
            $data->cheatingicon = $this->get_icon($cheating, 'A');

            $data->aiv30schoolyear = $aiv30schoolyear;
            $data->aivstartdate = NED::ned_date($startdate, '', null, NED::DT_FORMAT_DATE);
            $data->aiv30schoolyearicon = $this->get_icon($aiv30schoolyear, 'A');

            // Activity Deadlines.
            $data->missed_deadlinesicon = $this->get_icon($data->missed_deadlines, 'A');

            $data->deadlineextensionsaverage = 0;
            if ($data->activestudents > 0) {
                $data->deadlineextensionsaverage = round(($data->deadlineextensions / $data->activestudents), 1);
            }
            $data->deadlineextensionsicon = $this->get_icon($data->deadlineextensions, 'A');

            $data->deadlineextensions20 = $deadlineextensions20;
            $data->deadlineextensions20icon = $this->get_icon($deadlineextensions20, 'D');

            $data->deadlineextensions30 = $deadlineextensions30;
            $data->deadlineextensions30icon = $this->get_icon($deadlineextensions30, 'A');

            // Test Proctoring.
            $data->proctoringurl = (new \moodle_url('/local/tem/sessions.php', [
                'schoolid' => $this->schoolid
            ]))->out(false);

            $data->proctoringsubmitted = TEM::count_school_submitted_reports($this->schoolid);
            $data->proctoringsubmittedicon = $this->get_icon($data->proctoringsubmitted, 'B');

            $data->proctoringmissing = TEM::count_school_missing_reports($this->schoolid);
            $data->proctoringmissingicon = $this->get_icon($data->proctoringmissing, 'A');

            $data->proctorinwriting1 = TEM::count_school_writing_sessions($this->schoolid, 1);
            $data->proctorinwriting1icon = $this->get_icon($data->proctorinwriting1, 'B');

            $data->proctorinwriting2 = TEM::count_school_writing_sessions($this->schoolid, 2);
            $data->proctorinwriting2icon = $this->get_icon($data->proctorinwriting2, 'A');

            $data->proctorinwriting3 = TEM::count_school_writing_sessions($this->schoolid, 3, true);
            $data->proctorinwriting3icon = $this->get_icon($data->proctorinwriting3, 'D');

            // OSSLT Scores for Current School Year.
            $data->osslturl = (new \moodle_url('/report/ghs/ghs_english_proficiency.php', [
                'schoolid' => $this->schoolid,
                'ossltyear' => GHS::get_osslt_year(false)
            ]))->out(false);

            $wroteosslt = GHS::count_osslt($this->_persistent->get('code'), ['Fail', 'Pass']);
            $data->wroteosslticon = $this->get_icon($wroteosslt, 'A');
            $data->wroteosslt = $this->percentage_format($wroteosslt, $data->activestudents);

            $notwroteosslt = $data->activestudents - $wroteosslt;
            $data->notwroteosslticon = $this->get_icon($notwroteosslt, 'A');
            $data->notwroteosslt = $this->percentage_format($notwroteosslt, $data->activestudents);

            $passedosslt = GHS::count_osslt($this->_persistent->get('code'), 'Pass');
            $data->passedosslticon = $this->get_icon($passedosslt, 'B');
            $data->passedosslt = $this->percentage_format($passedosslt, $data->activestudents);

            $failedosslt = GHS::count_osslt($this->_persistent->get('code'), 'Fail');
            $data->failedosslticon = $this->get_icon($failedosslt, 'A');
            $data->failedosslt = $this->percentage_format($failedosslt, $data->activestudents);

            $failedossltover75 = GHS::count_osslt_failed_over75($this->_persistent->get('code'));
            $data->failedossltover75icon = $this->get_icon($failedossltover75, 'D');
            $data->failedossltover75 = $this->percentage_format($failedossltover75, $data->activestudents);

            // OSSLT Scores for Previous School Year.
            $data->prevosslturl = (new \moodle_url('/report/ghs/ghs_english_proficiency.php', [
                'schoolid' => $this->schoolid,
                'ossltyear' => GHS::get_osslt_year(true)
            ]))->out(false);

            $prevwroteosslt = GHS::count_osslt($this->_persistent->get('code'), ['Fail', 'Pass'], true);
            $data->prevwroteosslticon = $this->get_icon($prevwroteosslt, 'A');
            $data->prevwroteosslt = $this->percentage_format($prevwroteosslt, $data->activestudents);

            $prevnotwroteosslt = $data->activestudents - $prevwroteosslt;
            $data->prevnotwroteosslticon = $this->get_icon($prevnotwroteosslt, 'A');
            $data->prevnotwroteosslt = $this->percentage_format($prevnotwroteosslt, $data->activestudents);

            $prevpassedosslt = GHS::count_osslt($this->_persistent->get('code'), 'Pass', true);
            $data->prevpassedosslticon = $this->get_icon($prevpassedosslt, 'B');
            $data->prevpassedosslt = $this->percentage_format($prevpassedosslt, $data->activestudents);

            $prevfailedosslt = GHS::count_osslt($this->_persistent->get('code'), 'Fail', true);
            $data->prevfailedosslticon = $this->get_icon($prevfailedosslt, 'A');
            $data->prevfailedosslt = $this->percentage_format($prevfailedosslt, $data->activestudents);

            $prevfailedossltover75 = GHS::count_osslt_failed_over75($this->_persistent->get('code'), true);
            $data->prevfailedossltover75icon = $this->get_icon($prevfailedossltover75, 'D');
            $data->prevfailedossltover75 = $this->percentage_format($prevfailedossltover75, $data->activestudents);;

            // Logins.
            $data->usersurl = (new \moodle_url('/local/schoolmanager/view.php', [
                'schoolid' => $this->schoolid,
                'view' => 'students'
            ]))->out(false);

            $loggedusers3 = NED::count_logged_user(array_keys($data->students), 3);
            $data->loggedusers3icon = $this->get_icon($loggedusers3, 'B');
            $data->loggedusers3 = $this->percentage_format($loggedusers3, $data->activestudents);

            $loggedusers7 = NED::count_logged_user(array_keys($data->students), 7);
            $data->loggedusers7icon = $this->get_icon($loggedusers7, 'A');
            $data->loggedusers7 = $this->percentage_format($loggedusers7, $data->activestudents);

            $notloggedusers8 = NED::count_not_logged_user(array_keys($data->students), 8);
            $data->notloggedusers8icon = $this->get_icon($notloggedusers8, 'C');
            $data->notloggedusers8 = $this->percentage_format($notloggedusers8, $data->activestudents);

            $notloggedstaff10 = NED::count_not_logged_user(array_keys($staffs), 10);
            $data->notloggedstaff10icon = $this->get_icon($notloggedstaff10, 'C');
            $data->notloggedstaff10 = $this->percentage_format($notloggedstaff10, count($staffs));

            // Deadline Manager.
            $data->dmurl = (new \moodle_url('/report/ghs/ghs_group_enrollment.php', [
                'filterschool' => $this->schoolid,
            ]))->out(false);

            list($data->dmcomplete, $data->dmincomplete, $data->dmclasses, $data->dmcompleteended, $data->dmincompleteended) = NED::count_dm_scheule($this->_persistent->get('code'));
            $data->dmcompleteicon = $this->get_icon($data->dmcomplete, 'B');
            $data->dmincompleteicon = $this->get_icon($data->dmincomplete, 'A');
            $data->dmcompleteendedicon = $this->get_icon($data->dmcompleteended, 'B');
            $data->dmincompleteendedicon = $this->get_icon($data->dmincompleteended, 'A');

            unset($data->students);
        } if ($this->_view == SH::VIEW_CLASSES) {
            $data->show_output = true;
            include_once($CFG->dirroot. '/local/schoolmanager/class_report.php');
            $data->class_report = $html;
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
                        $school->aiv += SH::get_user_aiv($student, $school->persistent->_get_startdate(), $school->persistent->_get_enddate());
                        $school->aiv30 += SH::get_user_aiv($student, $school->persistent->_get_startdate(), $school->persistent->_get_enddate(), 30);
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

    /**
     * @param $number
     * @param $totalnumber
     * @return string
     */
    public function percentage_format($number, $totalnumber) {
        if (empty($totalnumber)) {
            return '';
        }

        $percenage = round((($number / $totalnumber) * 100));

        return "$number/$totalnumber ($percenage%)";
    }

    /**
     * @param $count
     * @param $type
     * @return false|string|void
     */
    public function get_icon($count, $type) {
        switch ($type) {
            case 'A':
                return  ($count) ? 'sad' : 'happy';
            case 'B':
                return  ($count) ? 'happy' : 'sad';
            case 'C':
                return  ($count) ? 'sad' : false;
            case 'D':
                return  ($count) ? 'warning' : false;
        }
    }

}
