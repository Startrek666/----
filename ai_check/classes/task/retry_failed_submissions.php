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
 * Scheduled task to retry failed AI check submissions.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ai_check\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to retry failed AI check submissions.
 */
class retry_failed_submissions extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:retry_failed_submissions', 'assignsubmission_ai_check');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $max_attempts = get_config('assignsubmission_ai_check', 'max_retry_attempts') ?: 3;
        
        // Find failed submissions that haven't exceeded max attempts
        $sql = "SELECT acg.*, s.assignment, s.userid 
                FROM {assignsubmission_ai_check_grades} acg
                JOIN {assign_submission} s ON acg.submission_id = s.id
                WHERE acg.status = 'failed' 
                AND acg.processing_attempts < ?
                AND acg.timemodified < ?";
        
        // Only retry records that failed more than 1 hour ago
        $retry_after = time() - (60 * 60);
        $failed_records = $DB->get_records_sql($sql, array($max_attempts, $retry_after));

        if (empty($failed_records)) {
            mtrace('No failed submissions to retry');
            return;
        }

        $retried = 0;
        foreach ($failed_records as $record) {
            try {
                // Get the file to retry processing
                $fs = get_file_storage();
                $context = \context_module::instance($record->assignment);
                $files = $fs->get_area_files($context->id, 'assignsubmission_file', 
                    'submission_files', $record->submission_id, 'filename', false);

                if (empty($files)) {
                    mtrace("No file found for submission {$record->submission_id}, marking as failed permanently");
                    $record->status = 'failed';
                    $record->error_message = 'No file found for retry';
                    $record->processing_attempts = $max_attempts; // Prevent further retries
                    $DB->update_record('assignsubmission_ai_check_grades', $record);
                    continue;
                }

                $file = reset($files);
                
                // Reset status to pending and queue for retry
                $record->status = 'pending';
                $record->error_message = '';
                $record->timemodified = time();
                $DB->update_record('assignsubmission_ai_check_grades', $record);

                // Queue the processing task
                $task = new process_submission();
                $task->set_custom_data(array(
                    'submission_id' => $record->submission_id,
                    'file_id' => $file->get_id(),
                    'assignment_id' => $record->assignment
                ));
                
                \core\task\manager::queue_adhoc_task($task);
                $retried++;
                
                mtrace("Queued retry for submission {$record->submission_id} (attempt " . 
                       ($record->processing_attempts + 1) . "/{$max_attempts})");

            } catch (\Exception $e) {
                mtrace("Failed to queue retry for submission {$record->submission_id}: " . $e->getMessage());
            }
        }

        mtrace("Queued {$retried} failed submissions for retry");
    }
} 