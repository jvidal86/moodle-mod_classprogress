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
use local_classprogress\local\session_item;
use mod_classprogress\local\access;
use mod_classprogress\local\plan_log;

/**
 * The log of an approved plan: pending sessions oldest first, then the logged ones (planned and unplanned, by day)
 * with their status and late flag, then the upcoming ones.
 *
 * Block and day titles are exported as typed, for the template to escape.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_view implements \renderable, \templatable {
    /** @var int The course module id of the plan activity, for the URLs. */
    private int $cmid = 0;

    /**
     * Constructor.
     *
     * @param plan_log $log The plan's log.
     */
    public function __construct(
        /** @var plan_log The plan's log. */
        private readonly plan_log $log,
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
     * Exports the view for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $this->cmid = access::cmid($this->log->plan);
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
        usort($logged, fn(array $a, array $b): int =>
            [dates::day_number($a['date']), $a['order']] <=> [dates::day_number($b['date']), $b['order']]);
        return (object) [
            'haspending' => !empty($pending),
            'pending' => $pending,
            'haslogged' => !empty($logged),
            'logged' => $logged,
            'hasupcoming' => !empty($upcoming),
            'upcoming' => $upcoming,
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
            'block' => $row->block,
            'title' => $row->title,
            'logurl' => (new url('/mod/classprogress/entry.php', ['id' => $this->cmid, 'itemid' => $item->id]))->out(false),
        ];
    }

    /**
     * Exports an entry, of a plan item or of an unplanned session.
     *
     * @param \stdClass $entry The entry row.
     * @param \stdClass|null $item Its plan item row; null for an unplanned session.
     * @return array Template context for the row, with order, the position among entries of the same day.
     */
    private function entry_row(\stdClass $entry, ?\stdClass $item): array {
        return [
            'date' => $entry->sessiondate,
            // Plan items keep the order of the text; unplanned sessions follow them, in the order they were added.
            'order' => $item === null ? intdiv(PHP_INT_MAX, 2) + (int) $entry->id : (int) $item->sortorder,
            'block' => $item === null ? '' : $item->block,
            'title' => $item === null ? get_string('unplannedsession', 'mod_classprogress') : $item->title,
            'status' => get_string('entrystatus_' . $entry->status, 'mod_classprogress'),
            'writtenon' => $entry->writtenon,
            'late' => self::late($this->log->days_late($entry)),
            'editurl' => (new url('/mod/classprogress/entry.php', ['id' => $this->cmid, 'entryid' => $entry->id]))->out(false),
            'historyurl' => (new url('/mod/classprogress/history.php', ['id' => $this->cmid, 'entryid' => $entry->id]))
                ->out(false),
        ];
    }
}
