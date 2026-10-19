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
 * Opens a plan activity, for the course's teachers only (REQ-MOD-002, REQ-MOD-003).
 *
 * A draft shows its status, the way to import and approve it, its days and its calendar; a draft without a school
 * year asks for one first (REQ-DAT-006). An approved plan shows its session log: pending, logged and upcoming sessions,
 * with links to log each one, to add an unplanned session, to the history of deleted entries, to the plan's days and
 * to its export.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use mod_classprogress\local\access;
use mod_classprogress\local\entry_service;
use mod_classprogress\local\plan_service;
use mod_classprogress\output\calendar_view;
use mod_classprogress\output\log_view;
use mod_classprogress\output\plan_view;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'classprogress');
require_login($course, false, $cm);
require_capability(access::VIEW, $cm->context);
$plan = plan_service::get((int) $USER->id, (int) $cm->instance);
$canedit = has_capability(access::EDIT, $cm->context);

$url = new \core\url('/mod/classprogress/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($plan->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

if ($plan->status === plan_service::STATUS_APPROVED) {
    echo $OUTPUT->heading(get_string('log', 'mod_classprogress'), 3);
    echo html_writer::tag('p', get_string('logintro', 'mod_classprogress'));
    $actions = [];
    if ($canedit) {
        $actions[] = html_writer::link(
            new \core\url('/mod/classprogress/entry.php', ['id' => $cm->id]),
            get_string('addunplanned', 'mod_classprogress'),
            ['class' => 'btn btn-secondary']
        );
    }
    $actions[] = html_writer::link(
        new \core\url('/mod/classprogress/history.php', ['id' => $cm->id]),
        get_string('deletedentries', 'mod_classprogress'),
        ['class' => 'btn btn-link']
    );
    $actions[] = html_writer::link(
        new \core\url('/mod/classprogress/plan.php', ['id' => $cm->id]),
        get_string('plandays', 'mod_classprogress'),
        ['class' => 'btn btn-link']
    );
    $actions[] = html_writer::link(
        new \core\url('/mod/classprogress/export.php', ['id' => $cm->id]),
        get_string('export', 'mod_classprogress'),
        ['class' => 'btn btn-link']
    );
    echo html_writer::tag('p', implode(' ', $actions), ['data-region' => 'log-actions']);
    echo $OUTPUT->render(new log_view(entry_service::log((int) $USER->id, (int) $plan->id)));
    echo $OUTPUT->footer();
    exit;
}

if (!plan_service::school_year($plan)) {
    echo $OUTPUT->notification(get_string('noschoolyear', 'mod_classprogress'), notification::NOTIFY_WARNING, false);
    if (has_capability('moodle/course:manageactivities', $cm->context)) {
        echo $OUTPUT->single_button(
            new \core\url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]),
            get_string('chooseschoolyear', 'mod_classprogress'),
            'get'
        );
    }
} else if ($canedit) {
    $actions = [html_writer::link(
        new \core\url('/mod/classprogress/import.php', ['id' => $cm->id]),
        get_string('importplan', 'mod_classprogress'),
        ['class' => 'btn ' . ($plan->sourcetext === null ? 'btn-primary' : 'btn-secondary')]
    )];
    if ($plan->sourcetext !== null) {
        $actions[] = html_writer::link(
            new \core\url('/mod/classprogress/approve.php', ['id' => $cm->id]),
            get_string('approveplan', 'mod_classprogress'),
            ['class' => 'btn btn-primary']
        );
    }
    echo html_writer::tag('p', implode(' ', $actions), ['data-region' => 'plan-actions']);
}
echo $OUTPUT->render(plan_view::for_plan((int) $USER->id, $plan));
echo $OUTPUT->heading(get_string('calendar', 'mod_classprogress'), 3);
echo $OUTPUT->render(calendar_view::for_plan((int) $USER->id, $plan));
echo $OUTPUT->footer();
