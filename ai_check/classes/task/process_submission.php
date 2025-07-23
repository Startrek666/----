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
 * Adhoc task to process AI checking of assignment submissions.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ai_check\task;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_ai_check\api\document_parser;

/**
 * Adhoc task to process AI checking of assignment submissions.
 */
class process_submission extends \core\task\adhoc_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:process_submission', 'assignsubmission_ai_check');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $submission_id = $data->submission_id;
        $file_id = $data->file_id;
        $assignment_id = $data->assignment_id;

        try {
            // Get the submission record
            $submission = $DB->get_record('assign_submission', array('id' => $submission_id));
            if (!$submission) {
                throw new \Exception('Submission not found: ' . $submission_id);
            }

            // Get AI check record
            $ai_record = $DB->get_record('assignsubmission_ai_check_grades', 
                array('submission_id' => $submission_id), '*', MUST_EXIST);

            // Update status to processing
            $ai_record->status = 'processing';
            $ai_record->timemodified = time();
            $DB->update_record('assignsubmission_ai_check_grades', $ai_record);

            // Get the file
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($file_id);
            if (!$file) {
                throw new \Exception('File not found: ' . $file_id);
            }

            // Step 1: Extract text from document
            $parser = new document_parser();
            $extract_result = $parser->extract_text_from_file($file);

            if (!$extract_result['success']) {
                throw new \Exception('Document parsing failed: ' . $extract_result['error']);
            }

            // Save task ID for tracking
            $ai_record->task_id = $extract_result['task_id'];
            $DB->update_record('assignsubmission_ai_check_grades', $ai_record);

            // Step 2: Poll for document parsing result
            $parse_result = $parser->poll_until_complete($extract_result['task_id']);

            if (!$parse_result['success']) {
                throw new \Exception('Document parsing timeout or failed: ' . $parse_result['error']);
            }

            $extracted_text = $parse_result['text'];
            $ai_record->extracted_text = $extracted_text;
            $DB->update_record('assignsubmission_ai_check_grades', $ai_record);

            // Step 3: Get assignment AI settings
            $assignment = $DB->get_record('assign', array('id' => $assignment_id), '*', MUST_EXIST);
            $ai_settings = $this->get_ai_settings($assignment_id);

            // Step 4: Call AI for grading
            $ai_result = $this->call_ai_grading($extracted_text, $ai_settings, $assignment->grade);

            if (!$ai_result['success']) {
                throw new \Exception('AI grading failed: ' . $ai_result['error']);
            }

            // Step 5: Update records with AI results
            $ai_record->ai_score = $ai_result['score'];
            $ai_record->ai_feedback = $ai_result['feedback'];
            $ai_record->status = 'completed';
            $ai_record->timemodified = time();
            $DB->update_record('assignsubmission_ai_check_grades', $ai_record);

            // Step 6: Update assignment grade if configured
            $this->update_assignment_grade($submission, $ai_result, $ai_settings, $assignment);

        } catch (\Exception $e) {
            // Update status to failed
            $ai_record = $DB->get_record('assignsubmission_ai_check_grades', 
                array('submission_id' => $submission_id));
            
            if ($ai_record) {
                $ai_record->status = 'failed';
                $ai_record->error_message = $e->getMessage();
                $ai_record->processing_attempts++;
                $ai_record->timemodified = time();
                $DB->update_record('assignsubmission_ai_check_grades', $ai_record);
            }

            mtrace('AI Check processing failed for submission ' . $submission_id . ': ' . $e->getMessage());
            
            // Re-queue if max attempts not reached
            $max_attempts = get_config('assignsubmission_ai_check', 'max_retry_attempts') ?: 3;
            if ($ai_record && $ai_record->processing_attempts < $max_attempts) {
                // Create a new task to retry later
                $retry_task = new self();
                $retry_task->set_custom_data($data);
                $retry_task->set_next_run_time(time() + (60 * $ai_record->processing_attempts)); // Exponential backoff
                \core\task\manager::queue_adhoc_task($retry_task);
            }
        }
    }

    /**
     * Get AI settings for the assignment.
     *
     * @param int $assignment_id
     * @return array
     */
    private function get_ai_settings($assignment_id) {
        global $DB;

        // Get AI config from the plugin configuration
        $ai_config_record = $DB->get_record('assign_plugin_config', array(
            'assignment' => $assignment_id,
            'plugin' => 'ai_check',
            'subtype' => 'assignsubmission',
            'name' => 'ai_config'
        ));

        if (!$ai_config_record) {
            // Fallback: try to get individual settings
            $settings = array();
            $config_names = array('standard_answer', 'grading_rubric', 'grading_mode');
            
            foreach ($config_names as $name) {
                $config = $DB->get_record('assign_plugin_config', array(
                    'assignment' => $assignment_id,
                    'plugin' => 'ai_check',
                    'subtype' => 'assignsubmission',
                    'name' => $name
                ));
                
                if ($config) {
                    $settings[$name] = $config->value;
                }
            }
            
            if (empty($settings)) {
                throw new \Exception('AI check configuration not found for assignment: ' . $assignment_id);
            }
            
            return $settings;
        }

        // Unserialize the config data
        $settings = unserialize($ai_config_record->value);
        if ($settings === false) {
            throw new \Exception('Invalid AI check configuration for assignment: ' . $assignment_id);
        }

        return $settings;
    }

    /**
     * Call AI manager for grading.
     *
     * @param string $text Extracted text
     * @param array $settings AI settings
     * @param float $max_score Maximum possible score
     * @return array
     */
    private function call_ai_grading($text, $settings, $max_score) {
        try {
            // Build the prompt
            $prompt = $this->build_ai_prompt($text, $settings, $max_score);

            // Call AI manager
            if (class_exists('\local_ai_manager\manager')) {
                $manager = new \local_ai_manager\manager('feedback');
                $response = $manager->perform_request($prompt, 'assignsubmission_ai_check', \context_system::instance()->id);
                
                if ($response->get_code() !== 200) {
                    throw new \Exception('AI Manager error: ' . $response->get_errormessage());
                }
                
                $ai_response = $response->get_content();
            } else {
                throw new \Exception('AI Manager not available');
            }

            // Parse AI response
            return $this->parse_ai_response($ai_response);

        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Build AI prompt for grading.
     *
     * @param string $text Student submission text
     * @param array $settings AI settings
     * @param float $max_score Maximum score
     * @return string
     */
    private function build_ai_prompt($text, $settings, $max_score) {
        $standard_answer = $settings['standard_answer'] ?? '';
        $grading_rubric = $settings['grading_rubric'] ?? '';

        $prompt = "你是一名专业的、客观的学科教师。你的任务是根据提供的"评分标准"和"参考答案"，为一份学生提交的作业打分并提供评语。\n";
        $prompt .= "请严格按照以下JSON格式输出，不要添加任何额外的解释性文字。\n\n";
        
        $prompt .= "评分标准如下：\n";
        $prompt .= $grading_rubric . "\n";
        $prompt .= "---\n";
        
        $prompt .= "参考答案/关键要点如下：\n";
        $prompt .= $standard_answer . "\n";
        $prompt .= "---\n\n";
        
        $prompt .= "以下是学生提交的作业内容：\n";
        $prompt .= "---\n";
        $prompt .= $text . "\n";
        $prompt .= "---\n\n";
        
        $prompt .= "请根据以上信息，完成以下评分任务。分数必须是0到{$max_score}之间的整数\n\n";
        
        $prompt .= "输出格式：\n";
        $prompt .= "{\n";
        $prompt .= '  "score": <分数>,' . "\n";
        $prompt .= '  "general_feedback": "<对作业的评价和给分理由，1-3句话>"' . "\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Parse AI response JSON.
     *
     * @param string $response AI response
     * @return array
     */
    private function parse_ai_response($response) {
        // Extract JSON from response (in case there's extra text)
        preg_match('/\{[^{}]*\}/', $response, $matches);
        
        if (empty($matches)) {
            return array(
                'success' => false,
                'error' => 'No valid JSON found in AI response'
            );
        }

        $json_str = $matches[0];
        $data = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON in AI response: ' . json_last_error_msg()
            );
        }

        if (!isset($data['score']) || !isset($data['general_feedback'])) {
            return array(
                'success' => false,
                'error' => 'Missing required fields in AI response'
            );
        }

        return array(
            'success' => true,
            'score' => floatval($data['score']),
            'feedback' => trim($data['general_feedback'])
        );
    }

    /**
     * Update assignment grade based on AI result.
     *
     * @param \stdClass $submission
     * @param array $ai_result
     * @param array $settings
     * @param \stdClass $assignment
     */
    private function update_assignment_grade($submission, $ai_result, $settings, $assignment) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $grading_mode = $settings['grading_mode'] ?? 'draft';
        
        // Convert score to grade
        $grade = $ai_result['score'];
        
        // Prepare grade update data
        $grade_item = array(
            'itemname' => $assignment->name,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assignment->id,
            'courseid' => $assignment->course
        );

        $grades = array(
            $submission->userid => array(
                'userid' => $submission->userid,
                'rawgrade' => $grade,
                'feedback' => $ai_result['feedback'],
                'feedbackformat' => FORMAT_HTML
            )
        );

        // Set grade as draft if configured
        if ($grading_mode === 'draft') {
            $grades[$submission->userid]['feedback'] .= ' <em>(AI生成，待教师审核)</em>';
            // In Moodle, we can set a flag to indicate this is a draft grade
            // This would require additional implementation in the assignment module
        }

        // Update grade
        grade_update('mod/assign', $assignment->course, 'mod', 'assign', $assignment->id, 0, $grades, $grade_item);
    }
} 