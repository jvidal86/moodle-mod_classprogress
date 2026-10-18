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

use mod_classprogress\local\plan_service;

/**
 * Tests for backing up a course with plan activities and restoring it into a new course (REQ-MOD-006).
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_classprogress_activity_structure_step
 * @covers     \restore_classprogress_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Loads the backup and restore libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Backs up a course and restores it into a new course, as the admin.
     *
     * @param \stdClass $course The course.
     * @param bool $userdata Whether the backup includes users and their data.
     * @return int The new course id.
     */
    private function backup_and_restore(\stdClass $course, bool $userdata): int {
        global $CFG, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $folder = 'classprogress-' . $course->id . '-' . (int) $userdata;
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $folder);
        $bc->destroy();

        $newid = \restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '-copy' . (int) $userdata,
            $course->category
        );
        $rc = new \restore_controller(
            $folder,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newid;
    }

    /**
     * An approved plan with days, imports, copies, entries and history, among them the history of a deleted entry.
     *
     * @return array [course, plan row, teacher].
     */
    private function approved_plan(): array {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $year = $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_schoolyear(
            ['userid' => $teacher->id]
        );
        $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_holiday(
            ['schoolyearid' => $year->id, 'label' => 'Festa']
        );
        $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_evaluation(
            ['schoolyearid' => $year->id, 'name' => '1a avaluació']
        );
        $plan = $generator->create_instance(['course' => $course->id, 'schoolyearid' => $year->id, 'status' => 'approved',
            'modulecode' => 'MP 0372', 'moduletitle' => 'Bases', 'sourcetext' => "# MP 0372 · Bases\n", 'intro' => 'Pla',
            'timetable' => [0 => 2, 4 => 2]]);
        $first = $generator->create_item(['planid' => $plan->id, 'sessiondate' => '2026-09-14', 'title' => 'Inici']);
        $second = $generator->create_item(['planid' => $plan->id, 'sessiondate' => '2026-09-18', 'title' => 'Entorn']);
        $generator->create_import(['planid' => $plan->id, 'userid' => $teacher->id, 'daycount' => 2]);
        $entry = $generator->create_entry(['planitemid' => $first->id, 'content' => 'Fet']);
        $unplanned = $generator->create_entry(['planid' => $plan->id, 'status' => 'added', 'content' => 'Extra']);
        $gone = $generator->create_entry(['planitemid' => $second->id, 'content' => 'Esborrat']);
        foreach ([$entry, $unplanned, $gone] as $row) {
            $generator->create_entry_hist(['entryid' => $row->id, 'userid' => $teacher->id]);
        }
        $generator->create_entry_hist(['entryid' => $gone->id, 'userid' => $teacher->id, 'action' => 'delete']);
        $DB->delete_records('classprogress_entry', ['id' => $gone->id]);
        return [$course, $DB->get_record('classprogress', ['id' => $plan->id]), $teacher];
    }

    /**
     * The rows of a restored course's only plan activity, without ids that change.
     *
     * @param int $courseid Course id.
     * @return array Table => rows as arrays of the fields that must survive.
     */
    private static function content(int $courseid): array {
        global $DB;

        $plan = $DB->get_record('classprogress', ['course' => $courseid], '*', MUST_EXIST);
        $rows = fn(string $table, string $fields): array => array_values(array_map(
            fn(\stdClass $row): array => array_diff_key((array) $row, ['id' => true]),
            $DB->get_records($table, ['planid' => $plan->id], 'id', 'id, ' . $fields)
        ));
        return [
            'plan' => [$plan->name, $plan->intro, $plan->modulecode, $plan->moduletitle, $plan->startdate, $plan->enddate,
                $plan->status, $plan->sourcetext, $plan->yearstart, $plan->yearend, (int) $plan->timeapproved],
            'slot' => $rows('classprogress_slot', 'weekday, hours'),
            'item' => $rows('classprogress_item', 'sortorder, sessiondate, block, title, content, hours'),
            'import' => $rows('classprogress_import', 'source, contenthash, daycount'),
            'holiday' => $rows('classprogress_holiday', 'startdate, enddate, label'),
            'evaluation' => $rows('classprogress_evaluation', 'name, startdate, enddate'),
            'entry' => $rows('classprogress_entry', 'sessiondate, status, content, writtenon'),
            'hist' => $rows('classprogress_entry_hist', 'action, snapshot'),
        ];
    }

    /**
     * With user data the restored approved plan is complete on its own: settings, text, days, imports, copies,
     * entries on their restored days and the whole history; it has no school year and needs none.
     *
     * @group REQ-MOD-006
     * @group REQ-LCK-004
     */
    public function test_a_backup_with_user_data_restores_the_whole_plan(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $plan, $teacher] = $this->approved_plan();
        $before = self::content($course->id);

        $newcourseid = $this->backup_and_restore($course, true);

        $this->assertEquals($before, self::content($newcourseid));
        $restored = $DB->get_record('classprogress', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('0', (string) $restored->schoolyearid);
        $this->assertSame((string) $teacher->id, (string) $restored->approvedby);
        $entry = $DB->get_record(
            'classprogress_entry',
            ['planid' => $restored->id, 'status' => 'done', 'sessiondate' => '2026-09-14'],
            '*',
            MUST_EXIST
        );
        $item = $DB->get_record('classprogress_item', ['id' => $entry->planitemid], '*', MUST_EXIST);
        $this->assertSame([(string) $restored->id, 'Inici'], [(string) $item->planid, $item->title]);
        $hist = $DB->get_records('classprogress_entry_hist', ['planid' => $restored->id], 'id');
        $this->assertCount(4, $hist);
        $this->assertSame(2, count(array_filter($hist, fn(\stdClass $row): bool => (int) $row->entryid === 0)));
        $this->assertSame([(string) $teacher->id], array_values(array_unique(array_column($hist, 'userid'))));
        $this->setUser($teacher);
        $this->assertNull(plan_service::calendar((int) $teacher->id, (int) $restored->id)->code);
        $this->assertCount(1, plan_service::holidays((int) $teacher->id, (int) $restored->id));
    }

    /**
     * Without user data the plan, timetable, days, imports and copies are restored, and no entry or history row.
     *
     * @group REQ-MOD-006
     */
    public function test_a_backup_without_user_data_keeps_the_plan_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->approved_plan();
        $before = self::content($course->id);

        $after = self::content($this->backup_and_restore($course, false));

        $this->assertSame([], $after['entry']);
        $this->assertSame([], $after['hist']);
        unset($before['entry'], $before['hist'], $after['entry'], $after['hist']);
        $this->assertEquals($before, $after);
    }

    /**
     * A restored draft has no school year, and asks for one before it is imported or approved (REQ-DAT-006).
     *
     * @group REQ-MOD-006
     * @group REQ-DAT-006
     */
    public function test_a_restored_draft_asks_for_a_school_year(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->get_plugin_generator('mod_classprogress')->create_instance(['course' => $course->id,
            'sourcetext' => "### 2026-09-14\nx\n"]);

        $restored = $DB->get_record('classprogress', ['course' => $this->backup_and_restore($course, false)], '*', MUST_EXIST);

        $this->assertSame(['draft', '0'], [$restored->status, (string) $restored->schoolyearid]);
        $this->assertNull(plan_service::school_year($restored));
        $this->expectExceptionObject(new \moodle_exception('error_noschoolyear', 'mod_classprogress'));
        plan_service::check_stored((int) get_admin()->id, (int) $restored->id);
    }
}
