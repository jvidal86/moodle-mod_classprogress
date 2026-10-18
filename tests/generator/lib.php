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

/**
 * Data generator for the class progress activity.
 *
 * @package    mod_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_classprogress\form\date_field;
use mod_classprogress\local\plan_service;

/**
 * Creates plan activities and the rows that hang from them, for PHPUnit and Behat.
 *
 * create_instance() goes through the activity form's callbacks, acting as the owner of the plan's school year (a new
 * school year of the current user, or of a new user when nobody is logged in, when none is given), so the plan is
 * stored as a teacher would store it; fields the form does not set (status approved, source text, module) are written
 * afterwards. The other methods insert one row each, with defaults that make it valid, and return it as stored.
 *
 * @package    mod_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_classprogress_generator extends testing_module_generator {
    /** @var int Rows created since the last reset, used to make default texts unique. */
    protected int $count = 0;

    /**
     * Clears the counter between tests.
     */
    #[\Override]
    public function reset() {
        $this->count = 0;
        parent::reset();
    }

    /**
     * Creates a plan activity.
     *
     * @param array|stdClass|null $record Fields: course (required); name; schoolyearid, or schoolyear by name (Behat);
     *     startdate and enddate as yyyy-mm-dd (default: those of the school year; empty for none); timetable as weekday
     *     => hours, or hours0 to hours6; status approved to approve it at once (with a copy of the school year's dates,
     *     holidays and evaluation periods, approved by the acting user); sourcetext, modulecode and moduletitle.
     * @param array|null $options Course module options.
     * @return stdClass The activity record, with cmid.
     */
    #[\Override]
    public function create_instance($record = null, ?array $options = null) {
        global $DB, $USER;

        $this->count++;
        $record = (array) ($record ?? []);
        if (isset($record['schoolyear'])) {
            $record['schoolyearid'] = $DB->get_field(
                'local_classprogress_schoolyear',
                'id',
                ['name' => $record['schoolyear']],
                MUST_EXIST
            );
            unset($record['schoolyear']);
        }
        if (empty($record['schoolyearid'])) {
            $userid = !empty($USER->id) ? (int) $USER->id : (int) $this->datagenerator->create_user()->id;
            $record['schoolyearid'] = $this->local()->create_schoolyear(['userid' => $userid])->id;
        }
        $year = $DB->get_record('local_classprogress_schoolyear', ['id' => $record['schoolyearid']], '*', MUST_EXIST);
        $extra = array_intersect_key($record, array_flip(['status', 'sourcetext', 'modulecode', 'moduletitle']));
        $record = array_diff_key($record, $extra);
        foreach (['startdate' => $year->startdate, 'enddate' => $year->enddate] as $field => $default) {
            $day = (string) ($record[$field] ?? $default);
            $record[$field] = $day;
        }
        foreach ($record['timetable'] ?? [] as $weekday => $hours) {
            $record['hours' . $weekday] = $hours;
        }
        unset($record['timetable']);

        $previous = clone $USER;
        if ((int) $USER->id !== (int) $year->userid) {
            \core\session\manager::set_user(\core_user::get_user($year->userid, '*', MUST_EXIST));
        }
        try {
            foreach (['startdate', 'enddate'] as $field) {
                $record[$field] = date_field::to_timestamp($record[$field]);
            }
            $instance = parent::create_instance($record, (array) $options);
        } finally {
            if ((int) $USER->id !== (int) $previous->id) {
                \core\session\manager::set_user($previous);
            }
        }

        if ($extra) {
            $this->write_extra($instance, $extra, $year);
        }
        $stored = $DB->get_record('classprogress', ['id' => $instance->id], '*', MUST_EXIST);
        $stored->cmid = $instance->cmid;
        return $stored;
    }

    /**
     * Creates a plan item (a planned day).
     *
     * @param array $record Fields to set: planid (required), placed after the plan's last item by default, on
     *     2026-09-14, block "RA1", no title, a one-line body, 2 hours.
     * @return stdClass The stored row.
     */
    public function create_item(array $record): stdClass {
        global $DB;

        $this->count++;
        $last = 'SELECT MAX(sortorder) FROM {classprogress_item} WHERE planid = ?';
        $record['sortorder'] ??= 1 + (int) $DB->get_field_sql($last, [$record['planid']]);
        return $this->insert('classprogress_item', [
            'sessiondate' => '2026-09-14',
            'block' => 'RA1',
            'title' => '',
            'content' => 'Planned content ' . $this->count,
            'hours' => 2,
            'timecreated' => time(),
        ], $record);
    }

    /**
     * Creates an entry.
     *
     * @param array $record Fields to set: planid, or planitemid (whose plan and date it takes). Unplanned by default,
     *     on 2026-09-14; status "done", a one-line Markdown text, no reason, written on the session date.
     * @return stdClass The stored row.
     */
    public function create_entry(array $record): stdClass {
        global $DB;

        $this->count++;
        if (!empty($record['planitemid'])) {
            $item = $DB->get_record('classprogress_item', ['id' => $record['planitemid']], '*', MUST_EXIST);
            $record['planid'] ??= $item->planid;
            $record['sessiondate'] ??= $item->sessiondate;
        }
        $record['sessiondate'] ??= '2026-09-14';
        $record['writtenon'] ??= $record['sessiondate'];
        return $this->insert('classprogress_entry', [
            'planitemid' => null,
            'status' => 'done',
            'content' => 'Done ' . $this->count,
            'contentformat' => FORMAT_MARKDOWN,
            'reason' => '',
            'reasonnote' => null,
            'measure' => null,
            'carry' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * Creates a history row for an entry.
     *
     * @param array $record Fields to set: entryid (required), userid (required); planid for an entry that no longer
     *     exists. Defaults: the entry's plan, not logged in as anyone, action "create" with a JSON snapshot of the entry.
     * @return stdClass The stored row.
     */
    public function create_entry_hist(array $record): stdClass {
        global $DB;

        $this->count++;
        $entry = $DB->get_record('classprogress_entry', ['id' => $record['entryid']]);
        $record['planid'] ??= $entry ? $entry->planid : null;
        return $this->insert('classprogress_entry_hist', [
            'realuserid' => null,
            'action' => 'create',
            'snapshot' => json_encode($entry ?: new stdClass()),
            'timecreated' => time(),
        ], $record);
    }

    /**
     * Creates an import log row.
     *
     * @param array $record Fields to set: planid and userid (required). Defaults: pasted text, a SHA-1 of a made-up
     *     text, no days.
     * @return stdClass The stored row.
     */
    public function create_import(array $record): stdClass {
        $this->count++;
        return $this->insert('classprogress_import', [
            'source' => 'paste',
            'filename' => '',
            'contenthash' => sha1('Plan text ' . $this->count),
            'daycount' => 0,
            'timecreated' => time(),
        ], $record);
    }

    /**
     * Writes the fields that the activity form does not set.
     *
     * @param stdClass $instance The new activity.
     * @param array $extra status, sourcetext, modulecode and moduletitle, as given.
     * @param stdClass $year The plan's school year.
     */
    protected function write_extra(stdClass $instance, array $extra, stdClass $year): void {
        global $DB;

        $fields = array_diff_key($extra, ['status' => true]);
        if (($extra['status'] ?? '') === plan_service::STATUS_APPROVED) {
            $fields += ['status' => plan_service::STATUS_APPROVED, 'timeapproved' => time(), 'approvedby' => $year->userid,
                'yearstart' => $year->startdate, 'yearend' => $year->enddate];
            foreach ($DB->get_records('local_classprogress_holiday', ['schoolyearid' => $year->id]) as $holiday) {
                $DB->insert_record('classprogress_holiday', (object) ['planid' => $instance->id,
                    'startdate' => $holiday->startdate, 'enddate' => $holiday->enddate, 'label' => $holiday->label]);
            }
            foreach ($DB->get_records('local_classprogress_evaluation', ['schoolyearid' => $year->id]) as $evaluation) {
                $DB->insert_record('classprogress_evaluation', (object) ['planid' => $instance->id,
                    'name' => $evaluation->name, 'startdate' => $evaluation->startdate, 'enddate' => $evaluation->enddate]);
            }
        }
        if ($fields) {
            $DB->update_record('classprogress', (object) (['id' => $instance->id] + $fields));
        }
    }

    /**
     * The data generator of local_classprogress, for school years.
     *
     * @return local_classprogress_generator The generator.
     */
    protected function local(): local_classprogress_generator {
        return $this->datagenerator->get_plugin_generator('local_classprogress');
    }

    /**
     * Inserts a row and reads it back.
     *
     * @param string $table Table name.
     * @param array $defaults Default values.
     * @param array $record Values given by the caller; they win over the defaults.
     * @return stdClass The stored row.
     */
    protected function insert(string $table, array $defaults, array $record): stdClass {
        global $DB;

        $id = $DB->insert_record($table, (object) array_merge($defaults, $record));
        return $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
    }
}
