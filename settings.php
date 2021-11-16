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

use local_schoolmanager as SM;
defined('MOODLE_INTERNAL') || die();

if (empty($hassiteconfig)){
    return;
}

require_once(__DIR__.'/lib.php');

// useful functions
$help_str = function($name, $visiblename='', $description='', $defaultsetting=''){
    $visiblename = empty($visiblename) ? $name : $visiblename;
    $description = empty($description) ? SM\check_str($visiblename . '_desc', '') : SM\check_str($description);
    $name = SM\PLUGIN_NAME . '/' . $name;
    return [$name, SM\check_str($visiblename), $description, SM\check_str($defaultsetting)];
};

$configheading = function($name, $heading='', $information=null){
    $heading = empty($heading) ? $name : $heading;
    $information = is_null($information) ? $heading . '_info' : $information;
    return new admin_setting_heading(SM\PLUGIN_NAME . '/' . $name, SM\check_str($heading),SM\check_str($information, ''));
};

$configselect = function($name, $visiblename='', $description='', $defaultsetting=0, $choices=[]) use ($help_str){
    list($name, $visiblename, $description, $defaultsetting) = $help_str($name, $visiblename, $description, $defaultsetting);
    return new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices);
};
$configselect2 = function($name, $defaultsetting=0, $choices=[], $visiblename='', $description='') use ($help_str){
    list($name, $visiblename, $description, $defaultsetting) = $help_str($name, $visiblename, $description, $defaultsetting);
    return new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices);
};

$configmultiselect = function($name, $visiblename='', $description='', $defaultsetting=0, $choices=[]) use ($help_str){
    list($name, $visiblename, $description, $defaultsetting) = $help_str($name, $visiblename, $description, $defaultsetting);
    return new admin_setting_configmultiselect($name, $visiblename, $description, $defaultsetting, $choices);
};

$yesnooptions = ['0' => get_string('no'), '1' => get_string('yes')];
$configyesno = function($name, $defaultsetting=0, $visiblename='', $description='') use ($configselect, $yesnooptions){
    return $configselect($name, $visiblename, $description, $defaultsetting, $yesnooptions);
};

$configtext = function($name, $visiblename, $description=null, $defaultsetting='', $paramtype=PARAM_RAW, $size=null) use ($help_str){
    list($name, $visiblename, $description, $defaultsetting) = $help_str($name, $visiblename, $description, $defaultsetting);
    return new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype, $size);
};

$configtextarea = function($name, $visiblename, $description=null, $defaultsetting='', $paramtype=PARAM_RAW, $cols='60', $rows='8')
use ($help_str){
    list($name, $visiblename, $description, $defaultsetting) = $help_str($name, $visiblename, $description, $defaultsetting);
    return new admin_setting_configtextarea($name, $visiblename, $description, $defaultsetting, $paramtype, $cols, $rows);
};

$configlink = function($name, $link, $text=null) use ($help_str) {
    $setting_name = SM\PLUGIN_NAME . '/' . $name;
    $title = SM\str($name);
    if (is_bool($text) && $text){
        $text = $title;
        $title = '';
    } else {
        $text = $text ?? $link;
    }
    return new admin_setting_description($setting_name, $title, SM\link([$link], $text, '', ['target' => '_blank']));
};

// add setting page
$settings = new admin_settingpage(SM\PLUGIN_NAME.'_settings', SM\str('pluginname'));
/** @var \admin_root $ADMIN */
$ADMIN->add('localplugins', $settings);

// settings
$settings->add($configlink('view_schoolmanager',SM\PLUGIN_URL, true));
$settings->add($configheading('general'));
//$settings->add($configyesno('disabled', 0));
$settings->add($configtextarea('academic_program','','',"1 Year",PARAM_TEXT, 10, 3));
$courses = [0 => get_string('choose')] + $DB->get_records_select_menu('course', 'id > 1', null, 'fullname ASC', 'id,fullname');
$records = badges_get_badges(BADGE_TYPE_SITE, 0, 'name', 'ASC', 0, 0);

$badgeoptions = [];
foreach ($records as $record) {
    $badgeoptions[$record->id] = $record->name;
}
$badgeoptions = [0 => get_string('choose')] + $badgeoptions;

$settings->add($configselect('general_cert_badge', '', '', 0, $badgeoptions));
$settings->add($configselect('advanced_cert_badge', '', '', 0, $badgeoptions));
