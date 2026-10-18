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
 * Backup task of the class progress activity.
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/classprogress/backup/moodle2/backup_classprogress_stepslib.php');

/**
 * Backs up one plan activity (REQ-MOD-006).
 *
 * @package    mod_classprogress
 * @category   backup
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_classprogress_activity_task extends backup_activity_task {
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
        $this->add_step(new backup_classprogress_activity_structure_step('classprogress_structure', 'classprogress.xml'));
    }

    /**
     * Encodes the links to the activity's pages so that a restore can point them to the new activity.
     *
     * @param string $content Content with links.
     * @return string The content with encoded links.
     */
    #[\Override]
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace("/({$base}\/mod\/classprogress\/index.php\?id=)([0-9]+)/", '$@CLASSPROGRESSINDEX*$2@$', $content);
        return preg_replace("/({$base}\/mod\/classprogress\/view.php\?id=)([0-9]+)/", '$@CLASSPROGRESSVIEWBYID*$2@$', $content);
    }
}
