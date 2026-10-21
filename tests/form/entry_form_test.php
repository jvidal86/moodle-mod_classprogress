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

namespace mod_classprogress\form;

use local_classprogress\local\entry_rules;

/**
 * Tests for the entry form: the status as three radio buttons or, after today, as fixed text; the reason labelled as
 * required; the second save button; the sizes of the text boxes.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\form\entry_form
 */
final class entry_form_test extends \advanced_testcase {
    /** @var \stdClass The teacher. */
    private \stdClass $teacher;

    /** @var \stdClass The approved plan. */
    private \stdClass $plan;

    /** @var \stdClass A session before today. */
    private \stdClass $past;

    /** @var \stdClass A session after today. */
    private \stdClass $future;

    /**
     * A teacher with an approved plan of two sessions, one on each side of today (2026-10-17).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        $course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($this->teacher);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $this->plan = $gen->create_instance(['course' => $course->id, 'status' => 'approved']);
        $this->past = $gen->create_item(['planid' => $this->plan->id, 'sessiondate' => '2026-10-12', 'title' => 'Entorn']);
        $this->future = $gen->create_item(['planid' => $this->plan->id, 'sessiondate' => '2026-11-02', 'title' => 'Consultes']);
    }

    /**
     * Builds the form of a session, as the entry page does.
     *
     * @param \stdClass $item The plan item.
     * @param bool $future Whether the session is after today.
     * @param int $nextpending Pending sessions left after this one.
     * @return entry_form The form.
     */
    private function form(\stdClass $item, bool $future, int $nextpending = 0): entry_form {
        $url = new \moodle_url('/mod/classprogress/entry.php', ['id' => 1, 'itemid' => $item->id]);
        return new entry_form($url, ['userid' => (int) $this->teacher->id, 'planid' => (int) $this->plan->id,
            'itemid' => (int) $item->id, 'future' => $future, 'nextpending' => $nextpending]);
    }

    /**
     * Reads the HTML of a form.
     *
     * @param entry_form $form The form.
     * @return \DOMXPath Its document.
     */
    private static function dom(entry_form $form): \DOMXPath {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $form->render());
        return new \DOMXPath($doc);
    }

    /**
     * The status of a session up to today is three radio buttons, all shown, with done chosen for a new entry; each
     * one is posted as its status.
     *
     * @group REQ-UIX-005
     * @group REQ-LOG-002
     */
    public function test_the_status_is_three_radio_buttons_with_done_chosen(): void {
        $dom = self::dom($this->form($this->past, false));

        $radios = $dom->query("//input[@type='radio'][@name='status']");
        $values = array_map(fn(\DOMElement $radio): string => $radio->getAttribute('value'), iterator_to_array($radios));
        $this->assertSame([entry_rules::DONE, entry_rules::PARTIAL, entry_rules::NOT_DONE], $values);
        $checked = $dom->query("//input[@type='radio'][@name='status'][@checked]");
        $this->assertSame(1, $checked->length);
        $this->assertSame(entry_rules::DONE, $checked->item(0)->getAttribute('value'));
        $this->assertSame(0, $dom->query("//select[@name='status']")->length);
        foreach (['done', 'partial', 'not_done'] as $status) {
            $label = get_string('entrystatus_' . $status, 'mod_classprogress');
            $this->assertSame(1, $dom->query("//label[normalize-space(.) = '{$label}']")->length, $label);
        }

        entry_form::mock_submit(['status' => entry_rules::NOT_DONE, 'content' => '', 'reason' => 'strike']);
        $this->assertSame(entry_rules::NOT_DONE, $this->form($this->past, false)->get_data()->status);
    }

    /**
     * After today the status is the fixed text of a cancellation known in advance, and the form asks only for the
     * reason, the note and the measure; a posted status other than not done is ignored.
     *
     * @group REQ-UIX-005
     * @group REQ-LOG-009
     */
    public function test_after_today_the_status_is_fixed_to_not_done(): void {
        $dom = self::dom($this->form($this->future, true));

        $this->assertSame(0, $dom->query("//input[@type='radio']")->length);
        $this->assertSame(0, $dom->query("//select[@name='status']")->length);
        $this->assertSame(0, $dom->query("//*[@name='content']")->length);
        $this->assertSame(0, $dom->query("//*[@name='carry']")->length);
        $fixed = get_string('entrystatusfixed', 'mod_classprogress');
        $this->assertStringContainsString($fixed, $dom->document->textContent);
        foreach (['reason', 'reasonnote', 'measure'] as $field) {
            $this->assertSame(1, $dom->query("//*[@name='{$field}'][not(@type='hidden')]")->length, $field);
        }

        entry_form::mock_submit(['status' => entry_rules::DONE, 'reason' => 'trip', 'reasonnote' => 'Sortida']);
        $data = $this->form($this->future, true)->get_data();
        $this->assertSame(entry_rules::NOT_DONE, $data->status);
        $this->assertSame('', entry_form::to_data($data, true)->content);
    }

    /**
     * The reason is labelled as required, on both kinds of form.
     *
     * @group REQ-UIX-005
     */
    public function test_the_reason_is_labelled_as_required(): void {
        $required = get_string('entryreason', 'mod_classprogress');
        $this->assertSame('Reason (required)', $required);

        foreach ([[$this->past, false], [$this->future, true]] as [$item, $future]) {
            $dom = self::dom($this->form($item, $future));
            $id = $dom->query("//select[@name='reason']")->item(0)->getAttribute('id');
            $this->assertSame($required, trim($dom->query("//label[@for='{$id}']")->item(0)->textContent));
        }
    }

    /**
     * With pending sessions left after this one, the form has a second button, named savenext, that says how many;
     * pressing it posts savenext. Without any, it has only "Save entry".
     *
     * @group REQ-UIX-006
     */
    public function test_a_second_button_saves_and_logs_the_next_pending_session(): void {
        $label = get_string('saveandnext', 'mod_classprogress', 3);
        $this->assertSame('Save and log the next pending session (3 left)', $label);

        $dom = self::dom($this->form($this->past, false, 3));
        $buttons = $dom->query("//input[@type='submit'][@name='savenext'] | //button[@type='submit'][@name='savenext']");
        $this->assertSame(1, $buttons->length);
        $button = $buttons->item(0);
        $this->assertSame($label, $button->nodeName === 'input' ? $button->getAttribute('value') : trim($button->textContent));
        $this->assertSame(0, self::dom($this->form($this->past, false, 0))->query("//*[@name='savenext']")->length);

        entry_form::mock_submit(['status' => entry_rules::DONE, 'content' => 'Fet', 'savenext' => $label]);
        $this->assertNotEmpty($this->form($this->past, false, 3)->get_data()->savenext);
        entry_form::mock_submit(['status' => entry_rules::DONE, 'content' => 'Fet', 'submitbutton' => 'Save entry']);
        $this->assertFalse(property_exists($this->form($this->past, false, 3)->get_data(), 'savenext'));
    }

    /**
     * The note and the corrective measure are smaller text boxes than what was done.
     *
     * @group REQ-UIX-007
     */
    public function test_the_note_and_the_measure_are_smaller_than_what_was_done(): void {
        $dom = self::dom($this->form($this->past, false));
        $rows = fn(string $name): int => (int) $dom->query("//textarea[@name='{$name}']")->item(0)->getAttribute('rows');

        $this->assertGreaterThan($rows('reasonnote'), $rows('content'));
        $this->assertGreaterThan($rows('measure'), $rows('content'));
    }
}
