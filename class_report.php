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
 * Main plugin page
 *
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $OUTPUT, $USER, $SITE, $CFG, $DB, $PAGE;

$html = '';

require_once("$CFG->libdir/phpspreadsheet/vendor/autoload.php");
require_once($CFG->libdir.'/adminlib.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use local_schoolmanager\shared_lib as NED;
use local_kica as kica;
use local_kica\output\menu_bar as KMB;

// Paging options.
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = optional_param('perpage', 250, PARAM_INT);
$sort      = optional_param('sort', '', PARAM_ALPHANUM);
$dir       = optional_param('dir', 'ASC', PARAM_ALPHA);
$export    = optional_param('export', 'full', PARAM_ALPHA);
// Action.
$action    = optional_param('action', false, PARAM_ALPHA);
$search    = optional_param('search', '', PARAM_TEXT);

$download      = optional_param('download', 0, PARAM_INT);

// Filters.
$view = optional_param('view', '', PARAM_TEXT);
$schoolid = optional_param('schoolid', 0, PARAM_INT);
$classid = optional_param('classid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$filterdepartment = optional_param('filterdepartment', '', PARAM_TEXT);
$filteractivestudents = optional_param('filteractivestudents', '', PARAM_TEXT);
$filterstartdate = optional_param('filterstartdate', 0, PARAM_INT);
$filterenddate = optional_param('filterenddate', 0, PARAM_INT);
$filterincludeclassesnotactive = optional_param('includeclassesnotactive', false, PARAM_BOOL);
$filterincludenoncreditcourses = optional_param('includenoncreditcourses', false, PARAM_BOOL);

$cfg = get_config('report_ghs');

//require_login(null, false);
$contextsystem = context_system::instance();

// Role
$isot = report_ghs\helper::is_ot($USER->id);

// Permission.
if (!NED::can_view_class_enrollment_report()) {
    throw new required_capability_exception($contextsystem, 'report/ghs:viewgroupenrollment', 'nopermissions', '');
}

$thispageurl = new moodle_url('/local/schoolmanager/view.php');
$fullpageurl = new moodle_url('/local/schoolmanager/view.php', [
    'schoolid' => $schoolid,
    'view' => $view,
    'classid' => $classid,
    'page' => $page,
    'perpage' => $perpage,
    'sort' => $sort,
    'dir' => $dir,
    'action' => $action,
    'courseid' => $courseid,
]);
$report = basename($thispageurl->out(), '.php');


$name = get_string('ghsgroupenrollment', 'report_ghs');
$title = get_string('ghsgroupenrollment', 'report_ghs');
$heading = $SITE->fullname;

$datacolumns = array(
    'activestudents' => 'r.activestudents',
    'cohortid' => 'r.cohortid',
    'course' => 'c.fullname',
    'path' => 'cc.path',
    'coursecode' => 'c.shortname',
    'ccategory' => 'c.category',
    'courseid' => 'r.courseid',
    'ctname' => "if(r.ctid = -1, '*multiple', (SELECT u3.firstname FROM {user} u3 WHERE u3.id = r.ctid))",
    'department' => 'r.department',
    'dmdateconflicts' => 'r.dmdateconflicts',
    'classid' => 'g.id',
    'dmrequired' => 'g.schedule',
    'dmstatus' => 'r.dmstatus',
    'enddate' => 'g.enddate',
    'enrolldateconflicts' => 'r.enrolldateconflicts',
    'gmessaging' => '(SELECT COUNT(1) FROM {message_conversations} conv WHERE conv.type = \'2\' AND conv.itemid = g.id AND conv.enabled = \'1\')',
    'grade' => 'r.grade',
    'groupid' => 'r.groupid',
    'groupidnumber' => 'g.idnumber',
    'groupname' => 'g.name',
    'id' => 'r.id',
    'moe_code' => 'r.moe_code',
    'moe_name' => 'r.moe_name',
    'otid' => 'r.otid',
    'otname' => "if(r.otid = -1, '*multiple', (SELECT u3.firstname FROM {user} u3 WHERE u3.id = r.otid))",
    'school' => ' coh.name',
    'startdate' => 'g.startdate',
    'subject' => 'r.subject',
    'suspendedstudents' => 'r.suspendedstudents',
    'totaldays' => '(CEIL((g.enddate - g.startdate) / 86400))',
);

$params = [];

// Filter.
$where = '';
if ($isot) {
    $where .= " AND ".$datacolumns['otid']." = :otid";
    $params['otid'] = $USER->id;
}

if ($schoolid) {
    if ($schoolid > 0) {
        $where .= " AND " . $datacolumns['cohortid'] . " = :school";
        $params['school'] = $schoolid;
    } else {
        $where .= " AND " . $datacolumns['cohortid'] . " != :school";
        $params['school'] = -$schoolid;
    }
}
if ($classid) {
    $where .= " AND " . $datacolumns['classid'] . " = :classid";
    $params['classid'] = $classid;
}
if ($courseid) {
    $where .= " AND " . $datacolumns['courseid'] . " = :courseid";
    $params['courseid'] = $courseid;
}


if ($filterdepartment) {
    $where .= " AND ".$datacolumns['department']." = :department";
    $params['department'] = $filterdepartment;
}
if (isset($filteractivestudents) && $filteractivestudents != '') {
    $where .= " AND ".$datacolumns['activestudents']." != 0";
    switch ($filteractivestudents) {
        case '0':
            $where .= " AND {$datacolumns['activestudents']} = 0";
            break;
        case '1':
            $where .= " AND {$datacolumns['activestudents']} = 1";
            break;
        case '1+':
            $where .= " AND {$datacolumns['activestudents']} > 1";
            break;
        case '10+':
            $where .= " AND {$datacolumns['activestudents']} > 10";
            break;
    }
}
if ($filterstartdate) {
    switch ($filterstartdate) {
        case 1: // None.
            $where .= " AND ({$datacolumns['startdate']} = 0 OR {$datacolumns['startdate']} IS NULL)";
            break;
        case 2: // Past.
            $where .= " AND ".$datacolumns['startdate']." < " .time() . " AND ({$datacolumns['startdate']} != 0 AND {$datacolumns['startdate']} IS NOT NULL)";
            break;
        case 3: // Future
            $where .= " AND ".$datacolumns['startdate']." > " .time() . " AND ({$datacolumns['startdate']} != 0 AND {$datacolumns['startdate']} IS NOT NULL)";;
            break;
    }
}
if ($filterenddate) {
    switch ($filterenddate) {
        case 1: // None.
            $where .= " AND ({$datacolumns['enddate']} = 0 OR {$datacolumns['enddate']} IS NULL)";
            break;
        case 2: // Past.
            $where .= " AND ".$datacolumns['enddate']." < " .time() . " AND ({$datacolumns['enddate']} != 0 AND {$datacolumns['enddate']} IS NOT NULL)";;
            break;
        case 3: // Future
            $where .= " AND ".$datacolumns['enddate']." > " .time() . " AND ({$datacolumns['enddate']} != 0 AND {$datacolumns['enddate']} IS NOT NULL)";;
            break;
    }
}
if (!$filterincludeclassesnotactive) {
    $time = time();
    $where .= " AND ({$datacolumns['startdate']} < :time1 AND :time2 < {$datacolumns['enddate']})";
    $params['time1'] = $time;
    $params['time2'] = $time;
}
if (!$filterincludenoncreditcourses) {
    $where .= " AND {$datacolumns['path']} NOT LIKE '/110/%'";
}

// Sort.
$order = '';
if ($sort) {
    $order = " ORDER BY $datacolumns[$sort] $dir";
} else {
    $order = " ORDER BY timecheck DESC, {$datacolumns['enddate']} ASC";
}

$pageparams = array();

// Filter by capabilies.
\report_ghs\helper::report_filter($where, $params, $report, 'report/ghs:viewgroupenrollment');

// Count records for paging.
$countsql = "SELECT COUNT(1)
            FROM {report_ghs_group_enrollment} r
          JOIN {course} c
            ON r.courseid = c.id
          JOIN {course_categories} cc 
            ON c.category = cc.id                
          JOIN {groups} g
            ON r.groupid = g.id
LEFT OUTER JOIN {cohort} coh ON r.cohortid = coh.id
         WHERE 0 = 0 
               $where";
$totalcount = $DB->count_records_sql($countsql, $params);

// Table columns.
$columns = array(
    'course',
    'coursecode',
    //'subject',
    //'grade',
    //'department',
    //'moe_code',
    'school',
    'groupname',
    //'gmessaging',
    'activestudents',
    //'suspendedstudents',
    'ctname',
    'otname',
    'startdate',
    'enddate',
    'totaldays',
    //'dmrequired',
    'dmstatus'
);
$columnsexport = array(
    'rowcount',
    'course',
    'coursecode',
    'subject',
    'grade',
    'department',
    'moe_code',
    'category',
    'basecategory',
    'school',
    'groupname',
    'groupid',
    'groupidnumber',
    'gmessaging',
    'activestudents',
    'suspendedstudents',
    'ctname',
    'otname',
    'startdate',
    'enddate',
    'totaldays',
    'dmrequired',
    'dmstatus'
);

$sql = "SELECT r.id,
               c.fullname course,
               c.shortname coursecode,
               coh.name school,
               g.name groupname,
               g.idnumber groupidnumber,
               r.activestudents,
               r.suspendedstudents,
               if(r.ctid = -1, '*multiple', (SELECT u3.firstname FROM {user} u3 WHERE u3.id = r.ctid)) ctname,
               if(r.otid = -1, '*multiple', (SELECT u3.firstname FROM {user} u3 WHERE u3.id = r.otid)) otname,
               g.startdate,
               g.enddate,
               (CEIL((g.enddate - g.startdate) / 86400)) totaldays,
               g.schedule dmrequired,
               r.dmdateconflicts,
               r.dmstatus,
               r.enrolldateconflicts,
               r.courseid,
               r.groupid,
               cc.name category,
               cc.path path,
               cc.parent basecategory,
               (SELECT COUNT(1) FROM {message_conversations} conv WHERE conv.type = '2' AND conv.itemid = g.id AND conv.enabled = '1') gmessaging,
               r.subject,
               r.grade,
               r.department,
               r.moe_code,
               r.moe_name,
               (SELECT e.id FROM {enrol} e WHERE e.courseid = r.courseid AND e.enrol = 'manual' AND e.status = '0') enrolid,
               (CASE WHEN g.enddate > ".time()." THEN \"1\" ELSE \"0\" END) timecheck
          FROM {report_ghs_group_enrollment} r
          JOIN {course} c
            ON r.courseid = c.id
          JOIN {course_categories} cc 
            ON c.category = cc.id                
          JOIN {groups} g
            ON r.groupid = g.id
LEFT OUTER JOIN {cohort} coh ON r.cohortid = coh.id
         WHERE 0 = 0
               $where
               $order";

if ($download && has_capability('report/ghs:downloadgradesbulk', $contextsystem)) {
    ob_start();
    set_time_limit(300);

    raise_memory_limit(MEMORY_EXTRA);

    if (ob_get_length()) {
        ob_end_clean();
    }

    /** @var \local_kica\output\renderer $renderer */
    $renderer = $PAGE->get_renderer('local_kica');

    $school = $DB->get_record('local_schoolmanager_school', ['id' => $schoolid]);

    $exportdata = [];

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $tablerow) {
        $group = $DB->get_record("groups", array('id' => $tablerow->groupid));

        $coursecontext = \context_course::instance($group->courseid);
        $activestudents = get_enrolled_users($coursecontext, 'mod/assign:submit', $group->id, 'u.*', 'u.id', 0, 0, true);

        foreach ($activestudents as $index => $activestudent) {
            if ($activestudent->suspended == 1) {
                unset($activestudents[$index]);
            } else {
                $activestudent->group = $group;
            }
        }

        list($head, $data) = $renderer->export_users_grades($group->courseid, $activestudents, $group, true);

        foreach ($data as $datum) {
            array_push($exportdata, $datum);
        }
    }

    $rs->close();
    $filename = $school->code . '_grades_' . date('jMY');
    NED::export_to_xlsx($head, $exportdata, $filename);
    exit;
} else if ($action == 'excel') {
    ob_start();
    set_time_limit(300);
    raise_memory_limit(MEMORY_EXTRA);

    $table = new stdClass();
    $table->head = ($export == 'current') ? $columns : $columnsexport;

    // Delete first rowcount column.
    $itemid = array_shift($table->head);
    // Delete last action column.
    //array_pop($table->head);

    $counter = 0;
    $filename = $report.'_'.(date('Y-m-d'));
    $downloadfilename = clean_filename($filename);

    $workbook = new Spreadsheet();
    $myxls = $workbook->setActiveSheetIndex(0);

    $numberofcolumns = count($table->head);

    $gradecolumns = array('coursegrade', 'kicaavg', 'kica70', 'kica30');

    // Header row.
    foreach ($table->head as $key => $heading) {
        $cell = Coordinate::stringFromColumnIndex($key + 1) . '1'; // A1 cell address.
        $myxls->setCellValue($cell, str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br(get_string($heading, 'report_ghs'))))));
    }

    // Data rows.
    $rs = $DB->get_recordset_sql($sql, $params);
    $rownum = 2;
    foreach ($rs as $tablerow) {
        $row = array();
        $columnum = 1;
        $col = [];
        foreach ($table->head as $column) {
            $_data = \report_ghs\helper::group_enrollment_data($tablerow, $column, $counter, $pageparams, true);
            $col[$column] = ['index' => $columnum, 'value' => $_data];

            $lowgrade  = false;
            if (in_array($column, $gradecolumns) && is_numeric($_data) && $_data < 50)  {
                $lowgrade  = true;
            }

            $cell = Coordinate::stringFromColumnIndex($columnum) . $rownum; // A2 cell address.
            if (preg_match("/^[fh]tt?ps?:\/\//", $_data)) {
                $linktext = \report_ghs\helper::group_enrollment_data($tablerow, $column.'_txt', $counter, $pageparams, true);
                $myxls->setCellValue($cell, $linktext);
                $myxls->getCell($cell)->getHyperlink()->setUrl($_data);
            } else {
                $myxls->setCellValue($cell, $_data);
            }

            if ($lowgrade) {
                $myxls->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID);
                $myxls->getStyle($cell)->getFill()->getStartColor()->setARGB('FFFCC7CE');
                $myxls->getStyle($cell)->getFont()->getColor()->setARGB('FF9C0006');
            }

            $columnum++;
        }

        if (is_numeric($col['coursegrade']['value']) && is_numeric($col['kica70']['value'])) {
            if ($col['coursegrade']['value'] > 50 && $col['kica70']['value'] < 50) {
                $cell = Coordinate::stringFromColumnIndex($col['coursegrade']['index']) . $rownum;
                $myxls->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID);
                $myxls->getStyle($cell)->getFill()->getStartColor()->setARGB('FFC0514D');
                $myxls->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');

                $cell = Coordinate::stringFromColumnIndex($col['kica70']['index']) . $rownum;
                $myxls->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID);
                $myxls->getStyle($cell)->getFill()->getStartColor()->setARGB('FFC0514D');
                $myxls->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
            }
        }

        $rownum++;
    }
    $rs->close();

    // Freeze header.
    $myxls->freezePane('A2');

    // Filter.
    $myxls->setAutoFilter(
        $myxls->calculateWorksheetDimension()
    );

    // Auto column width calculation.
    foreach (range('A', $myxls->getHighestDataColumn()) as $col) {
        $myxls->getColumnDimension($col)->setAutoSize(true);
    }

    // Header format.
    $styleArray = array(
        'font' => array(
            'bold' => true,
        ),
        'fill' => array(
            'fillType' => Fill::FILL_SOLID,
            'color' => array(
                'argb' => 'FFFFF000',
            ),
        ),
    );
    $myxls->getStyle('A1:'.Coordinate::stringFromColumnIndex($numberofcolumns).'1')->applyFromArray($styleArray);

    // Rename worksheet
    $myxls->setTitle('export');

    // Set active sheet index to the first sheet, so Excel opens this as the first sheet
    $workbook->setActiveSheetIndex(0);

    $objWriter = new Xlsx($workbook);

    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$downloadfilename.'.xlsx"');
    header('Cache-Control: max-age=0');

    ob_end_clean();
    $objWriter->save('php://output');
    exit;
} else if ($action == 'csv') {
    ob_start();
    set_time_limit(300);
    raise_memory_limit(MEMORY_EXTRA);
    $table = new stdClass();

    // Delete firs rowcount column.
    array_shift($columnsexport);
    // Delete last action column.
    //array_pop($columnsexport);

    $headers = $columnsexport;

    foreach ($headers as $ckey => $column) {
        $headers[$ckey] = get_string($column, 'report_ghs');
    }

    if (ob_get_length()) {
        ob_end_clean();
    }
    // Output headers so that the file is downloaded rather than displayed.
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$report.'_'.(date('Y-m-d')).'.csv');

    // Create a file pointer connected to the output stream.
    $outputcsv = fopen('php://output', 'w');

    // Output the column headings.
    fputcsv($outputcsv, $headers);

    $counter = 0;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $tablerow) {
        $row = array();
        foreach ($columnsexport as $column) {
            $row[] = \report_ghs\helper::group_enrollment_data($tablerow, $column, $counter, $pageparams, true);
        }
        fputcsv($outputcsv, $row);
    }
    $rs->close();
    exit;
} else {

    foreach ($columns as $column) {
        $string[$column] = get_string($column, 'report_ghs');
        if ($sort != $column) {
            $columnicon = "";
            $columndir = "ASC";
        } else {
            $columndir = $dir == "ASC" ? "DESC" : "ASC";
            $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
            $columnicon = $OUTPUT->pix_icon('t/'.$columnicon, '', 'moodle', array('class' => 'iconsort'));
        }
        if (($column == 'rowcount') || ($column == 'action') || ($column == 'kicadiff') || ($column == 'kicalink')) {
            $$column = $string[$column];
        } else {
            $sorturl = clone $fullpageurl;
            $sorturl->param('sort', $column);
            $sorturl->param('dir', $columndir);

            $$column = html_writer::link($sorturl->out(false), $string[$column]).' '.$columnicon;
        }
    }

    $table = new html_table();

    $table->head = array();
    $table->wrap = array();
    $table->attributes = ['class' => 'nedtable fullwidth'];

    foreach ($columns as $column) {
        $table->head[$column] = $$column;
        $table->wrap[$column] = '';
    }

    // Override cell wrap.
    $table->wrap['action'] = 'nowrap';
    $table->wrap['dmstatus'] = 'nowrap';
    $table->wrap['startdate'] = 'nowrap';
    $table->wrap['enddate'] = 'nowrap';
    $table->wrap['coursecode'] = 'nowrap';
    $table->wrap['groupname'] = 'nowrap';
    $table->align['activestudents'] = 'center';
    $table->align['totaldays'] = 'center';

    $tablerows = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    $counter = ($page * $perpage);

    foreach ($tablerows as $tablerow) {
        $row = new html_table_row();

        foreach ($columns as $column) {
            $varname = 'cell'.$column;
            $$varname = new html_table_cell(\report_ghs\helper::group_enrollment_data($tablerow, $column, $counter, $pageparams));

            if (isset($tablerow->{$column.'cls'})) {
                $$varname->attributes['class'] = 'bg-alert';
            }
        }

        $row->cells = array();
        foreach ($columns as $column) {
            $varname = 'cell' . $column;
            $row->cells[$column] = $$varname;
        }
        $table->data[] = $row;

    }

    $pagingurl = clone $fullpageurl;
    $pagingurl->remove_params('page');
    $pagingurl->remove_params('action');

    $pagingbar = new paging_bar($totalcount, $page, $perpage, $pagingurl, 'page');

    if ($outputpagingbar = $OUTPUT->render($pagingbar)) {
        $html .=  $outputpagingbar;
    } else {
        $html .=  html_writer::tag('div', '', ['class' => 'dummy-pagination']);
    }

    $html .=  html_writer::start_div('table-responsive');
    $html .=  html_writer::table($table);
    $html .=  html_writer::end_div();


    $html .=  $outputpagingbar ?? '';
    $html .=  $exportbuttons ?? '';
}