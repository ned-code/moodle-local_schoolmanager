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
 * Main view plugin page
 *
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schoolmanager\school_handler as SH;
use local_schoolmanager\shared_lib as NED;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$schoolid = optional_param('schoolid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_TEXT);

if (!$schoolid) {
    $context = NED::ctx();
    if (NED::has_capability('viewschooldescription', $context)) {
        $view = SH::VIEW_SCHOOL;
    } else {
        $view = SH::VIEW_SCHOOLS;
    }
} else {
    if ($action == 'resettimezone' && is_siteadmin()) {
        $school = new local_schoolmanager\school($schoolid);
        $school->reset_time_zone();
    }
}
require_login();

$ctx = NED::ctx();
$caps = ['viewownschooldashboard', 'viewallschooldashboards', 'viewstudentstaffsummary'];
if (!NED::has_any_capability($caps, $ctx)){
    throw new moodle_exception('permission');
}

$PAGE->set_context($ctx);
$PAGE->set_pagelayout('schoolmanager');
NED::page_set_title('pluginname', NED::url('~/view.php'));

$renderable = new local_schoolmanager\output\school($schoolid, $view);
$data = NED::render($renderable);

echo $OUTPUT->header();
echo $data;
echo $OUTPUT->footer();
