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
 * Who may reach a plan activity: the users holding its capabilities in its module context, checked live.
 *
 * A plan activity is shared by the teachers of its course and by nobody else (REQ-OWN-001, REQ-MOD-004). A user who
 * may not open it gets the same error as for a plan that does not exist (error_notfound, REQ-OWN-002); a user who may
 * open it but not work in it gets Moodle's permission error for a change.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string See the activity and its plan, log and history. */
    public const VIEW = 'mod/classprogress:view';

    /** @var string Import, approve and log sessions. */
    public const EDIT = 'mod/classprogress:edit';

    /**
     * Returns a plan the user may reach with a capability, checked live in the activity's module context.
     *
     * @param int $userid The acting user.
     * @param int $id Plan (activity instance) id.
     * @param string $capability self::VIEW to read, self::EDIT to change.
     * @return \stdClass The plan row.
     * @throws \moodle_exception error_notfound when the plan is missing or the user may not open it.
     * @throws \required_capability_exception When the user may open the plan but lacks $capability.
     */
    public static function plan(int $userid, int $id, string $capability = self::VIEW): \stdClass {
        global $DB;

        $plan = $DB->get_record('classprogress', ['id' => $id]);
        $context = $plan ? self::context($plan) : null;
        if (!$context || $userid <= 0 || isguestuser($userid) || !has_capability(self::VIEW, $context, $userid)) {
            throw new \moodle_exception('error_notfound', 'mod_classprogress');
        }
        if ($capability !== self::VIEW && !has_capability($capability, $context, $userid)) {
            throw new \required_capability_exception($context, $capability, 'nopermissions', '');
        }
        return $plan;
    }

    /**
     * Returns the module context of a plan.
     *
     * @param \stdClass $plan The plan row.
     * @return \context_module|null The context; null while the activity has no course module.
     */
    public static function context(\stdClass $plan): ?\context_module {
        $cm = get_coursemodule_from_instance('classprogress', $plan->id, $plan->course, false, IGNORE_MISSING);
        return $cm ? \context_module::instance($cm->id) : null;
    }

    /**
     * Returns the course module id of a plan activity, for the URLs of its pages.
     *
     * @param \stdClass $plan The plan row.
     * @return int The course module id; 0 while the activity has none.
     */
    public static function cmid(\stdClass $plan): int {
        return (int) (self::context($plan)?->instanceid ?? 0);
    }

    /**
     * Whether the request confirms a delete: confirm=1 with the user's sesskey.
     *
     * A missing sesskey counts as a wrong one, so the page shows its confirmation again instead of an error.
     *
     * @return bool True when the delete is confirmed.
     */
    public static function delete_confirmed(): bool {
        $sesskey = optional_param('sesskey', '', PARAM_RAW);
        return optional_param('confirm', 0, PARAM_BOOL) && $sesskey !== '' && confirm_sesskey($sesskey);
    }
}
