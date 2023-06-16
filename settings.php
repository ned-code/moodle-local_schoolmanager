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
 * Settings
 *
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schoolmanager\shared_lib as NED;

defined('MOODLE_INTERNAL') || die();

if (empty($hassiteconfig)){
    return;
}

require_once(__DIR__.'/lib.php');

// useful functions
$help_str = function($name, $visiblename='', $description='', $defaultsetting=''){
    $visiblename = empty($visiblename) ? $name : $visiblename;
    $description = empty($description) ? NED::str_check($visiblename . '_desc', '') : NED::str_check($description);
    $name = NED::$PLUGIN_NAME . '/' . $name;
    return [$name, NED::str_check($visiblename), $description, NED::str_check($defaultsetting)];
};

$configheading = function($name, $heading='', $information=null){
    $heading = empty($heading) ? $name : $heading;
    $information = is_null($information) ? $heading . '_info' : $information;
    return new admin_setting_heading(NED::$PLUGIN_NAME . '/' . $name, NED::str_check($heading), NED::str_check($information, ''));
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
    $setting_name = NED::$PLUGIN_NAME . '/' . $name;
    $title = NED::str($name);
    if (is_bool($text) && $text){
        $text = $title;
        $title = '';
    } else {
        $text = $text ?? $link;
    }
    return new admin_setting_description($setting_name, $title, NED::link([$link], $text, '', ['target' => '_blank']));
};

// add setting page
$settings = new admin_settingpage(NED::$PLUGIN_NAME.'_settings', NED::str('pluginname'));
/** @var \admin_root $ADMIN */
$ADMIN->add('localplugins', $settings);

// settings
$settings->add($configlink('view_schoolmanager', NED::url('~/'), true));
$settings->add($configlink('schoolmanager_tasks', NED::url('~/schoolmanager_tasks.php'), true));
$settings->add($configheading('general'));
//$settings->add($configyesno('disabled', 0));
$settings->add($configtextarea('academic_program','','',"1 Year",PARAM_TEXT, 10, 3));
$records = badges_get_badges(BADGE_TYPE_SITE, 0, 'name', 'ASC', 0, 0);

$badgeoptions = [];
foreach ($records as $record) {
    $badgeoptions[$record->id] = $record->name;
}
$badgeoptions = [0 => get_string('choose')] + $badgeoptions;

$settings->add($configselect('general_cert_badge', '', '', 0, $badgeoptions));
$settings->add($configselect('advanced_cert_badge', '', '', 0, $badgeoptions));

$find_school_sync_fields = function($type='menu', $find_default=false, $fullname=false){
    $choices = [
        0 => get_string('none')
    ];
    $default = 0;
    $profile_fields = profile_get_custom_fields();
    foreach ($profile_fields as $field){
        if ($field->datatype == $type){
            if ($find_default && !$default && $find_default == $field->shortname){
                $default = $field->id;
            }
            $choices[$field->id] = $fullname ? "$field->name {{$field->shortname}}" : $field->name;
        }
    }

    return [$choices, $default];
};

list($choices, $default) = $find_school_sync_fields('menu', 'partner_school');
$settings->add($configselect('school_field_to_sync', '', '', $default, $choices));

list($choices, $default) = $find_school_sync_fields('multiselect', 'district_admin');
$settings->add($configselect('schools_field_to_sync', '', '', $default, $choices));
