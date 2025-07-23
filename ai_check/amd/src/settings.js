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
 * JavaScript for AI Check assignment submission plugin settings.
 *
 * @module     assignsubmission_ai_check/settings
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    'use strict';

    /**
     * Initialize the settings page functionality.
     */
    function init() {
        try {
            var aiCheckEnabled = $('#id_assignsubmission_ai_check_enabled');

            // Only proceed if the element exists
            if (aiCheckEnabled.length === 0) {
                return;
            }

            var fileEnabled = $('#id_assignsubmission_file_enabled');
            var maxFiles = $('#id_assignsubmission_file_maxfiles');
            var fileTypes = $('#id_assignsubmission_file_filetypes');

            // Function to enforce file restrictions when AI check is enabled
            function enforceFileRestrictions() {
                try {
                    if (aiCheckEnabled.val() == '1') {
                        // Force file submission to be enabled
                        if (fileEnabled.length) {
                            fileEnabled.val('1').prop('disabled', true);
                        }

                        // Force max files to 1
                        if (maxFiles.length) {
                            maxFiles.val('1').prop('disabled', true);
                        }

                        // Force file types to pdf,docx
                        if (fileTypes.length) {
                            fileTypes.val('pdf,docx').prop('disabled', true);
                        }

                        // Add visual indicators
                        fileEnabled.closest('.form-group').addClass('ai-check-forced');
                        maxFiles.closest('.form-group').addClass('ai-check-forced');
                        fileTypes.closest('.form-group').addClass('ai-check-forced');

                        // Add help text if not already present
                        if (!$('.ai-check-notice').length && fileTypes.length) {
                            $('<div class="alert alert-info ai-check-notice mt-2">' +
                                '<strong>注意：</strong>启用AI批改时，文件提交设置将被自动配置为：最多1个文件，仅支持PDF和DOCX格式。' +
                                '</div>').insertAfter(fileTypes.closest('.form-group'));
                        }
                    } else {
                        // Re-enable fields when AI check is disabled
                        fileEnabled.prop('disabled', false);
                        maxFiles.prop('disabled', false);
                        fileTypes.prop('disabled', false);

                        // Remove visual indicators
                        fileEnabled.closest('.form-group').removeClass('ai-check-forced');
                        maxFiles.closest('.form-group').removeClass('ai-check-forced');
                        fileTypes.closest('.form-group').removeClass('ai-check-forced');

                        // Remove help text
                        $('.ai-check-notice').remove();
                    }
                } catch (e) {
                    console.log('AI Check: Error in enforceFileRestrictions', e);
                }
            }

            // Apply restrictions on page load
            enforceFileRestrictions();

            // Apply restrictions when AI check setting changes
            aiCheckEnabled.on('change', function () {
                enforceFileRestrictions();
            });

            // Add CSS for visual indicators
            if (!$('#ai-check-custom-css').length) {
                $('<style id="ai-check-custom-css">' +
                    '.ai-check-forced { opacity: 0.7; }' +
                    '.ai-check-forced input, .ai-check-forced select { background-color: #f8f9fa !important; }' +
                    '</style>').appendTo('head');
            }

            // Validation on form submit
            $(document).on('submit', 'form', function (e) {
                try {
                    if (aiCheckEnabled.val() == '1') {
                        var standardAnswer = $('#id_assignsubmission_ai_check_standard_answer').val();
                        var gradingRubric = $('#id_assignsubmission_ai_check_grading_rubric').val();

                        if (standardAnswer && standardAnswer.trim() === '') {
                            e.preventDefault();
                            alert('错误：启用AI批改时，参考答案/关键要点不能为空。');
                            return false;
                        }

                        if (gradingRubric && gradingRubric.trim() === '') {
                            e.preventDefault();
                            alert('错误：启用AI批改时，评分标准/建议不能为空。');
                            return false;
                        }
                    }
                } catch (e) {
                    console.log('AI Check: Error in form validation', e);
                }
            });

        } catch (e) {
            console.log('AI Check: Error in init', e);
        }
    }

    return {
        init: init
    };
}); 