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

use local_classprogress\local\session_groups;
use local_classprogress\local\written_on;

/**
 * The log of a plan as entry_service::log() reads it: its items, their entries, the unplanned sessions, and the items
 * sorted into pending, upcoming and logged for the teacher's own day.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_log {
    /**
     * Creates the log.
     *
     * @param \stdClass $plan The plan row.
     * @param string $today The teacher's own day, yyyy-mm-dd.
     * @param \stdClass[] $items Plan item rows keyed by id, in the order of the text.
     * @param \stdClass[] $entries Entry rows of the items, keyed by plan item id.
     * @param \stdClass[] $unplanned Entry rows of unplanned sessions, keyed by id, by day.
     * @param session_groups $groups The items sorted for today; empty for a draft.
     */
    public function __construct(
        /** @var \stdClass The plan row. */
        public readonly \stdClass $plan,
        /** @var string The teacher's own day, yyyy-mm-dd. */
        public readonly string $today,
        /** @var \stdClass[] Plan item rows keyed by id, in the order of the text. */
        public readonly array $items,
        /** @var \stdClass[] Entry rows of the items, keyed by plan item id. */
        public readonly array $entries,
        /** @var \stdClass[] Entry rows of unplanned sessions, keyed by id, by day. */
        public readonly array $unplanned,
        /** @var session_groups The items sorted for today; empty for a draft. */
        public readonly session_groups $groups,
    ) {
    }

    /**
     * How many days after its session an entry was first written.
     *
     * @param \stdClass $entry An entry row.
     * @return int Days late; 0 when written on or before the session day.
     */
    public function days_late(\stdClass $entry): int {
        return written_on::days_late($entry->writtenon, $entry->sessiondate);
    }
}
