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
 * AI Check assignment submission plugin main class.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * AI Check submission plugin class.
 */
class assign_submission_ai_check extends assign_submission_plugin {

    /**
     * Get the name of the submission plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_ai_check');
    }

    /**
     * Get the settings for this plugin.
     *
     * @param MoodleQuickForm $mform The form object
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $PAGE;

        // Early return if not in the right context
        if (!isset($this->assignment) || !$this->assignment) {
            return;
        }

        // Check if ai_manager is available (non-blocking check)
        $ai_manager_available = false;
        try {
            if (file_exists($CFG->dirroot . '/local/ai_manager/version.php')) {
                $ai_manager_available = class_exists('\local_ai_manager\manager');
            }
        } catch (Exception $e) {
            // Ignore errors during class check
        }
        
        if (!$ai_manager_available) {
            $mform->addElement('static', 'ai_check_error', 
                get_string('error', 'assignsubmission_ai_check'),
                get_string('ai_manager_required', 'assignsubmission_ai_check'));
            return;
        }

        $defaultenabled = $this->get_config('enabled');
        $mform->addElement('selectyesno', 'assignsubmission_ai_check_enabled',
            get_string('enabled', 'assignsubmission_ai_check'));
        $mform->setDefault('assignsubmission_ai_check_enabled', $defaultenabled);
        $mform->addHelpButton('assignsubmission_ai_check_enabled', 'enabled', 'assignsubmission_ai_check');

        // Standard answer field
        $mform->addElement('textarea', 'assignsubmission_ai_check_standard_answer',
            get_string('standard_answer', 'assignsubmission_ai_check'),
            array('rows' => 6, 'cols' => 60));
        $mform->setType('assignsubmission_ai_check_standard_answer', PARAM_TEXT);
        $mform->addHelpButton('assignsubmission_ai_check_standard_answer', 'standard_answer', 'assignsubmission_ai_check');
        $mform->hideIf('assignsubmission_ai_check_standard_answer', 'assignsubmission_ai_check_enabled', 'eq', 0);

        // Grading rubric field
        $mform->addElement('textarea', 'assignsubmission_ai_check_grading_rubric',
            get_string('grading_rubric', 'assignsubmission_ai_check'),
            array('rows' => 6, 'cols' => 60));
        $mform->setType('assignsubmission_ai_check_grading_rubric', PARAM_TEXT);
        $mform->addHelpButton('assignsubmission_ai_check_grading_rubric', 'grading_rubric', 'assignsubmission_ai_check');
        $mform->hideIf('assignsubmission_ai_check_grading_rubric', 'assignsubmission_ai_check_enabled', 'eq', 0);

        // Grading mode
        $grading_modes = array(
            'draft' => get_string('grading_mode_draft', 'assignsubmission_ai_check'),
            'publish' => get_string('grading_mode_publish', 'assignsubmission_ai_check')
        );
        $mform->addElement('select', 'assignsubmission_ai_check_grading_mode',
            get_string('grading_mode', 'assignsubmission_ai_check'), $grading_modes);
        $mform->setDefault('assignsubmission_ai_check_grading_mode', 'draft');
        $mform->addHelpButton('assignsubmission_ai_check_grading_mode', 'grading_mode', 'assignsubmission_ai_check');
        $mform->hideIf('assignsubmission_ai_check_grading_mode', 'assignsubmission_ai_check_enabled', 'eq', 0);

        // Information about file restrictions
        $mform->addElement('static', 'assignsubmission_ai_check_info',
            get_string('file_restrictions', 'assignsubmission_ai_check'),
            get_string('file_restrictions_desc', 'assignsubmission_ai_check'));
        $mform->hideIf('assignsubmission_ai_check_info', 'assignsubmission_ai_check_enabled', 'eq', 0);

        // Add JavaScript to handle file restrictions (only if in assignment context)
        try {
            if ($PAGE && $PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('assign', $PAGE->context->instanceid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $PAGE->requires->js_call_amd('assignsubmission_ai_check/settings', 'init');
                }
            }
        } catch (Exception $e) {
            // Ignore JS loading errors
        }
    }

    /**
     * Save the settings for this plugin.
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $result = true;
        
        if (isset($data->assignsubmission_ai_check_enabled)) {
            $result = $this->set_config('enabled', $data->assignsubmission_ai_check_enabled);
        }
        
        if ($this->get_config('enabled')) {
            $result = $result && $this->set_config('standard_answer', $data->assignsubmission_ai_check_standard_answer);
            $result = $result && $this->set_config('grading_rubric', $data->assignsubmission_ai_check_grading_rubric);
            $result = $result && $this->set_config('grading_mode', $data->assignsubmission_ai_check_grading_mode);
        }
        
        return $result;
    }

    /**
     * Force file submission settings when AI check is enabled.
     *
     * @param stdClass $data
     */
    private function force_file_submission_settings(stdClass $data) {
        // This is handled by JavaScript, PHP logic removed to prevent conflicts
    }

    /**
     * This is an over-ride of the assign_submission_plugin method
     *
     * @param stdClass|grade_grade $submissionorgrade The submission or grade
     * @param MoodleQuickForm $mform The form
     * @param stdClass $data The data
     * @param int $userid The user id
     * @return bool
     */
    public function get_form_elements_for_user($submissionorgrade, MoodleQuickForm $mform, stdClass $data, $userid) {
        // This plugin doesn't add form elements for users
        // Students upload files through the file submission plugin
        return true;
    }

    /**
     * Process the submission after it's been saved.
     * This method is now empty because the logic has been moved to an event observer
     * to ensure it runs after all submission data (including files) is saved.
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        // All logic moved to the observer in classes/observer.php
        return true;
    }

    /**
     * Display the submission for grading.
     *
     * @param stdClass $submission
     * @param bool $showviewlink
     * @return string
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        global $DB;

        if (!$this->is_enabled()) {
            return '';
        }

        $output = '';
        $scriptincluded = false;

        // A closure to include the JS only once.
        $includejs = function() use (&$output, &$scriptincluded) {
            if ($scriptincluded) {
                return;
            }
            $output .= $this->include_trigger_script();
            $scriptincluded = true;
        };

        try {
            error_log('AI Check view_summary: Checking submission ID ' . $submission->id);
            
            $ai_record = $DB->get_record('assignsubmission_ai_check_grades', ['submission_id' => $submission->id]);

            if (!$this->get_config('enabled')) {
                return ''; // AI not enabled, show nothing.
            }

            $manualtriggerallowed = has_capability('moodle/site:config', context_system::instance());

            if (!$ai_record) {
                $output .= html_writer::div(
                    html_writer::tag('i', '', ['class' => 'fa fa-info-circle']) . ' ' .
                    get_string('ai_processing_not_started', 'assignsubmission_ai_check'),
                    'alert alert-info'
                );
                
                if ($manualtriggerallowed) {
                    $output .= html_writer::div(
                        html_writer::tag('button', '🔄 手动触发AI处理 (管理员)', [
                            'onclick' => 'triggerAIProcessing(' . $submission->id . ', this)',
                            'class' => 'btn btn-secondary btn-sm',
                            'style' => 'margin-top: 10px;'
                        ]),
                        'manual-trigger-section'
                    );
                    $includejs();
                }
                
                return $output;
            }

            error_log('AI Check view_summary: Found AI record with status: ' . $ai_record->status);
            
            switch ($ai_record->status) {
                case 'pending':
                    $output = html_writer::div(
                        html_writer::tag('i', '', ['class' => 'fa fa-clock-o']) . ' ' .
                        get_string('ai_processing_pending', 'assignsubmission_ai_check'),
                        'alert alert-info'
                    );
                    break;
                case 'processing':
                    $output = html_writer::div(
                        html_writer::tag('i', '', ['class' => 'fa fa-spinner fa-spin']) . ' ' .
                        get_string('ai_processing_inprogress', 'assignsubmission_ai_check'),
                        'alert alert-warning'
                    );
                    break;
                case 'completed':
                    $output = $this->format_ai_result($ai_record);
                    break;
                case 'failed':
                    $output = html_writer::div(
                        html_writer::tag('i', '', ['class' => 'fa fa-exclamation-triangle']) . ' ' .
                        get_string('ai_processing_failed', 'assignsubmission_ai_check'),
                        'alert alert-danger'
                    );
                    if (!empty($ai_record->error_message)) {
                        $output .= html_writer::div(
                            html_writer::tag('small', 'Error: ' . htmlspecialchars($ai_record->error_message)),
                            'text-muted'
                        );
                    }
                    
                    if ($manualtriggerallowed) {
                        $output .= html_writer::div(
                            html_writer::tag('button', '🔄 重试AI处理', [
                                'onclick' => 'triggerAIProcessing(' . $submission->id . ', this)',
                                'class' => 'btn btn-warning btn-sm',
                                'style' => 'margin-top: 10px;'
                            ]),
                            'retry-section'
                        );
                        $includejs();
                    }
                    break;
                default:
                    $output = html_writer::div(
                        'Unknown status: ' . htmlspecialchars($ai_record->status),
                        'alert alert-secondary'
                    );
            }

            return $output;
        } catch (Exception $e) {
            error_log('AI Check view_summary error: ' . $e->getMessage());
            return html_writer::div(
                'Debug: Error loading AI status - ' . $e->getMessage(),
                'alert alert-danger'
            );
        }
    }

    /**
     * Format AI grading result for display.
     *
     * @param stdClass $ai_record
     * @return string
     */
    private function format_ai_result($ai_record) {
        $output = html_writer::div(
            html_writer::tag('i', '', array('class' => 'fa fa-check-circle text-success')) . ' ' .
            html_writer::tag('strong', get_string('ai_grading_result', 'assignsubmission_ai_check')),
            'alert alert-success'
        );
        
        if ($ai_record->ai_score !== null) {
            $output .= html_writer::tag('p', 
                html_writer::tag('strong', get_string('ai_score', 'assignsubmission_ai_check') . ': ') .
                html_writer::tag('span', $ai_record->ai_score, array('class' => 'badge badge-primary'))
            );
        }
        
        if (!empty($ai_record->ai_feedback)) {
            $output .= html_writer::div(
                html_writer::tag('strong', get_string('ai_feedback', 'assignsubmission_ai_check') . ':') .
                html_writer::tag('div', format_text($ai_record->ai_feedback), array('class' => 'mt-2')),
                'mt-3'
            );
        }

        return $output;
    }

    /**
     * Debug method to manually trigger AI processing for a submission.
     * This is for troubleshooting purposes only.
     * 
     * @param int $submission_id
     * @return array
     */
    public function debug_trigger_ai_processing($submission_id) {
        global $DB;
        
        try {
            $submission = $DB->get_record('assign_submission', ['id' => $submission_id]);
            if (!$submission) {
                return ['success' => false, 'error' => 'Submission not found'];
            }

            // Get assignment record, derive course-module id (cmid)
            $assignment_record = $DB->get_record('assign', ['id' => $submission->assignment]);
            if (!$assignment_record) {
                return ['success' => false, 'error' => 'Assignment not found'];
            }

            $cm = get_coursemodule_from_instance('assign', $assignment_record->id, $assignment_record->course, false, MUST_EXIST);
            $cmid = $cm->id;

            // Create assignment object – 兼容不同 Moodle 版本
            if (method_exists('assign', 'create')) {
                $assignment = \assign::create($cmid);
            } else {
                $context = \context_module::instance($cmid);
                $assignment = new \assign($cmid, $assignment_record, $context, $cm);
            }
            $aicheckplugin = $assignment->get_submission_plugin_by_type('ai_check');
            
            if (!$aicheckplugin || !$aicheckplugin->is_enabled()) {
                return ['success' => false, 'error' => 'AI Check plugin not enabled'];
            }

            // Check if there's a file submission
            $fs = get_file_storage();
            $files = $fs->get_area_files($assignment->get_context()->id,
                'assignsubmission_file', 'submission_files', $submission->id, 'filename', false);

            if (empty($files)) {
                return ['success' => false, 'error' => 'No files found'];
            }

            $file = reset($files);

            // Create or update AI check record
            $aicheckrecord = $DB->get_record('assignsubmission_ai_check_grades', ['submission_id' => $submission->id]);

            if (!$aicheckrecord) {
                $aicheckrecord = new \stdClass();
                $aicheckrecord->submission_id = $submission->id;
                $aicheckrecord->status = 'pending';
                $aicheckrecord->timecreated = time();
                $aicheckrecord->timemodified = time();
                $aicheckrecord->processing_attempts = 0;
                $aicheckrecord->error_message = '';
                $aicheckrecord->id = $DB->insert_record('assignsubmission_ai_check_grades', $aicheckrecord);
            } else {
                $aicheckrecord->status = 'pending';
                $aicheckrecord->processing_attempts = 0;
                $aicheckrecord->error_message = '';
                $aicheckrecord->timemodified = time();
                $DB->update_record('assignsubmission_ai_check_grades', $aicheckrecord);
            }

            // Queue the processing task
            $task = new \assignsubmission_ai_check\task\process_submission();
            $task->set_custom_data([
                'submission_id' => $submission->id,
                'file_id'       => $file->get_id(),
                'assignment_id' => $submission->assignment,
            ]);
            \core\task\manager::queue_adhoc_task($task);

            return ['success' => true, 'message' => 'AI processing task queued successfully'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Produces a summary of what was submitted for this plugin.
     *
     * @param stdClass $submission
     * @return array An array containing the text and html summary.
     */
    public function submission_summary_for_messages(stdClass $submission): array {
        if (!$this->is_enabled()) {
            return ['', ''];
        }

        $status = get_string('ai_processing_pending', 'assignsubmission_ai_check');
        $textsummary = get_string('pluginname', 'assignsubmission_ai_check') . ': ' . $status;
        $htmlsummary = html_writer::tag('strong', get_string('pluginname', 'assignsubmission_ai_check') . ':') . ' ' . $status;

        return [$textsummary, $htmlsummary];
    }

    /**
     * Return true if this plugin can upgrade old Moodle 2.2 assignment of this type and version.
     *
     * @param string $type
     * @param int $version
     * @return bool
     */
    public function can_upgrade($type, $version) {
        return false;
    }

    /**
     * Upgrade the settings from the old assignment to the new plugin based assignment.
     *
     * @param context $oldcontext
     * @param stdClass $oldassignment
     * @param string $log
     * @return bool
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, &$log) {
        return true;
    }

    /**
     * Return true if there are no submission files.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        // This plugin doesn't store files directly
        return false;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files.
     *
     * @return array
     */
    public function get_file_areas() {
        return array(); // No file areas for this plugin
    }

    /**
     * Copy the student's submission from a previous submission.
     *
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        // No copying needed for this plugin
        return true;
    }

    /**
     * Includes the JavaScript needed for the manual trigger button.
     * @return string The script tag.
     */
    private function include_trigger_script() {
        // This function should only be called once per page load.
        return html_writer::script('
            if (typeof window.triggerAIProcessing === "undefined") {
                window.triggerAIProcessing = function(submissionId, button) {
                    if (confirm("确定要手动触发或重试AI处理吗？")) {
                        button.disabled = true;
                        button.innerText = "处理中...";

                        fetch(M.cfg.wwwroot + "/mod/assign/submission/ai_check/manual_trigger.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: "submission_id=" + submissionId + "&sesskey=" + M.cfg.sesskey
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.text().then(text => {
                                    try {
                                        const errorData = JSON.parse(text);
                                        throw new Error(errorData.error || "Server error with no details.");
                                    } catch (e) {
                                        throw new Error("Server returned a non-JSON error. Check PHP logs.");
                                    }
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                alert("AI处理任务已成功添加到队列！页面将刷新。");
                                location.reload();
                            } else {
                                alert("错误: " + (data.error || "未知错误，请检查服务器日志。"));
                                button.disabled = false;
                                button.innerText = "🔄 重试AI处理";
                            }
                        })
                        .catch(error => {
                            alert("请求失败: " + error.message);
                            button.disabled = false;
                            button.innerText = "🔄 重试AI处理";
                        });
                    }
                }
            }
        ');
    }
} 