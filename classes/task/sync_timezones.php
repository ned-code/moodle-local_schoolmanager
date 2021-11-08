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

use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

class sync_timezones extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('synctimezone', 'local_schoolmanager');
    }

    public function execute() {
        global $DB;

        $rs = $DB->get_recordset('local_schoolmanager_school', ['synctimezone' => 1]);
        foreach ($rs as $school) {
            if (!$cohort = $DB->get_record('cohort', ['id' => $school->id, 'visible' => 1])) {
                continue;
            }

            if (!$cohortmembers = $DB->get_records('cohort_members', ['cohortid' => $school->id], '', 'userid')) {
                continue;
            }

            if ($cohort->timezone) {
                list($insql, $params) = $DB->get_in_or_equal(array_keys($cohortmembers));
                $params = array_merge([$cohort->timezone], $params);
                $DB->execute("UPDATE {user} SET timezone=? WHERE id $insql", $params);
            }
        }
        $rs->close();

        return true;
    }
}