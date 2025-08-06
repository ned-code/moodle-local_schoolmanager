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
 * Sync cohort members' timezones
 *
 * @package    local_schoolmanager
 * @copyright  Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\task;

use context_course;
use context_system;
use stdClass;


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/gdlib.php');
require_once($CFG->dirroot . '/group/lib.php');

class sync_groups extends \core\task\scheduled_task {

    public function get_name(){
        return get_string('syncgroups', 'local_schoolmanager');
    }

    public function execute(){
        global $DB;

        $contextsystem = context_system::instance();

        $fs = get_file_storage();

        $filter = $DB->sql_like('g.name', '?', false);

        $sql = "SELECT g.id, 
                       g.courseid, 
                       g.idnumber, 
                       g.name, 
                       g.description, 
                       g.descriptionformat, 
                       g.enrolmentkey, 
                       g.picture, 
                       g.timecreated, 
                       g.timemodified, 
                       g.schedule, 
                       g.startdate, 
                       g.enddate
                  FROM {groups} g
                 WHERE {$filter}";

        $rs = $DB->get_recordset('local_schoolmanager_school');
        foreach ($rs as $school){
            $params = [$school->code . '/%'];

            if (!$groups = $DB->get_records_sql($sql, $params)){
                continue;
            }

            $schollogo = null;

            if ($files = $fs->get_area_files($contextsystem->id, 'local_schoolmanager', 'compact_logo', $school->id, "itemid, filepath, filename", false)){
                $file = reset($files);
                if ($dir = make_temp_directory('forms')){
                    if ($tempfile = tempnam($dir, 'tempup_')){
                        if (!$schollogo = $file->copy_content_to($tempfile)){
                            @unlink($tempfile);
                        }
                    }
                }
            }

            foreach ($groups as $group){
                $data = new stdClass();
                $data->id = $group->id;
                $data->description = $school->name;
                $DB->update_record('groups', $data);

                $contextcourse = context_course::instance($group->courseid);

                $conversation = $DB->get_record('message_conversations', [
                    'component' => 'core_group', 'itemtype' => 'groups', 'itemid' => $group->id
                ]);

                if ($conversation){
                    if ($conversation->enabled == 0){
                        \core_message\api::enable_conversation($group->conversationid);
                    }
                } else {
                    $conversation = \core_message\api::create_conversation(
                        \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP,
                        [],
                        $group->name,
                        \core_message\api::MESSAGE_CONVERSATION_ENABLED,
                        'core_group',
                        'groups',
                        $group->id,
                        $contextcourse->id
                    );

                    // Add members to conversation if they exists in the group.
                    if ($groupmemberroles = groups_get_members_by_role($group->id, $group->courseid, 'u.id')){
                        $users = [];
                        foreach ($groupmemberroles as $roleid => $roledata){
                            foreach ($roledata->users as $member){
                                $users[] = $member->id;
                            }
                        }
                        \core_message\api::add_members_to_conversation($users, $conversation->id);
                    }
                }

                if ($schollogo){
                    $fs->delete_area_files($contextcourse->id, 'group', 'icon', $group->id);
                    $newpicture = 0;
                    if (!empty($tempfile) && $rev = process_new_icon($contextcourse, 'group', 'icon', $group->id, $tempfile)){
                        $newpicture = $rev;
                    } else {
                        $fs->delete_area_files($contextcourse->id, 'group', 'icon', $group->id);
                    }

                    if ($newpicture != $group->picture){
                        $DB->set_field('groups', 'picture', $newpicture, ['id' => $group->id]);
                    }
                }
            }

            if (!empty($tempfile)){
                @unlink($tempfile);
            }
        }
        $rs->close();

        \cache::make('core', 'groupdata')->purge();

        return true;
    }
}