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

use local_classprogress\local\calendar;
use local_classprogress\local\calendar_result;
use local_classprogress\local\calendar_session;
use local_classprogress\local\holiday_entry;
use local_classprogress\local\schoolyear_service;

/**
 * Tests for the plan service: a plan activity's settings (school year, class days, weekly timetable), its calendar,
 * and who may reach it.
 *
 * Timetables are weekday => hours with Monday = 0 and Sunday = 6. 2026-09-14 and 2026-10-12 are Mondays.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\local\plan_service
 * @covers     \mod_classprogress\local\access
 */
final class plan_service_test extends \advanced_testcase {
    /** @var string A small clean plan of two days. */
    private const PLAN = "# MP 0372 · Bases de dades\n## RA1\n### 2026-09-14 [2h]\nNormes\n### 2026-09-18 [2h]\nEntorn\n";

    /**
     * A new user with a role in a course.
     *
     * @param \stdClass $course The course.
     * @param string $role Role short name.
     * @return \stdClass The user.
     */
    private function user(\stdClass $course, string $role = 'editingteacher'): \stdClass {
        return $this->getDataGenerator()->create_and_enrol($course, $role);
    }

    /**
     * A school year of the user, created through the school year service.
     *
     * @param int $userid Owner.
     * @param string $holidays Holiday text.
     * @return int School year id.
     */
    private static function year(int $userid, string $holidays = ''): int {
        return schoolyear_service::create($userid, (object) [
            'name' => 'Curs 2026-2027',
            'startdate' => '2026-09-14',
            'enddate' => '2027-06-21',
            'holidays' => $holidays,
        ]);
    }

    /**
     * Plan data as the activity form gives the service: Monday and Friday 2 hours, 2026-09-14 to 2027-05-24.
     *
     * @param int $courseid Course id.
     * @param int $yearid School year id.
     * @param array $fields Fields to replace.
     * @return \stdClass The data.
     */
    private static function plandata(int $courseid, int $yearid, array $fields = []): \stdClass {
        return (object) array_merge([
            'course' => $courseid,
            'name' => 'Bases de dades',
            'schoolyearid' => $yearid,
            'startdate' => '2026-09-14',
            'enddate' => '2027-05-24',
            'timetable' => [0 => 2, 4 => 2],
        ], $fields);
    }

    /**
     * A plan activity added through the activity's callbacks by the owner of its school year.
     *
     * @param \stdClass $course The course.
     * @param int $yearid School year id.
     * @param array $fields Activity fields (startdate and enddate as days, timetable as weekday => hours).
     * @return int Plan id.
     */
    private function plan(\stdClass $course, int $yearid, array $fields = []): int {
        return (int) $this->getDataGenerator()->create_module('classprogress', array_merge([
            'course' => $course->id,
            'name' => 'Bases de dades',
            'schoolyearid' => $yearid,
            'startdate' => '2026-09-14',
            'enddate' => '2027-05-24',
            'timetable' => [0 => 2, 4 => 2],
        ], $fields))->id;
    }

    /**
     * Adding the activity stores a draft plan with the school year, class days and one slot per weekday that has hours;
     * the module stays empty until a text is imported.
     *
     * @group REQ-MOD-001
     */
    public function test_adding_the_activity_stores_a_draft_with_its_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $yearid = self::year($user->id);

        $id = $this->plan($course, $yearid, ['timetable' => [0 => 2, 2 => 0, 4 => 1.5]]);

        $plan = plan_service::get($user->id, $id);
        $this->assertSame((string) $course->id, (string) $plan->course);
        $this->assertSame('Bases de dades', $plan->name);
        $this->assertSame((string) $yearid, (string) $plan->schoolyearid);
        $this->assertSame(['', ''], [$plan->modulecode, $plan->moduletitle]);
        $this->assertSame(['2026-09-14', '2027-05-24'], [$plan->startdate, $plan->enddate]);
        $this->assertSame('draft', $plan->status);
        $this->assertNull($plan->sourcetext);
        $this->assertSame([0 => 2.0, 4 => 1.5], plan_service::timetable($user->id, $id));
    }

    /**
     * Saving the settings of a draft changes its school year, class days and timetable; data without a timetable keeps
     * it; empty class days are kept empty and the calendar then says so.
     *
     * @group REQ-CAL-002
     */
    public function test_saving_the_settings_of_a_draft(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $id = $this->plan($course, self::year($user->id));
        $other = self::year($user->id);

        plan_service::update($user->id, $id, self::plandata($course->id, $other, ['name' => '  Programació ',
            'startdate' => '2026-10-01', 'enddate' => '2027-05-31', 'timetable' => [0 => 0, 1 => 1.25, 3 => 2]]));
        $plan = plan_service::get($user->id, $id);
        $this->assertSame(
            ['Programació', (string) $other, '2026-10-01', '2027-05-31'],
            [$plan->name, (string) $plan->schoolyearid, $plan->startdate, $plan->enddate]
        );
        $this->assertSame([1 => 1.25, 3 => 2.0], plan_service::timetable($user->id, $id));

        $data = self::plandata($course->id, $other, ['startdate' => '', 'enddate' => null]);
        unset($data->timetable);
        plan_service::update($user->id, $id, $data);
        $plan = plan_service::get($user->id, $id);
        $this->assertSame([null, null], [$plan->startdate, $plan->enddate]);
        $this->assertSame([1 => 1.25, 3 => 2.0], plan_service::timetable($user->id, $id));
        $this->assertSame(calendar::MISSING_DATES, plan_service::calendar($user->id, $id)->code);
    }

    /**
     * Invalid plan data and the error each field gets.
     *
     * @return array Case name => [fields to replace, expected errors].
     */
    public static function invalid_data_cases(): array {
        return [
            'empty name' => [['name' => ' '], ['name' => 'error_name']],
            'no school year' => [['schoolyearid' => 0], ['schoolyearid' => 'error_schoolyear']],
            'missing school year' => [['schoolyearid' => 999999], ['schoolyearid' => 'error_schoolyear']],
            'date not ISO' => [['startdate' => '14/09/2026'], ['startdate' => 'error_baddate']],
            'no such date' => [['enddate' => '2027-02-29'], ['enddate' => 'error_baddate']],
            'end before start' => [['enddate' => '2026-09-13'], ['enddate' => 'error_endbeforestart']],
            'every four-digit year' => [['startdate' => '0001-01-01', 'enddate' => '9999-12-30'],
                ['startdate' => 'error_daterange', 'enddate' => 'error_daterange']],
            'first day before the school year' => [['startdate' => '2026-09-13'], ['startdate' => 'error_dateoutsideyear']],
            'last day after the school year' => [['enddate' => '2027-06-22'], ['enddate' => 'error_dateoutsideyear']],
            'hours above 24' => [['timetable' => [0 => 24.5]], ['timetable-0' => 'error_hours']],
            'negative hours' => [['timetable' => [3 => -1]], ['timetable-3' => 'error_hours']],
            'hours not finite' => [['timetable' => [1 => NAN]], ['timetable-1' => 'error_hours']],
            'weekday 7' => [['timetable' => [7 => 1]], ['timetable' => 'error_weekday']],
        ];
    }

    /**
     * Validation names the field and the error; the service refuses to store such data.
     *
     * @dataProvider invalid_data_cases
     * @group REQ-CAL-004
     * @param array $fields Fields to replace in valid data.
     * @param array $expected Expected errors, field => lang string id.
     */
    public function test_invalid_data_is_reported_and_refused(array $fields, array $expected): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $data = self::plandata($course->id, self::year($user->id), $fields);

        $this->assertSame($expected, plan_service::validate($user->id, $data));
        try {
            plan_service::create($user->id, $data);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame(reset($expected), $e->errorcode);
            $this->assertSame('mod_classprogress', $e->module);
        }
        $this->assertSame(0, $DB->count_records('classprogress'));
        $this->assertSame(0, $DB->count_records('classprogress_slot'));
    }

    /**
     * A plan takes only a school year of the acting teacher; one that another teacher gave it is kept when its
     * settings are saved unchanged, without being offered to anyone else (REQ-OWN-001).
     *
     * @group REQ-OWN-001
     * @group REQ-MOD-004
     */
    public function test_a_plan_takes_only_the_teachers_own_school_year_and_keeps_the_one_it_has(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->user($course);
        $colleague = $this->user($course);
        $ownyear = self::year($owner->id);
        $colleagueyear = self::year($colleague->id);
        $id = $this->plan($course, $ownyear);

        $this->assertSame(
            ['schoolyearid' => 'error_schoolyear'],
            plan_service::validate($colleague->id, self::plandata($course->id, $ownyear))
        );
        $this->assertSame([], plan_service::validate($colleague->id, self::plandata($course->id, $ownyear), $id));
        plan_service::update($colleague->id, $id, self::plandata($course->id, $ownyear, ['name' => 'Renamed']));
        $this->assertSame('Renamed', plan_service::get($colleague->id, $id)->name);
        $this->assertSame(
            ['schoolyearid' => 'error_schoolyear'],
            plan_service::validate($owner->id, self::plandata($course->id, $colleagueyear), $id)
        );
        plan_service::update($colleague->id, $id, self::plandata($course->id, $colleagueyear));
        $this->assertSame((string) $colleagueyear, (string) plan_service::get($owner->id, $id)->schoolyearid);
    }

    /**
     * Each real plan's calendar, from its first to its last day with the real school holidays, has exactly the
     * plan's number of days and hours: every day of those plans is a class day.
     *
     * @group REQ-CAL-001
     * @group REQ-CAL-002
     * @group REQ-HOL-003
     */
    public function test_calendar_of_the_real_plans_matches_their_days_and_hours(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $expected = json_decode(file_get_contents(__DIR__ . '/../fixtures/expected.json'), true);
        $yearid = self::year($user->id, file_get_contents(__DIR__ . '/../fixtures/holidays-centre-2026-27.txt'));

        foreach ($expected['plans'] as $name => $real) {
            $id = $this->plan($course, $yearid, ['startdate' => $real['first'], 'enddate' => $real['last'],
                'timetable' => $real['timetable']]);

            $result = plan_service::calendar($user->id, $id);

            $this->assertNull($result->code, $name);
            $this->assertCount($real['days'], $result->sessions, $name);
            $this->assertEquals($real['hours'], $result->totalhours, $name);
            $this->assertSame($real['first'], $result->sessions[0]->date, $name);
            $this->assertSame($real['last'], $result->sessions[$real['days'] - 1]->date, $name);
        }
    }

    /**
     * A draft's calendar is the calendar class's result for its dates, timetable and school year holidays, with the
     * sessions lost to each holiday under the holiday's id; editing the holidays changes the calendar of every draft of
     * that school year, and of no other.
     *
     * @group REQ-CAL-001
     * @group REQ-CAL-003
     * @group REQ-HOL-003
     */
    public function test_a_drafts_calendar_uses_the_holidays_of_its_school_year(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $yearid = self::year($user->id, "2026-10-12 Festa\n2026-12-22 to 2027-01-07 Nadal\n2027-02-02 Festa local");
        $otheryear = self::year($user->id);
        $id = $this->plan($course, $yearid, ['timetable' => [0 => 2, 1 => 1.5, 4 => 2]]);
        $other = $this->plan($course, $otheryear, ['timetable' => [0 => 2, 1 => 1.5, 4 => 2]]);
        $holidays = schoolyear_service::holidays($user->id, $yearid);

        $result = plan_service::calendar($user->id, $id);

        $this->assertEquals(calendar::compute('2026-09-14', '2027-05-24', [0 => 2.0, 1 => 1.5, 4 => 2.0], $holidays), $result);
        $ids = array_flip(array_map(fn(holiday_entry $h): string => $h->label, $holidays));
        $this->assertSame([$ids['Festa'] => 1, $ids['Nadal'] => 7, $ids['Festa local'] => 1], $result->lost);
        $this->assertSame([], plan_service::calendar($user->id, $other)->lost);

        schoolyear_service::update($user->id, $yearid, (object) ['name' => 'Curs 2026-2027', 'startdate' => '2026-09-14',
            'enddate' => '2027-06-21', 'holidays' => '2026-10-26 Pont']);
        $dates = fn(calendar_result $r): array => array_map(fn(calendar_session $s): string => $s->date, $r->sessions);
        $this->assertContains('2026-10-12', $dates(plan_service::calendar($user->id, $id)));
        $this->assertNotContains('2026-10-26', $dates(plan_service::calendar($user->id, $id)));
        $this->assertContains('2026-10-26', $dates(plan_service::calendar($user->id, $other)));
    }

    /**
     * A save made from a version older than the stored plan is refused and changes nothing.
     *
     * @group REQ-DAT-003
     */
    public function test_a_save_from_an_older_version_is_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $yearid = self::year($user->id);
        $id = $this->plan($course, $yearid);
        $opened = (int) plan_service::get($user->id, $id)->timemodified;

        plan_service::update($user->id, $id, self::plandata($course->id, $yearid, ['timetable' => [0 => 2],
            'timemodified' => $opened]));
        $saved = plan_service::get($user->id, $id);
        $this->assertGreaterThan($opened, (int) $saved->timemodified);

        try {
            plan_service::update($user->id, $id, self::plandata($course->id, $yearid, ['name' => 'Stale',
                'timemodified' => $opened]));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_changedelsewhere', $e->errorcode);
        }
        $this->assertEquals($saved, plan_service::get($user->id, $id));
    }

    /**
     * Adding a plan takes the lock of its school year, the one a delete of that school year takes, so the school year
     * cannot be deleted between the check and the insert.
     *
     * @group REQ-DAT-006
     */
    public function test_create_waits_for_the_school_year_lock(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->lock_factory = '\core\lock\file_lock_factory';
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $yearid = self::year($user->id);
        $lock = schoolyear_service::lock($yearid);

        try {
            plan_service::create($user->id, self::plandata($course->id, $yearid));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('locktimeout', $e->errorcode);
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $DB->count_records('classprogress'));
    }

    /**
     * A draft whose school year was deleted (or never came with a restore) keeps working without holidays, refuses
     * import and approval until it gets another school year, and accepts one of the teacher's.
     *
     * @group REQ-DAT-006
     * @group REQ-PRV-004
     */
    public function test_a_draft_without_its_school_year_asks_for_one(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user($course);
        $yearid = self::year($user->id, '2026-10-12 Festa');
        $id = $this->plan($course, $yearid);
        plan_service::import($user->id, $id, self::PLAN);
        schoolyear_service::delete($user->id, $yearid);
        $plan = plan_service::get($user->id, $id);

        $this->assertNull(plan_service::school_year($plan));
        $this->assertNull(plan_service::calendar($user->id, $id)->code);
        $this->assertSame([], plan_service::holidays($user->id, $id));
        foreach (['check', 'import', 'check_stored', 'approve'] as $operation) {
            try {
                match ($operation) {
                    'check' => plan_service::check($user->id, $id, self::PLAN),
                    'import' => plan_service::import($user->id, $id, self::PLAN),
                    'check_stored' => plan_service::check_stored($user->id, $id),
                    'approve' => plan_service::approve($user->id, $id, '', true),
                };
                $this->fail("Expected an exception for {$operation}");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_noschoolyear', $e->errorcode, $operation);
            }
        }
        $this->assertSame(
            ['schoolyearid' => 'error_schoolyear'],
            plan_service::validate($user->id, self::plandata($course->id, $yearid), $id)
        );

        $newyear = self::year($user->id);
        plan_service::update($user->id, $id, self::plandata($course->id, $newyear));
        $this->assertSame(0, plan_service::count_issues(plan_service::check_stored($user->id, $id), 'error'));
    }

    /**
     * Every teacher of the course reaches the plan: the editing teacher who added it, another editing teacher and a
     * non-editing teacher can read it, import into it and approve it (REQ-MOD-004).
     *
     * @group REQ-MOD-004
     * @group REQ-OWN-001
     */
    public function test_the_teachers_of_the_course_share_the_plan(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->user($course);
        $colleague = $this->user($course);
        $assistant = $this->user($course, 'teacher');
        $id = $this->plan($course, self::year($owner->id));

        plan_service::import($colleague->id, $id, self::PLAN);
        $this->assertCount(2, plan_service::items($assistant->id, $id));
        plan_service::approve(
            $assistant->id,
            $id,
            plan_service::fingerprint(plan_service::check_stored($assistant->id, $id)),
            true
        );

        $plan = plan_service::get($owner->id, $id);
        $this->assertSame('approved', $plan->status);
        $this->assertSame((int) $assistant->id, (int) $plan->approvedby);
    }

    /**
     * Users who are not teachers of the plan's course.
     *
     * @return array Case => [actor].
     */
    public static function outsider_cases(): array {
        return [
            'student of the course' => ['student'],
            'teacher of another course' => ['other teacher'],
            'user without a role' => ['nobody'],
            'guest' => ['guest'],
        ];
    }

    /**
     * Nobody but the course's teachers reaches the plan, and a missing plan gives the same error: reading, the
     * calendar, import and approval all refuse with error_notfound.
     *
     * @dataProvider outsider_cases
     * @group REQ-OWN-001
     * @group REQ-OWN-002
     * @group REQ-MOD-002
     * @param string $actor Who tries.
     */
    public function test_nobody_else_reaches_the_plan(string $actor): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->user($course);
        $id = $this->plan($course, self::year($owner->id));
        plan_service::import($owner->id, $id, self::PLAN);
        $user = match ($actor) {
            'student' => $this->user($course, 'student'),
            'other teacher' => $this->user($this->getDataGenerator()->create_course()),
            'nobody' => $this->getDataGenerator()->create_user(),
            'guest' => guest_user(),
        };

        foreach ([$id, $id + 1000] as $planid) {
            foreach (['get', 'items', 'calendar', 'check', 'import', 'approve'] as $operation) {
                try {
                    match ($operation) {
                        'get' => plan_service::get($user->id, $planid),
                        'items' => plan_service::items($user->id, $planid),
                        'calendar' => plan_service::calendar($user->id, $planid),
                        'check' => plan_service::check($user->id, $planid, self::PLAN),
                        'import' => plan_service::import($user->id, $planid, self::PLAN),
                        'approve' => plan_service::approve($user->id, $planid, '', true),
                    };
                    $this->fail("Expected an exception for {$operation}");
                } catch (\moodle_exception $e) {
                    $this->assertSame('error_notfound', $e->errorcode, $operation);
                }
            }
        }
        $this->assertSame('draft', plan_service::get($owner->id, $id)->status);
    }

    /**
     * A user who may open the plan but not change it (a role with view and without edit) reads it and gets Moodle's
     * permission error on import and approval; a site administrator reaches it as Moodle allows.
     *
     * @group REQ-MOD-004
     * @group REQ-ACC-003
     */
    public function test_reading_and_changing_need_their_own_capability(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->user($course);
        $reader = $this->user($course, 'teacher');
        $id = $this->plan($course, self::year($owner->id));
        $context = access::context(plan_service::get($owner->id, $id));
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability(access::EDIT, CAP_PROHIBIT, $roleid, $context->id);

        $this->assertSame('draft', plan_service::get($reader->id, $id)->status);
        $this->expectException(\required_capability_exception::class);
        try {
            plan_service::import($reader->id, $id, self::PLAN);
        } finally {
            $this->assertNull(plan_service::get(get_admin()->id, $id)->sourcetext);
            plan_service::import(get_admin()->id, $id, self::PLAN);
            $this->assertNotNull(plan_service::get($owner->id, $id)->sourcetext);
        }
    }
}
