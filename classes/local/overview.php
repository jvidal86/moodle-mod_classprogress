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
 * The plan activities a user can open, across courses, for the overview page of local_classprogress (REQ-MOD-005).
 *
 * local_classprogress calls for_user() only when this plugin is installed, so the two plugins stay one-way dependent.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class overview {
    /**
     * Lists the plan activities the user can open: those visible to them where they hold the view capability.
     *
     * @param int $userid The user.
     * @return array[] One per activity, by course and then by position in the course: name and course (formatted, safe
     *     to print), status (a readable word), pending (pending sessions of an approved plan; null for a draft) and url.
     */
    public static function for_user(int $userid): array {
        global $DB;

        if ($userid <= 0 || isguestuser($userid)) {
            return [];
        }
        $courses = get_user_capability_course(access::VIEW, $userid, true, 'fullname', 'fullname') ?: [];
        $cms = [];
        foreach ($courses as $course) {
            foreach (get_fast_modinfo($course->id, $userid)->get_instances_of('classprogress') as $cm) {
                if ($cm->uservisible && has_capability(access::VIEW, $cm->context, $userid)) {
                    $cms[(int) $cm->instance] = [$cm, format_string(
                        $course->fullname,
                        true,
                        ['context' => \context_course::instance($course->id)]
                    )];
                }
            }
        }
        if (!$cms) {
            return [];
        }
        $plans = $DB->get_records_list('classprogress', 'id', array_keys($cms), '', 'id, status');
        $approved = array_keys(array_filter($plans, fn(\stdClass $plan): bool =>
            $plan->status === plan_service::STATUS_APPROVED));
        $pending = entry_service::pending_counts($userid, $approved);
        $list = [];
        foreach ($cms as $id => [$cm, $coursename]) {
            $draft = $plans[$id]->status === plan_service::STATUS_DRAFT;
            $list[] = [
                'name' => $cm->get_formatted_name(),
                'course' => $coursename,
                'status' => get_string($draft ? 'statusdraftshort' : 'statusapprovedshort', 'mod_classprogress'),
                'pending' => $draft ? null : $pending[$id],
                'url' => $cm->url->out(false),
            ];
        }
        return $list;
    }
}
