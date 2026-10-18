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

/**
 * Event observers registered in db/events.php.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observers {
    /**
     * Erases the authorship of a user whose account is deleted, in every plan activity, without waiting for a privacy
     * request; the content stays (REQ-PRV-004).
     *
     * @param \core\event\user_deleted $event The account deletion.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        authorship::erase([(int) $event->objectid]);
    }
}
