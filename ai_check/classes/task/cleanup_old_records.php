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
 * Scheduled task to cleanup old AI check records.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ai_check\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to cleanup old AI check records.
 */
class cleanup_old_records extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanup_old_records', 'assignsubmission_ai_check');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $cleanup_days = get_config('assignsubmission_ai_check', 'cleanup_days');
        
        if (empty($cleanup_days) || $cleanup_days <= 0) {
            mtrace('Cleanup is disabled (cleanup_days = 0)');
            return;
        }

        $cutoff_time = time() - ($cleanup_days * 24 * 60 * 60);
        
        // Delete old completed and failed records
        $conditions = array(
            'timecreated < ?' => $cutoff_time,
            'status IN (?, ?)' => array('completed', 'failed')
        );

        $count = $DB->count_records_select('assignsubmission_ai_check_grades', 
            'timecreated < ? AND status IN (?, ?)', 
            array($cutoff_time, 'completed', 'failed'));

        if ($count > 0) {
            $DB->delete_records_select('assignsubmission_ai_check_grades', 
                'timecreated < ? AND status IN (?, ?)', 
                array($cutoff_time, 'completed', 'failed'));
            
            mtrace("Cleaned up {$count} old AI check records older than {$cleanup_days} days");
        } else {
            mtrace("No old records to clean up");
        }
    }
} 