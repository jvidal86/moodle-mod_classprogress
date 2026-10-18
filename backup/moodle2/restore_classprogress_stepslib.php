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
 * Restore structure of the class progress activity.
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores what backup_classprogress_activity_structure_step wrote (REQ-MOD-006).
 *
 * The restored plan has no school year (the school year is a teacher's private data): an approved plan is complete
 * on its own with the dates, holidays and evaluation periods it copied at approval, and a draft asks for a school year
 * (REQ-DAT-006). Users who are not restored are named as authorship::DELETED_USER. History rows of entries that were
 * deleted before the backup keep no entry id (0), so they still show among the plan's deleted entries.
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_classprogress_activity_structure_step extends restore_activity_structure_step {
    /**
     * The paths to restore; entries and history only with user data.
     *
     * @return restore_path_element[] The paths.
     */
    #[\Override]
    protected function define_structure() {
        $paths = [new restore_path_element('classprogress', '/activity/classprogress')];
        foreach (
            ['slot' => 'slots', 'item' => 'items', 'import' => 'imports', 'holiday' => 'holidays',
                'evaluation' => 'evaluations'] as $name => $list
        ) {
            $paths[] = new restore_path_element("classprogress_{$name}", "/activity/classprogress/{$list}/{$name}");
        }
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('classprogress_entry', '/activity/classprogress/entries/entry');
            $paths[] = new restore_path_element('classprogress_entry_hist', '/activity/classprogress/hists/hist');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the plan, without its school year.
     *
     * @param array $data The plan's fields.
     */
    protected function process_classprogress($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->schoolyearid = 0;
        $data->approvedby = $this->user($data->approvedby);
        $this->apply_activity_instance($DB->insert_record('classprogress', $data));
    }

    /**
     * Restores a timetable slot.
     *
     * @param array $data The slot's fields.
     */
    protected function process_classprogress_slot($data) {
        $this->insert('classprogress_slot', $data);
    }

    /**
     * Restores a planned day, remembering its new id for the entries.
     *
     * @param array $data The day's fields.
     */
    protected function process_classprogress_item($data) {
        $this->set_mapping('classprogress_item', $data['id'], $this->insert('classprogress_item', $data));
    }

    /**
     * Restores a row of the import log, remembering its new id for the events.
     *
     * @param array $data The row's fields.
     */
    protected function process_classprogress_import($data) {
        $data['userid'] = $this->user($data['userid']);
        $this->set_mapping('classprogress_import', $data['id'], $this->insert('classprogress_import', $data));
    }

    /**
     * Restores a holiday copied at approval.
     *
     * @param array $data The holiday's fields.
     */
    protected function process_classprogress_holiday($data) {
        $this->insert('classprogress_holiday', $data);
    }

    /**
     * Restores an evaluation period copied at approval.
     *
     * @param array $data The period's fields.
     */
    protected function process_classprogress_evaluation($data) {
        $this->insert('classprogress_evaluation', $data);
    }

    /**
     * Restores an entry, on its restored planned day, remembering its new id for the history.
     *
     * @param array $data The entry's fields.
     */
    protected function process_classprogress_entry($data) {
        if ($data['planitemid'] !== null && $data['planitemid'] !== '') {
            $data['planitemid'] = $this->get_mappingid('classprogress_item', $data['planitemid']) ?: null;
        } else {
            $data['planitemid'] = null;
        }
        $this->set_mapping('classprogress_entry', $data['id'], $this->insert('classprogress_entry', $data));
    }

    /**
     * Restores a history row, on its restored entry (0 for an entry deleted before the backup).
     *
     * @param array $data The row's fields.
     */
    protected function process_classprogress_entry_hist($data) {
        $data['entryid'] = $this->get_mappingid('classprogress_entry', $data['entryid']) ?: 0;
        $data['userid'] = $this->user($data['userid']);
        if ($data['realuserid'] !== null && $data['realuserid'] !== '') {
            $data['realuserid'] = $this->user($data['realuserid']);
        } else {
            $data['realuserid'] = null;
        }
        $this->insert('classprogress_entry_hist', $data);
    }

    /**
     * Restores the files of the description.
     */
    #[\Override]
    protected function after_execute() {
        $this->add_related_files('mod_classprogress', 'intro', null);
    }

    /**
     * Inserts a row that hangs from the restored plan.
     *
     * @param string $table Table.
     * @param array $data The row's fields, with its old id.
     * @return int The new id.
     */
    private function insert(string $table, array $data): int {
        global $DB;

        unset($data['id']);
        $data['planid'] = $this->get_new_parentid('classprogress');
        return (int) $DB->insert_record($table, (object) $data);
    }

    /**
     * The restored id of a user, or authorship::DELETED_USER when that user is not restored.
     *
     * @param mixed $userid The user's id in the backup.
     * @return int The user's id here.
     */
    private function user($userid): int {
        return (int) ($this->get_mappingid('user', $userid) ?: \mod_classprogress\local\authorship::DELETED_USER);
    }
}
