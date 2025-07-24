<?php
/**
 * Manual trigger API endpoint for AI processing
 * 手动触发AI处理的API端点
 */

// --- Enhanced Error Logging ---
define('AI_CHECK_LOG_FILE', __DIR__ . '/debug_log.txt');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', AI_CHECK_LOG_FILE);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE])) {
        // If we have a fatal error, we ensure a clean JSON response is sent.
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Log the fatal error
        $log_message = date('Y-m-d H:i:s') . " - [FATAL] " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'] . "\n";
        file_put_contents(AI_CHECK_LOG_FILE, $log_message, FILE_APPEND);
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500); // Internal Server Error
            echo json_encode([
                'success' => false,
                'error' => 'A fatal server error occurred. Please check the debug_log.txt file in the plugin directory for details.'
            ]);
        }
        exit();
    }
});
// --- End Enhanced Error Logging ---

// Prevent any output before JSON
ob_start();

try {
    // Try to load Moodle config
    $config_paths = [
        '/var/www/training.lemomate.com/config.php', // Use direct path from phpinfo
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php', 
        __DIR__ . '/../../../../config.php',
        dirname(__DIR__, 4) . '/config.php'
    ];

    $config_loaded = false;
    foreach ($config_paths as $config_path) {
        if (file_exists($config_path)) {
            require_once($config_path);
            $config_loaded = true;
            break;
        }
    }

    if (!$config_loaded) {
        throw new Exception('Moodle config not found');
    }

    // Manually include the assignment library, which defines the "assign" class.
    // 手动加载作业模块的核心库，"assign" 类就在这里面定义。
    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    // Clean any output that might have been generated from config.php
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json; charset=utf-8');

    // Check authentication in an AJAX-safe way
    if (!isloggedin() || isguestuser()) {
        throw new Exception('User not logged in.');
    }
    if (!has_capability('moodle/site:config', context_system::instance())) {
        throw new Exception('Insufficient permissions.');
    }
    if (!confirm_sesskey()) {
        throw new Exception('Invalid session key.');
    }

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    // Get submission ID
    $submission_id = required_param('submission_id', PARAM_INT);

    global $DB;

    // Get submission record
    $submission = $DB->get_record('assign_submission', ['id' => $submission_id]);
    if (!$submission) {
        throw new Exception('Submission not found');
    }

    // Get assignment record to find the course-module id (cmid)
    $assignment_record = $DB->get_record('assign', ['id' => $submission->assignment]);
    if (!$assignment_record) {
        throw new Exception('Assignment not found');
    }

    // Derive the course-module object to obtain cmid.
    $cm = get_coursemodule_from_instance('assign', $assignment_record->id, $assignment_record->course, false, MUST_EXIST);
    $cmid = $cm->id;

    // Create assignment object – 兼容不同 Moodle 版本。
    if (method_exists('assign', 'create')) {
        // Moodle 3.9+ 提供静态 create() 方法。
        $assignment = \assign::create($cmid);
    } else {
        // 回退到直接实例化构造函数。
        $context = \context_module::instance($cmid);
        $assignment = new \assign($cmid, $assignment_record, $context, $cm);
    }

    // 在旧版 Moodle 中 assign 类可能没有 is_submission_plugin_enabled()
    // 因此我们仅在方法存在时才调用它做额外检查。
    if (method_exists($assignment, 'is_submission_plugin_enabled')) {
        if (!$assignment->is_submission_plugin_enabled('ai_check')) {
            throw new Exception('AI Check plugin not enabled');
        }
    }

    $aicheckplugin = $assignment->get_submission_plugin_by_type('ai_check');
    if (!$aicheckplugin || !$aicheckplugin->is_enabled()) {
        throw new Exception('AI Check plugin instance not found');
    }

    // Check if AI auto-grading is enabled in settings
    $enabled = $aicheckplugin->get_config('enabled');
    if (!$enabled) {
        throw new Exception('AI auto-grading not enabled in settings');
    }

    // Check for files
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
        throw new Exception('No files found in submission');
    }

    $file = reset($files);

    // Create or update AI check record
    $aicheckrecord = $DB->get_record('assignsubmission_ai_check_grades', 
        ['submission_id' => $submission->id]);

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

    // Check if task class exists
    if (!class_exists('\assignsubmission_ai_check\task\process_submission')) {
        throw new Exception('Process submission task class not found');
    }

    // Create and queue task
    $task = new \assignsubmission_ai_check\task\process_submission();
    $task->set_custom_data([
        'submission_id' => $submission->id,
        'file_id' => $file->get_id(),
        'assignment_id' => $submission->assignment, // Use the assignment ID directly from submission record
    ]);

    \core\task\manager::queue_adhoc_task($task);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'AI processing task queued successfully',
        'submission_id' => $submission_id,
        'ai_record_id' => $aicheckrecord->id
    ]);

} catch (Exception $e) {
    // Clean any output that might have been generated
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    
    // Return error response
    // Log the caught exception
    $log_message = date('Y-m-d H:i:s') . " - [CAUGHT_EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents(AI_CHECK_LOG_FILE, $log_message, FILE_APPEND);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?> 