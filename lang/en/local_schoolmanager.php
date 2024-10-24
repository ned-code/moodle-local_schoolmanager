<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Rosedale School Manager';

// Capabilities
$string['schoolmanager:viewownschooldashboard'] = 'View own school manager';
$string['schoolmanager:viewallschooldashboards'] = 'View all school manager';
$string['schoolmanager:manage_schools'] = 'Manage schools';
$string['schoolmanager:manage_schools_extra'] = 'Manage extra school fields';
$string['schoolmanager:delete_schools'] = 'Delete schools';
$string['schoolmanager:manage_crews'] = 'Manage school cohorts';
$string['schoolmanager:manage_members'] = 'Manage cohort members';
$string['schoolmanager:view_ps_compliance_report'] = 'Can view Partner School Compliance report';
$string['schoolmanager:manage_extension_limit'] = 'Can view and manage number of allowed extensions in School Manager';
$string['schoolmanager:manage_extension_limit_override'] = 'Can ignore extension limit restriction set in School Manager';

// Config
$string['general'] = 'General';
$string['disabled'] = 'Disabled';
$string['view_schoolmanager'] = 'View School Manager';
$string['academic_program'] = 'Academic Program options';
$string['academic_program_desc'] = 'Values for "Academic Program" selector (one per line)';
$string['school_field_to_sync'] = 'Field to sync by school';
$string['schools_field_to_sync'] = 'Field to sync by schools';

// Tasks
$string['sync_school_admins'] = 'Sync School and District Admins to school cohorts';
$string['sync_course_admins'] = 'Auto enroll School and District Admins to courses';

// Other
$string['academicintegrity'] = 'Academic Violations';
$string['academicprogram'] = 'Academic Program';
$string['activestudents'] = 'Active Students';
$string['admissiondate'] = 'Admission Date';
$string['advanced_cert_badge'] = 'CT Advanced Qualification Badge';
$string['advanced_cert_course'] = 'CT Advanced Qualification Course';
$string['advancedcert'] = 'CT Advanced Qualification';
$string['aiv'] = 'AIV';
$string['aiv30'] = 'AIV-30';
$string['aiv30schoolyear'] = 'AV - Last 30 Days';
$string['aiv30tooltip'] = 'Academic Violations in last 30 days';
$string['aiva'] = 'AIV-A';
$string['aivatooltip'] = 'Academic Violation average (lower is better)';
$string['aivaverage'] = 'AV Average';
$string['aivreports'] = 'AV Reports';
$string['aivreports30'] = 'AV-30 Days';
$string['aivschoolyear'] = 'AV - School Year';
$string['aivtooltip'] = 'Academic Violations';
$string['averagegrade'] = 'Average grade';
$string['averagepp'] = 'Average PP';
$string['city'] = 'City';
$string['classdateextensions'] = 'Class Date Extensions';
$string['classes'] = 'Classes';
$string['classroomteachers'] = 'Classroom Teachers';
$string['country'] = 'Country';
$string['coursesperyear'] = 'Courses per year';
$string['crewcode'] = 'Cohort ID';
$string['crewdeletedsuccessfully'] = 'Cohort deleted successfully';
$string['crewname'] = 'Cohort Name';
$string['crews'] = 'Cohorts';
$string['crewsavedsuccessfully'] = 'Cohort saved successfully';
$string['ctacrate'] = 'CTs with AQ';
$string['ctaq'] = 'CT-AQ';
$string['ctaqtooltip'] = 'Classroom Teachers with Advanced Qualifications';
$string['ctgcrate'] = 'CTs with GQ';
$string['ctgq'] = 'CT-GQ';
$string['ctgqtooltip'] = 'Classroom Teachers with General Qualifications';
$string['cts'] = 'CTs';
$string['ctstooltip'] = 'Classroom Teachers';
$string['deadlineextensions'] = 'Deadline Extensions';
$string['deadlineextentions'] = 'Deadline Extensions';
$string['deadlinenotifications'] = 'Deadline Notifications';
$string['editschool'] = 'Edit school';
$string['expectedgraduation'] = 'Expected Graduation';
$string['extentions'] = 'Extentions';
$string['general_cert_badge'] = 'CT General Certification Badge';
$string['general_cert_course'] = 'CT General Certification Course';
$string['generalcert'] = 'CT General Certification';
$string['gpa'] = 'GPA';
$string['lastaccess'] = 'Last Access';
$string['location'] = 'Location';
$string['logo'] = 'Logo';
$string['misseddeadlines'] = 'Missed Deadlines';
$string['wrongsubmissions'] = 'Wrong submissions';
$string['name'] = 'Name';
$string['newcrewforusers'] = 'Select cohort for chosen users';
$string['nocrew'] = 'There are no crews.';
$string['nomyschools'] = 'There are no schools to display.';
$string['noschools'] = 'There are no schools to display.';
$string['note'] = 'Note';
$string['aboutschool'] = 'About this School';
$string['nousersatschool'] = 'There are no users in this school.';
$string['ppa'] = 'PPA';
$string['resettimezoneforall'] = 'Click here to reset timezone for all school members now';
$string['role'] = 'Role';
$string['rosedalecode'] = 'School Code';
$string['school'] = 'School';
$string['schooldeletedsuccessfully'] = 'School deleted successfully';
$string['schoolid'] = 'School ID';
$string['schoolinfo'] = 'School Info';
$string['schoolname'] = 'School Name';
$string['schoolcohortname'] = 'School cohort name';
$string['schools'] = 'Schools';
$string['schoolsavedsuccessfully'] = 'School saved successfully';
$string['schoolwebsite'] = 'School Website';
$string['schoolyear'] = 'School Year';
$string['schoolyearenddate'] = 'School year end date';
$string['schoolyearstartdate'] = 'School year start date';
$string['selectcohort'] = 'Select cohort which will become school';
$string['staff'] = 'Staff';
$string['staffaivreports30tooltip'] = 'Academic Violations by students in the last 30 days';
$string['staffaivreportstooltip'] = 'Total Academic Violations for students';
$string['staffextentionstooltip'] = 'Deadline Extensions';
$string['studentaivreports30tooltip'] = 'Academic Violations in the last 30 days';
$string['studentaivreportstooltip'] = 'Total Academic Violations';
$string['studentextentionstooltip'] = 'Deadline Extensions';
$string['studentname'] = 'Student Name';
$string['students'] = 'Students';
$string['summary'] = 'Summary';
$string['synctimezone'] = 'Sync timezones for all members';
$string['synctimezonewarning'] = 'The timezone for all users in the group will be automatically synchronized within 24 hours.';
$string['timezone'] = 'Time Zone';
$string['totalcts'] = 'Total CTs';
$string['totalschools'] = 'Total Schools';
$string['totalstudents'] = 'Total Students';
$string['users'] = 'Users';
$string['totalaiv'] = 'Total AIV';
$string['totalaiv30'] = 'Total AIV-30';
$string['showschoolswithnostudents'] = 'Show schools with no students';
$string['schoolmanager_tasks'] = 'School Manager tasks';
$string['notasks'] = 'There are no School Manager tasks';
$string['ct'] = 'CT';
$string['sa'] = 'SA';
$string['extmanager'] = 'Extension manager';
$string['extmanager_help'] = 'When set to SA, only users with SA extension capability can create extensions in any course that belongs to their schools';
$string['iptype'] = 'IP type in your school';
$string['iptype_help'] = 'An IP address is a unique set of numbers that identifies your local network device. There are two types of IP addresses: dynamic and static. “Dynamic” means that the address changes from time to time. “Static” means that the address doesn’t change unless you change it yourself. You may need to contact your network administrator to determine your local IP type.';
$string['static'] = 'Static';
$string['dynamic'] = 'Dynamic';
$string['schooladministrator'] = 'School Administrator';
$string['proctormanager'] = 'Proctor Manager for Tests/Exams';
$string['academicintegritymanager'] = 'Academic Violations Manager';
$string['enabletem'] = 'Enable TEM';
$string['englishproficiency'] = 'English Proficiency';
$string['forceproxysubmissionwindow'] = 'Force proxy submission window';
$string['fivehours'] = '5 Hours';
$string['twelvehours'] = '12 Hours';
$string['twentyfourhours'] = '24 Hours';
$string['badges'] = 'Badges';
$string['activitysetting'] = 'Activity Setting';
$string['classdeadlines'] = 'Class Deadlines';
$string['syncgroups'] = 'Sync groups';
$string['esl'] = 'ESL';
$string['studentsummary'] = 'Student Summary';
$string['staffsummary'] = 'Staff Summary';
$string['classroomassistants'] = 'Classroom Assistants';
$string['guidancecounsellors'] = 'Guidance Counsellors';
$string['certifiedproctors'] = 'Certified Proctors';
$string['proctor_cert_badge'] = 'Proctor Badge';
$string['defaultschoolyearstart'] = 'Default school year start';
$string['defaultschoolyearend'] = 'Default school year end';
$string['schoolyear'] = 'School Year';
$string['rosedaledefault'] = 'Rosedale Default: {$a}';
$string['custom'] = 'Custom';
$string['schoolprofile'] = 'School Profile';
$string['compliancereport'] = 'Compliance Report';
$string['extensionsallowed'] = 'Extensions allowed per student per activity';
$string['downloadallgrades'] = 'Download all grades';
