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
use local_classprogress\local\written_on;
use mod_classprogress\local\access;

/**
 * What the entry page shows around its form: above it, the planned day, read-only, and what the previous partial
 * session left over (template mod_classprogress/entry_context); after it, once the entry is saved, its written-on day,
 * late flag and last-edited time, with links to delete it and to its history (mod_classprogress/entry_written).
 *
 * The block is exported as typed, for the template to escape. The planned content and the left-over go through
 * format_text() as Markdown with cleaning on.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_context implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $plan The plan row.
     * @param \stdClass|null $item The plan item row; null for an unplanned session.
     * @param \stdClass|null $entry The stored entry row; null before it is saved.
     * @param string|null $leftover What the previous session left over, Markdown as typed; null when nothing.
     */
    public function __construct(
        /** @var \stdClass The plan row. */
        private readonly \stdClass $plan,
        /** @var \stdClass|null The plan item row; null for an unplanned session. */
        private readonly ?\stdClass $item,
        /** @var \stdClass|null The stored entry row; null before it is saved. */
        private readonly ?\stdClass $entry,
        /** @var string|null What the previous session left over; null when nothing. */
        private readonly ?string $leftover,
    ) {
    }

    /**
     * Exports the view for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $context = access::context($this->plan) ?? \context_system::instance();
        $cmid = access::cmid($this->plan);
        $item = $this->item;
        $entry = $this->entry;
        return (object) [
            'hasitem' => $item !== null,
            'block' => $item?->block,
            'hours' => $item === null || $item->hours === null ? '' : format_float((float) $item->hours, 2, true, true),
            'content' => $item === null ? '' : format_text($item->content, FORMAT_MARKDOWN, ['context' => $context]),
            'hasleftover' => $this->leftover !== null,
            'leftover' => $this->leftover === null ? '' : format_text($this->leftover, FORMAT_MARKDOWN, ['context' => $context]),
            'hasentry' => $entry !== null,
            'writtenon' => $entry === null ? '' : get_string('entrywrittenon', 'mod_classprogress', $entry->writtenon),
            'late' => $entry === null ? '' : log_view::late(written_on::days_late($entry->writtenon, $entry->sessiondate)),
            'lastedited' => $entry === null ? ''
                : get_string('entrylastedited', 'mod_classprogress', userdate($entry->timemodified)),
            'deleteurl' => $entry === null ? '' : (new url('/mod/classprogress/entry.php', ['id' => $cmid,
                'entryid' => $entry->id, 'action' => 'delete']))->out(false),
            'historyurl' => $entry === null ? '' : (new url('/mod/classprogress/history.php', ['id' => $cmid,
                'entryid' => $entry->id]))->out(false),
        ];
    }
}
