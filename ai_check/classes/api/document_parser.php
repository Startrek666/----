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
 * Document parser API client for AI Check assignment submission plugin.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ai_check\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Document parser API client class.
 */
class document_parser {

    /** @var string API base URL */
    private $api_base_url;

    /** @var string API key */
    private $api_key;

    /** @var int API timeout in seconds */
    private $timeout;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_base_url = get_config('assignsubmission_ai_check', 'document_parser_url');
        $this->api_key = get_config('assignsubmission_ai_check', 'document_parser_key');
        $this->timeout = get_config('assignsubmission_ai_check', 'api_timeout') ?: 60;
    }

    /**
     * Submit a document for parsing.
     *
     * @param string $file_path Path to the document file
     * @return array Response with task_id or error
     */
    public function submit_document($file_path) {
        $url = rtrim($this->api_base_url, '/') . '/queue-task';
        
        $headers = array(
            'X-API-Key: ' . $this->api_key
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => array(
                'file' => new \CURLFile($file_path)
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return array(
                'success' => false,
                'error' => 'CURL Error: ' . $error
            );
        }

        if ($http_code !== 202) {
            return array(
                'success' => false,
                'error' => 'HTTP Error: ' . $http_code,
                'response' => $response
            );
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response: ' . $response
            );
        }

        return array(
            'success' => true,
            'task_id' => $data['task_id'],
            'message' => $data['message'] ?? ''
        );
    }

    /**
     * Get the result of a parsing task.
     *
     * @param string $task_id Task ID returned from submit_document
     * @return array Response with text or status
     */
    public function get_result($task_id) {
        $url = rtrim($this->api_base_url, '/') . '/get-result/' . urlencode($task_id);
        
        $headers = array(
            'X-API-Key: ' . $this->api_key
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return array(
                'success' => false,
                'error' => 'CURL Error: ' . $error
            );
        }

        if ($http_code !== 200 && $http_code !== 202) {
            return array(
                'success' => false,
                'error' => 'HTTP Error: ' . $http_code,
                'response' => $response
            );
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response: ' . $response
            );
        }

        if ($http_code === 202) {
            // Task still pending
            return array(
                'success' => true,
                'status' => 'pending',
                'task_id' => $data['task_id']
            );
        }

        // Task completed
        return array(
            'success' => true,
            'status' => 'completed',
            'task_id' => $data['task_id'],
            'text' => $data['text'] ?? ''
        );
    }

    /**
     * Poll the API until the task is completed or timeout.
     *
     * @param string $task_id Task ID
     * @param int $max_attempts Maximum number of polling attempts
     * @param int $sleep_seconds Seconds to sleep between attempts
     * @return array Final result
     */
    public function poll_until_complete($task_id, $max_attempts = 30, $sleep_seconds = 5) {
        for ($i = 0; $i < $max_attempts; $i++) {
            $result = $this->get_result($task_id);
            
            if (!$result['success']) {
                return $result;
            }

            if ($result['status'] === 'completed') {
                return $result;
            }

            if ($i < $max_attempts - 1) {
                sleep($sleep_seconds);
            }
        }

        return array(
            'success' => false,
            'error' => 'Timeout: Task did not complete within ' . ($max_attempts * $sleep_seconds) . ' seconds'
        );
    }

    /**
     * Extract text from a Moodle stored file.
     *
     * @param \stored_file $file Moodle stored file object
     * @return array Response with text or error
     */
    public function extract_text_from_file(\stored_file $file) {
        // Create a temporary file
        $temp_path = tempnam(sys_get_temp_dir(), 'ai_check_');
        if (!$temp_path) {
            return array(
                'success' => false,
                'error' => 'Cannot create temporary file'
            );
        }

        try {
            // Copy file content to temporary file
            $file_content = $file->get_content();
            if (file_put_contents($temp_path, $file_content) === false) {
                return array(
                    'success' => false,
                    'error' => 'Cannot write to temporary file'
                );
            }

            // Submit the document
            $submit_result = $this->submit_document($temp_path);
            
            if (!$submit_result['success']) {
                return $submit_result;
            }

            // Return task ID for async processing
            return array(
                'success' => true,
                'task_id' => $submit_result['task_id'],
                'async' => true
            );

        } finally {
            // Clean up temporary file
            if (file_exists($temp_path)) {
                unlink($temp_path);
            }
        }
    }
} 