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

namespace mod_classprogress\event;

/**
 * Tests for the five events: valid ones are triggered in the plan activity's module context, invalid ones are
 * refused, and none carries plan or entry text.
 *
 * The services that trigger them have their own tests; these tests create the events directly from generated rows.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      REQ-AUD-001
 * @covers     \mod_classprogress\event\base
 * @covers     \mod_classprogress\event\plan_imported
 * @covers     \mod_classprogress\event\plan_approved
 * @covers     \mod_classprogress\event\entry_event
 * @covers     \mod_classprogress\event\entry_created
 * @covers     \mod_classprogress\event\entry_updated
 * @covers     \mod_classprogress\event\entry_deleted
 */
final class events_test extends \advanced_testcase {
    /** @var string Free text put in every text field of the plan, which must never reach an event. */
    private const PLAN_TEXT = 'Secret plan text';

    /** @var string Free text put in every text field of the entry, which must never reach an event. */
    private const ENTRY_TEXT = 'Secret entry text about Anna';

    /**
     * The five events: class, crud, object (table), other (key => table of the row it points to).
     *
     * @return array Cases keyed by event.
     */
    public static function event_cases(): array {
        $entryother = ['planid' => 'classprogress', 'planitemid' => 'classprogress_item'];
        return [
            'plan_imported' => [plan_imported::class, 'c', 'classprogress', ['importid' => 'classprogress_import']],
            'plan_approved' => [plan_approved::class, 'u', 'classprogress', []],
            'entry_created' => [entry_created::class, 'c', 'classprogress_entry', $entryother],
            'entry_updated' => [entry_updated::class, 'u', 'classprogress_entry', $entryother],
            'entry_deleted' => [entry_deleted::class, 'd', 'classprogress_entry', $entryother],
        ];
    }

    /**
     * The events whose other holds ids.
     *
     * @return array Cases keyed by event.
     */
    public static function events_with_ids_cases(): array {
        return array_filter(self::event_cases(), fn(array $case): bool => !empty($case[3]));
    }

    /**
     * Every required key of other for every event, to leave out one at a time.
     *
     * @return array Cases keyed by event and key.
     */
    public static function other_key_cases(): array {
        $cases = [];
        foreach (self::event_cases() as $name => [$class, , $object, $other]) {
            foreach (array_keys($other) as $key) {
                $cases["{$name} {$key}"] = [$class, $object, $other, $key];
            }
        }
        return $cases;
    }

    /**
     * The three entry events.
     *
     * @return array Cases keyed by event.
     */
    public static function entry_event_cases(): array {
        return array_intersect_key(self::event_cases(), array_flip(['entry_created', 'entry_updated', 'entry_deleted']));
    }

    /**
     * Logs in a new teacher of a course with a plan activity that has an item, an entry and an import, all full of
     * text.
     *
     * @return array Table name => row, plus 'user' => the teacher.
     */
    private function create_records(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $plan = $gen->create_instance(['course' => $course->id, 'name' => self::PLAN_TEXT, 'modulecode' => self::PLAN_TEXT,
            'moduletitle' => self::PLAN_TEXT, 'sourcetext' => '# ' . self::PLAN_TEXT]);
        $item = $gen->create_item(['planid' => $plan->id, 'block' => self::PLAN_TEXT, 'title' => self::PLAN_TEXT,
            'content' => self::PLAN_TEXT]);
        $entry = $gen->create_entry(['planitemid' => $item->id, 'content' => self::ENTRY_TEXT,
            'reasonnote' => self::ENTRY_TEXT, 'measure' => self::ENTRY_TEXT]);
        $import = $gen->create_import(['planid' => $plan->id, 'userid' => $user->id, 'filename' => self::PLAN_TEXT . '.md']);

        return ['user' => $user, 'classprogress' => $plan, 'classprogress_item' => $item, 'classprogress_entry' => $entry,
            'classprogress_import' => $import];
    }

    /**
     * Builds valid data for an event about generated rows.
     *
     * @param array $records Rows from create_records().
     * @param string $object Table of the event's object.
     * @param array $other Key of other => table of the row it points to.
     * @return array The data for create().
     */
    private static function event_data(array $records, string $object, array $other): array {
        return [
            'context' => \context_module::instance($records['classprogress']->cmid),
            'objectid' => (int) $records[$object]->id,
            'other' => array_map(fn(string $table): int => (int) $records[$table]->id, $other),
        ];
    }

    /**
     * An event with valid data is triggered in the activity's module context with the acting user and the right
     * properties, and links to the activity.
     *
     * @dataProvider event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud.
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_event_is_triggered_with_valid_data(string $class, string $crud, string $object, array $other): void {
        $this->resetAfterTest();
        $records = $this->create_records();
        $data = self::event_data($records, $object, $other);

        $sink = $this->redirectEvents();
        $class::create($data)->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf($class, $event);
        $this->assertSame($data['context']->id, $event->get_context()->id);
        $this->assertSame(CONTEXT_MODULE, (int) $event->contextlevel);
        $this->assertSame($crud, $event->crud);
        $this->assertSame(\core\event\base::LEVEL_TEACHING, $event->edulevel);
        $this->assertSame($object, $event->objecttable);
        $this->assertSame($data['objectid'], $event->objectid);
        $this->assertEquals($data['other'] ?: null, $event->other ?: null);
        $this->assertSame((int) $records['user']->id, (int) $event->userid);
        $url = new \core\url('/mod/classprogress/view.php', ['id' => $records['classprogress']->cmid]);
        $this->assertSame($url->out(false), $event->get_url()->out(false));
        $nameid = 'event' . str_replace('_', '', substr(strrchr($class, '\\'), 1));
        $this->assertSame(get_string($nameid, 'mod_classprogress'), $event::get_name());
        $this->assertStringContainsString("'{$records['user']->id}'", $event->get_description());
        $this->assertStringContainsString("'{$data['objectid']}'", $event->get_description());
    }

    /**
     * Leaving out any required key of other is refused.
     *
     * @dataProvider other_key_cases
     * @param string $class Event class.
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     * @param string $key The key to leave out.
     */
    public function test_missing_other_key_is_refused(string $class, string $object, array $other, string $key): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        unset($data['other'][$key]);

        $this->expectException(\coding_exception::class);
        $class::create($data);
    }

    /**
     * A value of other that is not an id is refused, so text cannot be put there.
     *
     * @dataProvider other_key_cases
     * @param string $class Event class.
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     * @param string $key The key to fill with text.
     */
    public function test_text_in_other_is_refused(string $class, string $object, array $other, string $key): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        $data['other'][$key] = self::ENTRY_TEXT;

        $this->expectException(\coding_exception::class);
        $class::create($data);
    }

    /**
     * A key of other that the event does not define is refused.
     *
     * @dataProvider event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_unknown_other_key_is_refused(string $class, string $crud, string $object, array $other): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        $data['other']['content'] = self::ENTRY_TEXT;

        $this->expectException(\coding_exception::class);
        $class::create($data);
    }

    /**
     * An event whose other must hold ids is refused without it.
     *
     * @dataProvider events_with_ids_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_event_without_other_is_refused(string $class, string $crud, string $object, array $other): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        unset($data['other']);

        $this->expectException(\coding_exception::class);
        $class::create($data);
    }

    /**
     * An event without an object id is refused.
     *
     * @dataProvider event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_event_without_objectid_is_refused(string $class, string $crud, string $object, array $other): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        unset($data['objectid']);

        $this->expectException(\coding_exception::class);
        $class::create($data);
    }

    /**
     * An event outside a module context is refused.
     *
     * @dataProvider event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_event_outside_a_module_context_is_refused(
        string $class,
        string $crud,
        string $object,
        array $other
    ): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        $data['context'] = \context_course::instance($data['context']->get_course_context()->instanceid);

        $this->expectException(\coding_exception::class);
        $class::create($data);
    }

    /**
     * An entry event for an unplanned session has a null plan item.
     *
     * @dataProvider entry_event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_entry_event_for_an_unplanned_session(string $class, string $crud, string $object, array $other): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);
        $data['other']['planitemid'] = null;

        $event = $class::create($data);

        $this->assertNull($event->other['planitemid']);
    }

    /**
     * Neither the name, the description nor the logged data of an event holds plan or entry text, and other holds
     * only ids.
     *
     * @dataProvider event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_event_holds_no_free_text(string $class, string $crud, string $object, array $other): void {
        $this->resetAfterTest();
        $data = self::event_data($this->create_records(), $object, $other);

        $sink = $this->redirectEvents();
        $class::create($data)->trigger();
        $event = $sink->get_events()[0];
        $sink->close();

        $logged = $event::get_name() . ' ' . $event->get_description() . ' ' . json_encode($event->get_data());
        $this->assertStringNotContainsString(self::PLAN_TEXT, $logged);
        $this->assertStringNotContainsString(self::ENTRY_TEXT, $logged);
        foreach ($event->other as $value) {
            $this->assertIsInt($value);
        }
    }

    /**
     * The object and the ids in other point to the plugin's tables and are mapped on course restore under the
     * mappings the restore step sets, named after the tables.
     *
     * @dataProvider event_cases
     * @param string $class Event class.
     * @param string $crud Expected crud (unused).
     * @param string $object Table of the object.
     * @param array $other Keys of other => tables.
     */
    public function test_event_ids_are_mapped_on_restore(string $class, string $crud, string $object, array $other): void {
        $mapped = fn(string $table): array => ['db' => $table, 'restore' => $table];

        $this->assertSame($mapped($object), $class::get_objectid_mapping());
        $this->assertSame(array_map($mapped, $other), $class::get_other_mapping());
    }
}
