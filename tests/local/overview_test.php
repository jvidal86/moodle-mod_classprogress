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

use local_classprogress\output\index_page;

/**
 * Tests for the overview of a teacher's plan activities across courses, shown on local_classprogress's landing page.
 *
 * The clock is frozen at 2026-10-18 10:00 UTC; the teachers' time zone is Europe/Madrid.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\local\overview
 * @covers     \local_classprogress\output\index_page
 */
final class overview_test extends \advanced_testcase {
    /**
     * Lists every plan activity the teacher can open, in every course where they teach, with its course, status,
     * pending sessions of an approved plan, and link; activities of other courses are not listed, and a student gets
     * an empty list. The landing page of local_classprogress shows them.
     *
     * @group REQ-MOD-005
     */
    public function test_the_overview_lists_the_teachers_plan_activities_across_courses(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $daw1 = $this->getDataGenerator()->create_course(['fullname' => 'DAW 1']);
        $daw2 = $this->getDataGenerator()->create_course(['fullname' => 'DAW 2']);
        $other = $this->getDataGenerator()->create_course(['fullname' => 'SMX 1']);
        $teacher = $this->getDataGenerator()->create_and_enrol($daw1, 'editingteacher', ['timezone' => 'Europe/Madrid']);
        $this->getDataGenerator()->enrol_user($teacher->id, $daw2->id, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($daw1, 'student');
        $approved = $gen->create_instance(['course' => $daw1->id, 'name' => 'MP 0372 & Bases', 'status' => 'approved']);
        foreach (['2026-10-12', '2026-10-16', '2026-10-30'] as $date) {
            $item = $gen->create_item(['planid' => $approved->id, 'sessiondate' => $date]);
        }
        $gen->create_entry(['planitemid' => $gen->create_item(['planid' => $approved->id, 'sessiondate' => '2026-10-14'])->id]);
        $draft = $gen->create_instance(['course' => $daw2->id, 'name' => 'MP 0374']);
        $gen->create_instance(['course' => $other->id, 'name' => 'MP 0221']);
        unset($item);

        $list = overview::for_user($teacher->id);

        $this->assertSame(['MP 0372 &amp; Bases', 'MP 0374'], array_column($list, 'name'));
        $this->assertSame(['DAW 1', 'DAW 2'], array_column($list, 'course'));
        $this->assertSame([get_string('statusapprovedshort', 'mod_classprogress'),
            get_string('statusdraftshort', 'mod_classprogress')], array_column($list, 'status'));
        $this->assertSame([2, null], array_column($list, 'pending'));
        $this->assertSame((new \core\url('/mod/classprogress/view.php', ['id' => $draft->cmid]))->out(false), $list[1]['url']);
        $this->assertSame([], overview::for_user($student->id));
        $this->assertSame([], overview::for_user(0));

        $this->setUser($teacher);
        $html = $PAGE->get_renderer('core')->render(index_page::for_user($teacher->id));
        $this->assertStringContainsString('MP 0372 &amp; Bases', $html);
        $this->assertStringContainsString('DAW 2', $html);
        $this->assertStringNotContainsString('MP 0221', $html);
    }
}
