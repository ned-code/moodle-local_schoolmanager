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
use local_schoolmanager\shared_lib as NED;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$SMR = new SM\output\school_manager_render();
$SM = $SMR->SM;

$title = $SMR::get_title();

$PAGE->set_context($SM->ctx);
$PAGE->set_url($SMR->get_my_url());
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title, $SMR::get_url());
switch($SMR->page){
    case $SMR::PAGE_CREW:
        $PAGE->navbar->add(NED::str('crews'), $SMR->get_my_url(null, false));
        break;
    case $SMR::PAGE_USER:
        $PAGE->navbar->add(NED::str('users'), $SMR->get_my_url());
        break;
}

$data = $SMR->render(true);
echo $OUTPUT->header();
echo $data;
echo $OUTPUT->footer();

