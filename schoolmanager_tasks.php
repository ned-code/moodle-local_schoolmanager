<?php
/**
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
use local_schoolmanager\shared_lib as NED;

$name = basename(__FILE__, '.php');
$title = NED::str($name);
$url = NED::url('~/'.$name.'.php');
$ctx = context_system::instance();

$PAGE->set_context($ctx);
require_login(null, false);

$PAGE->set_pagelayout('admin');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add(NED::str('pluginname'), NED::url('~/'));
$PAGE->navbar->add($title, $url);

if (!has_capability('moodle/site:config', $ctx)){
    NED::print_module_error('accessdenied', 'admin');
}

$task_records = $DB->get_records('task_scheduled', ['component' => NED::$PLUGIN_NAME], 'id');

if (empty($task_records)){
    $o = NED::notification(NED::str('notasks'), NED::NOTIFY_WARNING);
} else {
    $str = function($identifier, $a=null){
        return get_string($identifier, 'tool_task', $a);
    };
    $now = time();

    $table = NED::html_table('generaltable simple', null, [
        get_string('name'),
        $str('lastruntime'),
        $str('nextruntime'),
        NED::cell(get_string('edit'), 'text-align-center'),
        NED::cell(get_string('logs'), 'text-align-center'),
        NED::cell($str('runnow'), 'text-align-center'),
    ]);
    $showloglink = \core\task\logmanager::has_log_report();

    foreach ($task_records as $task_record){
        $task = \core\task\manager::scheduled_task_from_record($task_record);
        if (!$task) continue;

        $row = NED::row();
        $t_name = $task->get_name();
        $classname = get_class($task);
        $row->cells[] = $t_name;

        $last_run = $task->get_last_run_time();
        if ($last_run){
            $cell = NED::cell(
                NED::$C::str('x_ago', NED::time_diff_to_str_max($last_run, $now, 2)),
                '', ['title' => NED::ned_date($last_run)]);
        } else {
            $cell = get_string('never');
        }
        $row->cells[] = $cell;

        $next_run = $task->get_next_run_time();
        $plugininfo = core_plugin_manager::instance()->get_plugin_info($task->get_component());
        if ($plugininfo && $plugininfo->is_enabled() === false && !$task->get_run_if_component_disabled()) {
            $cell = $str('plugindisabled');
        } else if ($task->get_disabled()) {
            $cell = $str('taskdisabled');
        } else if ($next_run > $now) {
            $cell = NED::cell(
                NED::$C::str('after_x', NED::time_diff_to_str_max($next_run, $now, 2)),
                '', ['title' => NED::ned_date($next_run)]);
        } else {
            $cell = $str('asap');
        }
        $row->cells[] = $cell;

        if (empty($CFG->preventscheduledtaskchanges) && !$task->is_overridden()) {
            $editlink = NED::link(NED::url('/admin/tool/task/scheduledtasks.php', ['action' => 'edit', 'task' => $classname]),
                NED::fa('fa-cog m-0', '', $str('edittaskschedule', $t_name)), 'flex-center');
        } else {
            $editlink = NED::render(new pix_icon('t/locked', $str('scheduledtaskchangesdisabled')));
        }
        $row->cells[] = NED::cell($editlink, 'vertical-align-middle');

        $loglink = '';
        if ($showloglink) {
            $loglink = NED::link(\core\task\logmanager::get_url_for_task_class($classname),
                NED::fa('fa-file-text m-0', '', $str('viewlogs', $t_name)), 'flex-center');
        }
        $row->cells[] = NED::cell($loglink, 'vertical-align-middle');

        $runlink = NED::link(['/admin/tool/task/schedule_task.php', ['task' => $classname]],
            NED::fa('fa-play-circle-o m-0'), 'flex-center');
        $row->cells[] = NED::cell($runlink, 'vertical-align-middle');

        $table->data[] = $row;
    }

    $o = NED::render_table($table);
}

echo $OUTPUT->header();
echo $o;
echo $OUTPUT->footer();
