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
 * Version information for the AI Check assignment submission plugin.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->component = 'assignsubmission_ai_check';
$plugin->version = 2025010106;  // Fix compatibility for is_submission_plugin_enabled absence
$plugin->requires = 2022112800; // Moodle 4.1+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v1.0.6-alpha';

$plugin->dependencies = array(
    'mod_assign' => 2022112800,
    // 'local_ai_manager' => 2024042200, // Soft dependency - checked at runtime
); 