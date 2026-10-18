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

use local_classprogress\form\date_field;
use local_classprogress\local\schoolyear_service;
use mod_classprogress\form\plan_settings;

/**
 * Tests for the activity form: the school years it offers, the settings an approved plan freezes, and the days its
 * date selectors give. The refusal of a stale save is the plan service's (plan_service_test).
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress_mod_form
 * @covers     \mod_classprogress\form\plan_settings
 */
final class mod_form_test extends \advanced_testcase {
    /**
     * Loads the activity form.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/classprogress/mod_form.php');
    }

    /**
     * Builds the activity form as course/modedit.php does, to add an activity or to edit one.
     *
     * @param \stdClass $course The course.
     * @param \stdClass|null $plan The activity to edit, with cmid; null to add one.
     * @param array $post Values to post, as a teacher would submit the form; none to only show it.
     * @return array [the form, with the activity's values; those values].
     */
    private static function form(\stdClass $course, ?\stdClass $plan = null, array $post = []): array {
        global $PAGE;

        // A new page for each form, set to the course as require_login() in course/modedit.php does.
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url('/course/modedit.php');
        if ($plan === null) {
            [, , $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'classprogress', 0);
        } else {
            [$cm, , , $data, $cw] = get_moduleinfo_data(get_coursemodule_from_id('classprogress', $plan->cmid), $course);
        }
        if ($post) {
            // The stored days are not what a date selector posts; the form gives them back from its defaults.
            \mod_classprogress_mod_form::mock_submit($post + ['availabilityconditionsjson' => '']
                + array_diff_key((array) $data, ['startdate' => 1, 'enddate' => 1]));
        }
        $form = new \mod_classprogress_mod_form($data, $cw->section, $cm, $course);
        $form->set_data($data);
        return [$form, $data];
    }

    /**
     * The form offers the adding teacher's own school years and no other teacher's; with none it says how to make one.
     *
     * @group REQ-MOD-001
     * @group REQ-OWN-001
     */
    public function test_the_form_offers_the_teachers_own_school_years(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $local = $this->getDataGenerator()->get_plugin_generator('local_classprogress');
        $local->create_schoolyear(['userid' => $teacher->id, 'name' => 'Curs <2026>']);
        $local->create_schoolyear(['userid' => $other->id, 'name' => 'Curs del Bernat']);

        $this->setUser($teacher);
        $html = self::form($course)[0]->render();
        $this->setUser($other);
        $local->create_schoolyear(['userid' => $this->getDataGenerator()->create_user()->id, 'name' => 'Curs aliè']);
        $empty = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($empty);
        $none = self::form($course)[0]->render();

        $this->assertStringContainsString('Curs &lt;2026&gt;', $html);
        $this->assertStringNotContainsString('Curs del Bernat', $html);
        $this->assertStringNotContainsString('Curs aliè', $none);
        $this->assertStringContainsString('/local/classprogress/schoolyear.php', $none);
    }

    /**
     * For an approved plan the form freezes the school year, class days and timetable, says why, and gives the
     * stored values back whatever is posted for them.
     *
     * @group REQ-LCK-001
     */
    public function test_an_approved_plan_freezes_its_locked_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $plan = $this->getDataGenerator()->get_plugin_generator('mod_classprogress')->create_instance(['course' => $course->id,
            'status' => 'approved', 'timetable' => [0 => 2]]);

        $html = self::form($course, $plan)[0]->render();
        [$form] = self::form($course, $plan, ['name' => 'Nou nom', 'hours0' => 5, 'schoolyearid' => 999]);
        $data = $form->get_data();

        $this->assertStringContainsString(get_string('settingslocked', 'mod_classprogress'), $html);
        $this->assertSame('Nou nom', $data->name);
        $this->assertEquals(2, $data->hours0);
        $this->assertSame((int) $plan->schoolyearid, (int) $data->schoolyearid);
    }

    /**
     * Time zones far from UTC on both sides, and a day around a daylight saving change.
     *
     * @return array Case => [time zone, day].
     */
    public static function day_cases(): array {
        return [
            'Kiritimati' => ['Pacific/Kiritimati', '2026-09-14'],
            'Pago Pago' => ['Pacific/Pago_Pago', '2027-06-21'],
            'Madrid, the day summer time ends' => ['Europe/Madrid', '2026-10-25'],
        ];
    }

    /**
     * A class day picked in the teacher's own time zone is the day stored, and a stored day is the day shown.
     *
     * @dataProvider day_cases
     * @group REQ-CAL-004
     * @param string $timezone The teacher's time zone.
     * @param string $day The day.
     */
    public function test_class_days_keep_their_day_far_from_utc(string $timezone, string $day): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => $timezone]));

        $data = plan_settings::to_service_data((object) ['startdate' => date_field::to_timestamp($day), 'enddate' => 0]);
        $values = plan_settings::from_record((object) ['timemodified' => 1, 'schoolyearid' => 0, 'startdate' => $day,
            'enddate' => null], []);

        $this->assertSame([$day, ''], [$data->startdate, $data->enddate]);
        $this->assertSame(date_field::to_timestamp($day), $values['startdate']);
        $this->assertSame(0, $values['enddate']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], array_values($data->timetable));
        $this->assertNull(schoolyear_service::find(0));
    }
}
