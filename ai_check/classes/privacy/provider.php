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
 * Privacy Subsystem implementation for assignsubmission_ai_check.
 *
 * @package    assignsubmission_ai_check
 * @copyright  2025 AI Check Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ai_check\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for assignsubmission_ai_check implementing null_provider.
 */
class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\subplugin_provider {

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return  string
     */
    public static function get_reason() : string {
        return 'privacy:metadata';
    }

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
            'assignsubmission_ai_check_grades',
            [
                'submission_id' => 'privacy:metadata:assignsubmission_ai_check_grades:submission_id',
                'extracted_text' => 'privacy:metadata:assignsubmission_ai_check_grades:extracted_text',
                'ai_score' => 'privacy:metadata:assignsubmission_ai_check_grades:ai_score',
                'ai_feedback' => 'privacy:metadata:assignsubmission_ai_check_grades:ai_feedback',
                'timecreated' => 'privacy:metadata:assignsubmission_ai_check_grades:timecreated',
            ],
            'privacy:metadata:assignsubmission_ai_check_grades'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                FROM {assignsubmission_ai_check_grades} acg
                JOIN {assign_submission} s ON acg.submission_id = s.id
                JOIN {assign} a ON s.assignment = a.id
                JOIN {course_modules} cm ON a.id = cm.instance
                JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                WHERE s.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT s.userid
                FROM {assignsubmission_ai_check_grades} acg
                JOIN {assign_submission} s ON acg.submission_id = s.id
                JOIN {assign} a ON s.assignment = a.id
                JOIN {course_modules} cm ON a.id = cm.instance
                JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                WHERE cm.id = :cmid";

        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $sql = "SELECT acg.*, s.assignment
                    FROM {assignsubmission_ai_check_grades} acg
                    JOIN {assign_submission} s ON acg.submission_id = s.id
                    JOIN {assign} a ON s.assignment = a.id
                    JOIN {course_modules} cm ON a.id = cm.instance
                    JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                    WHERE cm.id = :cmid AND s.userid = :userid";

            $params = ['cmid' => $context->instanceid, 'userid' => $user->id];
            $records = $DB->get_records_sql($sql, $params);

            if (!empty($records)) {
                $data = [];
                foreach ($records as $record) {
                    $data[] = [
                        'ai_score' => $record->ai_score,
                        'ai_feedback' => $record->ai_feedback,
                        'status' => $record->status,
                        'timecreated' => transform::datetime($record->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'assignsubmission_ai_check')], 
                    (object) $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $sql = "DELETE FROM {assignsubmission_ai_check_grades}
                WHERE submission_id IN (
                    SELECT s.id
                    FROM {assign_submission} s
                    JOIN {assign} a ON s.assignment = a.id
                    JOIN {course_modules} cm ON a.id = cm.instance
                    JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                    WHERE cm.id = :cmid
                )";

        $DB->execute($sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $sql = "DELETE FROM {assignsubmission_ai_check_grades}
                    WHERE submission_id IN (
                        SELECT s.id
                        FROM {assign_submission} s
                        JOIN {assign} a ON s.assignment = a.id
                        JOIN {course_modules} cm ON a.id = cm.instance
                        JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                        WHERE cm.id = :cmid AND s.userid = :userid
                    )";

            $DB->execute($sql, ['cmid' => $context->instanceid, 'userid' => $user->id]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        
        $sql = "DELETE FROM {assignsubmission_ai_check_grades}
                WHERE submission_id IN (
                    SELECT s.id
                    FROM {assign_submission} s
                    JOIN {assign} a ON s.assignment = a.id
                    JOIN {course_modules} cm ON a.id = cm.instance
                    JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                    WHERE cm.id = :cmid AND s.userid {$usersql}
                )";

        $params = array_merge($userparams, ['cmid' => $context->instanceid]);
        $DB->execute($sql, $params);
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     * @param   array                   $subcontext     The subcontext within the context to export this information to.
     */
    public static function export_user_data_in_contexts(approved_contextlist $contextlist, array $subcontext) {
        static::export_user_data($contextlist);
    }

    /**
     * Delete all user data for the specified users in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to delete information for.
     * @param   array                   $subcontext     The subcontext within the context to delete this information from.
     */
    public static function delete_user_data_in_contexts(approved_contextlist $contextlist, array $subcontext) {
        static::delete_data_for_user($contextlist);
    }
} 