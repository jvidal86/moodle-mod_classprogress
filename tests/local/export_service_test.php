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

use local_classprogress\local\export_summary;
use local_classprogress\local\summary_figures;
use local_classprogress\local\summary_group;
use mod_classprogress\output\export_view;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tests for the export of a plan activity: the Plan vs done rows, the summary tables, the XLSX file read back, the
 * same numbers as the log and the overview, and the queries of a large plan.
 *
 * The clock is frozen at 2026-10-18 10:00 UTC, a Sunday, and the teachers' time zone is Europe/Madrid, so "today" is
 * 2026-10-18 and the export is made at 12:00 local time.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_classprogress\local\export_service
 * @covers     \mod_classprogress\local\plan_export
 * @covers     \mod_classprogress\output\export_view
 */
final class export_service_test extends \advanced_testcase {
    /** @var int The frozen moment: 2026-10-18 10:00 UTC. */
    private const NOW = 1792317600;

    /** @var string[] The header block labels of both sheets, as in PLAN.md section 5. */
    private const HEADER = ['Module', 'Course', 'Activity', 'School year', 'Plan dates', 'Planned sessions',
        'Planned hours', 'Exported by', 'Exported on'];

    /** @var string[] The column headers of the Plan vs done sheet, as in PLAN.md section 5. */
    private const COLUMNS = ['Date', 'Evaluation period', 'Block', 'Planned', 'Planned hours', 'Status', 'What was done',
        'Reason', 'Reason note', 'Corrective measure', 'Continues in the next session', 'Written on', 'Days late',
        'Logged by'];

    /** @var string[] The column headers of the Summary tables, as in PLAN.md section 5. */
    private const FIGURES = ['Group', 'Planned', 'Done', 'Partly done', 'Not done', 'Not logged', 'Upcoming', 'Added',
        'Coverage (%)', 'Planned hours', 'Hours done', 'Hours not done', 'Written late', 'Most days late'];

    /** @var string[] Text that a spreadsheet would read as a formula or a link if it were not written as text. */
    private const PAYLOADS = ['=HYPERLINK("http://x","y")', '+1', '-1', '@x'];

    /** @var \stdClass The teacher who exports, Anna Puig. */
    private \stdClass $teacher;

    /** @var \stdClass Another teacher of the course, Pau Serra. */
    private \stdClass $other;

    /** @var \stdClass The course, C1. */
    private \stdClass $course;

    /**
     * The activity generator.
     *
     * @return \mod_classprogress_generator The generator.
     */
    private function generator(): \mod_classprogress_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
    }

    /**
     * Freezes the clock and creates the course with its two teachers.
     */
    private function setup_course(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        $this->course = $this->getDataGenerator()->create_course(['fullname' => 'Cicle DAM & DAW', 'shortname' => 'C1']);
        $this->teacher = $this->getDataGenerator()->create_and_enrol(
            $this->course,
            'editingteacher',
            ['firstname' => 'Anna', 'lastname' => 'Puig', 'timezone' => 'Europe/Madrid']
        );
        $this->other = $this->getDataGenerator()->create_and_enrol(
            $this->course,
            'editingteacher',
            ['firstname' => 'Pau', 'lastname' => 'Serra', 'timezone' => 'Europe/Madrid']
        );
        $this->setUser($this->teacher);
    }

    /**
     * An approved plan of a school year 2026-09-14 to 2027-06-21 with three evaluation periods (a gap from
     * 2026-10-10 to 2026-10-12 between the first two; the third without days), and an RA heading without days in its
     * text.
     *
     * @param string $status The plan's status.
     * @return \stdClass The plan row, with cmid.
     */
    private function plan(string $status = 'approved'): \stdClass {
        $local = $this->getDataGenerator()->get_plugin_generator('local_classprogress');
        $year = $local->create_schoolyear(['userid' => $this->teacher->id]);
        $periods = [['1a avaluació', '2026-09-14', '2026-10-09'], ['3a avaluació', '2027-01-11', '2027-03-26'],
            ['2a avaluació', '2026-10-13', '2026-12-22']];
        foreach ($periods as [$name, $start, $end]) {
            $local->create_evaluation(['schoolyearid' => $year->id, 'name' => $name, 'startdate' => $start,
                'enddate' => $end]);
        }
        return $this->generator()->create_instance(['course' => $this->course->id, 'schoolyearid' => $year->id,
            'name' => 'Bases de dades', 'status' => $status, 'startdate' => '2026-09-14', 'enddate' => '2027-05-28',
            'modulecode' => 'MP 0372', 'moduletitle' => 'Gestió de bases de dades',
            'sourcetext' => "# MP 0372 · Gestió de bases de dades\n\n## RA1\n\n## RA9 · Sense dies\n"]);
    }

    /**
     * Fills a plan with ten planned days and two unplanned sessions: entries of every status, a day written late,
     * days not logged, today, an upcoming day, a cancellation logged in advance, a day without a block, a day outside
     * every evaluation period, two days on one date, decimal hours and an entry whose author was erased.
     *
     * @param int $planid Plan id.
     */
    private function fill(int $planid): void {
        $days = [
            ['2026-09-14', 'RA1', 'Presentació', "- Normes\n- **Avaluació**", 2],
            ['2026-09-16', 'RA1', 'Entorn', 'Instal·lació de l\'IDE', 1.5],
            ['2026-10-09', 'RA1', 'Examen RA1', 'Prova escrita', 2],
            ['2026-10-12', 'RA2', 'Disseny', 'Diagrama E/R', 2],
            ['2026-10-13', '', 'Tutoria', 'Dubtes', 1],
            ['2026-10-16', 'RA2', 'Formulari', self::PAYLOADS[0], 2],
            ['2026-10-16', 'RA2', 'Validació', 'Restriccions', 2],
            ['2026-10-18', 'RA2', 'Consultes', 'SELECT', 2],
            ['2026-10-19', 'RA2', 'Proves', 'Proves unitàries', 2],
            ['2026-10-20', 'RA2', 'Sortida', 'Visita a una empresa', 2],
        ];
        $items = [];
        foreach ($days as [$date, $block, $title, $content, $hours]) {
            $items[$title] = $this->generator()->create_item(['planid' => $planid, 'sessiondate' => $date,
                'block' => $block, 'title' => $title, 'content' => $content, 'hours' => $hours])->id;
        }
        $this->entry($this->teacher->id, ['planitemid' => $items['Presentació'], 'content' => '- Normes explicades',
            'writtenon' => '2026-09-14']);
        $partial = $this->entry($this->other->id, ['planitemid' => $items['Entorn'], 'status' => 'partial',
            'content' => 'Només la *instal·lació*', 'reason' => 'strike', 'reasonnote' => 'Vaga general',
            'measure' => '**Recuperar** el dijous', 'carry' => 1, 'writtenon' => '2026-09-19']);
        $this->generator()->create_entry_hist(['entryid' => $partial, 'userid' => $this->teacher->id,
            'action' => 'update']);
        $this->entry(0, ['planitemid' => $items['Examen RA1'], 'status' => 'not_done', 'content' => '',
            'reason' => 'incident', 'writtenon' => '2026-10-09']);
        $payloads = implode("\n", array_slice(self::PAYLOADS, 1));
        $this->entry($this->teacher->id, ['planitemid' => $items['Formulari'], 'content' => $payloads,
            'writtenon' => '2026-10-16']);
        $this->entry($this->teacher->id, ['planitemid' => $items['Sortida'], 'status' => 'not_done', 'content' => '',
            'reason' => 'trip', 'reasonnote' => 'Anul·lada', 'writtenon' => '2026-10-15']);
        $this->entry($this->teacher->id, ['planid' => $planid, 'sessiondate' => '2026-09-16', 'status' => 'added',
            'content' => 'Repàs', 'writtenon' => '2026-09-21']);
        $this->entry($this->teacher->id, ['planid' => $planid, 'sessiondate' => '2026-10-11', 'status' => 'added',
            'content' => 'Visita', 'writtenon' => '2026-10-11']);
    }

    /**
     * Creates an entry with the history row of its creation.
     *
     * @param int $userid Who created it; 0 for an erased user.
     * @param array $fields Entry fields.
     * @return int The entry id.
     */
    private function entry(int $userid, array $fields): int {
        $id = (int) $this->generator()->create_entry($fields)->id;
        $this->generator()->create_entry_hist(['entryid' => $id, 'userid' => $userid, 'action' => 'create']);
        return $id;
    }

    /**
     * The figures of a summary row as a list, in the order of the Summary columns after Group.
     *
     * @param summary_figures $figures The figures.
     * @return array The values.
     */
    private static function figures(summary_figures $figures): array {
        return [$figures->planned, $figures->done, $figures->partial, $figures->notdone, $figures->notlogged,
            $figures->upcoming, $figures->added, $figures->coverage, $figures->plannedhours, $figures->hoursdone,
            $figures->hoursnotdone, $figures->writtenlate, $figures->mostdayslate];
    }

    /**
     * A summary table as group name => figures, with the group names as the file writes them.
     *
     * @param summary_group[] $groups The table's rows.
     * @return array Group name => figures.
     */
    private static function table(array $groups): array {
        $table = [];
        foreach ($groups as $group) {
            $table[export_service::group_name($group)] = self::figures($group->figures);
        }
        return $table;
    }

    /**
     * Writes the export to a file and reads it back.
     *
     * @param plan_export $export The export.
     * @return array [the workbook, the path of the file].
     */
    private static function read_back(plan_export $export): array {
        $path = make_request_directory() . '/export.xlsx';
        file_put_contents($path, self::download($export));
        return [IOFactory::createReader('Xlsx')->load($path), $path];
    }

    /**
     * Sends the export as the download page does and captures the file.
     *
     * MoodleExcelWorkbook::close() sends HTTP headers, which PHP refuses with a warning on the command line once
     * PHPUnit has printed anything; that warning alone is ignored, every other one goes to PHPUnit.
     *
     * @param plan_export $export The export.
     * @return string The bytes of the file.
     */
    private static function download(plan_export $export): string {
        $previous = null;
        $previous = set_error_handler(function (int $errno, string $errstr, string $file = '', int $line = 0) use (&$previous) {
            if (str_starts_with($errstr, 'Cannot modify header information')) {
                return true;
            }
            return $previous ? $previous($errno, $errstr, $file, $line) : false;
        });
        ob_start();
        try {
            export_service::send($export);
        } finally {
            $bytes = ob_get_clean();
            restore_error_handler();
        }
        return $bytes;
    }

    /**
     * The values of a sheet, row by row from row 1, with null for empty cells.
     *
     * @param Worksheet $sheet The sheet.
     * @return array[] The rows.
     */
    private static function values(Worksheet $sheet): array {
        return $sheet->toArray(null, false, false, false);
    }

    /**
     * A value as the file holds it: nothing for an empty text, and text as write_string() stores it.
     *
     * @param mixed $value A value of the export.
     * @return mixed The value read back.
     */
    public static function as_written(mixed $value): mixed {
        if ($value === '') {
            return null;
        }
        if (is_string($value) && method_exists(\core\dataformat::class, 'escape_spreadsheet_formula')) {
            return \core\dataformat::escape_spreadsheet_formula($value);
        }
        return $value;
    }

    /**
     * Every planned day and unplanned session gives one row, by date and, within a date, planned days in plan order
     * and then unplanned sessions; the status of a day without an entry is not logged up to today and upcoming after;
     * text is plain text; logged by is the author of the entry's creation.
     *
     * @group REQ-EXP-003
     * @group REQ-EXP-004
     * @group REQ-EXP-007
     */
    public function test_the_plan_vs_done_rows(): void {
        $this->setup_course();
        $plan = $this->plan();
        $this->fill((int) $plan->id);

        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        $first = '1a avaluació';
        $second = '2a avaluació';
        $none = ['', '', '', '', '', '', null, ''];
        $expected = [
            ['2026-09-14', $first, 'RA1', "Presentació\n• Normes\n• Avaluació", 2.0, 'Done', '• Normes explicades', '', '',
                '', '', '2026-09-14', 0, 'Anna Puig'],
            ['2026-09-16', $first, 'RA1', "Entorn\nInstal·lació de l'IDE", 1.5, 'Partly done', 'Només la instal·lació',
                'Strike', 'Vaga general', 'Recuperar el dijous', 'Yes', '2026-09-19', 3, 'Pau Serra'],
            ['2026-09-16', $first, '', '', null, 'Added', 'Repàs', '', '', '', '', '2026-09-21', 5, 'Anna Puig'],
            ['2026-10-09', $first, 'RA1', "Examen RA1\nProva escrita", 2.0, 'Not done', '', 'Incident', '', '', '',
                '2026-10-09', 0, 'Deleted user'],
            ['2026-10-11', '', '', '', null, 'Added', 'Visita', '', '', '', '', '2026-10-11', 0, 'Anna Puig'],
            array_merge(['2026-10-12', '', 'RA2', "Disseny\nDiagrama E/R", 2.0, 'Not logged'], $none),
            array_merge(['2026-10-13', $second, '', "Tutoria\nDubtes", 1.0, 'Not logged'], $none),
            ['2026-10-16', $second, 'RA2', "Formulari\n" . self::PAYLOADS[0], 2.0, 'Done', "+1\n-1\n@x", '', '', '', '',
                '2026-10-16', 0, 'Anna Puig'],
            array_merge(['2026-10-16', $second, 'RA2', "Validació\nRestriccions", 2.0, 'Not logged'], $none),
            array_merge(['2026-10-18', $second, 'RA2', "Consultes\nSELECT", 2.0, 'Not logged'], $none),
            array_merge(['2026-10-19', $second, 'RA2', "Proves\nProves unitàries", 2.0, 'Upcoming'], $none),
            ['2026-10-20', $second, 'RA2', "Sortida\nVisita a una empresa", 2.0, 'Not done', '', 'Trip', 'Anul·lada', '',
                '', '2026-10-15', 0, 'Anna Puig'],
        ];
        $this->assertSame($expected, array_map('array_values', $export->rows));
        $this->assertSame('2026-10-18', $export->today);
    }

    /**
     * The three summary tables: overall; per block in order of first appearance, then no block and unplanned
     * sessions (an RA without days has no row); per evaluation period in date order, the first and last days
     * included, an empty period with zeros, then the sessions outside every period. Each table adds up to overall.
     *
     * @group REQ-EXP-005
     * @group REQ-EXP-006
     */
    public function test_the_summary_tables(): void {
        $this->setup_course();
        $plan = $this->plan();
        $this->fill((int) $plan->id);

        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        $this->assertSame(
            ['All sessions' => [10, 2, 1, 2, 4, 1, 2, 28, 18.5, 4.75, 4.75, 2, 5]],
            self::table($export->overall)
        );
        $this->assertSame([
            'RA1' => [3, 1, 1, 1, 0, 0, 0, 50, 5.5, 2.75, 2.75, 1, 3],
            'RA2' => [6, 1, 0, 1, 3, 1, 0, 20, 12.0, 2.0, 2.0, 0, 0],
            'No block' => [1, 0, 0, 0, 1, 0, 0, 0, 1.0, 0.0, 0.0, 0, 0],
            'Unplanned sessions' => [0, 0, 0, 0, 0, 0, 2, null, 0.0, 0.0, 0.0, 1, 5],
        ], self::table($export->perblock));
        $this->assertSame([
            '1a avaluació' => [3, 1, 1, 1, 0, 0, 1, 50, 5.5, 2.75, 2.75, 2, 5],
            '2a avaluació' => [6, 1, 0, 1, 3, 1, 0, 20, 11.0, 2.0, 2.0, 0, 0],
            '3a avaluació' => [0, 0, 0, 0, 0, 0, 0, null, 0.0, 0.0, 0.0, 0, 0],
            'Outside evaluation periods' => [1, 0, 0, 0, 1, 0, 1, 0, 2.0, 0.0, 0.0, 0, 0],
        ], self::table($export->perperiod));
    }

    /**
     * The header block names the module, course, activity, dates, planned sessions and hours, and who exported it
     * when, in their time zone; the file name is the module code, the course short name and the export date.
     *
     * @group REQ-EXP-002
     */
    public function test_the_header_block_and_the_file_name(): void {
        $this->setup_course();
        $plan = $this->plan();
        $this->fill((int) $plan->id);

        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        $this->assertSame(array_combine(self::HEADER, ['MP 0372 · Gestió de bases de dades', 'Cicle DAM & DAW',
            'Bases de dades', '2026-09-14 to 2027-06-21', '2026-09-14 to 2027-05-28', 10, 18.5, 'Anna Puig',
            '2026-10-18 12:00']), $export->header);
        $this->assertSame('MP 0372-C1-2026-10-18.xlsx', $export->filename);
    }

    /**
     * A course short name with path, quote and line-break characters gives a file name without any of them.
     *
     * @group REQ-EXP-002
     */
    public function test_the_file_name_drops_unsafe_characters(): void {
        global $DB;

        $this->setup_course();
        $plan = $this->plan();
        $DB->set_field('course', 'shortname', "../C\"1<x>|y\\z/\r\nX-Header: v", ['id' => $plan->course]);

        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        $this->assertSame('MP 0372-..C1xyzX-Header v-2026-10-18.xlsx', $export->filename);
    }

    /**
     * The file has the two sheets in order, each with the header block, then the rows or the three tables with the
     * labels of the layout contract; dates are text and figures numbers.
     *
     * @group REQ-EXP-002
     * @group REQ-EXP-006
     */
    public function test_the_file_holds_both_sheets_with_the_contract_labels(): void {
        $this->setup_course();
        $plan = $this->plan();
        $this->fill((int) $plan->id);
        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        [$book] = self::read_back($export);

        $this->assertSame(['Plan vs done', 'Summary'], $book->getSheetNames());
        foreach ($book->getAllSheets() as $sheet) {
            $values = self::values($sheet);
            $this->assertSame(self::HEADER, array_column(array_slice($values, 0, 9), 0), $sheet->getTitle());
            $this->assertEquals(array_values($export->header), array_column(array_slice($values, 0, 9), 1));
            $this->assertSame([null], array_unique($values[9]), $sheet->getTitle());
        }

        $rows = self::values($book->getSheetByName('Plan vs done'));
        $this->assertSame(self::COLUMNS, $rows[10]);
        $this->assertCount(11 + 12, $rows);
        $written = array_map(fn(array $row): array => array_map([self::class, 'as_written'], array_values($row)), $export->rows);
        $this->assertEquals($written, array_slice($rows, 11));
        $sheet = $book->getSheetByName('Plan vs done');
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A12')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('E12')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('M12')->getDataType());

        $summary = self::values($book->getSheetByName('Summary'));
        $layout = [10 => ['Overall'], 11 => self::FIGURES, 12 => ['All sessions'], 13 => [null],
            14 => ['Per block'], 15 => self::FIGURES, 16 => ['RA1'], 17 => ['RA2'], 18 => ['No block'],
            19 => ['Unplanned sessions'], 20 => [null], 21 => ['Per evaluation period'], 22 => self::FIGURES,
            23 => ['1a avaluació'], 24 => ['2a avaluació'], 25 => ['3a avaluació'], 26 => ['Outside evaluation periods']];
        $this->assertCount(27, $summary);
        foreach ($layout as $index => $start) {
            $this->assertSame($start, array_slice($summary[$index], 0, count($start)), 'Summary row ' . ($index + 1));
        }
        $this->assertEquals([10, 2, 1, 2, 4, 1, 2, 28, 18.5, 4.75, 4.75, 2, 5], array_slice($summary[12], 1));
        $this->assertEquals([0, 0, 0, 0, 0, 0, 2, null, 0, 0, 0, 1, 5], array_slice($summary[19], 1));
        $this->assertSame(DataType::TYPE_NUMERIC, $book->getSheetByName('Summary')->getCell('I13')->getDataType());
    }

    /**
     * No cell of the file is a formula or a link: text that begins with =, +, - or @ stays text, with the apostrophe
     * that Moodle puts before it.
     *
     * @group REQ-EXP-008
     */
    public function test_no_cell_is_a_formula_or_a_link(): void {
        $this->setup_course();
        $plan = $this->plan();
        $this->fill((int) $plan->id);
        $item = $this->generator()->create_item(['planid' => $plan->id, 'sessiondate' => '2026-11-02', 'block' => '=1+1',
            'title' => '', 'content' => self::PAYLOADS[0]]);
        $this->entry($this->teacher->id, ['planitemid' => $item->id, 'status' => 'not_done', 'content' => '@x',
            'reason' => 'other', 'reasonnote' => '+1', 'measure' => '-1', 'writtenon' => '2026-10-18']);
        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        [$book, $path] = self::read_back($export);

        $texts = [];
        foreach ($book->getAllSheets() as $sheet) {
            $this->assertSame([], $sheet->getHyperlinkCollection(), $sheet->getTitle());
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                $this->assertNotSame(DataType::TYPE_FORMULA, $cell->getDataType(), $sheet->getTitle() . '!' . $coordinate);
                if ($cell->getDataType() === DataType::TYPE_STRING) {
                    $texts[] = (string) $cell->getValue();
                }
            }
        }
        // Moodle 4.5 from about 4.5.8, and 5.3, put an apostrophe before such text; earlier 4.5 releases write it as is.
        $escaped = method_exists(\core\dataformat::class, 'escape_spreadsheet_formula');
        foreach (array_merge(self::PAYLOADS, ['=1+1']) as $payload) {
            $this->assertContains($escaped ? "'" . $payload : $payload, $texts);
        }

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/')) {
                $xml = $zip->getFromIndex($i);
                $this->assertStringNotContainsString('<f>', $xml, $name);
                $this->assertStringNotContainsString('<f ', $xml, $name);
                $this->assertStringNotContainsString('<hyperlink', $xml, $name);
            }
        }
        $zip->close();
    }

    /**
     * A draft plan has no export; a teacher outside the course gets the not-found error; any teacher of the course
     * may export.
     *
     * @group REQ-EXP-001
     */
    public function test_only_an_approved_plan_of_the_users_course_exports(): void {
        $this->setup_course();
        $draft = $this->plan('draft');
        $plan = $this->plan();
        $outsider = $this->getDataGenerator()->create_and_enrol(
            $this->getDataGenerator()->create_course(),
            'editingteacher'
        );
        $assistant = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');

        $this->assertSame((int) $plan->id, (int) export_service::build((int) $assistant->id, (int) $plan->id)->plan->id);
        $refused = [[$this->teacher->id, $draft->id, 'error_plannotapproved'],
            [$outsider->id, $plan->id, 'error_notfound'], [$outsider->id, $draft->id, 'error_notfound']];
        foreach ($refused as [$userid, $planid, $error]) {
            try {
                export_service::build((int) $userid, (int) $planid);
                $this->fail('Exported plan ' . $planid . ' for user ' . $userid);
            } catch (\moodle_exception $e) {
                $this->assertSame($error, $e->errorcode);
            }
        }
    }

    /**
     * The overall "not logged" equals the pending sessions of the log page and of the top-menu overview, and the
     * export page shows the figures of the file, hours with up to three decimals.
     *
     * @group REQ-EXP-009
     */
    public function test_the_same_numbers_everywhere(): void {
        global $PAGE;

        $this->setup_course();
        $plan = $this->plan();
        $this->fill((int) $plan->id);
        // A partly done day of 1.25 hours gives 0.625 hours done and not done: three decimals on the page too.
        $item = $this->generator()->create_item(['planid' => $plan->id, 'sessiondate' => '2026-10-14', 'block' => 'RA2',
            'hours' => 1.25]);
        $this->entry($this->teacher->id, ['planitemid' => $item->id, 'status' => 'partial', 'reason' => 'other',
            'writtenon' => '2026-10-14']);

        $export = export_service::build((int) $this->teacher->id, (int) $plan->id);

        $notlogged = $export->overall[0]->figures->notlogged;
        $this->assertSame(4, $notlogged);
        $pending = entry_service::pending_counts((int) $this->teacher->id, [(int) $plan->id]);
        $this->assertSame([(int) $plan->id => $notlogged], $pending);
        $this->assertCount($notlogged, entry_service::log((int) $this->teacher->id, (int) $plan->id)->groups->pending);
        $this->assertSame([$notlogged], array_column(overview::for_user((int) $this->teacher->id), 'pending'));

        $context = (new export_view($export, new \core\url('/mod/classprogress/export.php')))
            ->export_for_template($PAGE->get_renderer('core'));
        $page = [];
        foreach ($context->tables as $table) {
            foreach ($table['rows'] as $row) {
                $page[$table['id']][] = $row['cells'];
            }
        }
        $file = [];
        $tables = ['export-overall' => $export->overall, 'export-perblock' => $export->perblock,
            'export-perperiod' => $export->perperiod];
        foreach ($tables as $id => $groups) {
            foreach ($groups as $group) {
                $file[$id][] = array_map(
                    fn($value): string => $value === null ? '' : (string) $value,
                    array_merge([export_service::group_name($group)], self::figures($group->figures))
                );
            }
        }
        $this->assertSame($file, $page);
    }

    /**
     * A plan of 400 days, each with an entry, exports with as many queries as a plan of 5 days.
     *
     * @group REQ-EXP-010
     */
    public function test_a_large_plan_exports_with_a_fixed_number_of_queries(): void {
        global $DB;

        $this->setup_course();
        $counts = [];
        foreach ([5, 400] as $days) {
            $plan = $this->plan();
            for ($i = 0; $i < $days; $i++) {
                $date = (new \DateTimeImmutable('2026-09-14'))->modify('+' . ($i % 280) . ' days')->format('Y-m-d');
                $item = $this->generator()->create_item(['planid' => $plan->id, 'sessiondate' => $date,
                    'block' => 'RA' . (1 + $i % 7)]);
                $this->entry((int) $this->teacher->id, ['planitemid' => $item->id, 'status' => 'not_done',
                    'content' => '', 'reason' => 'other', 'writtenon' => $date]);
            }
            // A first export warms the caches that do not depend on the plan.
            self::download(export_service::build((int) $this->teacher->id, (int) $plan->id));
            $before = $DB->perf_get_queries();
            $export = export_service::build((int) $this->teacher->id, (int) $plan->id);
            self::download($export);
            $counts[$days] = $DB->perf_get_queries() - $before;
            $this->assertCount($days, $export->rows);
        }

        $this->assertGreaterThan(0, $counts[5]);
        $this->assertSame($counts[5], $counts[400]);
    }
}
