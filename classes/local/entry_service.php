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

use local_classprogress\local\entry_data;
use local_classprogress\local\entry_rules;
use local_classprogress\local\schoolyear_service;
use local_classprogress\local\session_item;
use local_classprogress\local\session_list;
use local_classprogress\local\written_on;
use mod_classprogress\event\entry_created;
use mod_classprogress\event\entry_deleted;
use mod_classprogress\event\entry_updated;

/**
 * The entries of a plan activity: what was done in each session of an approved plan, unplanned sessions, their
 * history, and the log of a plan.
 *
 * Entries are shared by the teachers of the activity's course (access::plan()): reading needs the view capability and
 * writing the edit capability, checked live in the activity's module context. A missing id and an id the user may not
 * reach give the same exception, error_notfound, and a list of ids is refused as a whole when one of them is not
 * reachable (REQ-OWN-001 to 003, REQ-MOD-004).
 *
 * Writes are refused for a plan that is not approved (error_plannotapproved) and for data that breaks entry_rules
 * (entryissue_<code> with the first issue); entries are stored as entry_rules::normalise() gives them, text as typed. A
 * plan item has at most one entry. Update and delete take the entry's timemodified as the form saw it and refuse a newer
 * stored entry (error_changedelsewhere); each save makes timemodified later than the one it replaces. The written-on
 * day is the teacher's own day (their time zone) at creation and never changes. "Now" is Moodle's clock.
 *
 * Every write holds the lock of the plan's entries and runs in one delegated transaction with its history row: a full
 * JSON snapshot of the entry (after the change; before it, for a delete), the acting user, and the real user when
 * logged in as them; the history is what records who wrote each version. History rows are never changed and outlive
 * their entry. The entry's event is triggered after the commit, in the activity's module context. An unplanned session
 * lies within the school year that the plan copied at approval.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_service {
    /** @var string History action: the entry was created. */
    public const ACTION_CREATE = 'create';

    /** @var string History action: the entry was changed. */
    public const ACTION_UPDATE = 'update';

    /** @var string History action: the entry was deleted. */
    public const ACTION_DELETE = 'delete';

    /** @var string Entry table. */
    private const ENTRY = 'classprogress_entry';

    /** @var string Entry history table. */
    private const HIST = 'classprogress_entry_hist';

    /** @var string Plan item table. */
    private const PLANITEM = 'classprogress_item';

    /**
     * Checks an entry as create() or update() would, and saves nothing.
     *
     * @param int $userid The acting user.
     * @param int $planid Plan id.
     * @param int|null $itemid The plan item, one of the plan's; null for an unplanned session.
     * @param entry_data $data The entry as typed.
     * @return string[] entry_rules issue codes; empty when valid.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound.
     */
    public static function check(int $userid, int $planid, ?int $itemid, entry_data $data): array {
        $plan = access::plan($userid, $planid, access::EDIT);
        $item = $itemid === null ? null : self::own_item($userid, $itemid, $planid);
        return self::issues($userid, $plan, $item, $data);
    }

    /**
     * Logs a session: the entry of a plan item, or an unplanned session.
     *
     * @param int $userid The acting user.
     * @param int $planid Plan id.
     * @param int|null $itemid The plan item, one of the plan's; null for an unplanned session.
     * @param entry_data $data The entry as typed; its date is used only for an unplanned session.
     * @return int The new entry id.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_plannotapproved, entryissue_<code>, error_changedelsewhere when
     *     the item already has an entry, or locktimeout.
     */
    public static function create(int $userid, int $planid, ?int $itemid, entry_data $data): int {
        global $DB;

        access::plan($userid, $planid, access::EDIT);
        $id = self::locked($planid, function () use ($DB, $userid, $planid, $itemid, $data): int {
            $plan = access::plan($userid, $planid, access::EDIT);
            $item = $itemid === null ? null : self::own_item($userid, $itemid, $planid);
            self::require_approved($plan);
            self::require_valid(self::issues($userid, $plan, $item, $data));
            if ($item !== null && $DB->record_exists(self::ENTRY, ['planitemid' => $item->id])) {
                throw new \moodle_exception('error_changedelsewhere', 'mod_classprogress');
            }
            $now = self::now();
            $data = entry_rules::normalise($data);
            return self::in_transaction(function () use ($DB, $userid, $planid, $item, $data, $now): int {
                $id = (int) $DB->insert_record(self::ENTRY, (object) (self::fields($data) + [
                    'planid' => $planid,
                    'planitemid' => $item?->id,
                    'sessiondate' => $item === null ? $data->date : $item->sessiondate,
                    'contentformat' => FORMAT_MARKDOWN,
                    'writtenon' => self::day($userid, $now),
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]));
                self::record($userid, self::ACTION_CREATE, $DB->get_record(self::ENTRY, ['id' => $id], '*', MUST_EXIST), $now);
                return $id;
            });
        });

        self::trigger(entry_created::class, $userid, $id, $planid, $itemid);
        return $id;
    }

    /**
     * Changes an entry; its plan item, written-on day and creation time stay.
     *
     * @param int $userid The acting user.
     * @param int $id Entry id.
     * @param entry_data $data The entry as typed; its date is used only for an unplanned session.
     * @param int $timemodified The entry's timemodified when the form was opened.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_plannotapproved, error_changedelsewhere, entryissue_<code>, or
     *     locktimeout.
     */
    public static function update(int $userid, int $id, entry_data $data, int $timemodified): void {
        global $DB;

        $entry = self::own_entry($userid, $id, access::EDIT);
        self::locked((int) $entry->planid, function () use ($DB, $userid, $id, $data, $timemodified): void {
            $entry = self::own_entry($userid, $id, access::EDIT);
            $plan = access::plan($userid, (int) $entry->planid, access::EDIT);
            self::require_approved($plan);
            self::require_current($entry, $timemodified);
            $item = $entry->planitemid === null ? null : self::own_item($userid, (int) $entry->planitemid, (int) $plan->id);
            self::require_valid(self::issues($userid, $plan, $item, $data));
            $now = max(self::now(), (int) $entry->timemodified + 1);
            $data = entry_rules::normalise($data);
            self::in_transaction(function () use ($DB, $userid, $id, $item, $data, $now): void {
                $DB->update_record(self::ENTRY, (object) (self::fields($data) + [
                    'id' => $id,
                    'sessiondate' => $item === null ? $data->date : $item->sessiondate,
                    'timemodified' => $now,
                ]));
                self::record($userid, self::ACTION_UPDATE, $DB->get_record(self::ENTRY, ['id' => $id], '*', MUST_EXIST), $now);
            });
        });

        self::trigger(entry_updated::class, $userid, $id, (int) $entry->planid, self::item_id($entry));
    }

    /**
     * Deletes an entry; its history stays, with a last row holding the entry as it was.
     *
     * @param int $userid The acting user.
     * @param int $id Entry id.
     * @param int $timemodified The entry's timemodified when the deletion was asked for.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_plannotapproved, error_changedelsewhere, or locktimeout.
     */
    public static function delete(int $userid, int $id, int $timemodified): void {
        global $DB;

        $entry = self::own_entry($userid, $id, access::EDIT);
        self::locked((int) $entry->planid, function () use ($DB, $userid, $id, $timemodified): void {
            $entry = self::own_entry($userid, $id, access::EDIT);
            self::require_approved(access::plan($userid, (int) $entry->planid, access::EDIT));
            self::require_current($entry, $timemodified);
            self::in_transaction(function () use ($DB, $userid, $entry): void {
                self::record($userid, self::ACTION_DELETE, $entry, self::now());
                $DB->delete_records(self::ENTRY, ['id' => $entry->id]);
            });
        });

        self::trigger(entry_deleted::class, $userid, $id, (int) $entry->planid, self::item_id($entry));
    }

    /**
     * Returns one entry.
     *
     * @param int $userid The acting user.
     * @param int $id Entry id.
     * @return \stdClass The entry row.
     * @throws \moodle_exception error_notfound.
     */
    public static function get(int $userid, int $id): \stdClass {
        return self::own_entry($userid, $id, access::VIEW);
    }

    /**
     * Returns one plan item.
     *
     * @param int $userid The acting user.
     * @param int $itemid Plan item id.
     * @return \stdClass The plan item row.
     * @throws \moodle_exception error_notfound.
     */
    public static function item(int $userid, int $itemid): \stdClass {
        return self::own_item($userid, $itemid, null);
    }

    /**
     * Returns the entry of a plan item, if it has one.
     *
     * @param int $userid The acting user.
     * @param int $itemid Plan item id.
     * @return \stdClass|null The entry row; null when the item has none.
     * @throws \moodle_exception error_notfound.
     */
    public static function for_item(int $userid, int $itemid): ?\stdClass {
        global $DB;

        self::own_item($userid, $itemid, null);
        return $DB->get_record(self::ENTRY, ['planitemid' => $itemid]) ?: null;
    }

    /**
     * Reads the log of a plan: its items and entries, and for an approved plan the items sorted into pending,
     * upcoming and logged for the teacher's own day.
     *
     * @param int $userid The acting user.
     * @param int $planid Plan id.
     * @return plan_log The log.
     * @throws \moodle_exception error_notfound.
     */
    public static function log(int $userid, int $planid): plan_log {
        global $DB;

        $plan = access::plan($userid, $planid);
        $items = $DB->get_records(self::PLANITEM, ['planid' => $planid], 'sortorder, id');
        $entries = $unplanned = [];
        foreach ($DB->get_records(self::ENTRY, ['planid' => $planid], 'sessiondate, id') as $entry) {
            if ($entry->planitemid === null) {
                $unplanned[$entry->id] = $entry;
            } else {
                $entries[(int) $entry->planitemid] = $entry;
            }
        }
        $today = self::day($userid, self::now());
        $groups = session_list::classify($plan->status, self::session_items($items), self::entry_data($entries), $today);
        return new plan_log($plan, $today, $items, $entries, $unplanned, $groups);
    }

    /**
     * Counts the pending sessions of several plans: items dated today or earlier without an entry, in approved plans.
     *
     * @param int $userid The acting user.
     * @param int[] $planids Plan ids.
     * @return int[] Plan id => pending sessions; 0 for a draft.
     * @throws \moodle_exception error_notfound, for the whole list, when the user may not open any of the plans.
     */
    public static function pending_counts(int $userid, array $planids): array {
        global $DB;

        $ids = array_values(array_unique(array_map('intval', $planids)));
        if (!$ids) {
            return [];
        }
        $plans = [];
        foreach ($ids as $id) {
            $plans[$id] = access::plan($userid, $id);
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $items = $DB->get_records_select(self::PLANITEM, "planid {$in}", $params, 'sortorder, id');
        $entries = $DB->get_records_select(self::ENTRY, "planid {$in} AND planitemid IS NOT NULL", $params);
        $today = self::day($userid, self::now());
        $counts = [];
        foreach ($ids as $id) {
            $own = array_filter($items, fn(\stdClass $item): bool => (int) $item->planid === $id);
            $logged = [];
            foreach ($entries as $entry) {
                if ((int) $entry->planid === $id) {
                    $logged[(int) $entry->planitemid] = $entry;
                }
            }
            $groups = session_list::classify($plans[$id]->status, self::session_items($own), self::entry_data($logged), $today);
            $counts[$id] = count($groups->pending);
        }
        return $counts;
    }

    /**
     * Gives what the previous item of the plan left over for an item: what was done in its entry, when partial with
     * carry-over.
     *
     * @param int $userid The acting user.
     * @param int $itemid Plan item id.
     * @return string|null The left-over text, Markdown as typed; null when there is none.
     * @throws \moodle_exception error_notfound.
     */
    public static function left_over(int $userid, int $itemid): ?string {
        global $DB;

        $item = self::own_item($userid, $itemid, null);
        $items = $DB->get_records(self::PLANITEM, ['planid' => $item->planid]);
        $entries = [];
        foreach ($DB->get_records_select(self::ENTRY, 'planid = ? AND planitemid IS NOT NULL', [$item->planid]) as $entry) {
            $entries[(int) $entry->planitemid] = $entry;
        }
        return session_list::left_over(self::session_items($items), self::entry_data($entries), $itemid);
    }

    /**
     * Returns the history of an entry, newest first; it outlives the entry.
     *
     * @param int $userid The acting user.
     * @param int $id Entry id, of an entry that may have been deleted.
     * @return \stdClass[] History rows keyed by id, newest first.
     * @throws \moodle_exception error_notfound.
     */
    public static function history(int $userid, int $id): array {
        global $DB;

        $rows = $DB->get_records(self::HIST, ['entryid' => $id], 'timecreated DESC, id DESC');
        if (!$rows) {
            // An entry the user may reach with no history yet; otherwise error_notfound.
            self::own_entry($userid, $id, access::VIEW);
            return [];
        }
        access::plan($userid, (int) reset($rows)->planid);
        return $rows;
    }

    /**
     * Returns the history of the deleted entries of a plan, newest first.
     *
     * @param int $userid The acting user.
     * @param int $planid Plan id.
     * @return \stdClass[] History rows keyed by id, newest first.
     * @throws \moodle_exception error_notfound.
     */
    public static function deleted_history(int $userid, int $planid): array {
        global $DB;

        access::plan($userid, $planid);
        $sql = 'SELECT h.*
                  FROM {' . self::HIST . '} h
             LEFT JOIN {' . self::ENTRY . '} e ON e.id = h.entryid
                 WHERE h.planid = :planid AND e.id IS NULL
              ORDER BY h.timecreated DESC, h.id DESC';
        return $DB->get_records_sql($sql, ['planid' => $planid]);
    }

    /**
     * The teacher's own day now, in their time zone.
     *
     * @param int $userid The acting user.
     * @return string The day, yyyy-mm-dd.
     */
    public static function today(int $userid): string {
        return self::day($userid, self::now());
    }

    /**
     * Reads an entry row as entry data.
     *
     * @param \stdClass $entry The entry row.
     * @return entry_data The data.
     */
    public static function to_data(\stdClass $entry): entry_data {
        return new entry_data(
            status: $entry->status,
            content: $entry->content,
            reason: $entry->reason,
            note: (string) $entry->reasonnote,
            measure: (string) $entry->measure,
            carry: !empty($entry->carry),
            date: $entry->sessiondate,
        );
    }

    /**
     * Refuses a write to the entries of a plan that is not approved.
     *
     * @param \stdClass $plan The plan row.
     * @throws \moodle_exception error_plannotapproved.
     */
    private static function require_approved(\stdClass $plan): void {
        if ($plan->status !== plan_service::STATUS_APPROVED) {
            throw new \moodle_exception('error_plannotapproved', 'mod_classprogress');
        }
    }

    /**
     * Refuses a change made from another version of the entry than the stored one.
     *
     * @param \stdClass $entry The stored entry row.
     * @param int $timemodified The version the change was made from.
     * @throws \moodle_exception error_changedelsewhere.
     */
    private static function require_current(\stdClass $entry, int $timemodified): void {
        if ((int) $entry->timemodified !== $timemodified) {
            throw new \moodle_exception('error_changedelsewhere', 'mod_classprogress');
        }
    }

    /**
     * Throws the first issue, if there is one.
     *
     * @param string[] $issues entry_rules issue codes.
     * @throws \moodle_exception entryissue_<code>.
     */
    private static function require_valid(array $issues): void {
        if ($issues) {
            throw new \moodle_exception('entryissue_' . $issues[0], 'mod_classprogress');
        }
    }

    /**
     * Checks an entry against the rules of its session, for the teacher's own day now.
     *
     * @param int $userid The acting user, already checked to reach the plan.
     * @param \stdClass $plan The plan row.
     * @param \stdClass|null $item The plan item row; null for an unplanned session.
     * @param entry_data $data The entry as typed.
     * @return string[] entry_rules issue codes.
     * @throws \moodle_exception error_plannotapproved, for an unplanned session of a plan without the school year dates
     *     that approval copies.
     */
    private static function issues(int $userid, \stdClass $plan, ?\stdClass $item, entry_data $data): array {
        $today = self::day($userid, self::now());
        if ($item !== null) {
            return entry_rules::check_planned($data, $item->sessiondate, $today);
        }
        if (empty($plan->yearstart) || empty($plan->yearend)) {
            throw new \moodle_exception('error_plannotapproved', 'mod_classprogress');
        }
        return entry_rules::check_unplanned($data, $plan->yearstart, $plan->yearend, $today);
    }

    /**
     * Returns a plan item that the user may reach.
     *
     * @param int $userid The acting user.
     * @param int $id Plan item id.
     * @param int|null $planid The plan it must belong to; null for any plan the user may reach.
     * @return \stdClass The plan item row.
     * @throws \moodle_exception error_notfound, whether the item is missing, out of reach or another plan's.
     */
    private static function own_item(int $userid, int $id, ?int $planid): \stdClass {
        global $DB;

        $item = $DB->get_record(self::PLANITEM, ['id' => $id]);
        if (!$item || ($planid !== null && (int) $item->planid !== $planid)) {
            throw new \moodle_exception('error_notfound', 'mod_classprogress');
        }
        access::plan($userid, (int) $item->planid);
        return $item;
    }

    /**
     * Returns an entry that the user may reach with a capability.
     *
     * @param int $userid The acting user.
     * @param int $id Entry id.
     * @param string $capability access::VIEW or access::EDIT.
     * @return \stdClass The entry row.
     * @throws \moodle_exception error_notfound, whether the entry is missing or out of reach.
     * @throws \required_capability_exception When the user may open the plan but lacks $capability.
     */
    private static function own_entry(int $userid, int $id, string $capability): \stdClass {
        global $DB;

        $entry = $DB->get_record(self::ENTRY, ['id' => $id]);
        if (!$entry) {
            throw new \moodle_exception('error_notfound', 'mod_classprogress');
        }
        access::plan($userid, (int) $entry->planid, $capability);
        return $entry;
    }

    /**
     * The fields of an entry that come from what was typed.
     *
     * @param entry_data $data The normalised entry.
     * @return array Field => value.
     */
    private static function fields(entry_data $data): array {
        return [
            'status' => $data->status,
            'content' => $data->content,
            'reason' => $data->reason,
            'reasonnote' => $data->note,
            'measure' => $data->measure,
            'carry' => $data->carry ? 1 : 0,
        ];
    }

    /**
     * Writes a history row for an entry.
     *
     * @param int $userid The acting user.
     * @param string $action self::ACTION_CREATE, ACTION_UPDATE or ACTION_DELETE.
     * @param \stdClass $entry The entry row after the change; before it, for a delete.
     * @param int $now The time of the change.
     */
    private static function record(int $userid, string $action, \stdClass $entry, int $now): void {
        global $DB, $USER;

        $loggedinas = \core\session\manager::is_loggedinas() && (int) $USER->id === $userid;
        $DB->insert_record(self::HIST, (object) [
            'entryid' => $entry->id,
            'planid' => $entry->planid,
            'userid' => $userid,
            'realuserid' => $loggedinas ? (int) \core\session\manager::get_realuser()->id : null,
            'action' => $action,
            'snapshot' => json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'timecreated' => $now,
        ]);
    }

    /**
     * Triggers an entry event in the activity's module context, with ids only.
     *
     * @param string $class The event class.
     * @param int $userid The acting user.
     * @param int $id Entry id.
     * @param int $planid Plan id.
     * @param int|null $itemid Plan item id; null for an unplanned session.
     */
    private static function trigger(string $class, int $userid, int $id, int $planid, ?int $itemid): void {
        $class::create([
            'objectid' => $id,
            'userid' => $userid,
            'context' => access::context(access::plan($userid, $planid)),
            'other' => ['planid' => $planid, 'planitemid' => $itemid],
        ])->trigger();
    }

    /**
     * The plan item id of an entry.
     *
     * @param \stdClass $entry The entry row.
     * @return int|null The id; null for an unplanned session.
     */
    private static function item_id(\stdClass $entry): ?int {
        return $entry->planitemid === null ? null : (int) $entry->planitemid;
    }

    /**
     * Reads plan item rows as the session list needs them.
     *
     * @param \stdClass[] $items Plan item rows.
     * @return session_item[] The items.
     */
    private static function session_items(array $items): array {
        return array_values(array_map(
            fn(\stdClass $item): session_item => new session_item((int) $item->id, $item->sessiondate, (int) $item->sortorder),
            $items
        ));
    }

    /**
     * Reads entry rows as entry data, keeping the keys.
     *
     * @param \stdClass[] $entries Entry rows.
     * @return entry_data[] The data.
     */
    private static function entry_data(array $entries): array {
        return array_map(fn(\stdClass $entry): entry_data => self::to_data($entry), $entries);
    }

    /**
     * The day a moment falls on for a user, in their time zone.
     *
     * @param int $userid The user.
     * @param int $time The moment.
     * @return string The day, yyyy-mm-dd.
     */
    private static function day(int $userid, int $time): string {
        $user = \core_user::get_user($userid, 'id, timezone', MUST_EXIST);
        return written_on::day($time, \core_date::get_user_timezone($user));
    }

    /**
     * Now, from Moodle's clock.
     *
     * @return int Unix time.
     */
    private static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Runs a write holding the lock of a plan's entries, so that its checks and its writes are not split by another
     * request.
     *
     * @param int $planid Plan id.
     * @param callable $work The write; what it returns is returned.
     * @return mixed What the write returns.
     * @throws \moodle_exception locktimeout.
     */
    private static function locked(int $planid, callable $work): mixed {
        $factory = \core\lock\lock_config::get_lock_factory('mod_classprogress');
        $lock = $factory->get_lock('entries-' . $planid, schoolyear_service::LOCK_TIMEOUT);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * Runs writes in one delegated transaction: all of them are stored, or none.
     *
     * @param callable $writes The writes; what they return is returned.
     * @return mixed What the writes return.
     */
    private static function in_transaction(callable $writes): mixed {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $writes();
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            // Rolls back and throws the exception again.
            $transaction->rollback($e);
        }
    }
}
