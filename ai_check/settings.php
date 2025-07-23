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
 * Global settings for the AI Check assignment submission plugin.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading('assignsubmission_ai_check_settings',
        get_string('api_settings', 'assignsubmission_ai_check'),
        ''));

    // Document parser API URL
    $settings->add(new admin_setting_configtext('assignsubmission_ai_check/document_parser_url',
        get_string('document_parser_url', 'assignsubmission_ai_check'),
        get_string('document_parser_url_desc', 'assignsubmission_ai_check'),
        'https://api-utils.lemomate.com/docparser/',
        PARAM_URL));

    // API Key
    $settings->add(new admin_setting_configtext('assignsubmission_ai_check/document_parser_key',
        get_string('document_parser_key', 'assignsubmission_ai_check'),
        get_string('document_parser_key_desc', 'assignsubmission_ai_check'),
        'L5kGzmjwqXbk0ViD@',
        PARAM_TEXT));

    // API Timeout
    $settings->add(new admin_setting_configtext('assignsubmission_ai_check/api_timeout',
        get_string('api_timeout', 'assignsubmission_ai_check'),
        get_string('api_timeout_desc', 'assignsubmission_ai_check'),
        60,
        PARAM_INT));

    // Maximum retry attempts
    $settings->add(new admin_setting_configtext('assignsubmission_ai_check/max_retry_attempts',
        'Maximum retry attempts',
        'Maximum number of retry attempts for failed processing',
        3,
        PARAM_INT));

    // Cleanup old records after days
    $settings->add(new admin_setting_configtext('assignsubmission_ai_check/cleanup_days',
        'Cleanup old records (days)',
        'Delete AI processing records older than this many days (0 = never delete)',
        90,
        PARAM_INT));
} 