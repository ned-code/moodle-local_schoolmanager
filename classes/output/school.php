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
use local_tem\helper as tem_help;
use local_tem\tem as TEM;
use report_ghs\helper as GHS;

defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */
require_once($CFG->dirroot . '/local/schoolmanager/lib.php');
require_once($CFG->dirroot . '/badges/renderer.php');

/**
 * Renderable School class
 */
class school implements \renderable, \templatable {
    /** the same keys, as object properties from {@see \local_ned_controller\ned_grade_controller::get_students_ngc_records_count()}*/
    const NGC_KEYS = ['wrong_submissions', 'late_submissions', 'missed_deadlines'];

    const ICON_SAD_HAPPY = 1;
    const ICON_HAPPY_SAD = 2;
    const ICON_SAD_FALSE = 3;
    const ICON_WARN_FALSE = 4;
    const ICON_WARN_SAD_HAPPY = 5;

    protected int $_schoolid;
    protected SM\school $_persistent;
    protected $_view;
    protected $_data;
    protected $_students;
    protected $_staffs;
    /**
     * @var SM\school_manager
     */
    protected $_sm;

    /**
     * school constructor.
     *
     * @param $schoolid
     * @param $view
     */
    public function __construct($schoolid, $view) {
        $this->_schoolid = $schoolid;
        $this->_persistent = new SM\school($schoolid);
        $this->_sm = new SM\school_manager();
        $this->_view = $view;
        $url = SH::get_url();
        if (!$this->_schoolid) {
            $sh = SH::get_school_handler();
            $schools = $sh->get_schools();
            if (count($schools) == 1) {
                $url->param('schoolid', reset($schools)->id);
                if (NED::has_capability('viewschooldescription')) {
                    $url->param('view', SH::VIEW_SCHOOL);
                }
                redirect($url);
            }
        } else {
            $url = NED::page()->url;
            $url->param('schoolid', $this->_schoolid);
            $url->param(NED::PAR_VIEW, $this->_view);
            NED::page()->set_url($url);
        }
        $this->_data = (object)[];
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $header = new school_header($this->_schoolid, $this->_view);
        $this->_data = NED::object_merge($this->_data, $header->export_for_template($output));
        $this->_data->show_prev_30 = false;
        $has_summary_blocks = true;
        $context = NED::ctx();
        $this->_data->viewschoolprofile = NED::has_capability('viewschoolprofile', $context);

        switch ($this->_view){
            case SH::VIEW_STUDENTS:
                if (NED::has_capability('viewstudentstaffsummary', $context)) {
                    $this->_export_view_students();
                    $this->_data->viewstudents = 1;
                }
                break;
            case SH::VIEW_STAFF:
                if (NED::has_capability('viewstudentstaffsummary', $context)) {
                    $this->_export_view_staff();
                    $this->_data->viewsstaff = 1;
                }
                break;
            case SH::VIEW_SCHOOL:
                $this->_export_view_school();
                break;
            case SH::VIEW_SCHOOLS:
                $this->_export_view_schools();
                $has_summary_blocks = false;
                break;
            case SH::VIEW_CLASSES:
                /** @var string $html - get it from /local/schoolmanager/class_report.php */
                global $html;
                $this->_data->show_output = true;
                include_once(NED::$DIRROOT.'/local/schoolmanager/class_report.php');
                $this->_data->class_report = $html;
                $has_summary_blocks = false;
                break;
            case SH::VIEW_EPC:
                $has_summary_blocks = false;
                $this->_data->show_output = true;

                $file = NED::$DIRROOT . '/local/epctracker/epc_report.php';
                if (!file_exists($file)){
                    $this->_data->class_report = NED::notification('noepctracker', NED::NOTIFY_ERROR, false);
                    break;
                }

                $students = $this->get_students();
                if (empty($students)) {
                    $this->_data->class_report = NED::notification('nostudents', NED::NOTIFY_WARNING, false);
                    break;
                }

                /** @var string $html - get it from /local/epctracker/epc_report.php */
                global $html;
                include_once($file);
                $this->_data->class_report = $html;

                break;
            case SH::VIEW_FROZENACCOUNTS:
                /** @var string $html - get it from /local/schoolmanager/frozen_accounts.php */
                global $html;
                $this->_data->show_output = true;
                include_once(NED::$DIRROOT.'/local/schoolmanager/frozen_accounts.php');
                $this->_data->class_report = $html;
                $has_summary_blocks = false;
                break;
        }

        if ($has_summary_blocks){
            $this->_export_summary_blocks();
        }

        $data = $this->get_data();
        $this->clean_memory();
        return $data;
    }

    /**
     * Return start and end of the school year (of the current school)
     *
     * @return array{0: int, 1: int} - [$startdate, $enddate]
     */
    public function get_school_year(){
        $startdate = $this->_persistent->_get_startdate();
        $enddate = $this->_persistent->_get_enddate();
        return [$startdate, $enddate];
    }

    /**
     * Calculate $percenage and format data as "$number/$totalnumber ($percenage%)"
     *
     * @param int $number
     * @param int $totalnumber
     *
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
     * @param int $val0 - value to check
     * @param int $type - type of compression, {@see static::ICON_SAD_HAPPY} by default
     * @param int $val1 - (optional) second value to compare, 0 by default
     *
     * @return false|string
     */
    static public function get_icon($val0, $type=null, $val1=0){
        switch ($type) {
            default:
            case static::ICON_SAD_HAPPY:
                return ($val0 > $val1) ? 'sad' : 'happy';
            case static::ICON_HAPPY_SAD:
                return ($val0 > $val1) ? 'happy' : 'sad';
            case static::ICON_SAD_FALSE:
                return ($val0) ? 'sad' : false;
            case static::ICON_WARN_FALSE:
                return ($val0) ? 'warning' : false;
            case static::ICON_WARN_SAD_HAPPY:
                if (empty($val0) && empty($val1)) return 'happy';
                else return match(true){
                    $val0 > $val1  => 'warning',
                    $val0 < $val1 => 'happy',
                    default => 'sad'
                };
        }
    }

    /**
     * Return dynamic string $val0 relative to $val1
     * @param numeric $val0
     * @param numeric $val1 - (optional) default 0
     *
     * @return string - translate string of changes between $val0 and $val1
     */
    public function get_dynamic($val0, $val1=0): string {
        static $str_data = null;
        if (empty($str_data)){
            $str_data = [
                'up'    => NED::str('dynamic-up'),
                'down'  => NED::str('dynamic-down'),
                'same'  => NED::str('dynamic-same'),
            ];
        }

        return match (true){
            $val0 > $val1 => $str_data['up'],
            $val0 < $val1 => $str_data['down'],
            default => $str_data['same']
        };
    }

    /**
     * Export data for the student view
     *
     * @return void - result saves in the $this->_data;
     */
    protected function _export_view_students(){
        [$startdate, $enddate] = $this->get_school_year();

        $students = $this->get_students();
        if (empty($students)) return;

        //$gpas = $ppas = [];
        $students_ngc = NED::$ned_grade_controller::get_students_ngc_records_count(array_keys($students), $startdate, $enddate);

        $badgerenderer = new \core_badges_renderer(NED::page(), '');
        foreach ($students as $sid => $student) {
            $user_link = NED::link(['/my/index.php', ['userid' => $sid]], fullname($student), 'student');
            $student->username = NED::get_profile_with_menu_flag($sid, null, $user_link, true);
            $student->lastaccess = SH::get_user_lastaccess($student);
            $student->deadlineextensions = SH::get_user_number_of_dl_extensions([$student->id], $startdate, $enddate);
            /*$student->gpa = SH::get_user_gpa($student, $courses);
            if (!is_null($student->gpa)) {
                $gpas[] = $student->gpa;
            }*/
            /*$participationpower = SH::get_user_ppa($student, $courses); // TODO: It slows down the page loading.
            $student->ppa = NED::str('pp-'.\theme_ned_boost\output\course::get_participation_power_status_by_power($participationpower),null,'local_ned_controller');
            if (!is_null($participationpower)) {
                $ppas[] = $participationpower;
            }*/
            $student->aiv = SH::get_users_aiv($student, $startdate, $enddate);
            $student->aiv30 = SH::get_users_aiv($student, $startdate, $enddate, 30);

            if ($records = badges_get_user_badges($sid, 0, null, null, null, true)) {
                $student->badges = $badgerenderer->print_badges_list($records, $sid, true);
            }

            foreach (static::NGC_KEYS as $ngc_key){
                $val = ($students_ngc[$sid] ?? null)?->$ngc_key ?? 0;
                $student->$ngc_key = $val;
            }
        }

        $this->_data->students = array_values($students);
    }

    /**
     * Export data for the staff view
     *
     * @return void - result saves in the $this->_data;
     */
    protected function _export_view_staff(){
        [$startdate, $enddate] = $this->get_school_year();
        $staffs = $this->get_staffs();
        $badgerenderer = new \core_badges_renderer(NED::page(), '');

        foreach ($staffs as $staff) {
            $staff->username = NED::q_user_link($staff);
            $staff->role = $staff->def_role ?? '';
            $staff->deadlineextensions = '';
            $staff->aivreports = '';
            $staff->aivreports30 = '';
            $staff->ctgc = false;
            $staff->ctac = false;

            if ($records = badges_get_user_badges($staff->id, 0, null, null, null, true)) {
                $staff->badges = $badgerenderer->print_badges_list($records, $staff->id, true);
            }

            if ($staff->role === SM\school_manager::SCHOOL_CT_ROLE) {
                $staffstudents = [];
                $classes = SH::get_classes($staff, $this->_schoolid);
                $staff->students = 0;
                $staff->deadlineextensions = 0;
                $staff->aivreports = 0;
                $staff->aivreports30 = 0;

                $staff->ctgc = SH::has_certificate_badge($staff->id, 'general');
                $staff->ctac = SH::has_certificate_badge($staff->id, 'advanced');

                foreach ($classes as $class) {
                    $courseid = $class['courseid'];
                    foreach ($class['users'] as $user){
                        $userid = $user['id'];
                        if (isset($staffstudents[$courseid][$userid])) continue;

                        $staffstudents[$courseid][$userid] = $userid;
                        $staff->deadlineextensions +=
                            SH::get_user_number_of_dl_extensions([$userid], $startdate, $enddate, [$courseid]);
                        $staff->aivreports += SH::get_users_aiv([$userid], $startdate, $enddate, 0, $courseid);
                        $staff->aivreports30 += SH::get_users_aiv([$userid], $startdate, $enddate, 30, $courseid);
                    }
                }
            }
            $staff->lastaccess = SH::get_user_lastaccess($staff);
        }

        $this->_data->staffs = $staffs ? array_values($staffs) : null;
    }

    /**
     * Export data for the single school view
     * Do not mixed up with the {@see static::_export_view_schools()}
     *
     * @return void - result saves in the $this->_data;
     */
    protected function _export_view_school(){
        /**
         * Data for the "School Profile" block get from the school_header data
         * @see static::export_for_template()
         */
        $this->_data->caneditschool = $this->_sm->can_manage_schools();
        $this->_data->editschoolurl = NED::url('~/index.php', ['schoolid' => $this->_schoolid]);
        $this->_data->hasdifferenttimezone = SH::has_different_timezone_users_in_school($this->_persistent->get_cohort());
        $this->_data->edittimezoneurl = (NED::url('~/view.php', [
            'view' => $this->_view,
            'schoolid' => $this->_schoolid,
            'action' => 'resettimezone',
        ]))->out(false);
        $this->_data->isadmin = is_siteadmin();
        $this->_data->viewschooldescription = NED::has_capability('viewschooldescription', NED::ctx());

        if (empty($this->_persistent->get('hidecompliancereport'))) {
            $this->_data->compliance_report = $this->_get_compliance_report_data();
        }
    }

    /**
     * Export data for the several schools view
     * Do not mixed up with the {@see static::_export_view_school()}
     *
     * @return void - result saves in the $this->_data;
     */
    protected function _export_view_schools(){
        $sh = SH::get_school_handler();
        $schools = $sh->get_schools();
        $showall = NED::get_showallschools_param_value();
        $this->_data->showall = $showall;
        $this->_data->totalstudents = 0;
        $this->_data->totalcts = 0;
        $this->_data->totalctgc = 0;
        $this->_data->totalctac = 0;
        $this->_data->totalaiv = 0;
        $this->_data->totalaiv30 = 0;
        foreach ($schools as $index => $school) {
            $school->persistent = new SM\school($school->id);
            $school->schoolurl = (NED::url('~/view.php', ['view' => SH::VIEW_SCHOOL, 'schoolid' => $school->id]))->out(false);
            $school->timezone = $school->persistent->get_timezone();
            $school->schoolyear = $school->persistent->get_schoolyear();
            $startdate = $school->persistent->_get_startdate();
            $enddate = $school->persistent->_get_enddate();
            $school->numberofstudents = 0;
            $school->ctgc = 0;
            $school->ctac = 0;
            $school->aivreports = 0;
            $school->aiv = 0;
            $school->aiv30 = 0;
            if ($students = $this->_sm->get_school_students($school->id, true, $this->_sm::DEF_MEMBER_ROLE, false)) {
                $school->numberofstudents = count($students);
                $students_ids = array_keys($students);
                $school->aiv += SH::get_users_aiv($students_ids, $startdate, $enddate);
                $school->aiv30 += SH::get_users_aiv($students_ids, $startdate, $enddate, 30);

                $this->_data->totalstudents += $school->numberofstudents;
                $this->_data->totalaiv += $school->aiv;
                $this->_data->totalaiv30 += $school->aiv30;
                $school->aivaverage = round(($school->aiv /  $school->numberofstudents), 1);
            } else {
                if (!$showall) {
                    unset($schools[$index]);
                    continue;
                }
            }

            $school->numberofcts = 0;
            if ($cts = $this->_sm->get_school_students($school->id, true, NED::DEFAULT_ROLE_CT, false)) {
                $school->numberofcts = count($cts);
                $this->_data->totalcts += $school->numberofcts;
                foreach ($cts as $ct) {
                    if (SH::has_certificate_badge($ct->id, 'general')) {
                        $school->ctgc++;
                    }
                    if (SH::has_certificate_badge($ct->id, 'advanced')) {
                        $school->ctac++;
                    }
                }
                $this->_data->totalctgc += $school->ctgc;
                $this->_data->totalctac += $school->ctac;
            }

            $actions = [];
            if ($this->_sm->can_manage_schools()){
                $actions[] = [
                    'url' =>  NED::url('~/index.php', ['schoolid' => $school->id]),
                    'icon' => new \pix_icon('i/edit', get_string('edit')),
                    'attributes' => ['class' => 'action-edit'],
                ];
            }

            $actionshtml = [];
            foreach ($actions as $action) {
                $action['attributes']['role'] = 'button';
                $actionshtml[] = NED::O()->action_icon($action['url'], $action['icon'], null, $action['attributes']);
            }
            $school->actionlinks = NED::span($actionshtml, 'class-item-actions item-actions');

            if ($logourl = SM\school_manager::get_logo_url($school->id)) {
                $school->iconindicator = NED::fa('fa-picture-o m-0');
            } else {
                $school->iconindicator = NED::fa('fa-square-o m-0');
            }
        }
        $this->_data->schools = array_values($schools);
        $this->_data->totalschools = count($schools);

        if ($this->_data->totalcts > 0) {
            // Note: next lines are not the same :)
            $this->_data->ctgcrate = round((($this->_data->totalctgc / $this->_data->totalcts) * 100), 0);
            $this->_data->ctacrate = round((($this->_data->totalctac / $this->_data->totalcts) * 100), 0);
        }
    }

    /**
     * Export (save to data) Student and Staff summary blocks
     *
     * @return void
     */
    protected function _export_summary_blocks(){
        if (NED::has_capability('viewstudentstaffsummary', NED::ctx())) {
            $this->_data->student_summary = $this->_get_student_summary_block_data();
            $this->_data->staff_summary = $this->_get_staff_summary_block_data();
        }
    }

    /**
     * Get data object for the Student Summary block
     *
     * @return object|null - object with data for template local_schoolmanager/school_summary_students
     */
    protected function _get_student_summary_block_data(){
        $students = $this->get_students();
        $students_ids = array_keys($students);
        $students_count = NED::count($students);
        [$startdate, $enddate] = $this->get_school_year();

        $res = (object)[];

        $res->activestudents = $students_count;
        $res->deadlineextensions = SH::get_user_number_of_dl_extensions($students_ids, $startdate, $enddate);
        $res->aivschoolyear = SH::get_users_aiv($students_ids, $startdate, $enddate);
        $res->aiv30schoolyear = SH::get_users_aiv($students_ids, $startdate, $enddate, 30);
        $res->aivaverage = $students_count > 0 ? round(($res->aivschoolyear / $students_count), 1) : '-';

        $ngc_data = NED::$ned_grade_controller::get_students_ngc_records_count($students_ids, $startdate, $enddate, null);
        $ngc_data = reset($ngc_data);
        foreach (static::NGC_KEYS as $ngc_key){
            $res->$ngc_key = $ngc_data->$ngc_key ?? 0;
        }

        return $res;
    }

    /**
     * Export data for the Staff Summary block
     *
     * @return object|null - object with data for template local_schoolmanager/school_summary_staff
     */
    protected function _get_staff_summary_block_data(){
        $res = (object)[];

        $staffs = $this->get_staffs();
        $res->staff_count = count($staffs);
        $res->classroomteachers = 0;
        $res->classroomassistants = 0;
        $res->guidancecounsellors = 0;

        $res->generalcert = 0;
        $res->advancedcert = 0;
        $res->certifiedproctors = 0;
        $admins = [];

        foreach ($staffs as $staff){
            if (SH::has_certificate_badge($staff->id, 'proctor')) {
                $res->certifiedproctors++;
            }

            if (empty($staff->def_role)) continue;

            switch($staff->def_role){
                default: break;
                case NED::DEFAULT_ROLE_CA:
                    $res->classroomassistants++;
                    break;
                case NED::DEFAULT_ROLE_GC:
                    $res->guidancecounsellors++;
                    break;
                case NED::DEFAULT_ROLE_SCHOOL_ADMINISTRATOR:
                    $admins[] = $staff;
                    break;

                case NED::DEFAULT_ROLE_CT:
                    $res->classroomteachers++;

                    if (SH::has_certificate_badge($staff->id, 'general')){
                        $res->generalcert++;
                    }
                    if (SH::has_certificate_badge($staff->id, 'advanced')){
                        $res->advancedcert++;
                    }

                    break;
            }
        }

        if (!empty($admins)) {
            $admin = reset($admins);
            $res->schooladministrator = NED::q_user_link($admin);

            $proctormanager = $this->_persistent->get('proctormanager');
            if ($proctormanager && !empty($staffs[$proctormanager])){
                $res->proctormanager = NED::q_user_link($staffs[$proctormanager]);
            }

            $academicintegritymanager = $this->_persistent->get('academicintegritymanager');
            if ($academicintegritymanager && !empty($staffs[$academicintegritymanager])){
                $res->academicintegritymanager = NED::q_user_link($staffs[$academicintegritymanager]);
            }
        }

        return $res;
    }

    protected function _get_compliance_report_data(){
        if (!NED::has_capability('view_ps_compliance_report')) return null;

        $res = (object)[];

        $res->aiv_report = $this->_get_aiv_report();
        $res->ngc_dm_report = $this->_get_ngc_dm_report();
        $res->tem_report = $this->_get_proctoring_report();
        $res->osslt_report = $this->_get_osslt_report();
        // Other - Logins
        $res->login_report = $this->_get_logins_report();
        // Other - DM
        $res->dm_report = $this->_get_dm_report();

        return $res;
    }

    /**
     * Reports, "Academic Integrity Violations" section
     *
     * @return object|null
     */
    protected function _get_aiv_report(){
        if (!NED::is_ai_exists()) return null;

        $res = (object)[];
        $res->aivurl = (new \moodle_url(NED::PAGE_AI_INFRACTIONS,
            ['school' => $this->_schoolid, 'state' => -1,])
        )->out(false);
        $res->title_help_obj = NED::get_help_icon('cr_aiv_help');

        [$startdate, $enddate] = $this->get_school_year();
        [$prev_30_start, $prev_30_end] = NED::time_process_period(NED::PERIOD_PREV_30);
        $res->show_prev30 = $prev_30_start >= $startdate;
        $prev_30_end = min($prev_30_end, $enddate);

        $students = $this->get_students();
        $students_ids = array_keys($students);

        $aivschoolyear = $this->_data->aivschoolyear ?? $this->_data->student_summary->aivschoolyear ?? null;
        if (!isset($aivschoolyear)){
            $aivschoolyear = SH::get_users_aiv($students_ids, $startdate, $enddate);
        }

        $res->aivstartdate = NED::ned_date($startdate, '', null, NED::DT_FORMAT_DATE);
        $res->aivschoolyear = $aivschoolyear;
        $res->aivicon = $this->get_icon($aivschoolyear);
        $aiv30schoolyear = 0;
        $aiv_prev_30 = 0;

        if ($aivschoolyear > 0){
            $majorplagiarism = 0;
            $cheating = 0;
            if (!empty($students)){
                $majorplagiarism = infraction::get_user_aiv_count(
                    $students_ids, null, $startdate, $enddate,
                    null, false, null, infraction::PENALTY_MAJOR_PLAGIARISM
                );
                $cheating = infraction::get_user_aiv_count(
                    $students_ids, null, $startdate, $enddate,
                    null, false, null, infraction::PENALTY_CHEATING
                );
            }

            $res->majorplagiarism = $this->percentage_format($majorplagiarism, $aivschoolyear);
            $res->majorplagiarismicon = $this->get_icon($majorplagiarism);
            $res->cheating = $this->percentage_format($cheating, $aivschoolyear);
            $res->cheatingicon = $this->get_icon($cheating);

            $aiv30schoolyear = SH::get_users_aiv($students_ids, $startdate, $enddate, 30);

            if ($res->show_prev30){
                $aiv_prev_30 = SH::get_users_aiv($students_ids, $prev_30_start, $prev_30_end);
            }
        }

        $res->aiv30schoolyear = $aiv30schoolyear;
        $res->aiv_prev_30 = $aiv_prev_30;
        $res->aiv30schoolyearicon = $this->get_icon($aiv30schoolyear);

        if ($res->show_prev30){
            $res->aiv_prev_30icon = $this->get_icon($aiv_prev_30);
            $res->aiv30_dynamic_icon = $this->get_icon($aiv30schoolyear, static::ICON_WARN_SAD_HAPPY, $aiv_prev_30);
            $res->aiv30_dynamic_state = $this->get_dynamic($aiv30schoolyear, $aiv_prev_30);
        }

        return $res;
    }

    /**
     * Reports, "Activity deadlines" section
     *
     * @return object|null
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    protected function _get_ngc_dm_report(){
        $res = (object)[];

        $NGC = NED::$ned_grade_controller;
        $res->ngcurl = (new \moodle_url(NED::PAGE_GRADE_CONTROLLER, [
            NED::PAR_SCHOOL => $this->_schoolid,
            $NGC::$NGC_RENDER::PAR_REASON => $NGC::REASON_SUBMISSION,
            $NGC::$NGC_RENDER::PAR_GRADE_TYPE => $NGC::GT_AWARD_ZERO,
        ]))->out(false);
        $res->title_help_obj = NED::get_help_icon('cr_ngc_dm_help');

        [$startdate, $enddate] = $this->get_school_year();
        [$last_30_start, $last_30_end] = NED::time_process_period(NED::PERIOD_LAST_30);
        $last_30_start = max($last_30_start, $startdate);
        $last_30_end = min($last_30_end, $enddate);
        [$prev_30_start, $prev_30_end] = NED::time_process_period(NED::PERIOD_PREV_30);
        $res->show_prev30 = $prev_30_start >= $startdate;
        $prev_30_end = min($prev_30_end, $enddate);

        $students = $this->get_students();
        $students_count = count($students);
        $students_ids = array_keys($students);

        // Hide missed_deadlines metrics for now
        $res->show_missed_deadlines = false;
        if ($res->show_missed_deadlines){
            $missed_deadlines = $this->_data->missed_deadlines ?? $this->_data->student_summary->missed_deadlines ?? null;
            $ngc_periods = [NED::PERIOD_LAST_30];
            if ($res->show_prev30){
                $ngc_periods[] = NED::PERIOD_PREV_30;
            }
            if (!isset($missed_deadlines)){
                $ngc_periods[] = NED::PERIOD_TOTAL;
            }

            $ngc_data = $NGC::get_students_ngc_records_count($students_ids, $startdate, $enddate, null, $ngc_periods);
            $ngc_data = reset($ngc_data);

            $res->missed_deadlines = $missed_deadlines ?? $ngc_data->{"missed_deadlines_".NED::PERIOD_TOTAL} ?? 0;
            $res->missed_deadlines_last_30 = $ngc_data->{"missed_deadlines_".NED::PERIOD_LAST_30} ?? 0;
            $res->missed_deadlines_prev_30 = $ngc_data->{"missed_deadlines_".NED::PERIOD_PREV_30} ?? 0;

            $res->missed_deadlinesicon = $this->get_icon($res->missed_deadlines);
            $res->missed_deadlines_last_30_icon = $this->get_icon($res->missed_deadlines_last_30);
        }

        $res->deadlineextensions = $this->_data->student_summary->deadlineextensions ?? $this->_data->deadlineextensions ?? null;
        if (!isset($res->deadlineextensions)){
            $res->deadlineextensions = SH::get_user_number_of_dl_extensions($students_ids, $startdate, $enddate);
        }

        $res->deadlineextensionsaverage = 0;
        if ($students_count > 0) {
            $res->deadlineextensionsaverage = round(($res->deadlineextensions / $students_count), 1);
        }
        $res->deadlineextensionsicon = $this->get_icon($res->deadlineextensions);

        $res->deadlineextensions_last_30 = SH::get_user_number_of_dl_extensions($students_ids, $last_30_start, $last_30_end);
        $res->deadlineextensionsicon_last_30 = $this->get_icon($res->deadlineextensions_last_30);

        $res->deadlineextensions20 = SH::get_user_number_with_extensions20($students_ids, $startdate, $enddate);
        $res->deadlineextensions20icon = $this->get_icon($res->deadlineextensions20, static::ICON_WARN_FALSE);

        if ($res->show_prev30){
            if ($res->show_missed_deadlines){
                $res->missed_deadlines_prev_30_icon = $this->get_icon($res->missed_deadlines_prev_30);
                $res->missed_deadlines_30_dynamic_icon =
                    $this->get_icon($res->missed_deadlines_last_30, static::ICON_WARN_SAD_HAPPY, $res->missed_deadlines_prev_30);
                $res->missed_deadlines_30_dynamic_state = $this->get_dynamic($res->missed_deadlines_last_30, $res->missed_deadlines_prev_30);
            }

            $res->deadlineextensions_prev_30 = SH::get_user_number_of_dl_extensions($students_ids, $prev_30_start, $prev_30_end);
            $res->deadlineextensions_prev_30_icon = $this->get_icon($res->deadlineextensions_prev_30);

            $res->deadlineextensions30_dynamic_icon =
                $this->get_icon($res->deadlineextensions_last_30, static::ICON_WARN_SAD_HAPPY, $res->deadlineextensions_prev_30);
            $res->deadlineextensions30_dynamic_state = $this->get_dynamic($res->deadlineextensions_last_30, $res->deadlineextensions_prev_30);
        }

        return $res;
    }

    /**
     * Get School Test Proctoring Section Data
     *
     * @return object|null
     */
    protected function _get_proctoring_report() {
        if (!NED::is_tem_exists()) return null;

        $res = (object)[];
        [$startdate, $enddate] = $this->get_school_year();
        [$last_30_start, $last_30_end] = NED::time_process_period(NED::PERIOD_LAST_30);


        $res->proctoringurl = (new \moodle_url('/local/tem/sessions.php', [
            'schoolid' => $this->_schoolid,
        ]))->out(false);
        $res->title_help_obj = NED::get_help_icon('cr_tem_proctoring_help');

        $res->proctoringsubmitted = tem_help::count_school_submitted_reports($this->_schoolid, $startdate, $enddate);
        $res->proctoringsubmittedicon = $this->get_icon($res->proctoringsubmitted, static::ICON_HAPPY_SAD);

        $res->proctoringmissing = tem_help::count_school_missing_reports($this->_schoolid, $startdate, $enddate);
        $res->proctoringmissingicon = $this->get_icon($res->proctoringmissing);

        $res->proctoringoverdue10days = NED::count_school_proctoring_reports($this->_schoolid, $startdate, $enddate,DAYSECS * 10);
        $res->proctoringoverdue10daysicon = $this->get_icon($res->proctoringoverdue10days, static::ICON_WARN_FALSE);

        $res->proctorinwriting1 = tem_help::count_school_writing_sessions($this->_schoolid, 1, $startdate, $enddate);
        $res->proctorinwriting1icon = $this->get_icon($res->proctorinwriting1, static::ICON_HAPPY_SAD);

        $res->proctorinwriting2 = tem_help::count_school_writing_sessions($this->_schoolid, 2, $startdate, $enddate);
        $res->proctorinwriting2icon = $this->get_icon($res->proctorinwriting2);

        $res->proctorinwriting3 = tem_help::count_school_writing_sessions($this->_schoolid, 3, $startdate, $enddate);
        $res->proctorinwriting3icon = $this->get_icon($res->proctorinwriting3, static::ICON_WARN_FALSE);

        $students = $this->get_students();
        $students_ids = array_keys($students);

        $res->ip_violations = tem_help::count_ip_violations_by_students($students_ids, $this->_schoolid, $startdate, $enddate);
        $res->ip_violationsicon = $this->get_icon($res->ip_violations);

        $res->ip_violations_last_30 = tem_help::count_ip_violations_by_students($students_ids, $this->_schoolid, $last_30_start, $last_30_end);
        $res->ip_violations_last_30icon = $this->get_icon($res->ip_violations_last_30);

        if (tem_help::can_view_sessions()) {
            $violations_url = new \moodle_url(NED::$C::PAGE_TEM_VIOLATIONS);

            $violations_url->params([
                'school' => $this->_schoolid,
                'irregularities' => NED::flag_set_arr_bitmask(TEM::VIOLATION_IPS_FLAGS),
                NED::PAR_SCHOOL_YEAR => 0
            ]);

            $res->ip_violations_url = $violations_url->out(false);

            $violations_url->params(['period' => NED::PERIOD_1MONTH]);
            $res->ip_violations_last_30_url = $violations_url->out(false);
        }


        return $res;
    }

    /**
     * Get School OSSLT Scores Section Data
     *
     * @return object|null
     */
    protected function _get_osslt_report(){
        if (!NED::is_ghs_exists()) return null;

        $res = (object)[];
        $school_code = $this->_persistent->get('code');
        $students = $this->get_students();
        $activestudents = count($students);

        // OSSLT Scores for Current School Year.
        $res->osslturl = (new \moodle_url('/report/ghs/ghs_english_proficiency.php', [
            'schoolid' => $this->_schoolid,
            'ossltyear' => GHS::get_osslt_year(false),
        ]))->out(false);
        $res->title_help_obj = NED::get_help_icon('cr_osslt_help');

        $res->show_current_year = empty(NED::$C::get_config('hideossltdata'));
        if ($res->show_current_year){
            $wroteosslt = GHS::count_osslt($school_code, [NED::OSSLT_STATUS_PASS, NED::OSSLT_STATUS_FAIL]);
            $res->showpassfailosslt = $wroteosslt;
            $res->wroteosslticon = $this->get_icon($wroteosslt, static::ICON_HAPPY_SAD);
            $res->wroteosslt = $this->percentage_format($wroteosslt, $activestudents);

            $notwroteosslt = $activestudents - $wroteosslt;
            $res->notwroteosslticon = $this->get_icon($notwroteosslt);
            $res->notwroteosslt = $this->percentage_format($notwroteosslt, $activestudents);

            $passedosslt = GHS::count_osslt($school_code, NED::OSSLT_STATUS_PASS);
            $res->passedosslticon = $this->get_icon($passedosslt, static::ICON_HAPPY_SAD);
            $res->passedosslt = $this->percentage_format($passedosslt, $wroteosslt);

            $failedosslt = GHS::count_osslt($school_code, NED::OSSLT_STATUS_FAIL);
            $res->failedosslticon = $this->get_icon($failedosslt);
            $res->failedosslt = $this->percentage_format($failedosslt, $wroteosslt);

            $failedossltover75 = GHS::count_osslt_failed_over75($school_code);
            $res->failedossltover75icon = $this->get_icon($failedossltover75, static::ICON_WARN_FALSE);
            $res->failedossltover75 = $this->percentage_format($failedossltover75, $wroteosslt);
        }

        // OSSLT Scores for Previous School Year.
        $res->prevosslturl = (new \moodle_url('/report/ghs/ghs_english_proficiency.php', [
            'schoolid' => $this->_schoolid,
            'ossltyear' => GHS::get_osslt_year(true),
        ]))->out(false);

        $res->show_prev_year = true;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($res->show_prev_year){
            $prevwroteosslt = GHS::count_osslt($school_code, [NED::OSSLT_STATUS_PASS, NED::OSSLT_STATUS_FAIL], true);
            $res->showprevpassfailosslt = $prevwroteosslt;
            $res->prevwroteosslticon = $this->get_icon($prevwroteosslt, static::ICON_HAPPY_SAD);
            $res->prevwroteosslt = $prevwroteosslt;

            $prevpassedosslt = GHS::count_osslt($school_code, NED::OSSLT_STATUS_PASS, true);
            $res->prevpassedosslticon = $this->get_icon($prevpassedosslt, static::ICON_HAPPY_SAD);
            $res->prevpassedosslt = $this->percentage_format($prevpassedosslt, $prevwroteosslt);

            $prevfailedosslt = GHS::count_osslt($school_code, NED::OSSLT_STATUS_FAIL, true);
            $res->prevfailedosslticon = $this->get_icon($prevfailedosslt);
            $res->prevfailedosslt = $this->percentage_format($prevfailedosslt, $prevwroteosslt);

            $prevfailedossltover75 = GHS::count_osslt_failed_over75($school_code, true);
            $res->prevfailedossltover75icon = $this->get_icon($prevfailedossltover75, static::ICON_WARN_FALSE);
            $res->prevfailedossltover75 = $this->percentage_format($prevfailedossltover75, $prevwroteosslt);
        }

        return $res;
    }

    /**
     * Get logins section report
     *
     * @return object|null
     */
    protected function _get_logins_report(){
        [$startdate,] = $this->get_school_year();
        $school_year_days = (time() - $startdate)/DAYSECS;
        if ($school_year_days < 0) return null;

        $res = (object)[];
        $students = $this->get_students();
        $students_count = count($students);
        $students_ids = array_keys($students);
        $staffs = $this->get_staffs();
        $staffs_count = count($staffs);

        $res->usersurl = (new \moodle_url('/local/schoolmanager/view.php', [
            'schoolid' => $this->_schoolid,
            'view' => 'students',
        ]))->out(false);
        $res->title_help_obj = NED::get_help_icon('cr_logins_help');

        if ($students_count > 0){
            if ($school_year_days >= 3){
                $loggedusers3 = NED::count_logged_user($students_ids, 3);
            }
            if ($school_year_days >= 7){
                $loggedusers7 = NED::count_logged_user($students_ids, 7);
            }
            if ($school_year_days >= 8){
                $notloggedusers8 = NED::count_not_logged_user($students_ids, 8);
            }
        }
        if ($staffs_count){
            $notloggedstaff10 = NED::count_not_logged_user(array_keys($staffs), 10);
        }

        if (isset($loggedusers3)){
            $res->show_loggedusers3 = true;
            $res->loggedusers3icon = $this->get_icon($loggedusers3, static::ICON_HAPPY_SAD);
            $res->loggedusers3 = $this->percentage_format($loggedusers3, $students_count);
        }
        if (isset($loggedusers7)){
            $res->show_loggedusers7 = true;
            $res->loggedusers7icon = $this->get_icon($loggedusers7, static::ICON_HAPPY_SAD);
            $res->loggedusers7 = $this->percentage_format($loggedusers7, $students_count);
        }
        if (isset($notloggedusers8)){
            $res->notloggedusers8icon = $this->get_icon($notloggedusers8, static::ICON_SAD_FALSE);
            $res->notloggedusers8 = $this->percentage_format($notloggedusers8, $students_count);
            $res->show_notloggedusers8 = (bool)$res->notloggedusers8icon;
        }
        if (isset($notloggedstaff10)){
            $res->notloggedstaff10icon = $this->get_icon($notloggedstaff10, static::ICON_SAD_FALSE);
            $res->notloggedstaff10 = $this->percentage_format($notloggedstaff10, $staffs_count);
            $res->show_notloggedstaff10 = (bool)$res->notloggedstaff10icon;
        }

        return $res;
    }

    /**
     * Get DM section report
     *
     * @return object|null
     */
    protected function _get_dm_report(){
        if (!NED::is_tt_exists()) return null;

        $res = (object)[];
        [$startdate, $enddate] = $this->get_school_year();
        $school_code = $this->_persistent->get('code');

        $res->dmurl = (new \moodle_url('/report/ghs/ghs_group_enrollment.php', [
            'filterschool' => $this->_schoolid,
        ]))->out(false);
        $res->title_help_obj = NED::get_help_icon('cr_dm_help');

        [$res->dmcomplete, $res->dmincomplete, $res->dmclasses, $res->dmcompleteended, $res->dmincompleteended] =
            NED::count_dm_schedule($school_code, $startdate, $enddate);
        $res->dmcompleteicon = $this->get_icon($res->dmcomplete, static::ICON_HAPPY_SAD);
        $res->dmincompleteicon = $this->get_icon($res->dmincomplete);
        $res->dmcompleteendedicon = $this->get_icon($res->dmcompleteended, static::ICON_HAPPY_SAD);
        $res->dmincompleteendedicon = $this->get_icon($res->dmincompleteended);

        $res->classenddateextesion = NED::count_school_classes_enddate_extensions($school_code, $startdate, $enddate);
        $res->classenddateextesionicon = $this->get_icon($res->classenddateextesion);
        $res->classenddateextesion_last_30days =
            NED::count_school_classes_enddate_extensions($school_code, $startdate, $enddate, 30);
        $res->classenddateextesionicon_last_30days = $this->get_icon($res->classenddateextesion_last_30days);

        return $res;
    }

    /**
     * Get students for the current school
     * Returned records contain user.* fields, + schoolid, crewid, def_role
     *   - where def_role - string data from user profile (example: "Student"), don't mix up with role (and its id) table
     *
     * @return array|object[]
     */
    public function get_students(){
        if (!isset($this->_students)){
            $this->_students = [];
            if (!empty($this->_schoolid)){
                $this->_students = $this->_sm->get_school_students($this->_schoolid, true, $this->_sm::DEF_MEMBER_ROLE, false) ?: [];
            }
        }
        return $this->_students;
    }

    /**
     * Get students for the current school
     * Returned records contain user.* fields, + schoolid, crewid, def_role
     *   - where def_role - string data from user profile (example: "Student"), don't mix up with role (and its id) table
     *
     * @return array|object[]
     */
    public function get_staffs(){
        if (!isset($this->_staffs)){
            $this->_staffs = [];
            if (!empty($this->_schoolid)){
                $this->_staffs = $this->_sm->get_school_students($this->_schoolid, true, $this->_sm::STAFF_ROLES, false) ?: [];
            }
        }
        return $this->_staffs;
    }

    /**
     * Get data object for the main export method
     *
     * @return object
     */
    public function get_data(){
        return $this->_data ?: ((object)[]);
    }

    /**
     * Clean data to save some memory
     * It's not remove data, which get once only during constructor
     *
     * @return void
     */
    public function clean_memory(){
        unset($this->_students);
        unset($this->_staffs);
        unset($this->_data);
    }
}
