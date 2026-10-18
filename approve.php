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
 * Approve a draft plan, for good, after reading its stored text again with its errors and warnings.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_classprogress\local\plan_issue;
use mod_classprogress\form\approve_form;
use mod_classprogress\local\access;
use mod_classprogress\local\plan_service;
use mod_classprogress\output\plan_preview;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'classprogress');
require_login($course, false, $cm);
require_capability(access::EDIT, $cm->context);
$plan = plan_service::get((int) $USER->id, (int) $cm->instance);

$viewurl = new \core\url('/mod/classprogress/view.php', ['id' => $cm->id]);
$url = new \core\url('/mod/classprogress/approve.php', ['id' => $cm->id]);
$title = get_string('approveplan', 'mod_classprogress');
$PAGE->set_url($url);
$PAGE->set_title($title . ': ' . format_string($plan->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($title);

if ($plan->status !== plan_service::STATUS_DRAFT) {
    throw new \moodle_exception('error_planlocked', 'mod_classprogress', $viewurl);
}
if (!plan_service::school_year($plan)) {
    throw new \moodle_exception('error_noschoolyear', 'mod_classprogress', $viewurl);
}

$result = plan_service::check_stored((int) $USER->id, (int) $plan->id);
$form = new approve_form($url, ['warnings' => plan_service::count_issues($result, plan_issue::WARNING)]);
if ($form->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $form->get_data()) {
    try {
        plan_service::approve((int) $USER->id, (int) $plan->id, $data->seen, !empty($data->warningsread));
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_changedelsewhere') {
            throw $e;
        }
        // What was shown is no longer what would be approved: show it again.
        redirect($url, get_string('error_changedelsewhere', 'mod_classprogress'), null, notification::NOTIFY_ERROR);
    }
    redirect($viewurl, get_string('planapproved', 'mod_classprogress'), null, notification::NOTIFY_SUCCESS);
}
$form->set_data(['id' => $cm->id, 'seen' => plan_service::fingerprint($result)]);

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 3);
echo html_writer::tag('p', get_string('approveplanintro', 'mod_classprogress'));
echo $OUTPUT->render(new plan_preview($plan, $result, true));
if (plan_service::count_issues($result, plan_issue::ERROR)) {
    echo $OUTPUT->notification(get_string('approveplannoerrors', 'mod_classprogress'), notification::NOTIFY_ERROR, false);
    echo $OUTPUT->continue_button($viewurl);
} else {
    $form->display();
}
echo $OUTPUT->footer();
