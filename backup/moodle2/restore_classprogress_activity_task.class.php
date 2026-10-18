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
 * Restore task of the class progress activity.
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/classprogress/backup/moodle2/restore_classprogress_stepslib.php');

/**
 * Restores one plan activity (REQ-MOD-006).
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_classprogress_activity_task extends restore_activity_task {
    /**
     * The activity has no settings of its own.
     */
    #[\Override]
    protected function define_my_settings() {
    }

    /**
     * The one step: the plan and what hangs from it.
     */
    #[\Override]
    protected function define_my_steps() {
        $this->add_step(new restore_classprogress_activity_structure_step('classprogress_structure', 'classprogress.xml'));
    }

    /**
     * The contents whose links are decoded: the description.
     *
     * @return restore_decode_content[] The contents.
     */
    #[\Override]
    public static function define_decode_contents() {
        return [new restore_decode_content('classprogress', ['intro'], 'classprogress')];
    }

    /**
     * The links that backup_classprogress_activity_task encoded.
     *
     * @return restore_decode_rule[] The rules.
     */
    #[\Override]
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('CLASSPROGRESSVIEWBYID', '/mod/classprogress/view.php?id=$1', 'course_module'),
            new restore_decode_rule('CLASSPROGRESSINDEX', '/mod/classprogress/index.php?id=$1', 'course'),
        ];
    }
}
