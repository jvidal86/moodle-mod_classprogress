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

use core_privacy\local\request\writer;
use mod_classprogress\local\authorship;
use tool_dataprivacy\api;
use tool_dataprivacy\data_request;
use tool_dataprivacy\task\initiate_data_request_task;
use tool_dataprivacy\task\process_data_request_task;

/**
 * Privacy requests end to end, through the site's data privacy tool (REQ-REL-006): a teacher's export request and
 * deletion request, approved and processed by their tasks as on a real site, with both plugins' providers.
 *
 * Every test sets up teacher 1 with a school year (a holiday, an evaluation period), an approved plan activity of that
 * year where teacher 1 and teacher 2 each wrote an entry, and a draft plan activity of the same year; teacher 3 has
 * their own school year and plan activity in another course. The site's data registry is not configured, as on a new
 * site, so a deletion request reaches every context of the user.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\privacy\provider
 * @covers     \local_classprogress\privacy\provider
 * @covers     \mod_classprogress\local\observers
 * @covers     \local_classprogress\local\observers
 */
final class request_test extends \advanced_testcase {
    /**
     * Creates the teachers, their school years and their plan activities.
     *
     * @return array Named users, plans and school years.
     */
    private function create_data(): array {
        $gen = $this->getDataGenerator();
        $mod = $gen->get_plugin_generator('mod_classprogress');
        $local = $gen->get_plugin_generator('local_classprogress');
        $course = $gen->create_course();
        $othercourse = $gen->create_course();
        $t1 = $gen->create_and_enrol($course, 'editingteacher');
        $t2 = $gen->create_and_enrol($course, 'teacher');
        $t3 = $gen->create_and_enrol($othercourse, 'editingteacher');

        $years = [];
        foreach (['t1' => $t1, 't3' => $t3] as $name => $user) {
            $year = $local->create_schoolyear(['userid' => $user->id, 'name' => "Curs de {$name}"]);
            $local->create_holiday(['schoolyearid' => $year->id, 'label' => "Festa de {$name}"]);
            $local->create_evaluation(['schoolyearid' => $year->id, 'name' => "1a avaluació de {$name}"]);
            $years[$name] = $year;
        }

        $plan = $mod->create_instance(['course' => $course->id, 'name' => 'Pla de t1', 'schoolyearid' => $years['t1']->id,
            'status' => 'approved']);
        $draft = $mod->create_instance(['course' => $course->id, 'name' => 'Esborrany de t1',
            'schoolyearid' => $years['t1']->id]);
        $other = $mod->create_instance(['course' => $othercourse->id, 'name' => 'Pla de t3',
            'schoolyearid' => $years['t3']->id, 'status' => 'approved']);

        $first = $mod->create_item(['planid' => $plan->id, 'sessiondate' => '2026-09-14']);
        $second = $mod->create_item(['planid' => $plan->id, 'sessiondate' => '2026-09-18']);
        $mine = $mod->create_entry(['planitemid' => $first->id, 'content' => 'Fet per t1']);
        $mod->create_entry_hist(['entryid' => $mine->id, 'userid' => $t1->id]);
        $mod->create_entry_hist(['entryid' => $mine->id, 'userid' => $t1->id, 'action' => 'update']);
        $theirs = $mod->create_entry(['planitemid' => $second->id, 'content' => 'Fet per t2']);
        $mod->create_entry_hist(['entryid' => $theirs->id, 'userid' => $t2->id]);
        $otheritem = $mod->create_item(['planid' => $other->id]);
        $entry = $mod->create_entry(['planitemid' => $otheritem->id, 'content' => 'Fet per t3']);
        $mod->create_entry_hist(['entryid' => $entry->id, 'userid' => $t3->id]);

        return ['t1' => $t1, 't2' => $t2, 't3' => $t3, 'years' => $years, 'plan' => $plan, 'draft' => $draft,
            'other' => $other];
    }

    /**
     * Creates a request for a user as the admin, approves it and runs the tasks that the site's cron would run.
     *
     * @param int $userid The user the request is for.
     * @param int $type api::DATAREQUEST_TYPE_EXPORT or api::DATAREQUEST_TYPE_DELETE.
     * @return data_request The request after processing.
     */
    private function process_request(int $userid, int $type): data_request {
        $this->setAdminUser();
        $request = api::create_data_request($userid, $type);
        ob_start();
        try {
            // With filtering on, an export request first gathers the user's contexts for the privacy officer.
            $this->runAdhocTasks(initiate_data_request_task::class);
            api::approve_data_request($request->get('id'));
            $this->runAdhocTasks(process_data_request_task::class);
        } finally {
            ob_end_clean();
        }
        return new data_request($request->get('id'));
    }

    /**
     * Reads every data file of a request's export.
     *
     * @param int $userid The user the export is for.
     * @param int $requestid The request.
     * @return array Path inside the export => every text value of its data.json.
     */
    private function exported(int $userid, int $requestid): array {
        $files = get_file_storage()->get_area_files(
            \context_user::instance($userid)->id,
            'tool_dataprivacy',
            'export',
            $requestid,
            'id',
            false
        );
        $this->assertCount(1, $files);
        $dir = make_request_directory();
        reset($files)->extract_to_pathname(get_file_packer('application/zip'), $dir);

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'data.json') {
                $data = json_decode(file_get_contents($file->getPathname()), true);
                $texts = [];
                array_walk_recursive($data, function ($value) use (&$texts): void {
                    $texts[] = (string) $value;
                });
                $found[substr($file->getPathname(), strlen($dir) + 1)] = $texts;
            }
        }
        return $found;
    }

    /**
     * The data files of an export that hold a text, and where they are.
     *
     * @param array $exported From exported().
     * @param string $text The text.
     * @return string[] Paths of the files holding it.
     */
    private static function paths_with(array $exported, string $text): array {
        return array_keys(array_filter($exported, fn(array $texts): bool => in_array($text, $texts, true)));
    }

    /**
     * What a plan activity holds, without the user ids that name its authors.
     *
     * @param int $planid Plan id.
     * @return array Table => rows.
     */
    private static function content(int $planid): array {
        global $DB;

        $strip = fn(array $rows): array => array_map(fn(\stdClass $row): array =>
            array_diff_key((array) $row, ['userid' => 1, 'realuserid' => 1, 'approvedby' => 1]), array_values($rows));
        $content = ['plan' => $strip([$DB->get_record('classprogress', ['id' => $planid], '*', MUST_EXIST)])];
        foreach (['item', 'holiday', 'evaluation', 'import', 'entry', 'entry_hist'] as $table) {
            $content[$table] = $strip($DB->get_records('classprogress_' . $table, ['planid' => $planid], 'id'));
        }
        return $content;
    }

    /**
     * A school year with its holidays and evaluation periods.
     *
     * @param int $yearid School year id.
     * @return array Table => rows.
     */
    private static function school_year(int $yearid): array {
        global $DB;

        return [
            'schoolyear' => $DB->get_records('local_classprogress_schoolyear', ['id' => $yearid]),
            'holiday' => $DB->get_records('local_classprogress_holiday', ['schoolyearid' => $yearid], 'id'),
            'evaluation' => $DB->get_records('local_classprogress_evaluation', ['schoolyearid' => $yearid], 'id'),
        ];
    }

    /**
     * Whether the privacy officer filters the contexts of an export request: off on a new site; on, the request
     * first gathers the user's contexts.
     *
     * @return array Case => [allowfiltering].
     */
    public static function filtering(): array {
        return ['without filtering' => [0], 'with filtering' => [1]];
    }

    /**
     * An approved export request for a teacher gives a file with their school year, its holidays and evaluation
     * periods, and, under their plan activity, the entry and history rows they wrote; nothing of other teachers.
     *
     * @dataProvider filtering
     * @group REQ-REL-006
     * @param int $allowfiltering The data privacy tool's allowfiltering setting.
     */
    public function test_an_export_request_gives_a_file_with_the_teachers_data(int $allowfiltering): void {
        $this->resetAfterTest();
        set_config('allowfiltering', $allowfiltering, 'tool_dataprivacy');
        writer::setup_real_writer_instance();
        $data = $this->create_data();

        $request = $this->process_request((int) $data['t1']->id, api::DATAREQUEST_TYPE_EXPORT);

        $this->assertEquals(api::DATAREQUEST_STATUS_DOWNLOAD_READY, $request->get('status'));
        $exported = $this->exported((int) $data['t1']->id, (int) $request->get('id'));
        $years = self::paths_with($exported, 'Curs de t1');
        $this->assertCount(1, $years);
        $this->assertContains('Festa de t1', $exported[$years[0]]);
        $this->assertContains('1a avaluació de t1', $exported[$years[0]]);

        $entries = self::paths_with($exported, 'Fet per t1');
        $this->assertCount(2, $entries, 'The entry, and the snapshot in its history');
        foreach ($entries as $path) {
            $this->assertStringContainsString('Pla de t1', $path);
        }
        $history = array_values(array_filter($entries, fn(string $path): bool =>
            str_contains($path, get_string('privacy:path:history', 'mod_classprogress'))));
        $this->assertCount(1, $history);
        $this->assertSame(['create', 'update'], array_values(array_intersect($exported[$history[0]], ['create', 'update'])));

        foreach (['Fet per t2', 'Curs de t3', 'Festa de t3', 'Fet per t3'] as $other) {
            $this->assertSame([], self::paths_with($exported, $other), $other);
        }
    }

    /**
     * An approved deletion request for a teacher deletes their school years with their holidays and evaluation
     * periods, anonymises their authorship in plan activities and keeps the content (REQ-PRV-003), leaves other
     * teachers' data alone, and then deletes the account itself.
     *
     * @group REQ-REL-006
     */
    public function test_a_deletion_request_erases_the_teachers_data_as_agreed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->assertFalse(\tool_dataprivacy\data_registry::defaults_set());
        $data = $this->create_data();
        $plan = self::content((int) $data['plan']->id);
        $draft = self::content((int) $data['draft']->id);
        $other = self::content((int) $data['other']->id);
        $otheryear = self::school_year((int) $data['years']['t3']->id);
        $this->assertSame([(int) $data['t1']->id, (int) $data['t2']->id], self::sorted(authorship::users_of($data['plan']->id)));

        $request = $this->process_request((int) $data['t1']->id, api::DATAREQUEST_TYPE_DELETE);

        $this->assertEquals(api::DATAREQUEST_STATUS_DELETED, $request->get('status'));
        $gone = ['schoolyear' => [], 'holiday' => [], 'evaluation' => []];
        $this->assertSame($gone, self::school_year((int) $data['years']['t1']->id));
        $this->assertSame([(int) $data['t2']->id], self::sorted(authorship::users_of($data['plan']->id)));
        $this->assertEquals($plan, self::content((int) $data['plan']->id));
        $this->assertEquals($draft, self::content((int) $data['draft']->id));
        $this->assertEquals($other, self::content((int) $data['other']->id));
        $this->assertSame([(int) $data['t3']->id], authorship::users_of($data['other']->id));
        $this->assertEquals($otheryear, self::school_year((int) $data['years']['t3']->id));
        // The data privacy tool deletes the account once the data is gone.
        $this->assertEquals(1, $DB->get_field('user', 'deleted', ['id' => $data['t1']->id]));
        $this->assertEquals(0, $DB->get_field('user', 'deleted', ['id' => $data['t2']->id]));
    }

    /**
     * Sorts user ids.
     *
     * @param int[] $ids User ids.
     * @return int[] The same ids, as integers, in order.
     */
    private static function sorted(array $ids): array {
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }
}
