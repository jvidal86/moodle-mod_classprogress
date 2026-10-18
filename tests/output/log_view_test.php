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

namespace mod_classprogress\output;

use local_classprogress\local\entry_data;
use local_classprogress\local\entry_rules;
use mod_classprogress\local\entry_service;

/**
 * Tests for the log, entry and history views: what a teacher typed is shown escaped, or cleaned for Markdown.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\output\log_view
 * @covers     \mod_classprogress\output\entry_context
 * @covers     \mod_classprogress\output\history_view
 */
final class log_view_test extends \advanced_testcase {
    /**
     * Block and day titles are escaped; planned content, entry text, note and measure go through Markdown with
     * cleaning, so no script or event handler survives on any of the three pages.
     *
     * @group REQ-DAT-004
     */
    public function test_typed_text_is_escaped_or_cleaned(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $plan = $gen->create_instance(['course' => $course->id, 'status' => 'approved']);
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $item = $gen->create_item(['planid' => $plan->id, 'sessiondate' => '2026-10-16', 'block' => 'RA <C++> ' . $payload,
            'title' => 'Pont <- Immaculada ' . $payload, 'content' => 'Pla ' . $payload]);
        $next = $gen->create_item(['planid' => $plan->id, 'sessiondate' => '2026-10-17']);
        $typed = new entry_data(entry_rules::PARTIAL, 'Fet ' . $payload, 'incident', 'Nota ' . $payload, 'Mesura', true);
        $id = entry_service::create($user->id, (int) $plan->id, (int) $item->id, $typed);
        $renderer = $PAGE->get_renderer('core');

        $html = $renderer->render(new log_view(entry_service::log($user->id, (int) $plan->id)))
            . $renderer->render(new entry_context($plan, $item, entry_service::get($user->id, $id), null))
            . $renderer->render(new entry_context($plan, $next, null, entry_service::left_over($user->id, (int) $next->id)))
            . $renderer->render(new history_view($plan, entry_service::history($user->id, $id)));

        $this->assertStringContainsString('RA &lt;C++&gt;', $html);
        $this->assertStringContainsString('Pont &lt;- Immaculada', $html);
        foreach (['Pla', 'Fet', 'Nota'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<script', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*onerror/i', $html);
    }
}
