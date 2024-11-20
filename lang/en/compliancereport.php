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
 * @copyright  2024 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cr_aiv_help'] = "
    <div>This section tracks the number of Academic Conduct Violations (ACVs) over three specific time periods:</div> 
    <ul>
        <li>Since the beginning of the current school year</li>
        <li>During the previous 30-day period </li>
        <li>In the most recent 30 days</li>
    </ul>
    <p>
        The number of ACVs in the last 30 days is then compared to the previous 30 days to determine whether there is an improvement. 
        The goal is for the number of ACVs in the most recent 30 days to be lower than in the preceding 30-day period.
    </p>
    <div> 
        Promoting and upholding academic integrity is a cornerstone of the OSSD program, ensuring that students develop honesty, 
        accountability, and respect for intellectual property in their academic pursuits. 
        All teachers and administrators share the collective responsibility of instilling these values 
        and ensuring compliance with academic integrity standards.
    </div>
";

$string['cr_ngc_dm_help'] = "
    <div> 
        This section tracks the number of deadline extensions that were awarded by 
        Classroom Teachers over three specific time periods:
    </div> 
    <ul>
        <li>Since the beginning of the current school year</li>
        <li>During the previous 30-day period</li>
        <li>In the most recent 30 days</li>
    </ul>
    <div>
        The number of deadline extensions in the last 30 days is compared to the previous 30 days to gauge 
        whether there is a trend toward improvement. 
        As per Rosedale policy, deadline extensions should be used sparingly and accompanied by a good reason. 
        Deadline extensions should not be used simply to allow students to submit assignments within a short time frame. 
    </div>
";

$string['cr_tem_proctoring_help'] = "
    <div>This section provides the following metrics regarding Test Proctoring Reports and the Test-Writing sessions:</div>
    <ul>
        <li>Number or Proctoring reports have been submitted since the start of the school year</li>
        <li>Number of proctoring report that are currently missing</li>
        <li>Number of proctoring reports that are overdue by more than 10 days</li>
        <li>Number of tests that had 1 writing session 
            (this means that all student in the class wrote the test at the same time)</li>
        <li>Number of tests that had 2 or more writing sessions</li>
        <li>Number of tests that had more than 2 writing sessions</li>
    </ul>
    <p>
        It is very important to submit Proctoring Reports in a timely manner. 
        It is also important to keep writing sessions to a minimum. 
        Ideally, all tests and exams should only have one writing session.  
    </p>
    <h6> What is a Writing Session? </h6>
    <p>
        A writing session refers to an instance where a group of students (e.g., a class) gathers to complete a proctored activity, 
        such as a test, exam, or similar assessment. 
        Ideally, <span class='color-red'>all students in a class should complete the proctored activity at the same time</span>. 
        If some students begin the activity at different times or on different days, 
        these will be considered separate writing sessions.  
    </p>
    <div>Key Points: </div>
    <ul>
        <li>Writing sessions are defined by the start time of each student.</li>
        <li>Students who begin the activity within the same hour (60 minutes) are grouped into the same writing session.</li>
        <li>Separate writing sessions require separate Proctor Reports.</li>
    </ul>
";

$string['cr_osslt_help'] = "
    <div>
        This section provides the following metrics regarding the Ontario Secondary School Literacy Test (OSSLT) performance scores:  
    </div>
    <ul>
        <li> Number of students who wrote the OSSLT  in this school year </li>
        <li> Number of students who did not write the OSSLT in this school year </li>
        <li> If data is available for previous school years, it will be shown here.</li>
        <li> If data is available, this section will count the number of students that failed the OSSLT 
            but have ENG3U or ENG4U grade over 75%. </li>
    </ul>
    <p>
        The OSSLT assesses whether students have achieved the minimum literacy standards expected across all subjects 
        up to the Grade 9 level. All students are required to meet the secondary school literacy graduation requirement in order 
        to earn an Ontario Secondary School Diploma (OSSD). 
    </p>
    <div>
        If you have students who failed the OSSLT but have high grades in grade 11 or grade English (ENG3U or ENG4U), 
        it may suggest potential issues with academic integrity. 
        It is the responsibility of local school staff to investigate any such discrepancies.  
    </div>
";

$string['cr_dm_help'] = "
    <div>This section provides the following metrics regarding the status of the Deadline Manager at the course level:</div>
    <ul>
        <li>Number of active classes that have complete and incomplete Deadline Manager schedules</li>
        <li>Number of finished classes that had complete and incomplete Deadline Manager schedules</li>
        <li>Number class end-date extensions</li>
        <li>Number of class end-date extensions in the last 30 days</li>
    </ul>
    <p>
        The Deadline Manager is used to set due dates for all summative activities. 
        Once your Deadline Manager is complete, your students will be able to see upcoming due dates in 
        the \"Important Dates\" block as well as the \"Student Progress\" page. 
    </p>
    <div>
        Please review your upcoming due dates regularly to avoid abrupt changes. 
        Remember that Online Teachers also rely on your Deadline Manager to plan their grading time. 
        Out of courtesy to them, try not to change due dates that are set to expire within a week. 
    </div>
";

$string['cr_logins_help'] = "
    <div>This section provides the following metrics regarding the logins for students and staff in your school:</div>
    <ul>
        <li>Number of students that have logged in within last 3 days and 7 days.</li>
        <li>Number of staff that have not logged in for more than 10 days. </li>
    </ul>
";
