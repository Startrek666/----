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
        global $DB;
        
        if (isset($data->assignsubmission_ai_check_enabled)) {
            $this->set_config('enabled', $data->assignsubmission_ai_check_enabled);
            
            if ($data->assignsubmission_ai_check_enabled) {
                $this->set_config('standard_answer', $data->assignsubmission_ai_check_standard_answer);
                $this->set_config('grading_rubric', $data->assignsubmission_ai_check_grading_rubric);
                $this->set_config('grading_mode', $data->assignsubmission_ai_check_grading_mode);
                
                // Force file submission settings when AI check is enabled
                $this->force_file_submission_settings($data);
                
                // Store configuration in a more accessible format for the task
                $config_data = array(
                    'standard_answer' => $data->assignsubmission_ai_check_standard_answer,
                    'grading_rubric' => $data->assignsubmission_ai_check_grading_rubric,
                    'grading_mode' => $data->assignsubmission_ai_check_grading_mode
                );
                
                // Store as serialized data for easy retrieval
                $this->set_config('ai_config', serialize($config_data));
            }
        }
        return true;
    }

    /**
     * Force file submission settings when AI check is enabled.
     *
     * @param stdClass $data
     */
    private function force_file_submission_settings(stdClass $data) {
        // Force file submission to be enabled with specific settings
        $data->assignsubmission_file_enabled = 1;
        $data->assignsubmission_file_maxfiles = 1;
        $data->assignsubmission_file_filetypes = 'pdf,docx';
    }

    /**
     * Validate the settings for this plugin.
     *
     * @param array $data
     * @return array
     */
    public function get_form_elements_for_user(stdClass $submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        // This plugin doesn't add form elements for users
        // Students upload files through the file submission plugin
        return true;
    }

    /**
     * Process the submission after it's been saved.
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB;

        if (!$this->is_enabled()) {
            return true;
        }

        try {
            // Check if there's a file submission
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->assignment->get_context()->id, 
                'assignsubmission_file', 'submission_files', $submission->id, 'filename', false);

            if (empty($files)) {
                return true; // No files to process
            }

            // Get the first (and should be only) file
            $file = reset($files);
            
            // Create or update AI check record
            $ai_check_record = $DB->get_record('assignsubmission_ai_check_grades', 
                array('submission_id' => $submission->id));
            
            if (!$ai_check_record) {
                $ai_check_record = new stdClass();
                $ai_check_record->submission_id = $submission->id;
                $ai_check_record->status = 'pending';
                $ai_check_record->timecreated = time();
                $ai_check_record->timemodified = time();
                $ai_check_record->id = $DB->insert_record('assignsubmission_ai_check_grades', $ai_check_record);
            } else {
                $ai_check_record->status = 'pending';
                $ai_check_record->timemodified = time();
                $DB->update_record('assignsubmission_ai_check_grades', $ai_check_record);
            }

            // Queue the processing task
            if (class_exists('\assignsubmission_ai_check\task\process_submission')) {
                $task = new \assignsubmission_ai_check\task\process_submission();
                $task->set_custom_data(array(
                    'submission_id' => $submission->id,
                    'file_id' => $file->get_id(),
                    'assignment_id' => $this->assignment->get_instance()->id
                ));
                
                \core\task\manager::queue_adhoc_task($task);
            }

        } catch (Exception $e) {
            // Log error but don't fail the submission
            error_log('AI Check plugin error: ' . $e->getMessage());
        }

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

        try {
            $ai_record = $DB->get_record('assignsubmission_ai_check_grades', 
                array('submission_id' => $submission->id));

            if (!$ai_record) {
                return get_string('no_ai_processing', 'assignsubmission_ai_check');
            }

            $output = '';
            
            switch ($ai_record->status) {
                case 'pending':
                    $output = get_string('ai_processing_pending', 'assignsubmission_ai_check');
                    break;
                case 'processing':
                    $output = get_string('ai_processing_inprogress', 'assignsubmission_ai_check');
                    break;
                case 'completed':
                    $output = $this->format_ai_result($ai_record);
                    break;
                case 'failed':
                    $output = get_string('ai_processing_failed', 'assignsubmission_ai_check');
                    break;
            }

            return $output;
        } catch (Exception $e) {
            // Return empty string on error
            error_log('AI Check view_summary error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Format AI grading result for display.
     *
     * @param stdClass $ai_record
     * @return string
     */
    private function format_ai_result($ai_record) {
        $output = html_writer::tag('h4', get_string('ai_grading_result', 'assignsubmission_ai_check'));
        
        if ($ai_record->ai_score !== null) {
            $output .= html_writer::tag('p', 
                get_string('ai_score', 'assignsubmission_ai_check', $ai_record->ai_score));
        }
        
        if (!empty($ai_record->ai_feedback)) {
            $output .= html_writer::tag('div', 
                html_writer::tag('strong', get_string('ai_feedback', 'assignsubmission_ai_check')) . 
                html_writer::tag('p', format_text($ai_record->ai_feedback)));
        }

        return $output;
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
} 