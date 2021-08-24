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
use local_schoolmanager as SM;
use local_schoolmanager\school_handler as SH;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$schoolid = optional_param('schoolid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_TEXT);

if (!$schoolid) {
    $view = SH::VIEW_SCHOOLS;
} else {
    if ($action == 'resettimezone' && is_siteadmin()) {
        $school = new SM\school($schoolid);
        $school->reset_time_zone();
    }
}

require_login();

$contextsystem = context_system::instance();

if (!has_any_capability([
    'local/schoolmanager:viewownschooldashboard',
    'local/schoolmanager:viewallschooldashboards'], $contextsystem)) {
    throw new moodle_exception('permission');
}

$thispageurl = new moodle_url('/local/schoolmanager/view.php');
$title = get_string('pluginname', 'local_schoolmanager');

$PAGE->set_context($contextsystem);
$PAGE->set_pagelayout('schoolmanager');
$PAGE->set_url($thispageurl);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title, $thispageurl);
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_schoolmanager');
$renderable = new SM\output\school($schoolid, $view);
echo $renderer->render($renderable);
echo $OUTPUT->footer();