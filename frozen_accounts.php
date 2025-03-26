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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');

use local_schoolmanager\school_handler as SH;
use report_ghs\helper;
use report_ghs\shared_lib as NED;

// Paging options.
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = optional_param('perpage', 20, PARAM_INT);
$sort      = optional_param('sort', 'id', PARAM_ALPHANUM);
$dir       = optional_param('dir', 'ASC', PARAM_ALPHA);

// Filters.
$schoolid  = optional_param('schoolid', 0, PARAM_INT);
$filterstatus  = optional_param('filterstatus', 0, PARAM_INT);
$filterstudentid  = optional_param('filterstudentid', 0, PARAM_INT);
$filterreason  = optional_param('filterreason', 0, PARAM_INT);

$cfg = get_config('report_ghs');

require_login(null, false);
$contextsystem = context_system::instance();

// Permission.
if (!has_any_capability(['report/ghs:viewfrozenaccountsallschools', 'report/ghs:viewfrozenaccountsownschool'], $contextsystem)) {
    throw new required_capability_exception($contextsystem, 'report/ghs:viewfrozenaccountsownschool', 'nopermissions', '');
}

$thispageurl = new moodle_url('/local/schoolmanager/view.php');
$fullpageurl = new moodle_url('/local/schoolmanager/view.php', [
    'page' => $page,
    'perpage' => $perpage,
    'sort' => $sort,
    'dir' => $dir,
    'schoolid' => $schoolid,
    'view' => SH::VIEW_FROZENACCOUNTS,
    'filterstatus' => $filterstatus,
    'filterstudentid' => $filterstudentid,
    'filterreason' => $filterreason
]);

$title = get_string('ghsfrozenaccounts', 'report_ghs');

$datacolumns = [
    'dateunfrozen' => 'r.dateunfrozen',
    'frozenby' => 'r.frozenby',
    'frozendate' => 'r.frozendate',
    'id' => 'r.id',
    'note' => 'r.note',
    'reason' => 'r.reason',
    'resolution' => 'r.resolution',
    'school' => 'coh.name',
    'schoolid' => 'r.schoolid',
    'status' => 'r.status',
    'student' => "CONCAT(u.firstname, ' ', u.lastname)",
    'studentid' => 'r.userid',
];

$params = [];
$schoolparams = [];

// Filter.
$where = '';
$schoolinsql = '';

$multipleschools = true;
if (has_capability('report/ghs:viewfrozenaccountsallschools', $contextsystem)) {
    $sh = new SH();
    if (!$schools = $sh->get_schools()) {
        $where .= " AND 0=1";
    } else {
        if (count($schools) == 1) {
            $multipleschools = false;
        }
        list($schoolinsql, $schoolparams) = $DB->get_in_or_equal(array_keys($schools), SQL_PARAMS_NAMED);
        $where .= " AND " . $datacolumns['schoolid'] . " {$schoolinsql}";
        $params = array_merge($params, $schoolparams);
    }
}

// Schools.
$sql = "SELECT DISTINCT r.schoolid, coh.name  school
          FROM {report_ghs_frozen_accounts} r
          JOIN {cohort} coh ON r.schoolid = coh.id
         WHERE 0 = 0 
               $where
      ORDER BY coh.name ASC";

if ($schoolid) {
    $where .= " AND ".$datacolumns['schoolid']." = :schoolid";
    $params['schoolid'] = $schoolid ?? 0;
}
if ($filterstatus) {
    $where .= " AND ".$datacolumns['status']." = :status";
    $params['status'] = $filterstatus;
}
if ($filterreason) {
    $where .= " AND ".$datacolumns['reason']." = :reason";
    $params['reason'] = $filterreason;
}
if ($filterstudentid) {
    $where .= " AND ".$datacolumns['studentid']." = :studentid";
    $params['studentid'] = $filterstudentid;
}

// Sort.
$order = '';
if ($sort) {
    $order = " ORDER BY $datacolumns[$sort] $dir";
}

$pageparams = ['returnurl' => $fullpageurl->out_as_local_url()];

// Count records for paging.
$countsql = "SELECT COUNT(1)
               FROM {report_ghs_frozen_accounts} r
               JOIN {cohort} coh ON r.schoolid = coh.id
               JOIN {user} u ON r.userid = u.id
              WHERE 0 = 0 $where";
$totalcount = $DB->count_records_sql($countsql, $params);

// Table columns.
$columns = [
    'school',
    'student',
    'frozendate',
    'frozenby',
    'reason',
    'dateunfrozen',
    'resolution',
    'note',
    'action',
];

if (!$multipleschools) {
    array_shift($columns);
}

$sql = "SELECT  r.*,
                coh.name  school,
                CONCAT(u.firstname, ' ', u.lastname) student
           FROM {report_ghs_frozen_accounts} r
           JOIN {cohort} coh ON r.schoolid = coh.id
           JOIN {user} u ON r.userid = u.id
          WHERE 0 = 0
                $where
                $order";

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
    if (($column == 'rowcount') || ($column == 'action')) {
        $$column = $string[$column];
    } else {
        $sorturl = $thispageurl;
        $sorturl->param('perpage', $perpage);
        $sorturl->param('sort', $column);
        $sorturl->param('dir', $columndir);

        $$column = html_writer::link($sorturl->out(false), $string[$column]).' '.$columnicon;
    }
}

$table = new html_table();

$table->head = array();
$table->wrap = array();
$table->attributes = ['class' => 'nedtable fullwidth frozen-accounts-table'];

foreach ($columns as $column) {
    $table->head[$column] = $$column;
    $table->wrap[$column] = '';
}

// Override cell wrap.
$table->wrap['action'] = 'nowrap';

$tablerows = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

$counter = ($page * $perpage);

foreach ($tablerows as $tablerow) {
    $row = new html_table_row();

    foreach ($columns as $column) {
        $varname = 'cell'.$column;
        $$varname = new html_table_cell(helper::frozen_accounts_data($tablerow, $column, $counter, $pageparams));
    }

    $row->cells = array();
    foreach ($columns as $column) {
        $varname = 'cell' . $column;
        $row->cells[$column] = $$varname;
    }
    $table->data[] = $row;
}

$html .= html_writer::start_div('page-content-wrapper', array('id' => 'page-content'));
$html .= html_writer::tag('h1', $title, array('class' => 'page-title'));

$handler = new SH();
$schoolfilter = $handler->get_control_form($schoolid, $fullpageurl, false, true);

// Students.
$sql = "SELECT DISTINCT r.userid,
               CONCAT(u.firstname, ' ', u.lastname) fullname
          FROM {report_ghs_frozen_accounts} r
	      JOIN {user} u
            ON r.userid = u.id
	     WHERE r.schoolid {$schoolinsql}
      ORDER BY u.lastname";
$studentoptions = ['0' => get_string('all')] + $DB->get_records_sql_menu($sql, $schoolparams);

$statusoptions = ['0' => get_string('all')] + NED::get_freze_statuses();
$reasonoptions = ['0' => get_string('all')] + NED::get_freze_reasons();

// Filter form.
$searchformurl = new moodle_url('/report/ghs/ghs_class_deadlines.php');

$searchform =
    // First row.
    html_writer::start_div('form-inline').
    html_writer::start_div('form-group').

    NED::single_select($fullpageurl, 'filterstatus', $statusoptions, $filterstatus, get_string('status', 'report_ghs'), ['class' => 'mb-2 mr-sm-2']).
    NED::single_select($fullpageurl, 'filterstudentid', $studentoptions, $filterstudentid, get_string('student', 'report_ghs'), ['class' => 'mb-2 mr-sm-2']).
    NED::single_select($fullpageurl, 'filterreason', $reasonoptions, $filterreason, get_string('reason', 'report_ghs'), ['class' => 'mb-2 mr-sm-2']).

    html_writer::end_div().
    html_writer::end_div();

$html .= html_writer::div($searchform, 'search-form-wrapper mt-4', ['id' => 'search-form']);


$pagingurl = new moodle_url('/local/schoolmanager/view.php', [
    'perpage' => $perpage,
    'sort' => $sort,
    'dir' => $dir,
]);

$pagingbar = new paging_bar($totalcount, $page, $perpage, $pagingurl, 'page');
if (has_capability('report/ghs:canmanagefrozenaccount', $contextsystem)) {
    $html .= html_writer::div(
        html_writer::div(
            html_writer::link(
                new moodle_url('/report/ghs/freeze_account.php'),
                get_string('freezeaccount', 'report_ghs'), ['class' => 'btn btn-primary']),
            'col-md-12 text-right mb-2'
        ),
        'row'
    );
}

if ($outputpagingbar = $OUTPUT->render($pagingbar)) {
    $html .= $outputpagingbar;
}
$html .= html_writer::table($table);
$html .= $outputpagingbar;

$html .= html_writer::end_div(); // Main wrapper.
