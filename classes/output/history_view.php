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

use local_classprogress\local\written_on;
use mod_classprogress\local\access;

/**
 * History rows of entries, newest first: the change, when and by whom, and the entry as it was after it (before it,
 * for a delete).
 *
 * The entry's texts go through format_text() as Markdown with cleaning on; codes become their names. A snapshot that
 * cannot be read shows its fields empty.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_view implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $plan The plan row.
     * @param \stdClass[] $rows History rows, newest first.
     */
    public function __construct(
        /** @var \stdClass The plan row. */
        private readonly \stdClass $plan,
        /** @var \stdClass[] History rows, newest first. */
        private readonly array $rows,
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
        $text = fn(string $markdown): string => format_text($markdown, FORMAT_MARKDOWN, ['context' => $context]);
        $versions = [];
        foreach ($this->rows as $row) {
            $snapshot = json_decode($row->snapshot);
            $entry = is_object($snapshot) ? $snapshot : new \stdClass();
            $status = (string) ($entry->status ?? '');
            $reason = (string) ($entry->reason ?? '');
            $date = (string) ($entry->sessiondate ?? '');
            $writtenon = (string) ($entry->writtenon ?? '');
            $versions[] = [
                'action' => get_string('historyaction_' . $row->action, 'mod_classprogress'),
                'time' => userdate($row->timecreated),
                'by' => self::by((int) $row->userid, $row->realuserid === null ? null : (int) $row->realuserid),
                'date' => $date,
                'status' => $status === '' ? '' : get_string('entrystatus_' . $status, 'mod_classprogress'),
                'content' => $text((string) ($entry->content ?? '')),
                'reason' => $reason === '' ? '' : get_string('reason_' . $reason, 'mod_classprogress'),
                'note' => $text((string) ($entry->reasonnote ?? '')),
                'measure' => $text((string) ($entry->measure ?? '')),
                'carry' => !empty($entry->carry),
                'writtenon' => $writtenon,
                'late' => $writtenon === '' || $date === '' ? '' : log_view::late(written_on::days_late($writtenon, $date)),
            ];
        }
        return (object) ['hasversions' => !empty($versions), 'versions' => $versions];
    }

    /**
     * Writes who made a change; a user whose authorship was erased (id 0, REQ-PRV-003) shows as a deleted user.
     *
     * @param int $userid The acting user.
     * @param int|null $realuserid The real user behind a "log in as"; null otherwise.
     * @return string Such as "By Anna Puig".
     */
    private static function by(int $userid, ?int $realuserid): string {
        $name = function (int $id): string {
            $user = $id > 0 ? \core_user::get_user($id) : false;
            return $user ? fullname($user) : get_string('deleteduser', 'mod_classprogress');
        };
        if ($realuserid === null) {
            return get_string('historyby', 'mod_classprogress', $name($userid));
        }
        return get_string('historyloggedinas', 'mod_classprogress', (object) [
            'realuser' => $name($realuserid),
            'user' => $name($userid),
        ]);
    }
}
