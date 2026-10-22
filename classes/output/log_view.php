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

use core\url;
use local_classprogress\local\dates;
use local_classprogress\local\plain_text;
use local_classprogress\local\session_item;
use mod_classprogress\local\access;
use mod_classprogress\local\plan_log;

/**
 * The log of an approved plan (REQ-UIX-001, REQ-UIX-002): a card with the session to log, today's or else the next one;
 * the actions; the pending sessions, oldest first; the upcoming ones, soonest first; the logged ones (planned and
 * unplanned), newest day first, with their status and late flag; and the link to the deleted entries. The upcoming and
 * logged lists show their first rows and fold the rest.
 *
 * Every planned row and the card carry the session's block, hours and plan (REQ-UIX-003). Block and day titles are
 * exported as typed, for the template to escape; the plan goes through format_text() as Markdown with cleaning on, as
 * on the entry form.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_view implements \renderable, \templatable {
    /** @var int Rows listed before the rest of the upcoming or logged sessions is folded. */
    public const SHOWN = 5;

    /** @var int The course module id of the plan activity, for the URLs. */
    private int $cmid = 0;

    /** @var \context|null The activity's context, for format_text(). */
    private ?\context $context = null;

    /**
     * Constructor.
     *
     * @param plan_log $log The plan's log.
     * @param bool $canedit Whether the user may log sessions; without it, nothing on the page links to the entry form.
     */
    public function __construct(
        /** @var plan_log The plan's log. */
        private readonly plan_log $log,
        /** @var bool Whether the user may log sessions. */
        private readonly bool $canedit = false,
    ) {
    }

    /**
     * Writes how late an entry was written.
     *
     * @param int $days Days late.
     * @return string Such as "Late by 3 days"; empty when it was not late.
     */
    public static function late(int $days): string {
        if ($days === 0) {
            return '';
        }
        return $days === 1 ? get_string('lateone', 'mod_classprogress') : get_string('late', 'mod_classprogress', $days);
    }

    /**
     * Names a session: its title or, when the title is empty, the first line of its plan (REQ-UIX-003).
     *
     * @param \stdClass $item The plan item row.
     * @return string The name, as typed, for the caller to escape; empty when the session has neither.
     */
    public static function session_title(\stdClass $item): string {
        return $item->title !== '' ? $item->title : self::first_line($item->content, 60);
    }

    /**
     * Gives the first line of a Markdown text as plain text, without its bullet, cut to a length.
     *
     * @param string $markdown The text, as typed.
     * @param int $length Most characters, the ellipsis included.
     * @return string The line, as typed, for the caller to escape; empty for a blank text.
     */
    public static function first_line(string $markdown, int $length): string {
        foreach (explode("\n", plain_text::from_markdown($markdown)) as $line) {
            $line = trim(preg_replace(['/^\s*•\s*/u', '/\s+/u'], ['', ' '], $line));
            if ($line !== '') {
                return \core_text::strlen($line) > $length ? rtrim(\core_text::substr($line, 0, $length - 1)) . '…' : $line;
            }
        }
        return '';
    }

    /**
     * Exports the view for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $this->cmid = access::cmid($this->log->plan);
        $this->context = access::context($this->log->plan) ?? \context_system::instance();
        $groups = $this->log->groups;
        $pending = array_map(fn(session_item $item): array => $this->item_row($item), $groups->pending);
        $upcoming = array_map(fn(session_item $item): array => $this->item_row($item), $groups->upcoming);
        $logged = [];
        foreach ($groups->logged as $item) {
            $logged[] = $this->entry_row($this->log->entries[$item->id], $this->log->items[$item->id]);
        }
        foreach ($this->log->unplanned as $entry) {
            $logged[] = $this->entry_row($entry, null);
        }
        // Newest day first; the sessions of one day keep their order.
        usort($logged, fn(array $a, array $b): int =>
            [dates::day_number($b['date']), $a['order']] <=> [dates::day_number($a['date']), $b['order']]);
        $page = fn(string $script): string => (new url('/mod/classprogress/' . $script, ['id' => $this->cmid]))->out(false);
        return (object) [
            'card' => $this->card(),
            'canedit' => $this->canedit,
            'addurl' => $this->canedit ? $page('entry.php') : '',
            'planurl' => $page('plan.php'),
            'exporturl' => $page('export.php'),
            'deletedurl' => $page('history.php'),
            'pending' => ['count' => count($pending), 'rows' => $pending, 'primary' => true],
            'upcoming' => self::folded($upcoming),
            'logged' => self::folded($logged),
        ];
    }

    /**
     * Splits a list into the rows shown and the rows folded.
     *
     * @param array $rows Template contexts of the rows, in order.
     * @return array Template context: count, rows shown, and more, the folded rows, or null when none is folded.
     */
    private static function folded(array $rows): array {
        return [
            'count' => count($rows),
            'rows' => array_slice($rows, 0, self::SHOWN),
            'more' => count($rows) > self::SHOWN ? ['rows' => array_slice($rows, self::SHOWN)] : null,
            'primary' => false,
        ];
    }

    /**
     * Exports the session to log first: the first of today without an entry or, when there is none, the next one.
     *
     * @return array|null Template context of the card, with istoday; null when no session is left from today on.
     */
    private function card(): ?array {
        $today = array_filter($this->log->groups->pending, fn(session_item $item): bool => $item->date === $this->log->today);
        $item = reset($today) ?: ($this->log->groups->upcoming[0] ?? null);
        if ($item === null) {
            return null;
        }
        return ['istoday' => $item->date === $this->log->today] + $this->item_row($item);
    }

    /**
     * Exports what a planned row shows of its session: its name and, when opened, its block, hours and plan.
     *
     * @param \stdClass $row The plan item row.
     * @return array Template context.
     */
    private function planned(\stdClass $row): array {
        $title = self::session_title($row);
        return [
            'hasplan' => true,
            'title' => $title === '' ? $row->sessiondate : $title,
            'block' => $row->block,
            'hours' => $row->hours === null ? '' : format_float((float) $row->hours, 2, true, true),
            'content' => format_text($row->content, FORMAT_MARKDOWN, ['context' => $this->context]),
        ];
    }

    /**
     * Exports a plan item without an entry.
     *
     * @param session_item $item The item.
     * @return array Template context for the row.
     */
    private function item_row(session_item $item): array {
        $row = $this->log->items[$item->id];
        return [
            'date' => $row->sessiondate,
            'logurl' => (new url('/mod/classprogress/entry.php', ['id' => $this->cmid, 'itemid' => $item->id]))->out(false),
        ] + $this->planned($row);
    }

    /**
     * Exports an entry, of a plan item or of an unplanned session.
     *
     * @param \stdClass $entry The entry row.
     * @param \stdClass|null $item Its plan item row; null for an unplanned session.
     * @return array Template context for the row, with order, the position among entries of the same day.
     */
    private function entry_row(\stdClass $entry, ?\stdClass $item): array {
        $unplanned = ['hasplan' => false, 'title' => get_string('unplannedsession', 'mod_classprogress'), 'block' => ''];
        return [
            'date' => $entry->sessiondate,
            // Plan items keep the order of the text; unplanned sessions follow them, in the order they were added.
            'order' => $item === null ? intdiv(PHP_INT_MAX, 2) + (int) $entry->id : (int) $item->sortorder,
            'done' => self::first_line($entry->content, 80),
            'status' => get_string('entrystatus_' . $entry->status, 'mod_classprogress'),
            'writtenon' => $entry->writtenon,
            'late' => self::late($this->log->days_late($entry)),
            'editurl' => (new url('/mod/classprogress/entry.php', ['id' => $this->cmid, 'entryid' => $entry->id]))->out(false),
            'historyurl' => (new url('/mod/classprogress/history.php', ['id' => $this->cmid, 'entryid' => $entry->id]))
                ->out(false),
        ] + ($item === null ? $unplanned : $this->planned($item));
    }
}
