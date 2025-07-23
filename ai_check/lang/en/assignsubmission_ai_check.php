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
 * Strings for component 'assignsubmission_ai_check', language 'en'
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'AI智能批改';
$string['pluginname_help'] = '启用AI智能批改功能，自动分析和评分学生提交的作业文档。';

// Settings
$string['enabled'] = '启用AI自动批改';
$string['enabled_help'] = '勾选此项将启用AI自动批改功能。学生提交的文档将被自动分析和评分。';
$string['standard_answer'] = '参考答案/关键要点';
$string['standard_answer_help'] = '输入作业的参考答案或关键要点，AI将根据此内容评估学生答案。';
$string['grading_rubric'] = '评分标准/建议';
$string['grading_rubric_help'] = '输入详细的评分标准和建议，AI将根据此标准给出分数和评语。';
$string['grading_mode'] = '评分模式';
$string['grading_mode_help'] = '选择AI评分后的处理方式：保存为草稿需要教师审核，直接发布成绩会立即发布给学生。';
$string['grading_mode_draft'] = '保存为草稿，待教师审核';
$string['grading_mode_publish'] = '直接发布成绩';

// File restrictions
$string['file_restrictions'] = '文件限制说明';
$string['file_restrictions_desc'] = '<strong>重要提示：</strong>启用AI批改后，学生只能上传1个文件，且仅支持PDF和DOCX格式。';

// Error messages
$string['error'] = '错误';
$string['ai_manager_required'] = 'AI智能批改功能需要安装 local_ai_manager 插件。请联系系统管理员。';

// Processing status
$string['no_ai_processing'] = '暂无AI批改记录';
$string['ai_processing_pending'] = 'AI批改排队中...';
$string['ai_processing_inprogress'] = 'AI正在批改中...';
$string['ai_processing_failed'] = 'AI批改失败，请联系教师';

// Results display
$string['ai_grading_result'] = 'AI批改结果';
$string['ai_score'] = 'AI评分：{$a}';
$string['ai_feedback'] = 'AI评语：';

// API settings
$string['api_settings'] = 'API设置';
$string['document_parser_url'] = '文档解析API地址';
$string['document_parser_url_desc'] = '用于解析PDF和DOCX文档的API地址';
$string['document_parser_key'] = 'API密钥';
$string['document_parser_key_desc'] = '文档解析API的访问密钥';
$string['api_timeout'] = 'API超时时间（秒）';
$string['api_timeout_desc'] = 'API请求的超时时间，默认60秒';

// Privacy
$string['privacy:metadata:assignsubmission_ai_check_grades'] = 'AI批改结果';
$string['privacy:metadata:assignsubmission_ai_check_grades:submission_id'] = '提交ID';
$string['privacy:metadata:assignsubmission_ai_check_grades:extracted_text'] = '从文档中提取的文本内容';
$string['privacy:metadata:assignsubmission_ai_check_grades:ai_score'] = 'AI给出的分数';
$string['privacy:metadata:assignsubmission_ai_check_grades:ai_feedback'] = 'AI生成的评语';
$string['privacy:metadata:assignsubmission_ai_check_grades:timecreated'] = '创建时间';

// Capabilities
$string['ai_check:use'] = '使用AI批改功能';
$string['ai_check:configure'] = '配置AI批改参数';
$string['ai_check:viewairesults'] = '查看AI批改结果';

// Task names
$string['task:cleanup_old_records'] = '清理旧的AI批改记录';
$string['task:retry_failed_submissions'] = '重试失败的AI批改任务';
$string['task:process_submission'] = '处理学生提交的作业'; 