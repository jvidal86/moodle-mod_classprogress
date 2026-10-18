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

namespace mod_classprogress\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_classprogress\local\authorship;

/**
 * Privacy provider: what a teacher authored in plan activities, in each activity's module context (REQ-PRV-002 to 004).
 *
 * A plan activity is its course's shared record: a user's data there is their authorship, who approved the plan,
 * who imported its text and who wrote each version of an entry (and the real user behind a "log in as"). The export
 * holds the approvals, imports, entries and history rows the user authored; erasure replaces their user id by
 * authorship::DELETED_USER and keeps the content, so the record for the inspection stays complete.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes every table and field that holds personal data, or text that may name students.
     *
     * @param collection $collection The collection to add to.
     * @return collection The same collection.
     */
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $tables = [
            'classprogress' => ['approvedby', 'timeapproved'],
            'classprogress_import' => ['userid', 'source', 'filename', 'contenthash', 'daycount', 'timecreated'],
            'classprogress_entry' => ['sessiondate', 'status', 'content', 'reason', 'reasonnote', 'measure', 'carry',
                'writtenon', 'timecreated', 'timemodified'],
            'classprogress_entry_hist' => ['userid', 'realuserid', 'action', 'snapshot', 'timecreated'],
        ];
        foreach ($tables as $table => $fields) {
            $strings = [];
            foreach ($fields as $field) {
                $strings[$field] = "privacy:metadata:{$table}:{$field}";
            }
            $collection->add_database_table($table, $strings, "privacy:metadata:{$table}");
        }
        return $collection;
    }

    /**
     * Finds the module contexts of the plan activities where the user is named as an author.
     *
     * @param int $userid The user.
     * @return contextlist The contexts.
     */
    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $planids = authorship::plans_of($userid);
        if (!$planids) {
            return $contextlist;
        }
        [$insql, $params] = $DB->get_in_or_equal($planids, SQL_PARAMS_NAMED);
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.instance {$insql}";
        $contextlist->add_from_sql($sql, $params + ['contextlevel' => CONTEXT_MODULE, 'modname' => 'classprogress']);
        return $contextlist;
    }

    /**
     * Finds the users named as authors in a plan activity's module context.
     *
     * @param userlist $userlist The list to fill, for one context.
     */
    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $planid = self::plan_of($userlist->get_context());
        if ($planid !== null) {
            $userlist->add_users(authorship::users_of($planid));
        }
    }

    /**
     * Exports what the user authored in each approved plan activity context.
     *
     * @param approved_contextlist $contextlist The approved contexts of one user.
     */
    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $planid = self::plan_of($context);
            if ($planid !== null) {
                self::export_plan($context, $planid, $userid);
            }
        }
    }

    /**
     * Erases every author's name in a plan activity context; the content stays.
     *
     * @param \context $context The context.
     */
    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $planid = self::plan_of($context);
        if ($planid !== null) {
            authorship::erase(authorship::users_of($planid), [$planid]);
        }
    }

    /**
     * Erases the user's name in each approved plan activity context; the content stays.
     *
     * @param approved_contextlist $contextlist The approved contexts of one user.
     */
    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $planids = array_filter(array_map(fn(\context $context): ?int => self::plan_of($context), $contextlist->get_contexts()));
        if ($planids) {
            authorship::erase([(int) $contextlist->get_user()->id], array_values($planids));
        }
    }

    /**
     * Erases the names of the approved users in one plan activity context; the content stays.
     *
     * @param approved_userlist $userlist The approved users of one context.
     */
    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $planid = self::plan_of($userlist->get_context());
        if ($planid !== null) {
            authorship::erase($userlist->get_userids(), [$planid]);
        }
    }

    /**
     * The plan of a context, when it is the module context of a plan activity.
     *
     * @param \context $context The context.
     * @return int|null The plan id; null for any other context.
     */
    private static function plan_of(\context $context): ?int {
        if (!$context instanceof \context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('classprogress', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }

    /**
     * Writes what a user authored in one plan: the approval, the imports, the entries whose first version they wrote,
     * and every history row they made or made while another user was logged in as them.
     *
     * @param \context $context The activity's module context.
     * @param int $planid Plan id.
     * @param int $userid The user.
     */
    private static function export_plan(\context $context, int $planid, int $userid): void {
        global $DB;

        $writer = writer::with_context($context);
        $plan = $DB->get_record('classprogress', ['id' => $planid], 'id, name, approvedby, timeapproved', MUST_EXIST);
        if ((int) $plan->approvedby === $userid) {
            $writer->export_data([get_string('privacy:path:approval', 'mod_classprogress')], (object) [
                'name' => $plan->name,
                'timeapproved' => transform::datetime($plan->timeapproved),
            ]);
        }

        $imports = [];
        foreach ($DB->get_records('classprogress_import', ['planid' => $planid, 'userid' => $userid], 'id') as $row) {
            $imports[] = (object) [
                'source' => $row->source,
                'filename' => $row->filename,
                'contenthash' => $row->contenthash,
                'daycount' => (int) $row->daycount,
                'timecreated' => transform::datetime($row->timecreated),
            ];
        }
        if ($imports) {
            $writer->export_data([get_string('privacy:path:imports', 'mod_classprogress')], (object) ['imports' => $imports]);
        }

        $select = 'planid = :planid AND (userid = :userid OR realuserid = :realuserid)';
        $rows = $DB->get_records_select(
            'classprogress_entry_hist',
            $select,
            ['planid' => $planid, 'userid' => $userid, 'realuserid' => $userid],
            'timecreated, id'
        );
        $history = [];
        $created = [];
        foreach ($rows as $row) {
            $history[] = (object) [
                'entryid' => (int) $row->entryid,
                'action' => $row->action,
                'userid' => transform::user((int) $row->userid),
                'realuserid' => $row->realuserid === null ? null : transform::user((int) $row->realuserid),
                'snapshot' => json_decode($row->snapshot) ?? $row->snapshot,
                'timecreated' => transform::datetime($row->timecreated),
            ];
            if ($row->action === 'create') {
                $created[] = (int) $row->entryid;
            }
        }
        if ($history) {
            $writer->export_data([get_string('privacy:path:history', 'mod_classprogress')], (object) ['history' => $history]);
        }

        $entries = [];
        foreach ($created ? $DB->get_records_list('classprogress_entry', 'id', $created, 'sessiondate, id') : [] as $row) {
            $entries[] = (object) [
                'id' => (int) $row->id,
                'sessiondate' => $row->sessiondate,
                'status' => $row->status,
                'content' => $row->content,
                'reason' => $row->reason,
                'reasonnote' => $row->reasonnote,
                'measure' => $row->measure,
                'carry' => transform::yesno($row->carry),
                'writtenon' => $row->writtenon,
                'timecreated' => transform::datetime($row->timecreated),
                'timemodified' => transform::datetime($row->timemodified),
            ];
        }
        if ($entries) {
            $writer->export_data([get_string('privacy:path:entries', 'mod_classprogress')], (object) ['entries' => $entries]);
        }
    }
}
