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

namespace mod_classprogress;

use mod_classprogress\local\access;
use mod_classprogress\local\plan_service;

/**
 * Tests for the activity's callbacks: features, capabilities, who sees the activity, deletion and course reset.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::classprogress_supports
 * @covers     ::classprogress_cm_info_dynamic
 * @covers     ::classprogress_delete_instance
 * @covers     ::classprogress_reset_userdata
 * @covers     ::classprogress_update_instance
 */
final class lib_test extends \advanced_testcase {
    /**
     * Loads the libraries the callbacks are called from.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/classprogress/lib.php');
    }

    /**
     * The activity generator.
     *
     * @return \mod_classprogress_generator The generator.
     */
    private function generator(): \mod_classprogress_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
    }

    /**
     * The activity has a description and backup, its purpose is administration, and it has no grades, groups or
     * completion rules.
     *
     * @group REQ-MOD-001
     */
    public function test_supported_features(): void {
        $this->assertTrue(classprogress_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(classprogress_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(classprogress_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertSame(MOD_PURPOSE_ADMINISTRATION, classprogress_supports(FEATURE_MOD_PURPOSE));
        $unsupported = [FEATURE_GRADE_HAS_GRADE, FEATURE_GROUPS, FEATURE_COMPLETION_TRACKS_VIEWS, FEATURE_COMPLETION_HAS_RULES];
        foreach ($unsupported as $feature) {
            $this->assertFalse(classprogress_supports($feature));
        }
        $this->assertNull(classprogress_supports('unknown'));
    }

    /**
     * Editing teachers and managers may add the activity; view and edit belong to every kind of teacher and to
     * managers; no archetype of students, authenticated users or guests holds any of them.
     *
     * @group REQ-MOD-001
     * @group REQ-MOD-002
     */
    public function test_capability_archetypes(): void {
        $expected = [
            'mod/classprogress:addinstance' => ['editingteacher', 'manager'],
            'mod/classprogress:view' => ['editingteacher', 'manager', 'teacher'],
            'mod/classprogress:edit' => ['editingteacher', 'manager', 'teacher'],
        ];
        foreach ($expected as $capability => $archetypes) {
            $info = get_capability_info($capability);
            $this->assertNotEmpty($info, $capability);
            $allowed = [];
            $all = ['manager', 'coursecreator', 'editingteacher', 'teacher', 'student', 'guest', 'user', 'frontpage'];
            foreach ($all as $archetype) {
                if ((get_default_capabilities($archetype)[$capability] ?? null) === CAP_ALLOW) {
                    $allowed[] = $archetype;
                }
            }
            sort($allowed);
            $this->assertSame($archetypes, $allowed, $capability);
        }
        $this->assertSame(CONTEXT_MODULE, (int) get_capability_info(access::VIEW)->contextlevel);
    }

    /**
     * The activity is offered in the activity chooser of editing teachers, and not of non-editing teachers or
     * students.
     *
     * @group REQ-MOD-001
     */
    public function test_only_editing_teachers_and_managers_may_add_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = [];
        foreach (['editingteacher', 'manager', 'teacher', 'student'] as $role) {
            $users[$role] = $this->getDataGenerator()->create_and_enrol($course, $role);
        }

        $allowed = array_map(fn(\stdClass $user): bool => course_allowed_module($course, 'classprogress', $user), $users);

        $this->assertSame(['editingteacher' => true, 'manager' => true, 'teacher' => false, 'student' => false], $allowed);
    }

    /**
     * Teachers of the course see the activity and can open it; students, even of the same course, neither see it on
     * the course page nor open it by URL.
     *
     * @group REQ-MOD-002
     */
    public function test_students_neither_see_nor_open_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $plan = $this->generator()->create_instance(['course' => $course->id]);

        $this->setUser($teacher);
        $cm = get_fast_modinfo($course)->get_cm($plan->cmid);
        $this->assertTrue($cm->uservisible);
        $this->assertTrue($cm->is_visible_on_course_page());
        require_login($course, false, $cm, false, true);

        $this->setUser($student);
        $cm = get_fast_modinfo($course)->get_cm($plan->cmid);
        $this->assertFalse($cm->uservisible);
        $this->assertFalse($cm->is_visible_on_course_page());
        $this->expectException(\require_login_exception::class);
        require_login($course, false, $cm, false, true);
    }

    /**
     * Deleting the course deletes its plan activity with every row that hangs from it, and nothing of another
     * course's activity. (course_delete_module() is deprecated from Moodle 5.2, so the test deletes the course.)
     *
     * @group REQ-MOD-007
     */
    public function test_deleting_the_activity_deletes_all_its_data(): void {
        global $DB;

        $this->resetAfterTest();
        $courses = [];
        $plans = [];
        foreach ([1, 2] as $n) {
            $courses[$n] = $this->getDataGenerator()->create_course();
            $plan = $this->generator()->create_instance(['course' => $courses[$n]->id, 'status' => 'approved',
                'timetable' => [0 => 2]]);
            $item = $this->generator()->create_item(['planid' => $plan->id]);
            $entry = $this->generator()->create_entry(['planitemid' => $item->id]);
            $this->generator()->create_entry_hist(['entryid' => $entry->id, 'userid' => 2]);
            $this->generator()->create_import(['planid' => $plan->id, 'userid' => 2]);
            $DB->insert_record('classprogress_holiday', ['planid' => $plan->id, 'startdate' => '2026-10-12',
                'enddate' => '2026-10-12', 'label' => 'Festa']);
            $DB->insert_record('classprogress_evaluation', ['planid' => $plan->id, 'name' => '1a',
                'startdate' => '2026-09-14', 'enddate' => '2026-12-22']);
            $plans[$n] = $plan;
        }
        $kept = array_map(
            fn(string $table): int => $DB->count_records($table, ['planid' => $plans[2]->id]),
            array_combine(plan_service::CHILDREN, plan_service::CHILDREN)
        );

        delete_course($courses[1], false);

        $this->assertFalse($DB->record_exists('classprogress', ['id' => $plans[1]->id]));
        foreach (plan_service::CHILDREN as $table) {
            $this->assertSame(0, $DB->count_records($table, ['planid' => $plans[1]->id]), $table);
            $this->assertSame($kept[$table], $DB->count_records($table, ['planid' => $plans[2]->id]), $table);
        }
        $this->assertGreaterThan(0, array_sum($kept));
        $this->assertFalse(classprogress_delete_instance($plans[1]->id));
    }

    /**
     * A course reset that removes user data deletes the activities' entries and history and keeps their plans; without
     * the option nothing goes.
     *
     * @group REQ-MOD-007
     */
    public function test_course_reset_removes_entries_and_history_and_keeps_the_plan(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $counts = function (int $planid) use ($DB): array {
            return array_map(
                fn(string $table): int => $DB->count_records($table, ['planid' => $planid]),
                ['entry' => 'classprogress_entry', 'hist' => 'classprogress_entry_hist', 'item' => 'classprogress_item']
            );
        };
        $planids = [];
        foreach ([$course, $other] as $c) {
            $plan = $this->generator()->create_instance(['course' => $c->id, 'status' => 'approved']);
            $item = $this->generator()->create_item(['planid' => $plan->id]);
            $entry = $this->generator()->create_entry(['planitemid' => $item->id]);
            $this->generator()->create_entry_hist(['entryid' => $entry->id, 'userid' => 2]);
            $planids[] = (int) $plan->id;
        }

        $this->assertSame([], classprogress_reset_userdata((object) ['courseid' => $course->id]));
        $this->assertSame(['entry' => 1, 'hist' => 1, 'item' => 1], $counts($planids[0]));
        $status = classprogress_reset_userdata((object) ['courseid' => $course->id, 'reset_classprogress_entries' => 1]);

        $this->assertCount(1, $status);
        $this->assertFalse($status[0]['error']);
        $this->assertSame(['entry' => 0, 'hist' => 0, 'item' => 1], $counts($planids[0]));
        $this->assertSame(['entry' => 1, 'hist' => 1, 'item' => 1], $counts($planids[1]));
        $this->assertSame('approved', $DB->get_field('classprogress', 'status', ['id' => $planids[0]]));
        $this->assertSame(['reset_classprogress_entries' => 1], classprogress_reset_course_form_defaults($course));
    }

    /**
     * The settings of an approved plan take a new name and description, and refuse a change of school year, class
     * days or timetable through the activity form's callback.
     *
     * @group REQ-LCK-001
     */
    public function test_an_approved_plan_takes_only_a_new_name_and_description(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $plan = $this->generator()->create_instance(['course' => $course->id, 'status' => 'approved',
            'timetable' => [0 => 2]]);
        $form = (object) ['instance' => $plan->id, 'course' => $course->id, 'name' => 'Nou nom', 'intro' => 'Nova',
            'introformat' => FORMAT_HTML, 'schoolyearid' => $plan->schoolyearid,
            'startdate' => \local_classprogress\form\date_field::to_timestamp($plan->startdate),
            'enddate' => \local_classprogress\form\date_field::to_timestamp($plan->enddate), 'hours0' => 2];

        $this->assertTrue(classprogress_update_instance($form));
        $stored = plan_service::get($teacher->id, $plan->id);
        $this->assertSame(['Nou nom', 'Nova'], [$stored->name, $stored->intro]);

        $form->hours0 = 3;
        $this->expectExceptionObject(new \moodle_exception('error_planlocked', 'mod_classprogress'));
        classprogress_update_instance($form);
    }
}
