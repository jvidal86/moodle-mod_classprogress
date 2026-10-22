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

    /**
     * A session is named by its title or, when the title is empty, by the first line of its plan as plain text, cut to
     * about 60 characters; what was done is cut the same way, to about 80.
     *
     * @group REQ-UIX-003
     * @group REQ-UIX-007
     */
    public function test_a_session_without_a_title_is_named_by_its_plan(): void {
        $item = fn(string $title, string $content): \stdClass => (object) ['title' => $title, 'content' => $content];
        $long = "\n- **Repàs** del tema 1 abans de l'examen: consultes amb JOIN, subconsultes i agregats\n- Segona línia";

        $this->assertSame('Entorn', log_view::session_title($item('Entorn', 'Instal·lació')));
        $this->assertSame('Hola, món', log_view::session_title($item('', 'Hola, món')));
        $this->assertSame('', log_view::session_title($item('', '')));
        $title = log_view::session_title($item('', $long));
        $this->assertStringStartsWith("Repàs del tema 1 abans de l'examen", $title);
        $this->assertStringEndsWith('…', $title);
        $this->assertLessThanOrEqual(60, \core_text::strlen($title));
        $this->assertStringNotContainsString('agregats', $title);

        $done = log_view::first_line(str_repeat('Normes explicades a tota la classe. ', 4) . "\nI més coses", 80);
        $this->assertStringStartsWith('Normes explicades a tota la classe.', $done);
        $this->assertLessThanOrEqual(80, \core_text::strlen($done));
        $this->assertSame('Prova 1 < 2 & co', log_view::first_line("Prova  1 < 2 & co\nI més", 80));
    }

    /**
     * Creates an approved plan of the current user, with items dated relative to today.
     *
     * @param array $items Plan item fields, each with days: the session's day relative to today.
     * @return array The plan and its items, keyed by title.
     */
    private function plan_with_items(array $items): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $plan = $gen->create_instance(['course' => $course->id, 'status' => 'approved']);
        $today = new \DateTimeImmutable(entry_service::today((int) $user->id));
        $rows = [];
        foreach ($items as $item) {
            $date = $today->modify(sprintf('%+d days', $item['days']))->format('Y-m-d');
            unset($item['days']);
            $rows[$item['title']] = $gen->create_item(['planid' => $plan->id, 'sessiondate' => $date] + $item);
        }
        return [$plan, $rows];
    }

    /**
     * Exports the log of a plan for its template.
     *
     * @param \stdClass $plan The plan.
     * @return \stdClass The template context.
     */
    private static function export(\stdClass $plan): \stdClass {
        global $PAGE, $USER;

        $log = entry_service::log((int) $USER->id, (int) $plan->id);
        return (new log_view($log, true))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * The card shows the first session of today that has no entry, with its plan and "Log this session"; then the
     * next session after today, with "Log a cancellation"; and no card when no session is left from today on.
     *
     * @group REQ-UIX-001
     */
    public function test_the_card_shows_todays_session_then_the_next_one(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        [$plan, $items] = $this->plan_with_items([
            ['days' => -3, 'title' => 'Ahir'],
            ['days' => 0, 'title' => 'Primera hora'],
            ['days' => 0, 'title' => 'Segona hora', 'block' => 'RA2', 'hours' => 1.5, 'content' => '- **Triggers**'],
            ['days' => 2, 'title' => 'Dilluns'],
            ['days' => 5, 'title' => 'Dijous'],
        ]);
        $create = function (string $title, string $status, string $reason = '') use ($plan, $items): void {
            global $USER;
            $data = new entry_data($status, 'Fet', $reason);
            entry_service::create((int) $USER->id, (int) $plan->id, (int) $items[$title]->id, $data);
        };
        $itemid = fn(\stdClass $context): int => (int) (new \core\url($context->card['logurl']))->get_param('itemid');

        $create('Primera hora', entry_rules::DONE);
        $card = self::export($plan)->card;
        $this->assertTrue($card['istoday']);
        $this->assertSame('Segona hora', $card['title']);
        $this->assertSame($items['Segona hora']->sessiondate, $card['date']);
        $this->assertSame(['RA2', '1.5'], [$card['block'], $card['hours']]);
        $this->assertStringContainsString('<strong>Triggers</strong>', $card['content']);
        $this->assertSame((int) $items['Segona hora']->id, $itemid(self::export($plan)));

        $create('Segona hora', entry_rules::DONE);
        $context = self::export($plan);
        $this->assertFalse($context->card['istoday']);
        $this->assertSame('Dilluns', $context->card['title']);
        $this->assertSame((int) $items['Dilluns']->id, $itemid($context));

        $create('Dilluns', entry_rules::NOT_DONE, 'strike');
        $this->assertSame('Dijous', self::export($plan)->card['title']);
        $create('Dijous', entry_rules::NOT_DONE, 'trip');
        $context = self::export($plan);
        $this->assertNull($context->card);
        $this->assertSame(['Ahir'], array_column($context->pending['rows'], 'title'));
    }

    /**
     * Pending sessions are all listed, oldest first; upcoming sessions list the next five and fold the others; logged
     * sessions list the latest five, newest first, and fold the others. Each list gives its count.
     *
     * @group REQ-UIX-002
     * @group REQ-LOG-001
     */
    public function test_the_lists_show_five_and_fold_the_rest(): void {
        global $USER;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        $items = [];
        foreach (range(1, 7) as $n) {
            $items[] = ['days' => -30 + $n, 'title' => "Passada {$n}"];
            $items[] = ['days' => $n, 'title' => "Futura {$n}"];
        }
        $items[] = ['days' => -2, 'title' => 'Pendent B'];
        $items[] = ['days' => -4, 'title' => 'Pendent A'];
        [$plan, $rows] = $this->plan_with_items($items);
        foreach (range(1, 7) as $n) {
            $data = new entry_data(entry_rules::DONE, "Fet {$n}");
            entry_service::create((int) $USER->id, (int) $plan->id, (int) $rows["Passada {$n}"]->id, $data);
        }

        $context = self::export($plan);

        $this->assertSame(2, $context->pending['count']);
        $this->assertSame(['Pendent A', 'Pendent B'], array_column($context->pending['rows'], 'title'));
        $this->assertNull($context->pending['more'] ?? null);
        $this->assertSame(7, $context->upcoming['count']);
        $shown = ['Futura 1', 'Futura 2', 'Futura 3', 'Futura 4', 'Futura 5'];
        $this->assertSame($shown, array_column($context->upcoming['rows'], 'title'));
        $this->assertSame(['Futura 6', 'Futura 7'], array_column($context->upcoming['more']['rows'], 'title'));
        $this->assertSame(7, $context->logged['count']);
        $shown = ['Passada 7', 'Passada 6', 'Passada 5', 'Passada 4', 'Passada 3'];
        $this->assertSame($shown, array_column($context->logged['rows'], 'title'));
        $this->assertSame(['Passada 2', 'Passada 1'], array_column($context->logged['more']['rows'], 'title'));
    }

    /**
     * Every row carries its session's block, hours and planned content, rendered as on the entry form; a logged row
     * also carries the first line of what was done; a session without a title is named by its plan.
     *
     * @group REQ-UIX-003
     * @group REQ-LCK-003
     */
    public function test_every_row_carries_its_plan(): void {
        global $USER;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        $payload = '<script>alert(1)</script>[enllaç](javascript:alert(2))';
        [$plan, $rows] = $this->plan_with_items([
            ['days' => -7, 'title' => 'Feta', 'block' => 'RA1', 'hours' => 2, 'content' => "- **Normes** {$payload}"],
            ['days' => -1, 'title' => 'Pendent', 'block' => 'RA1', 'hours' => 1, 'content' => 'Model E/R'],
            ['days' => 3, 'title' => '', 'block' => 'RA2', 'hours' => 2, 'content' => "- Repàs del tema 1\n- Exercicis"],
        ]);
        $done = new entry_data(entry_rules::DONE, "Normes explicades\n\nI una prova");
        entry_service::create((int) $USER->id, (int) $plan->id, (int) $rows['Feta']->id, $done);

        $context = self::export($plan);

        $logged = $context->logged['rows'][0];
        $this->assertTrue($logged['hasplan']);
        $this->assertSame(['RA1', '2'], [$logged['block'], $logged['hours']]);
        $this->assertStringContainsString('<strong>Normes</strong>', $logged['content']);
        $this->assertStringNotContainsString('<script', $logged['content']);
        $this->assertStringNotContainsString('javascript:', $logged['content']);
        $this->assertSame('Normes explicades', $logged['done']);
        $pending = $context->pending['rows'][0];
        $this->assertSame(['Pendent', 'RA1', '1'], [$pending['title'], $pending['block'], $pending['hours']]);
        $this->assertStringContainsString('Model E/R', $pending['content']);
        $upcoming = $context->upcoming['rows'][0];
        $this->assertSame('Repàs del tema 1', $upcoming['title']);
        $this->assertStringContainsString('<li>Exercicis</li>', $upcoming['content']);
    }

    /**
     * An entry, and an untitled session's plan, holding U+E000 or U+E001 (the private-use characters the Markdown
     * converter of local_classprogress 0.9.0 looped on) render on the log page, with the characters as typed (R6-1).
     *
     * @group REQ-UIX-003
     */
    public function test_text_with_private_use_characters_renders(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        [$plan, $rows] = $this->plan_with_items([
            ['days' => -2, 'title' => 'Feta'],
            ['days' => 2, 'title' => '', 'content' => "- Pla \u{E000}0\u{E001} enganxat"],
        ]);
        $done = new entry_data(entry_rules::DONE, "Fet amb un caràcter \u{E000} enganxat");
        entry_service::create((int) $USER->id, (int) $plan->id, (int) $rows['Feta']->id, $done);

        $context = self::export($plan);
        $html = $PAGE->get_renderer('core')->render(new log_view(entry_service::log((int) $USER->id, (int) $plan->id), true));

        $this->assertSame("Fet amb un caràcter \u{E000} enganxat", $context->logged['rows'][0]['done']);
        $this->assertSame("Pla \u{E000}0\u{E001} enganxat", $context->upcoming['rows'][0]['title']);
        $this->assertStringContainsString("Fet amb un caràcter \u{E000} enganxat", $html);
    }

    /**
     * Renders the log page of a plan.
     *
     * @param \stdClass $plan The plan.
     * @param bool $canedit Whether the user may log sessions.
     * @return \DOMXPath The page's document.
     */
    private static function page(\stdClass $plan, bool $canedit): \DOMXPath {
        global $PAGE, $USER;

        $view = new log_view(entry_service::log((int) $USER->id, (int) $plan->id), $canedit);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $PAGE->get_renderer('core')->render($view));
        return new \DOMXPath($doc);
    }

    /**
     * The card's button is the page's main action for today's session; for a later one, a cancellation, it steps back
     * so that today's and the pending sessions stay the main actions (R6-4).
     *
     * @group REQ-UIX-001
     */
    public function test_the_card_button_steps_back_for_a_later_session(): void {
        global $USER;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        [$plan, $rows] = $this->plan_with_items([['days' => 0, 'title' => 'Avui'], ['days' => 3, 'title' => 'Dijous']]);
        $button = fn(): string => self::page($plan, true)->query("//section[@data-region='log-card']//a")->item(0)
            ->getAttribute('class');

        $this->assertStringContainsString('btn-primary', $button());
        entry_service::create((int) $USER->id, (int) $plan->id, (int) $rows['Avui']->id, new entry_data(entry_rules::DONE, 'Fet'));
        $this->assertStringContainsString('btn-outline-secondary', $button());
        $this->assertStringNotContainsString('btn-primary', $button());
    }

    /**
     * Without the capability to log sessions, the log page offers no link to the entry form: no card button, no Log
     * button, no Edit entry link and no unplanned session; the card, the rows and the histories stay (R6-6).
     *
     * @group REQ-UIX-001
     * @group REQ-UIX-002
     */
    public function test_without_the_edit_capability_nothing_links_to_the_entry_form(): void {
        global $USER;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        [$plan, $rows] = $this->plan_with_items([
            ['days' => -3, 'title' => 'Feta'],
            ['days' => -1, 'title' => 'Pendent'],
            ['days' => 0, 'title' => 'Avui'],
            ['days' => 2, 'title' => 'Futura'],
        ]);
        entry_service::create((int) $USER->id, (int) $plan->id, (int) $rows['Feta']->id, new entry_data(entry_rules::DONE, 'Fet'));
        $entrylinks = "//a[contains(@href, '/mod/classprogress/entry.php')]";

        $viewer = self::page($plan, false);
        $this->assertSame(0, $viewer->query($entrylinks)->length);
        $this->assertSame(1, $viewer->query("//section[@data-region='log-card']")->length);
        $this->assertSame(1, $viewer->query("//a[contains(@href, '/mod/classprogress/history.php') and contains(@href, 'entryid')]")
            ->length);
        foreach (['Feta', 'Pendent', 'Avui', 'Futura'] as $title) {
            $this->assertSame(1, $viewer->query("//tr[.//summary[normalize-space(.) = '{$title}']]")->length, $title);
        }
        // Card, two pending and one upcoming Log buttons, Edit entry and the unplanned session.
        $this->assertSame(6, self::page($plan, true)->query($entrylinks)->length);
    }

    /**
     * A date with two planned days (REQ-PLN-010) shows both sessions on the log, in plan order, each with its own link,
     * and each is logged with its own entry; after both, nothing is pending (R3-2).
     *
     * @group REQ-PLN-010
     * @group REQ-LOG-001
     * @group REQ-LOG-005
     */
    public function test_a_date_with_two_planned_days_shows_and_logs_both(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1792317600);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $plan = $gen->create_instance(['course' => $course->id, 'status' => 'approved']);
        $first = $gen->create_item(['planid' => $plan->id, 'sessiondate' => '2026-10-15', 'title' => 'Primera hora']);
        $second = $gen->create_item(['planid' => $plan->id, 'sessiondate' => '2026-10-15', 'title' => 'Segona hora']);
        $renderer = $PAGE->get_renderer('core');
        $log = fn(): \stdClass => (new log_view(entry_service::log($user->id, (int) $plan->id)))->export_for_template($renderer);
        $itemid = fn(string $url): int => (int) (new \core\url($url))->get_param('itemid');

        $before = $log();
        $this->assertSame(['Primera hora', 'Segona hora'], array_column($before->pending['rows'], 'title'));
        $this->assertSame(['2026-10-15', '2026-10-15'], array_column($before->pending['rows'], 'date'));
        $urls = array_column($before->pending['rows'], 'logurl');
        $this->assertSame([(int) $first->id, (int) $second->id], array_map($itemid, $urls));

        $done = new entry_data(entry_rules::DONE, 'Primera part feta');
        $firstentry = entry_service::create($user->id, (int) $plan->id, (int) $first->id, $done);
        $between = $log();
        $this->assertSame(['Segona hora'], array_column($between->pending['rows'], 'title'));
        $this->assertSame(['Primera hora'], array_column($between->logged['rows'], 'title'));

        $partial = new entry_data(entry_rules::PARTIAL, 'Segona part a mitges', 'incident');
        $secondentry = entry_service::create($user->id, (int) $plan->id, (int) $second->id, $partial);
        $after = $log();
        $this->assertSame([], $after->pending['rows']);
        $this->assertSame(['Primera hora', 'Segona hora'], array_column($after->logged['rows'], 'title'));
        $this->assertSame([get_string('entrystatus_done', 'mod_classprogress'),
            get_string('entrystatus_partial', 'mod_classprogress')], array_column($after->logged['rows'], 'status'));
        $this->assertNotSame($firstentry, $secondentry);
        $this->assertSame($firstentry, (int) entry_service::for_item($user->id, (int) $first->id)->id);
        $this->assertSame($secondentry, (int) entry_service::for_item($user->id, (int) $second->id)->id);
    }
}
