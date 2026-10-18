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
 * Behat data generator for the class progress activity.
 *
 * @package    mod_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates the days and entries of plan activities for Behat scenarios, through the activity's data generator.
 *
 * The activities themselves are Moodle's "activities" (activity classprogress, course, name, schoolyear by name,
 * startdate, enddate, hours0 to hours6, status approved for a locked plan). Entities: "mod_classprogress > plan items"
 * (plan by activity name, sessiondate, title) and "entries" (planitem by title, written with the history row of its
 * creation by the user given, as the entry service writes it).
 *
 * @package    mod_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_classprogress_generator extends behat_generator_base {
    /**
     * The entities that scenarios can create.
     *
     * @return array Entity name => how to create it.
     */
    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'plan items' => [
                'singular' => 'plan item',
                'datagenerator' => 'item',
                'required' => ['plan', 'sessiondate', 'title'],
                'switchids' => ['plan' => 'planid'],
            ],
            'entries' => [
                'singular' => 'entry',
                'datagenerator' => 'entry',
                'required' => ['planitem', 'user'],
                'switchids' => ['planitem' => 'planitemid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Finds a plan activity by name.
     *
     * @param string $name Its name.
     * @return int Its plan id.
     */
    protected function get_plan_id(string $name): int {
        global $DB;

        return (int) $DB->get_field('classprogress', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Finds a plan item by title.
     *
     * @param string $title Its title.
     * @return int Its id.
     */
    protected function get_planitem_id(string $title): int {
        global $DB;

        return (int) $DB->get_field('classprogress_item', 'id', ['title' => $title], MUST_EXIST);
    }

    /**
     * Creates an entry with the history row of its creation.
     *
     * @param array $data Entry fields, with the user who wrote it.
     */
    protected function process_entry(array $data): void {
        $userid = $data['userid'];
        unset($data['userid']);
        $entry = $this->componentdatagenerator->create_entry($data);
        $this->componentdatagenerator->create_entry_hist(['entryid' => $entry->id, 'userid' => $userid,
            'timecreated' => $entry->timecreated]);
    }
}
