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
 * Backup structure of the class progress activity.
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines what a backup of a plan activity holds (REQ-MOD-006).
 *
 * Always: the plan with its text, status and the school year dates it copied at approval, its timetable, its days, its
 * import log, and the holidays and evaluation periods it copied at approval. Only with user data: the entries and
 * their whole history. The school year itself is a teacher's private data and is not backed up.
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_classprogress_activity_structure_step extends backup_activity_structure_step {
    /**
     * Builds the tree of elements and where each takes its rows from.
     *
     * @return backup_nested_element The activity element.
     */
    #[\Override]
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $plan = new backup_nested_element('classprogress', ['id'], ['name', 'intro', 'introformat', 'schoolyearid',
            'modulecode', 'moduletitle', 'startdate', 'enddate', 'status', 'sourcetext', 'yearstart', 'yearend',
            'timeapproved', 'approvedby', 'timecreated', 'timemodified']);
        $slots = new backup_nested_element('slots');
        $slot = new backup_nested_element('slot', ['id'], ['weekday', 'hours', 'timecreated', 'timemodified']);
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], ['sortorder', 'sessiondate', 'block', 'title', 'content', 'hours',
            'timecreated']);
        $imports = new backup_nested_element('imports');
        $import = new backup_nested_element('import', ['id'], ['userid', 'source', 'filename', 'contenthash', 'daycount',
            'timecreated']);
        $holidays = new backup_nested_element('holidays');
        $holiday = new backup_nested_element('holiday', ['id'], ['startdate', 'enddate', 'label']);
        $evaluations = new backup_nested_element('evaluations');
        $evaluation = new backup_nested_element('evaluation', ['id'], ['name', 'startdate', 'enddate']);
        $entries = new backup_nested_element('entries');
        $entry = new backup_nested_element('entry', ['id'], ['planitemid', 'sessiondate', 'status', 'content',
            'contentformat', 'reason', 'reasonnote', 'measure', 'carry', 'writtenon', 'timecreated', 'timemodified']);
        $hists = new backup_nested_element('hists');
        $hist = new backup_nested_element('hist', ['id'], ['entryid', 'userid', 'realuserid', 'action', 'snapshot',
            'timecreated']);

        foreach (
            [[$slots, $slot], [$items, $item], [$imports, $import], [$holidays, $holiday],
                [$evaluations, $evaluation], [$entries, $entry], [$hists, $hist]] as [$list, $element]
        ) {
            $plan->add_child($list);
            $list->add_child($element);
        }

        $plan->set_source_table('classprogress', ['id' => backup::VAR_ACTIVITYID]);
        $byplan = ['planid' => backup::VAR_PARENTID];
        $slot->set_source_table('classprogress_slot', $byplan, 'weekday ASC');
        $item->set_source_table('classprogress_item', $byplan, 'sortorder ASC, id ASC');
        $import->set_source_table('classprogress_import', $byplan, 'id ASC');
        $holiday->set_source_table('classprogress_holiday', $byplan, 'id ASC');
        $evaluation->set_source_table('classprogress_evaluation', $byplan, 'id ASC');
        if ($userinfo) {
            $entry->set_source_table('classprogress_entry', $byplan, 'id ASC');
            $hist->set_source_table('classprogress_entry_hist', $byplan, 'id ASC');
        }

        $plan->annotate_ids('user', 'approvedby');
        $import->annotate_ids('user', 'userid');
        $hist->annotate_ids('user', 'userid');
        $hist->annotate_ids('user', 'realuserid');
        $plan->annotate_files('mod_classprogress', 'intro', null);

        return $this->prepare_activity_structure($plan);
    }
}
