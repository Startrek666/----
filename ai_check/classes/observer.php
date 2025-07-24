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
 * Event observer for the AI Check assignment submission plugin.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ai_check;

defined('MOODLE_INTERNAL') || die();

class observer {
    /**
     * Handles submission created or updated events.
     *
     * @param \core\event\base $event The event object.
     * @return bool
     */
    public static function submission_created_or_updated(\core\event\base $event) {
        // CRITICAL: This method must NEVER throw exceptions or return false
        // as it could break the assignment submission process
        
        global $DB;

        try {
            // Step 1: Basic event validation
            $eventclass = get_class($event);
            error_log("AI Check Observer: Event received: {$eventclass}");
            
            // Only process assignment submission events
            if (strpos($eventclass, 'mod_assign\\event\\') === false || 
                strpos($eventclass, 'submission') === false) {
                error_log("AI Check Observer: Skipping non-submission event: {$eventclass}");
                return true;
            }

            // Step 2: Extract submission ID safely
            $submissionid = null;
            if (isset($event->objectid) && $event->objectid > 0) {
                $submissionid = $event->objectid;
            }
            
            if (!$submissionid) {
                error_log("AI Check Observer: No valid submission ID found");
                return true;
            }

            error_log("AI Check Observer: Processing submission ID: {$submissionid}");

            // Step 3: Get submission record
            $submission = $DB->get_record('assign_submission', ['id' => $submissionid]);
            if (!$submission) {
                error_log("AI Check Observer: Submission {$submissionid} not found in database");
                return true;
            }

            // Step 4: Create assignment object
            try {
                // Get assignment record, and derive course-module id (cmid)
                $assignment_record = $DB->get_record('assign', ['id' => $submission->assignment]);
                if (!$assignment_record) {
                    error_log("AI Check Observer: Assignment {$submission->assignment} not found in database");
                    return true;
                }

                $cm = get_coursemodule_from_instance('assign', $assignment_record->id, $assignment_record->course, false, MUST_EXIST);
                $cmid = $cm->id;

                // 兼容不同 Moodle 版本：优先使用静态 create()，否则直接实例化。
                if (method_exists('assign', 'create')) {
                    $assignment = \assign::create($cmid);
                } else {
                    $context = \context_module::instance($cmid);
                    $assignment = new \assign($cmid, $assignment_record, $context, $cm);
                }
            } catch (\Exception $e) {
                error_log("AI Check Observer: Cannot create assignment object: " . $e->getMessage());
                return true;
            }

            // Step 5: Check if AI Check plugin is enabled
            // 兼容旧版 Moodle：仅在 assign 类存在该方法时才检查插件启用状态
            if (method_exists($assignment, 'is_submission_plugin_enabled')) {
                if (!$assignment->is_submission_plugin_enabled('ai_check')) {
                    // 插件未在此作业中启用
                    error_log('AI Check plugin not enabled for this assignment');
                    return true;
                }
            }

            $aicheckplugin = $assignment->get_submission_plugin_by_type('ai_check');
            if (!$aicheckplugin || !$aicheckplugin->is_enabled()) {
                error_log("AI Check Observer: AI Check plugin instance not found or disabled");
                return true;
            }

            // Step 6: Check if AI auto-grading is enabled in settings
            $enabled = $aicheckplugin->get_config('enabled');
            if (!$enabled) {
                error_log("AI Check Observer: AI auto-grading disabled in plugin settings");
                return true;
            }

            // Step 7: Check for files (be patient, files might be uploading)
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $assignment->get_context()->id,
                'assignsubmission_file', 
                'submission_files', 
                $submission->id, 
                'filename', 
                false
            );

            if (empty($files)) {
                error_log("AI Check Observer: No files found for submission {$submissionid} - will defer processing");
                
                // Schedule a delayed task to check again later
                $task = new \assignsubmission_ai_check\task\process_submission();
                $task->set_custom_data([
                    'submission_id' => $submission->id,
                    'file_id' => null, // Will be found in the task
                    'assignment_id' => $submission->assignment,
                ]);
                $task->set_next_run_time(time() + 10); // Wait 10 seconds
                \core\task\manager::queue_adhoc_task($task);
                
                error_log("AI Check Observer: Queued delayed task for submission {$submissionid}");
                return true;
            }

            $file = reset($files);
            error_log("AI Check Observer: Found file: " . $file->get_filename());

            // Step 8: Create or update AI check record
            $aicheckrecord = $DB->get_record('assignsubmission_ai_check_grades', 
                ['submission_id' => $submission->id]);

            if (!$aicheckrecord) {
                // Create new record
                $aicheckrecord = new \stdClass();
                $aicheckrecord->submission_id = $submission->id;
                $aicheckrecord->status = 'pending';
                $aicheckrecord->timecreated = time();
                $aicheckrecord->timemodified = time();
                $aicheckrecord->processing_attempts = 0;
                $aicheckrecord->error_message = '';
                
                $aicheckrecord->id = $DB->insert_record('assignsubmission_ai_check_grades', $aicheckrecord);
                error_log("AI Check Observer: Created AI record with ID {$aicheckrecord->id}");
            } else {
                // Update existing record
                $aicheckrecord->status = 'pending';
                $aicheckrecord->processing_attempts = 0;
                $aicheckrecord->error_message = '';
                $aicheckrecord->timemodified = time();
                
                $DB->update_record('assignsubmission_ai_check_grades', $aicheckrecord);
                error_log("AI Check Observer: Updated existing AI record");
            }

            // Step 9: Queue the processing task
            if (class_exists('\assignsubmission_ai_check\task\process_submission')) {
                $task = new \assignsubmission_ai_check\task\process_submission();
                $task->set_custom_data([
                    'submission_id' => $submission->id,
                    'file_id' => $file->get_id(),
                    'assignment_id' => $submission->assignment,
                ]);
                
                \core\task\manager::queue_adhoc_task($task);
                error_log("AI Check Observer: Successfully queued processing task for submission {$submissionid}");
            } else {
                error_log("AI Check Observer: Process submission task class not found");
                
                // Update record to show error
                $aicheckrecord->status = 'failed';
                $aicheckrecord->error_message = 'Process submission task class not found';
                $DB->update_record('assignsubmission_ai_check_grades', $aicheckrecord);
            }

        } catch (\Throwable $e) {
            // Catch ALL errors including fatal ones
            error_log("AI Check Observer: CRITICAL ERROR - " . $e->getMessage());
            error_log("AI Check Observer: Stack trace - " . $e->getTraceAsString());
            // Continue execution - NEVER let this break the submission
        }

        // ALWAYS return true to ensure submission process continues
        return true;
    }
} 