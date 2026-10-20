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

use local_classprogress\local\dates;
use local_classprogress\local\export_session;
use local_classprogress\local\export_summary;
use local_classprogress\local\plain_text;
use local_classprogress\local\summary_figures;
use local_classprogress\local\summary_group;

/**
 * The export of an approved plan: the Plan vs done rows and the summary tables, for the export page and the XLSX file
 * (REQ-EXP-001 to 010).
 *
 * build() reads the plan's log as the log page does (entry_service::log(), the same "today"), its evaluation periods
 * and the author of each entry's creation, with a number of queries that does not depend on the plan's size; the page
 * and the file both show what it gives. The file follows the layout contract of PLAN.md section 5: two sheets, Plan vs
 * done and Summary, each starting with the header block; text is written with write_string() and figures with
 * write_number(), never a formula or a link; dates are yyyy-mm-dd text and Markdown is written as plain text.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_service {
    /** @var string[] Plan vs done columns: row key => language string of the header. */
    public const COLUMNS = [
        'date' => 'exportcol_date',
        'period' => 'exportcol_period',
        'block' => 'exportcol_block',
        'planned' => 'exportcol_planned',
        'hours' => 'exportcol_hours',
        'status' => 'exportcol_status',
        'done' => 'exportcol_done',
        'reason' => 'exportcol_reason',
        'note' => 'exportcol_note',
        'measure' => 'exportcol_measure',
        'carry' => 'exportcol_carry',
        'writtenon' => 'exportcol_writtenon',
        'dayslate' => 'exportcol_dayslate',
        'loggedby' => 'exportcol_loggedby',
    ];

    /** @var string[] Summary columns after the group: summary_figures field => language string of the header. */
    public const FIGURES = [
        'planned' => 'exportfig_planned',
        'done' => 'exportfig_done',
        'partial' => 'exportfig_partial',
        'notdone' => 'exportfig_notdone',
        'notlogged' => 'exportfig_notlogged',
        'upcoming' => 'exportfig_upcoming',
        'added' => 'exportfig_added',
        'coverage' => 'exportfig_coverage',
        'plannedhours' => 'exportfig_plannedhours',
        'hoursdone' => 'exportfig_hoursdone',
        'hoursnotdone' => 'exportfig_hoursnotdone',
        'writtenlate' => 'exportfig_writtenlate',
        'mostdayslate' => 'exportfig_mostdayslate',
    ];

    /** @var string[] The summary tables: plan_export property => language string of the title. */
    public const TABLES = [
        'overall' => 'exporttable_overall',
        'perblock' => 'exporttable_perblock',
        'perperiod' => 'exporttable_perperiod',
    ];

    /**
     * Reads the export of an approved plan.
     *
     * @param int $userid The exporting user.
     * @param int $planid Plan id.
     * @return plan_export The export.
     * @throws \moodle_exception error_notfound, or error_plannotapproved for a draft.
     */
    public static function build(int $userid, int $planid): plan_export {
        global $DB;

        $plan = access::plan($userid, $planid);
        if ($plan->status !== plan_service::STATUS_APPROVED) {
            throw new \moodle_exception('error_plannotapproved', 'mod_classprogress');
        }
        $log = entry_service::log($userid, $planid);
        $periods = array_values($DB->get_records('classprogress_evaluation', ['planid' => $planid], 'startdate, enddate, id'));
        $authors = self::authors($planid);

        $lines = [];
        foreach ($log->items as $item) {
            $entry = $log->entries[(int) $item->id] ?? null;
            $state = export_summary::state($entry?->status, $item->sessiondate, $log->today);
            $order = [dates::day_number($item->sessiondate), 0, (int) $item->sortorder, (int) $item->id];
            $lines[] = [$order, self::line($log, $periods, $authors, $item, $entry, $state)];
        }
        foreach ($log->unplanned as $entry) {
            // Unplanned sessions follow the planned days of their date, in the order they were added.
            $order = [dates::day_number($entry->sessiondate), 1, (int) $entry->id, 0];
            $lines[] = [$order, self::line($log, $periods, $authors, null, $entry, export_summary::ADDED)];
        }
        usort($lines, fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $rows = array_column(array_column($lines, 1), 0);
        $sessions = array_column(array_column($lines, 1), 1);

        $overall = export_summary::overall($sessions);
        return new plan_export(
            plan: $plan,
            today: $log->today,
            header: self::header($userid, $plan, count($log->items), $overall[0]->figures),
            rows: $rows,
            overall: $overall,
            perblock: export_summary::per_block($sessions),
            perperiod: export_summary::per_period($sessions, $periods),
            filename: clean_filename($plan->modulecode . '-' . get_course($plan->course)->shortname . '-' . $log->today
                . '.xlsx'),
        );
    }

    /**
     * Sends the export as an XLSX download.
     *
     * @param plan_export $export The export.
     */
    public static function send(plan_export $export): void {
        global $CFG;

        require_once($CFG->libdir . '/excellib.class.php');
        raise_memory_limit(MEMORY_EXTRA);
        $workbook = new \MoodleExcelWorkbook($export->filename);
        self::write($export, $workbook);
        $workbook->close();
    }

    /**
     * Writes the two sheets of the export into a workbook.
     *
     * @param plan_export $export The export.
     * @param \MoodleExcelWorkbook $workbook The workbook, without sheets.
     */
    public static function write(plan_export $export, \MoodleExcelWorkbook $workbook): void {
        $bold = $workbook->add_format(['bold' => 1]);
        $wrap = $workbook->add_format(['text_wrap' => 1, 'v_align' => 'top']);

        $sheet = $workbook->add_worksheet(get_string('exportsheet_plan', 'mod_classprogress'));
        $row = self::write_header($sheet, $export, $bold);
        self::write_cells($sheet, $row++, self::strings(self::COLUMNS), $bold);
        foreach ($export->rows as $values) {
            self::write_cells($sheet, $row++, array_values($values), $wrap);
        }
        foreach ([[0, 2, 12], [3, 3, 50], [4, 5, 12], [6, 6, 50], [7, 13, 16]] as [$first, $last, $width]) {
            $sheet->set_column($first, $last, $width);
        }

        $sheet = $workbook->add_worksheet(get_string('exportsheet_summary', 'mod_classprogress'));
        $row = self::write_header($sheet, $export, $bold);
        $headers = array_merge([get_string('exportfig_group', 'mod_classprogress')], self::strings(self::FIGURES));
        foreach (self::TABLES as $property => $title) {
            $sheet->write_string($row++, 0, get_string($title, 'mod_classprogress'), $bold);
            self::write_cells($sheet, $row++, $headers, $bold);
            foreach ($export->$property as $group) {
                self::write_cells($sheet, $row++, array_merge([self::group_name($group)], self::figures($group->figures)));
            }
            // One empty row between tables.
            $row++;
        }
        $sheet->set_column(0, 0, 30);
    }

    /**
     * Names a row of a summary table.
     *
     * @param summary_group $group The row.
     * @return string The block or period as typed, or the name of the group, such as "No block".
     */
    public static function group_name(summary_group $group): string {
        if ($group->kind === export_summary::GROUP_BLOCK || $group->kind === export_summary::GROUP_PERIOD) {
            return $group->name;
        }
        return get_string('exportgroup_' . $group->kind, 'mod_classprogress');
    }

    /**
     * The figures of a summary row, in the order of the Summary columns after the group.
     *
     * @param summary_figures $figures The figures.
     * @return array Numbers; null for an empty coverage.
     */
    public static function figures(summary_figures $figures): array {
        return array_map(fn(string $field) => $figures->$field, array_keys(self::FIGURES));
    }

    /**
     * Builds the row and the summary session of a planned day or an unplanned session.
     *
     * @param plan_log $log The plan's log.
     * @param \stdClass[] $periods The plan's evaluation periods.
     * @param string[] $authors Entry id => who created it.
     * @param \stdClass|null $item The plan item row; null for an unplanned session.
     * @param \stdClass|null $entry The entry row; null for a planned day without one.
     * @param string $state The export_summary state.
     * @return array [the row, its export_session].
     */
    private static function line(
        plan_log $log,
        array $periods,
        array $authors,
        ?\stdClass $item,
        ?\stdClass $entry,
        string $state
    ): array {
        $date = $item === null ? $entry->sessiondate : $item->sessiondate;
        $hours = $item === null || $item->hours === null ? null : (float) $item->hours;
        $dayslate = $entry === null ? null : $log->days_late($entry);
        $planned = $item === null ? '' : implode("\n", array_filter([(string) $item->title,
            plain_text::from_markdown((string) $item->content)], fn(string $text): bool => $text !== ''));
        $row = [
            'date' => $date,
            'period' => (string) export_summary::period_of($date, $periods),
            'block' => $item === null ? '' : (string) $item->block,
            'planned' => $planned,
            'hours' => $hours,
            'status' => get_string('entrystatus_' . $state, 'mod_classprogress'),
            'done' => plain_text::from_markdown((string) $entry?->content),
            'reason' => (string) $entry?->reason === '' ? '' : get_string('reason_' . $entry->reason, 'mod_classprogress'),
            'note' => plain_text::from_markdown((string) $entry?->reasonnote),
            'measure' => plain_text::from_markdown((string) $entry?->measure),
            'carry' => empty($entry?->carry) ? '' : get_string('yes'),
            'writtenon' => (string) $entry?->writtenon,
            'dayslate' => $dayslate,
            'loggedby' => $entry === null ? '' : ($authors[(int) $entry->id] ?? ''),
        ];
        return [$row, new export_session($date, $state, $item === null ? null : (string) $item->block, $hours, $dayslate)];
    }

    /**
     * Reads who created each entry of a plan: the user of its first create history row.
     *
     * @param int $planid Plan id.
     * @return string[] Entry id => full name; "Deleted user" when the user was erased or deleted.
     */
    private static function authors(int $planid): array {
        global $DB;

        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT h.id, h.entryid, h.userid, u.deleted, {$names}
                  FROM {classprogress_entry_hist} h
             LEFT JOIN {user} u ON u.id = h.userid
                 WHERE h.planid = :planid AND h.action = :action
              ORDER BY h.timecreated, h.id";
        $deleted = get_string('deleteduser', 'mod_classprogress');
        $authors = [];
        foreach ($DB->get_records_sql($sql, ['planid' => $planid, 'action' => entry_service::ACTION_CREATE]) as $row) {
            $known = (int) $row->userid > 0 && $row->deleted !== null && !$row->deleted;
            $authors[(int) $row->entryid] ??= $known ? fullname($row) : $deleted;
        }
        return $authors;
    }

    /**
     * The header block of both sheets.
     *
     * @param int $userid The exporting user.
     * @param \stdClass $plan The plan row.
     * @param int $sessions Planned sessions.
     * @param summary_figures $overall The overall figures.
     * @return array Field (module, course, ..., exportedon; the label is the string exportlabel_<field>) => value, in
     *     order.
     */
    private static function header(int $userid, \stdClass $plan, int $sessions, summary_figures $overall): array {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $course = get_course($plan->course);
        $now = \core\di::get(\core\clock::class)->time();
        $zone = new \DateTimeZone(\core_date::get_user_timezone($user));
        $module = array_filter([(string) $plan->modulecode, (string) $plan->moduletitle], fn(string $text): bool => $text !== '');
        $values = [
            'module' => count($module) === 2
                ? get_string('planname', 'mod_classprogress', (object) ['code' => $module[0], 'title' => $module[1]])
                : implode('', $module),
            'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id),
                'escape' => false]),
            'activity' => format_string($plan->name, true, ['context' => access::context($plan), 'escape' => false]),
            'schoolyear' => self::range($plan->yearstart, $plan->yearend),
            'plandates' => self::range($plan->startdate, $plan->enddate),
            'plannedsessions' => $sessions,
            'plannedhours' => $overall->plannedhours,
            'exportedby' => fullname($user),
            'exportedon' => (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m-d H:i'),
        ];
        return $values;
    }

    /**
     * Writes a range of days.
     *
     * @param string|null $first First day, yyyy-mm-dd.
     * @param string|null $last Last day, yyyy-mm-dd.
     * @return string Such as "2026-09-14 to 2027-06-21"; empty when a day is missing.
     */
    private static function range(?string $first, ?string $last): string {
        if (empty($first) || empty($last)) {
            return '';
        }
        return get_string('daterange', 'mod_classprogress', (object) ['start' => $first, 'end' => $last]);
    }

    /**
     * Reads language strings of the plugin.
     *
     * @param string[] $keys Anything => string identifier.
     * @return string[] The strings, in order.
     */
    private static function strings(array $keys): array {
        return array_values(array_map(fn(string $key): string => get_string($key, 'mod_classprogress'), $keys));
    }

    /**
     * Writes the header block of a sheet, then leaves one empty row.
     *
     * @param \MoodleExcelWorksheet $sheet The sheet.
     * @param plan_export $export The export.
     * @param \MoodleExcelFormat $bold The format of the labels.
     * @return int The next row, from 0.
     */
    private static function write_header(\MoodleExcelWorksheet $sheet, plan_export $export, \MoodleExcelFormat $bold): int {
        $row = 0;
        foreach ($export->header as $field => $value) {
            // Keyed by field, not by label: two labels a site translates alike must not merge into one row (R5-4).
            $sheet->write_string($row, 0, get_string('exportlabel_' . $field, 'mod_classprogress'), $bold);
            self::write_cells($sheet, $row++, [null, $value]);
        }
        return $row + 1;
    }

    /**
     * Writes a row of cells from column A: numbers with write_number(), text with write_string(); an empty text or a
     * null leaves the cell empty.
     *
     * @param \MoodleExcelWorksheet $sheet The sheet.
     * @param int $row The row, from 0.
     * @param array $values Text, numbers or null, by column.
     * @param \MoodleExcelFormat|null $format The cells' format.
     */
    private static function write_cells(
        \MoodleExcelWorksheet $sheet,
        int $row,
        array $values,
        ?\MoodleExcelFormat $format = null
    ): void {
        foreach (array_values($values) as $col => $value) {
            if (is_int($value) || is_float($value)) {
                $sheet->write_number($row, $col, $value, $format);
            } else if ($value !== null && $value !== '') {
                $sheet->write_string($row, $col, (string) $value, $format);
            }
        }
    }
}
