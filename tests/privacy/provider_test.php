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
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_classprogress\local\authorship;

/**
 * Tests for the privacy provider: metadata, the export of what a teacher authored in plan activities, and the erasure
 * that anonymises authorship and keeps the content (REQ-PRV-001 to 004).
 *
 * Every test sets up two plan activities in two courses; in the first, teacher 1 approved the plan and imported its
 * text, teacher 1 and teacher 2 each wrote entries, and an admin changed one while logged in as teacher 1.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\privacy\provider
 * @covers     \mod_classprogress\local\authorship
 * @covers     \mod_classprogress\local\observers
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'mod_classprogress';

    /**
     * Creates the two activities and their authors.
     *
     * @return array Named users, plans, contexts and rows.
     */
    private function create_data(): array {
        global $DB;

        $gen = $this->getDataGenerator()->get_plugin_generator(self::COMPONENT);
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $t1 = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $t2 = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $t3 = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $admin = get_admin();
        $year = $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_schoolyear(['userid' => $t1->id]);
        $plan = $gen->create_instance(['course' => $course->id, 'schoolyearid' => $year->id, 'status' => 'approved']);
        $otheryear = $this->getDataGenerator()->get_plugin_generator('local_classprogress')->create_schoolyear(
            ['userid' => $t3->id]
        );
        $other = $gen->create_instance(['course' => $othercourse->id, 'schoolyearid' => $otheryear->id,
            'status' => 'approved']);
        $gen->create_import(['planid' => $plan->id, 'userid' => $t1->id, 'daycount' => 2, 'filename' => 'pla.md']);
        $item = $gen->create_item(['planid' => $plan->id, 'sessiondate' => '2026-09-14']);
        $mine = $gen->create_entry(['planitemid' => $item->id, 'content' => 'Fet per T1; Anna absent']);
        $theirs = $gen->create_entry(['planid' => $plan->id, 'status' => 'added', 'content' => 'Afegit per T2']);
        $gen->create_entry_hist(['entryid' => $mine->id, 'userid' => $t1->id]);
        $gen->create_entry_hist(['entryid' => $mine->id, 'userid' => $t1->id, 'realuserid' => $admin->id,
            'action' => 'update']);
        $gen->create_entry_hist(['entryid' => $theirs->id, 'userid' => $t2->id]);
        $otheritem = $gen->create_item(['planid' => $other->id]);
        $gen->create_entry_hist(['entryid' => $gen->create_entry(['planitemid' => $otheritem->id])->id, 'userid' => $t3->id]);
        return [
            't1' => $t1, 't2' => $t2, 't3' => $t3, 'admin' => $admin,
            'plan' => $DB->get_record('classprogress', ['id' => $plan->id]), 'other' => $other,
            'context' => \context_module::instance($plan->cmid), 'othercontext' => \context_module::instance($other->cmid),
            'course' => $course,
        ];
    }

    /**
     * The content of a plan activity that erasure must keep: plan, imports, entries and history, without user ids.
     *
     * @param int $planid Plan id.
     * @return array Table => rows without their user fields.
     */
    private static function content(int $planid): array {
        global $DB;

        $strip = fn(array $rows): array => array_map(fn(\stdClass $row): array =>
            array_diff_key((array) $row, ['userid' => 1, 'realuserid' => 1, 'approvedby' => 1]), array_values($rows));
        return [
            'plan' => $strip([$DB->get_record('classprogress', ['id' => $planid])]),
            'import' => $strip($DB->get_records('classprogress_import', ['planid' => $planid], 'id')),
            'entry' => $strip($DB->get_records('classprogress_entry', ['planid' => $planid], 'id')),
            'hist' => $strip($DB->get_records('classprogress_entry_hist', ['planid' => $planid], 'id')),
        ];
    }

    /**
     * The user ids named as authors in a plan.
     *
     * @param int $planid Plan id.
     * @return int[] Sorted user ids, without the deleted-user placeholder.
     */
    private static function authors(int $planid): array {
        $ids = authorship::users_of($planid);
        sort($ids);
        return $ids;
    }

    /**
     * Every table and field that holds personal data or text that may name students is described with a string, and
     * the description warns about students.
     *
     * @group REQ-PRV-001
     */
    public function test_metadata_describes_every_table_with_personal_data(): void {
        $items = provider::get_metadata(new collection(self::COMPONENT))->get_collection();

        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_name();
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), self::COMPONENT), $item->get_name());
            foreach ($item->get_privacy_fields() as $field => $stringid) {
                $this->assertSame("privacy:metadata:{$item->get_name()}:{$field}", $stringid);
                $this->assertTrue(get_string_manager()->string_exists($stringid, self::COMPONENT), $stringid);
            }
        }
        $this->assertSame(
            ['classprogress', 'classprogress_import', 'classprogress_entry', 'classprogress_entry_hist'],
            $names
        );
        foreach (['classprogress_entry', 'classprogress_entry_hist'] as $table) {
            $this->assertStringContainsString('students', get_string('privacy:metadata:' . $table, self::COMPONENT));
        }
    }

    /**
     * A user's data lives in the module context of each plan activity where they are named as an author, and the
     * users of a context are its authors, the logged-in-as admin included.
     *
     * @group REQ-PRV-002
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        $data = $this->create_data();
        $contextids = fn(\stdClass $user): array =>
            array_map('intval', provider::get_contexts_for_userid($user->id)->get_contextids());

        $this->assertSame([(int) $data['context']->id], $contextids($data['t1']));
        $this->assertSame([(int) $data['context']->id], $contextids($data['t2']));
        $this->assertSame([(int) $data['context']->id], $contextids($data['admin']));
        $this->assertSame([(int) $data['othercontext']->id], $contextids($data['t3']));
        $this->assertSame([], $contextids($this->getDataGenerator()->create_user()));

        $userlist = new userlist($data['context'], self::COMPONENT);
        provider::get_users_in_context($userlist);
        $expected = [(int) $data['t1']->id, (int) $data['t2']->id, (int) $data['admin']->id];
        sort($expected);
        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $this->assertSame($expected, $found);
        $courselist = new userlist(\context_course::instance($data['course']->id), self::COMPONENT);
        provider::get_users_in_context($courselist);
        $this->assertSame([], $courselist->get_userids());
    }

    /**
     * The export holds, in the activity's context, what the user authored: the approval, the imports, the entries
     * they first wrote and every history row they made; nothing of another teacher's entries.
     *
     * @group REQ-PRV-002
     */
    public function test_export_holds_what_the_user_authored(): void {
        $this->resetAfterTest();
        $data = $this->create_data();
        $path = fn(string $name): array => [get_string('privacy:path:' . $name, self::COMPONENT)];

        $this->export_context_data_for_user($data['t1']->id, $data['context'], self::COMPONENT);

        $writer = writer::with_context($data['context']);
        $this->assertNotEmpty($writer->get_data($path('approval'))->timeapproved);
        $this->assertSame('pla.md', $writer->get_data($path('imports'))->imports[0]->filename);
        $entries = $writer->get_data($path('entries'))->entries;
        $this->assertSame(['Fet per T1; Anna absent'], array_column($entries, 'content'));
        $history = $writer->get_data($path('history'))->history;
        $this->assertSame(['create', 'update'], array_column($history, 'action'));
        $this->assertSame('Fet per T1; Anna absent', $history[0]->snapshot->content);
        $this->assertFalse(writer::with_context($data['othercontext'])->has_any_data());

        writer::reset();
        $this->export_context_data_for_user($data['t2']->id, $data['context'], self::COMPONENT);
        $writer = writer::with_context($data['context']);
        $this->assertSame([], $writer->get_data($path('approval')));
        $this->assertSame(['Afegit per T2'], array_column($writer->get_data($path('entries'))->entries, 'content'));
    }

    /**
     * Erasing a user in an activity replaces their user id by the deleted-user placeholder wherever they authored
     * something, keeps the content, and leaves other users and other activities alone.
     *
     * @group REQ-PRV-003
     */
    public function test_erasing_a_user_anonymises_their_authorship_and_keeps_the_content(): void {
        $this->resetAfterTest();
        $data = $this->create_data();
        $before = self::content($data['plan']->id);
        $other = self::content($data['other']->id);

        provider::delete_data_for_user(new approved_contextlist(
            $data['t1'],
            self::COMPONENT,
            [$data['context']->id, $data['othercontext']->id]
        ));

        $this->assertEquals($before, self::content($data['plan']->id));
        $this->assertEquals($other, self::content($data['other']->id));
        $authors = [(int) $data['t2']->id, (int) $data['admin']->id];
        sort($authors);
        $this->assertSame($authors, self::authors($data['plan']->id));
        $this->assertSame([(int) $data['t3']->id], self::authors($data['other']->id));
        $this->assertSame([], provider::get_contexts_for_userid($data['t1']->id)->get_contextids());
    }

    /**
     * Erasing a list of users of a context anonymises only them; erasing all users of a context anonymises everyone
     * there and keeps the content.
     *
     * @group REQ-PRV-003
     */
    public function test_erasing_users_of_a_context(): void {
        $this->resetAfterTest();
        $data = $this->create_data();
        $before = self::content($data['plan']->id);

        provider::delete_data_for_users(new approved_userlist($data['context'], self::COMPONENT, [$data['admin']->id]));
        $authors = [(int) $data['t1']->id, (int) $data['t2']->id];
        sort($authors);
        $this->assertSame($authors, self::authors($data['plan']->id));

        provider::delete_data_for_all_users_in_context($data['context']);
        provider::delete_data_for_all_users_in_context(\context_course::instance($data['course']->id));

        $this->assertSame([], self::authors($data['plan']->id));
        $this->assertEquals($before, self::content($data['plan']->id));
        $this->assertSame([(int) $data['t3']->id], self::authors($data['other']->id));
    }

    /**
     * Deleting a user account anonymises their authorship in every activity at once, without a privacy request.
     *
     * @group REQ-PRV-004
     */
    public function test_deleting_an_account_anonymises_its_authorship(): void {
        $this->resetAfterTest();
        $data = $this->create_data();
        $before = self::content($data['plan']->id);

        delete_user($data['t1']);

        $this->assertEquals($before, self::content($data['plan']->id));
        $authors = [(int) $data['t2']->id, (int) $data['admin']->id];
        sort($authors);
        $this->assertSame($authors, self::authors($data['plan']->id));
        $this->assertSame([(int) $data['t3']->id], self::authors($data['other']->id));
    }
}
