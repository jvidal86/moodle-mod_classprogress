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

namespace mod_classprogress;

/**
 * Tests for the database schema of the activity: install, upgrade, and one entry per plan item.
 *
 * The suite runs on PostgreSQL (4.5 stack) and MariaDB (5.3 stack), which is where the unique index on the nullable
 * entry.planitemid has to behave the same.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class schema_test extends \advanced_testcase {
    /** @var string[] The tables of the activity in PLAN.md section 3b. */
    private const TABLES = ['classprogress', 'classprogress_slot', 'classprogress_item', 'classprogress_import',
        'classprogress_entry', 'classprogress_entry_hist', 'classprogress_holiday', 'classprogress_evaluation'];

    /**
     * Reads the activity's install.xml.
     *
     * @return \xmldb_structure The tables declared in db/install.xml.
     */
    private static function install_xml(): \xmldb_structure {
        global $DB;

        $DB->get_manager();
        $file = new \xmldb_file(\core_component::get_component_directory('mod_classprogress') . '/db/install.xml');
        if (!$file->fileExists() || !$file->loadXMLStructure() || !$file->isLoaded()) {
            throw new \coding_exception('db/install.xml cannot be read');
        }
        return $file->getStructure();
    }

    /**
     * Installing creates exactly the tables of the data model, as install.xml declares them, and the upgrade function
     * succeeds.
     *
     * @group REQ-DAT-001
     */
    public function test_install_creates_the_tables_and_upgrade_succeeds(): void {
        global $CFG, $DB;
        require_once(\core_component::get_component_directory('mod_classprogress') . '/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');

        $declared = array_map(fn(\xmldb_table $t): string => $t->getName(), self::install_xml()->getTables());

        $this->assertEqualsCanonicalizing(self::TABLES, $declared);
        $this->assertSame([], $DB->get_manager()->check_database_schema(self::install_xml(), ['extratables' => false]));
        $this->assertTrue(xmldb_classprogress_upgrade((int) get_config('mod_classprogress', 'version')));
    }

    /**
     * Each plan item can have its own entry, entries without a plan item (unplanned sessions) are not limited, and a
     * second entry for a plan item is refused by the database, also when an entry is moved onto it.
     *
     * @group REQ-DAT-002
     */
    public function test_one_entry_per_plan_item_and_any_number_of_unplanned_ones(): void {
        global $DB;

        $this->resetAfterTest();
        // A refused insert ends a PostgreSQL transaction, so the test does not run in one.
        $this->preventResetByRollback();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_classprogress');
        $plan = $gen->create_instance(['course' => $this->getDataGenerator()->create_course()->id]);
        $first = $gen->create_item(['planid' => $plan->id]);
        $second = $gen->create_item(['planid' => $plan->id]);
        $gen->create_entry(['planitemid' => $first->id]);
        $gen->create_entry(['planitemid' => $second->id]);
        foreach (['2026-09-14', '2026-09-14', '2026-09-15'] as $date) {
            $unplanned = $gen->create_entry(['planid' => $plan->id, 'sessiondate' => $date]);
        }
        $this->assertSame(5, $DB->count_records('classprogress_entry', ['planid' => $plan->id]));

        try {
            $gen->create_entry(['planitemid' => $first->id]);
            $this->fail('Expected the database to refuse a second entry');
        } catch (\dml_write_exception $e) {
            $this->assertSame(1, $DB->count_records('classprogress_entry', ['planitemid' => $first->id]));
        }
        $this->expectException(\dml_write_exception::class);
        $DB->update_record('classprogress_entry', (object) ['id' => $unplanned->id, 'planitemid' => $first->id]);
    }
}
