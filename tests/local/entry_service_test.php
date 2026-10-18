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
use local_classprogress\local\session_groups;
use local_classprogress\local\session_item;
use mod_classprogress\event\entry_created;
use mod_classprogress\event\entry_deleted;
use mod_classprogress\event\entry_updated;

/**
 * Tests for the entry service: logging what was done in each session, its history, and the log of a plan, shared by
 * the teachers of the plan activity's course.
 *
 * The clock is frozen at 2026-10-18 10:00 UTC, a Sunday, and the teacher's time zone is Europe/Madrid, so "today" is
 * 2026-10-18 unless a test says otherwise.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\local\entry_service
 * @covers     \mod_classprogress\local\plan_log
 */
final class entry_service_test extends \advanced_testcase {
    /** @var int The frozen moment: 2026-10-18 10:00 UTC. */
    private const NOW = 1792317600;

    /** @var int[] User id => the course where teacher() made them an editing teacher. */
    private array $courses = [];

    /**
     * The activity generator.
     *
     * @return \mod_classprogress_generator The generator.
     */
    private function generator(): \mod_classprogress_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
    }

    /**
     * A new user with an editing teacher role in a new course.
     *
     * @param string $timezone The user's time zone.
     * @return \stdClass The user.
     */
    private function teacher(string $timezone = 'Europe/Madrid'): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', ['timezone' => $timezone]);
        $this->courses[(int) $user->id] = (int) $course->id;
        return $user;
    }

    /**
     * A plan activity in the course of a teacher, in a school year 2026-09-14 to 2027-06-21 of that teacher, with one
     * item per date, in this order.
     *
     * @param int $userid A user made by teacher().
     * @param string[] $dates The items' days.
     * @param string $status The plan's status.
     * @return array [plan id, item ids in the order of $dates].
     */
    private function plan(int $userid, array $dates = ['2026-10-16'], string $status = 'approved'): array {
        $year = $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_schoolyear(
            ['userid' => $userid]
        );
        $plan = $this->generator()->create_instance(['course' => $this->courses[$userid], 'schoolyearid' => $year->id,
            'status' => $status]);
        $items = [];
        foreach ($dates as $date) {
            $items[] = (int) $this->generator()->create_item(['planid' => $plan->id, 'sessiondate' => $date])->id;
        }
        return [(int) $plan->id, $items];
    }

    /**
     * Entry data that is valid for a past planned session.
     *
     * @param array $fields Fields to replace.
     * @return entry_data The data.
     */
    private static function done(array $fields = []): entry_data {
        return new entry_data(...($fields + ['status' => entry_rules::DONE, 'content' => 'Normes de l\'aula']));
    }

    /**
     * Freezes the clock at NOW, or a number of seconds later.
     *
     * @param int $later Seconds after NOW.
     */
    private function freeze(int $later = 0): void {
        $this->mock_clock_with_frozen(self::NOW + $later);
    }

    /**
     * The history rows of the plugin, oldest first.
     *
     * @return \stdClass[] Rows.
     */
    private static function history_rows(): array {
        global $DB;

        return array_values($DB->get_records('classprogress_entry_hist', null, 'id'));
    }

    /**
     * Logging a planned session stores the entry with the item's plan and day, its text exactly as typed, the format
     * Markdown, and the teacher's own day as written-on.
     *
     * @group REQ-LOG-002
     * @group REQ-LOG-004
     * @group REQ-DAT-004
     */
    public function test_logging_a_session_stores_the_entry_as_typed(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $text = "  Pont <- Immaculada & <b>Anna</b>\n- Programació <C++> i Java ";

        $id = entry_service::create($user->id, $planid, $itemid, self::done(['content' => $text]));

        $entry = entry_service::get($user->id, $id);
        $this->assertSame((string) $planid, (string) $entry->planid);
        $this->assertSame((string) $itemid, (string) $entry->planitemid);
        $this->assertSame('2026-10-16', $entry->sessiondate);
        $this->assertSame('done', $entry->status);
        $this->assertSame($text, $entry->content);
        $this->assertSame((string) FORMAT_MARKDOWN, (string) $entry->contentformat);
        $this->assertSame('2026-10-18', $entry->writtenon);
        $this->assertSame((string) self::NOW, (string) $entry->timecreated);
        $this->assertSame((string) self::NOW, (string) $entry->timemodified);
        $this->assertEquals($entry, entry_service::for_item($user->id, $itemid));
    }

    /**
     * Time zones and their day at 2026-10-18 22:30 UTC.
     *
     * @return array Case => [time zone, written-on day].
     */
    public static function timezone_cases(): array {
        return [
            'Madrid is already on the next day' => ['Europe/Madrid', '2026-10-19'],
            'New York is still on the same day' => ['America/New_York', '2026-10-18'],
        ];
    }

    /**
     * The written-on day is the day in the teacher's own time zone at the moment of the first save.
     *
     * @dataProvider timezone_cases
     * @group REQ-LOG-004
     * @param string $timezone The teacher's time zone.
     * @param string $expected The written-on day.
     */
    public function test_written_on_is_the_teachers_own_day(string $timezone, string $expected): void {
        $this->resetAfterTest();
        $this->freeze(12 * HOURSECS + 30 * MINSECS);
        $user = $this->teacher($timezone);
        [$planid, [$itemid]] = $this->plan($user->id);

        $id = entry_service::create($user->id, $planid, $itemid, self::done());

        $this->assertSame($expected, entry_service::get($user->id, $id)->writtenon);
        $this->assertSame($expected, entry_service::today($user->id));
    }

    /**
     * Saving again updates the same entry: one entry per item, the written-on day kept, the last-edited time new.
     *
     * @group REQ-LOG-005
     * @group REQ-LOG-004
     */
    public function test_saving_again_updates_the_entry_and_keeps_its_written_on_day(): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $id = entry_service::create($user->id, $planid, $itemid, self::done());
        $this->freeze(3 * DAYSECS);

        entry_service::update($user->id, $id, self::done(['content' => 'Second version']), self::NOW);

        $entry = entry_service::get($user->id, $id);
        $this->assertSame('Second version', $entry->content);
        $this->assertSame('2026-10-18', $entry->writtenon);
        $this->assertSame((string) self::NOW, (string) $entry->timecreated);
        $this->assertSame((string) (self::NOW + 3 * DAYSECS), (string) $entry->timemodified);
        $this->assertSame(1, $DB->count_records('classprogress_entry', ['planitemid' => $itemid]));
    }

    /**
     * A second entry for an item that already has one is refused as changed elsewhere (another tab logged it first).
     *
     * @group REQ-LOG-005
     * @group REQ-DAT-002
     */
    public function test_a_second_entry_for_the_same_item_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        entry_service::create($user->id, $planid, $itemid, self::done());

        try {
            entry_service::create($user->id, $planid, $itemid, self::done(['content' => 'Other tab']));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        $this->assertSame(1, $DB->count_records('classprogress_entry'));
        $this->assertCount(1, self::history_rows());
    }

    /**
     * An update or a delete made from an older version of the entry is refused and changes nothing; the version is
     * always newer after a save, even within the same second.
     *
     * @group REQ-LOG-005
     * @group REQ-DAT-003
     */
    public function test_a_stale_update_or_delete_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $id = entry_service::create($user->id, $planid, $itemid, self::done());
        entry_service::update($user->id, $id, self::done(['content' => 'Saved in the first tab']), self::NOW);
        $saved = $DB->get_record('classprogress_entry', ['id' => $id]);
        $this->assertGreaterThan(self::NOW, (int) $saved->timemodified);

        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    entry_service::update($user->id, $id, self::done(['content' => 'Stale']), self::NOW);
                } else {
                    entry_service::delete($user->id, $id, self::NOW);
                }
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_changedelsewhere', $e->errorcode);
            }
        }
        $this->assertEquals($saved, $DB->get_record('classprogress_entry', ['id' => $id]));
        $this->assertCount(2, self::history_rows());
    }

    /**
     * Every write records a history row with a full snapshot of the entry (after the change; before it, for a
     * delete), the acting user and no real user.
     *
     * @group REQ-HIS-001
     */
    public function test_every_write_records_a_full_snapshot_and_the_acting_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);

        $id = entry_service::create($user->id, $planid, $itemid, self::done());
        $created = $DB->get_record('classprogress_entry', ['id' => $id]);
        $this->freeze(MINSECS);
        entry_service::update($user->id, $id, self::done(['content' => 'Changed']), (int) $created->timemodified);
        $updated = $DB->get_record('classprogress_entry', ['id' => $id]);
        $this->freeze(2 * MINSECS);
        entry_service::delete($user->id, $id, (int) $updated->timemodified);

        $rows = self::history_rows();
        $this->assertSame(['create', 'update', 'delete'], array_column($rows, 'action'));
        $snapshots = array_map(fn($row) => (object) json_decode($row->snapshot, true), $rows);
        $this->assertEquals([$created, $updated, $updated], $snapshots);
        foreach ($rows as $i => $row) {
            $this->assertSame((string) $id, (string) $row->entryid);
            $this->assertSame((string) $planid, (string) $row->planid);
            $this->assertSame((string) $user->id, (string) $row->userid);
            $this->assertNull($row->realuserid);
            $this->assertSame((string) (self::NOW + $i * MINSECS), (string) $row->timecreated);
        }
    }

    /**
     * An administrator logged in as the teacher writes the teacher's entry, and the history names both.
     *
     * @group REQ-HIS-001
     */
    public function test_log_in_as_records_the_real_user(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $this->setAdminUser();
        \core\session\manager::loginas($user->id, \context_system::instance(), false);

        entry_service::create($user->id, $planid, $itemid, self::done());

        $row = self::history_rows()[0];
        $this->assertSame((string) $user->id, (string) $row->userid);
        $this->assertSame((string) get_admin()->id, (string) $row->realuserid);
    }

    /**
     * A failure in the history write undoes the entry write: they are one transaction. The history table is renamed
     * away so that its insert fails; no event is triggered.
     *
     * @group REQ-HIS-001
     */
    public function test_a_failed_history_write_leaves_no_entry(): void {
        global $DB;

        $this->resetAfterTest();
        // The test's own transaction would make the service's one nested, and only the outermost one really rolls back.
        $this->preventResetByRollback();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $sink = $this->redirectEvents();
        $dbman = $DB->get_manager();
        $dbman->rename_table(new \xmldb_table('classprogress_entry_hist'), 'classprogress_gone');
        try {
            try {
                entry_service::create($user->id, $planid, $itemid, self::done());
                $this->fail('Expected the save to fail');
            } catch (\dml_exception $e) {
                $this->assertFalse($DB->is_transaction_started());
            }
            $this->assertSame(0, $DB->count_records('classprogress_entry'));
            $this->assertSame([], $sink->get_events());
        } finally {
            $dbman->rename_table(new \xmldb_table('classprogress_gone'), 'classprogress_entry_hist');
        }
    }

    /**
     * Deleting an entry keeps its history, which can still be read newest first, unchanged; the plan's deleted
     * entries list it, and only it.
     *
     * @group REQ-LOG-005
     * @group REQ-HIS-002
     */
    public function test_delete_removes_the_entry_and_keeps_its_history(): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid, $otherid]] = $this->plan($user->id, ['2026-10-14', '2026-10-16']);
        $id = entry_service::create($user->id, $planid, $itemid, self::done());
        $kept = entry_service::create($user->id, $planid, $otherid, self::done());
        $first = self::history_rows();
        $this->freeze(MINSECS);
        entry_service::update($user->id, $id, self::done(['content' => 'Second']), self::NOW);
        $this->freeze(2 * MINSECS);

        entry_service::delete($user->id, $id, self::NOW + MINSECS);

        $this->assertFalse($DB->record_exists('classprogress_entry', ['id' => $id]));
        $this->assertNull(entry_service::for_item($user->id, $itemid));
        $history = entry_service::history($user->id, $id);
        $this->assertSame(['delete', 'update', 'create'], array_column(array_values($history), 'action'));
        $this->assertEquals($first[0], end($history));
        $this->assertEquals(array_values($history), array_values(entry_service::deleted_history($user->id, $planid)));
        $this->assertCount(1, entry_service::history($user->id, $kept));
    }

    /**
     * Each write triggers its event once its transaction is committed, in the owner's user context, with ids only; a
     * refused write triggers none.
     *
     * @group REQ-AUD-001
     */
    public function test_each_write_triggers_its_event(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $sink = $this->redirectEvents();

        $id = entry_service::create($user->id, $planid, $itemid, self::done());
        entry_service::update($user->id, $id, self::done(['content' => 'Second']), self::NOW);
        try {
            entry_service::delete($user->id, $id, self::NOW);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        entry_service::delete($user->id, $id, self::NOW + 1);
        $extra = new entry_data(entry_rules::ADDED, 'Extra', date: '2026-10-17');
        $unplanned = entry_service::create($user->id, $planid, null, $extra);

        $events = $sink->get_events();
        $this->assertSame(
            [entry_created::class, entry_updated::class, entry_deleted::class, entry_created::class],
            array_map('get_class', $events)
        );
        foreach ($events as $event) {
            $this->assertSame((int) $user->id, (int) $event->userid);
            $this->assertSame(access::context(plan_service::get($user->id, $planid))->id, $event->get_context()->id);
        }
        $this->assertSame([$id, $id, $id, $unplanned], array_map(fn($event) => (int) $event->objectid, $events));
        $this->assertEquals(['planid' => $planid, 'planitemid' => $itemid], $events[2]->other);
        $this->assertEquals(['planid' => $planid, 'planitemid' => null], $events[3]->other);
    }

    /**
     * A draft plan takes no entries and shows no log.
     *
     * @group REQ-LOG-008
     */
    public function test_only_approved_plans_are_logged(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id, ['2026-10-16'], 'draft');

        foreach ([$itemid, null] as $target) {
            try {
                entry_service::create($user->id, $planid, $target, $target ? self::done()
                    : new entry_data(entry_rules::ADDED, 'Extra', date: '2026-10-16'));
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_plannotapproved', $e->errorcode);
            }
        }
        $log = entry_service::log($user->id, $planid);
        $this->assertEquals(new session_groups(), $log->groups);
        $this->assertSame([$planid => 0], entry_service::pending_counts($user->id, [$planid]));
    }

    /**
     * Entries that break a rule, for a planned item dated 2026-10-16 or 2026-10-20 or for an unplanned session.
     *
     * @return array Case => [item day or null for unplanned, entry fields, issue codes].
     */
    public static function invalid_cases(): array {
        $done = ['status' => entry_rules::DONE, 'content' => 'x'];
        $added = ['status' => entry_rules::ADDED, 'content' => 'x'];
        return [
            'done without what was done' => ['2026-10-16', ['content' => ' '] + $done, [entry_rules::CONTENT_REQUIRED]],
            'partial without a reason' => ['2026-10-16', ['status' => entry_rules::PARTIAL] + $done,
                [entry_rules::REASON_REQUIRED]],
            'not done with an unknown reason' => ['2026-10-16', ['status' => entry_rules::NOT_DONE, 'reason' => 'flood'],
                [entry_rules::BAD_REASON]],
            'added for a planned item' => ['2026-10-16', $added, [entry_rules::BAD_STATUS]],
            'done after today' => ['2026-10-20', $done, [entry_rules::FUTURE_NOT_DONE_ONLY]],
            'done for an unplanned session' => [null, $done + ['date' => '2026-10-16'], [entry_rules::BAD_STATUS]],
            'unplanned after today' => [null, $added + ['date' => '2026-10-19'], [entry_rules::FUTURE_UNPLANNED]],
            'unplanned outside the school year' => [null, $added + ['date' => '2026-09-11'], [entry_rules::OUTSIDE_YEAR]],
            'unplanned on a day that does not exist' => [null, $added + ['date' => '2026-02-30'], [entry_rules::BAD_DATE]],
        ];
    }

    /**
     * An entry that breaks a rule is reported by check() and refused by create() with its first issue, and nothing
     * is stored.
     *
     * @dataProvider invalid_cases
     * @group REQ-LOG-002
     * @group REQ-LOG-003
     * @group REQ-LOG-007
     * @group REQ-LOG-009
     * @param string|null $date The item's day; null for an unplanned session.
     * @param array $fields The entry.
     * @param string[] $issues The issue codes.
     */
    public function test_invalid_entries_are_reported_and_refused(?string $date, array $fields, array $issues): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id, [$date ?? '2026-10-16']);
        $itemid = $date === null ? null : $itemid;
        $data = new entry_data(...$fields);

        $this->assertSame($issues, entry_service::check($user->id, $planid, $itemid, $data));
        try {
            entry_service::create($user->id, $planid, $itemid, $data);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('entryissue_' . $issues[0], $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('classprogress_entry'));
    }

    /**
     * A session after today can be logged as not done with its reason, as a cancellation known in advance; once the
     * day has come it can be changed to done.
     *
     * @group REQ-LOG-009
     */
    public function test_a_session_after_today_can_be_logged_as_not_done(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id, ['2026-10-20']);
        $strike = new entry_data(entry_rules::NOT_DONE, reason: 'strike', note: 'Vaga anunciada');

        $id = entry_service::create($user->id, $planid, $itemid, $strike);
        $this->freeze(DAYSECS);
        try {
            entry_service::update($user->id, $id, self::done(), self::NOW);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('entryissue_future_not_done_only', $e->errorcode);
        }
        $this->freeze(2 * DAYSECS);
        entry_service::update($user->id, $id, self::done(), self::NOW);

        $this->assertSame('done', entry_service::get($user->id, $id)->status);
    }

    /**
     * Done keeps no reason, note or measure; partial keeps them and its carry-over, which the next item of the plan
     * then shows as left over.
     *
     * @group REQ-LOG-003
     * @group REQ-LOG-006
     */
    public function test_reasons_and_carry_over_are_kept_only_where_they_apply(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$first, $second, $third]] = $this->plan($user->id, ['2026-10-12', '2026-10-14', '2026-10-16']);
        $deviation = ['reason' => 'incident', 'note' => 'Projector', 'measure' => 'Next week', 'carry' => true];

        $done = entry_service::create($user->id, $planid, $first, self::done($deviation));
        $half = self::done(['status' => entry_rules::PARTIAL, 'content' => 'Half of the list'] + $deviation);
        $partial = entry_service::create($user->id, $planid, $second, $half);

        $row = entry_service::get($user->id, $done);
        $this->assertSame(['', '', '', '0'], [$row->reason, $row->reasonnote, $row->measure, (string) $row->carry]);
        $row = entry_service::get($user->id, $partial);
        $this->assertSame(['incident', 'Projector', 'Next week', '1'], [$row->reason, $row->reasonnote, $row->measure,
            (string) $row->carry]);
        $this->assertNull(entry_service::left_over($user->id, $second));
        $this->assertSame('Half of the list', entry_service::left_over($user->id, $third));
    }

    /**
     * Unplanned sessions belong to no item, have the status added, and any number may share a day.
     *
     * @group REQ-LOG-007
     */
    public function test_unplanned_sessions_belong_to_no_item(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$planid] = $this->plan($user->id);
        $extra = new entry_data(entry_rules::ADDED, 'Extra session', date: '2026-10-15');

        $one = entry_service::create($user->id, $planid, null, $extra);
        $two = entry_service::create($user->id, $planid, null, $extra);

        $log = entry_service::log($user->id, $planid);
        $this->assertSame([$one, $two], array_map('intval', array_keys($log->unplanned)));
        $this->assertNull($log->unplanned[$one]->planitemid);
        $this->assertSame('added', $log->unplanned[$one]->status);
        $this->assertSame('2026-10-15', $log->unplanned[$one]->sessiondate);
        $this->assertSame([], $log->entries);
    }

    /**
     * The log of a plan: pending items oldest first (today included), upcoming ones after today, logged ones, and
     * how late each entry was written.
     *
     * @group REQ-LOG-001
     * @group REQ-LOG-004
     */
    public function test_log_sorts_the_sessions_and_gives_days_late(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        $dates = ['2026-10-12', '2026-10-14', '2026-10-18', '2026-10-20', '2026-10-16'];
        [$planid, [$late, $older, $today, $upcoming, $newer]] = $this->plan($user->id, $dates);
        entry_service::create($user->id, $planid, $late, self::done());

        $log = entry_service::log($user->id, $planid);

        $ids = fn(array $items): array => array_map(fn(session_item $item): int => $item->id, $items);
        $this->assertSame('2026-10-18', $log->today);
        $this->assertSame([$older, $newer, $today], $ids($log->groups->pending));
        $this->assertSame([$upcoming], $ids($log->groups->upcoming));
        $this->assertSame([$late], $ids($log->groups->logged));
        $this->assertSame(6, $log->days_late($log->entries[$late]));
        $this->assertSame([$late, $older, $today, $upcoming, $newer], array_map('intval', array_keys($log->items)));
        $this->assertSame([$planid => 3], entry_service::pending_counts($user->id, [$planid]));
    }

    /**
     * A request with several plan ids is refused as a whole when one of them is not the user's.
     *
     * @group REQ-OWN-003
     * @group REQ-OWN-002
     */
    public function test_pending_counts_refuse_the_whole_list_when_one_id_is_not_the_users(): void {
        $this->resetAfterTest();
        $this->freeze();
        $user = $this->teacher();
        [$own] = $this->plan($user->id);
        [$foreign] = $this->plan($this->teacher()->id);

        foreach ([$foreign, $foreign + 1000] as $other) {
            try {
                entry_service::pending_counts($user->id, [$own, $other]);
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_notfound', $e->errorcode);
            }
        }
        $this->assertSame([], entry_service::pending_counts($user->id, []));
    }

    /**
     * A write waits for the lock of the plan's entries, so that the changed-elsewhere check and the write cannot be
     * split by another request.
     *
     * @group REQ-DAT-003
     */
    public function test_a_write_waits_for_the_lock_of_the_plans_entries(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->lock_factory = '\core\lock\file_lock_factory';
        $this->freeze();
        $user = $this->teacher();
        [$planid, [$itemid]] = $this->plan($user->id);
        $lock = \core\lock\lock_config::get_lock_factory('mod_classprogress')->get_lock('entries-' . $planid, 0);

        try {
            entry_service::create($user->id, $planid, $itemid, self::done());
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('locktimeout', $e->errorcode);
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $DB->count_records('classprogress_entry'));
    }

    /**
     * Calls one service method on the owner's plan, item and entry, or on the ids given.
     *
     * @param string $operation Method name.
     * @param int $userid The acting user.
     * @param int $planid Plan id.
     * @param int $itemid Plan item id.
     * @param int $entryid Entry id.
     * @return mixed What the method returns.
     */
    private static function call(string $operation, int $userid, int $planid, int $itemid, int $entryid): mixed {
        $added = new entry_data(entry_rules::ADDED, 'Taken', date: '2026-10-16');
        return match ($operation) {
            'get' => entry_service::get($userid, $entryid),
            'for_item' => entry_service::for_item($userid, $itemid),
            'item' => entry_service::item($userid, $itemid),
            'check' => entry_service::check($userid, $planid, $itemid, self::done()),
            'create' => entry_service::create($userid, $planid, $itemid, self::done()),
            'create unplanned' => entry_service::create($userid, $planid, null, $added),
            'update' => entry_service::update($userid, $entryid, self::done(['content' => 'Taken']), 0),
            'delete' => entry_service::delete($userid, $entryid, 0),
            'log' => entry_service::log($userid, $planid),
            'left_over' => entry_service::left_over($userid, $itemid),
            'history' => entry_service::history($userid, $entryid),
            'deleted_history' => entry_service::deleted_history($userid, $planid),
            'pending_counts' => entry_service::pending_counts($userid, [$planid]),
        };
    }

    /**
     * The operations of the service that take an id.
     *
     * @return string[] Method names, as call() takes them.
     */
    private static function operations(): array {
        return ['get', 'for_item', 'item', 'check', 'create', 'create unplanned', 'update', 'delete', 'log', 'left_over',
            'history', 'deleted_history', 'pending_counts'];
    }

    /**
     * Users who are not teachers of the plan's course, for every operation.
     *
     * @return array Case => [actor, operation].
     */
    public static function foreign_access_cases(): array {
        $cases = [];
        foreach (['teacher of another course', 'student of the course'] as $actor) {
            foreach (self::operations() as $operation) {
                $cases["{$actor} {$operation}"] = [$actor, $operation];
            }
        }
        return $cases;
    }

    /**
     * An approved plan with a past item that has an entry, and the rows as they stand.
     *
     * @return array [teacher, plan id, item id, entry id, snapshot of the entry and history tables].
     */
    private function owned_entry(): array {
        global $DB;

        $owner = $this->teacher();
        [$planid, [$itemid]] = $this->plan($owner->id);
        $entryid = entry_service::create($owner->id, $planid, $itemid, self::done());
        $before = [$DB->get_records('classprogress_entry'), $DB->get_records('classprogress_entry_hist')];
        return [$owner, $planid, $itemid, $entryid, $before];
    }

    /**
     * A user who is not a teacher of the plan's course.
     *
     * @param string $actor The kind of user, as in foreign_access_cases().
     * @param int $ownerid A teacher of the course.
     * @return int The user id.
     */
    private function outsider(string $actor, int $ownerid): int {
        if ($actor === 'student of the course') {
            $course = get_course($this->courses[$ownerid]);
            return (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        }
        return (int) $this->teacher()->id;
    }

    /**
     * Nobody but the teachers of the course can read, write, delete or see the history of its entries.
     *
     * @dataProvider foreign_access_cases
     * @group REQ-OWN-001
     * @group REQ-MOD-002
     * @param string $actor Who acts.
     * @param string $operation What they try.
     */
    public function test_nobody_else_reaches_an_entry(string $actor, string $operation): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        [$owner, $planid, $itemid, $entryid, $before] = $this->owned_entry();
        $userid = $this->outsider($actor, (int) $owner->id);

        try {
            self::call($operation, $userid, $planid, $itemid, $entryid);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_notfound', $e->errorcode);
        }

        $this->assertEquals($before, [$DB->get_records('classprogress_entry'),
            $DB->get_records('classprogress_entry_hist')]);
    }

    /**
     * A missing id and an id out of reach give exactly the same exception, for every operation.
     *
     * @dataProvider foreign_access_cases
     * @group REQ-OWN-002
     * @param string $actor Who acts.
     * @param string $operation What they try.
     */
    public function test_missing_and_foreign_ids_give_the_same_error(string $actor, string $operation): void {
        $this->resetAfterTest();
        $this->freeze();
        [$owner, $planid, $itemid, $entryid] = $this->owned_entry();
        $userid = $this->outsider($actor, (int) $owner->id);
        $caught = [];

        foreach ([0, 1000] as $offset) {
            try {
                self::call($operation, $userid, $planid + $offset, $itemid + $offset, $entryid + $offset);
                $this->fail('Expected an exception');
            } catch (\moodle_exception $e) {
                $caught[] = [get_class($e), $e->errorcode, $e->module, $e->getMessage(), $e->link, $e->a, $e->debuginfo];
            }
        }

        $this->assertSame($caught[0], $caught[1]);
    }

    /**
     * Another teacher of the course, editing or not, logs sessions, edits and deletes an entry the first teacher
     * wrote, and the history names who made each change (REQ-MOD-004).
     *
     * @group REQ-MOD-004
     * @group REQ-HIS-001
     */
    public function test_the_teachers_of_the_course_share_the_log(): void {
        $this->resetAfterTest();
        $this->freeze();
        [$owner, $planid, $itemid, $entryid] = $this->owned_entry();
        $course = get_course($this->courses[(int) $owner->id]);
        $colleague = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['timezone' => 'Europe/Madrid']);

        entry_service::update($colleague->id, $entryid, self::done(['content' => 'Revisat']), self::NOW);
        $this->freeze(MINSECS);
        entry_service::delete($owner->id, $entryid, self::NOW + 1);
        $added = entry_service::create($colleague->id, $planid, null, new entry_data(
            entry_rules::ADDED,
            'Extra',
            date: '2026-10-17'
        ));

        $this->assertSame(
            [(int) $owner->id, (int) $colleague->id, (int) $owner->id, (int) $colleague->id],
            array_map(fn(\stdClass $row): int => (int) $row->userid, self::history_rows())
        );
        $this->assertSame('Extra', entry_service::get($owner->id, $added)->content);
        $this->assertCount(3, entry_service::history($colleague->id, $entryid));
    }

    /**
     * The operations that write.
     *
     * @return array Case => [operation].
     */
    public static function write_cases(): array {
        $cases = [];
        foreach (['check', 'create', 'create unplanned', 'update', 'delete'] as $operation) {
            $cases[$operation] = [$operation];
        }
        return $cases;
    }

    /**
     * A user who may open the plan but not edit it reads its log and history, and gets Moodle's permission error, with
     * nothing written, on every write.
     *
     * @dataProvider write_cases
     * @group REQ-MOD-004
     * @group REQ-ACC-003
     * @param string $operation What they try.
     */
    public function test_writes_need_the_edit_capability(string $operation): void {
        global $DB;

        $this->resetAfterTest();
        $this->freeze();
        [$owner, $planid, $itemid, $entryid, $before] = $this->owned_entry();
        $reader = $this->getDataGenerator()->create_and_enrol(get_course($this->courses[(int) $owner->id]), 'teacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability(access::EDIT, CAP_PROHIBIT, $roleid, access::context(plan_service::get($owner->id, $planid))->id);

        $this->assertCount(1, entry_service::history($reader->id, $entryid));
        try {
            self::call($operation, $reader->id, $planid, $itemid, $entryid);
            $this->fail('Expected an exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->assertEquals($before, [$DB->get_records('classprogress_entry'),
            $DB->get_records('classprogress_entry_hist')]);
    }
}
