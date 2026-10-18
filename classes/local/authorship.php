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

namespace mod_classprogress\local;

/**
 * Who authored what in plan activities, and the erasure of that authorship (REQ-PRV-002 to 004).
 *
 * A plan activity is its course's shared record for the inspection, so erasing a user never deletes its content:
 * every user id that names them as an author (who approved the plan, who imported its text, who made each change in the
 * history, and the real user behind a "log in as") is replaced by DELETED_USER, shown as a deleted user. Entries
 * themselves hold no user id: their history records who wrote each version. The activity's data is fully deleted
 * only with the activity or its course.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class authorship {
    /** @var int The user id that replaces an erased author. */
    public const DELETED_USER = 0;

    /**
     * The plan ids where a user is named as an author.
     *
     * @param int $userid The user.
     * @return int[] Plan ids.
     */
    public static function plans_of(int $userid): array {
        global $DB;

        $sql = "SELECT id FROM {classprogress} WHERE approvedby = :approver
                 UNION
                SELECT planid FROM {classprogress_import} WHERE userid = :importer
                 UNION
                SELECT planid FROM {classprogress_entry_hist} WHERE userid = :author OR realuserid = :realuser";
        $params = ['approver' => $userid, 'importer' => $userid, 'author' => $userid, 'realuser' => $userid];
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * The users named as authors in a plan.
     *
     * @param int $planid Plan id.
     * @return int[] User ids, without DELETED_USER.
     */
    public static function users_of(int $planid): array {
        global $DB;

        $sql = "SELECT approvedby FROM {classprogress} WHERE id = :plan1
                 UNION
                SELECT userid FROM {classprogress_import} WHERE planid = :plan2
                 UNION
                SELECT userid FROM {classprogress_entry_hist} WHERE planid = :plan3
                 UNION
                SELECT realuserid FROM {classprogress_entry_hist} WHERE planid = :plan4 AND realuserid IS NOT NULL";
        $params = ['plan1' => $planid, 'plan2' => $planid, 'plan3' => $planid, 'plan4' => $planid];
        $ids = array_map('intval', $DB->get_fieldset_sql($sql, $params));
        return array_values(array_diff(array_unique($ids), [self::DELETED_USER]));
    }

    /**
     * Replaces users' ids by DELETED_USER wherever they are named as authors, in one transaction; content stays.
     *
     * @param int[] $userids The users to erase.
     * @param int[]|null $planids Only in these plans; null for every plan.
     */
    public static function erase(array $userids, ?array $planids = null): void {
        global $DB;

        $userids = array_values(array_diff(array_map('intval', $userids), [self::DELETED_USER]));
        if (!$userids || $planids === []) {
            return;
        }
        $columns = [
            ['classprogress', 'approvedby', 'id'],
            ['classprogress_import', 'userid', 'planid'],
            ['classprogress_entry_hist', 'userid', 'planid'],
            ['classprogress_entry_hist', 'realuserid', 'planid'],
        ];
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($columns as [$table, $field, $planfield]) {
                self::replace($table, $field, $userids, $planids, $planfield);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Rolls back and throws the exception again.
            $transaction->rollback($e);
        }
    }

    /**
     * Replaces user ids in one column.
     *
     * @param string $table Table.
     * @param string $field Column holding a user id.
     * @param int[] $userids The users to erase.
     * @param int[]|null $planids Only in these plans; null for every plan.
     * @param string $planfield Column holding the plan id.
     */
    private static function replace(string $table, string $field, array $userids, ?array $planids, string $planfield): void {
        global $DB;

        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $where = "{$field} {$usersql}";
        if ($planids !== null) {
            [$plansql, $planparams] = $DB->get_in_or_equal($planids, SQL_PARAMS_NAMED, 'plan');
            $where .= " AND {$planfield} {$plansql}";
            $params += $planparams;
        }
        $DB->set_field_select($table, $field, self::DELETED_USER, $where, $params);
    }
}
