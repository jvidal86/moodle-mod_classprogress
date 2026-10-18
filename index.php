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
 * The plan activities of a course that the user may open, with their status.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_classprogress\local\access;
use mod_classprogress\local\plan_service;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_course_login($course);

$PAGE->set_url(new \core\url('/mod/classprogress/index.php', ['id' => $course->id]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'mod_classprogress'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('modulenameplural', 'mod_classprogress'));

$table = new html_table();
$table->head = [get_string('name'), get_string('status', 'mod_classprogress')];
$table->attributes['class'] = 'generaltable';
foreach (get_fast_modinfo($course)->get_instances_of('classprogress') as $cm) {
    if (!$cm->uservisible || !has_capability(access::VIEW, $cm->context)) {
        continue;
    }
    $plan = plan_service::get((int) $USER->id, (int) $cm->instance);
    $draft = $plan->status === plan_service::STATUS_DRAFT;
    $table->data[] = [
        html_writer::link($cm->url, $cm->get_formatted_name()),
        get_string($draft ? 'statusdraftshort' : 'statusapprovedshort', 'mod_classprogress'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_classprogress'));
if (empty($table->data)) {
    echo $OUTPUT->notification(
        get_string('noplanactivities', 'mod_classprogress'),
        \core\output\notification::NOTIFY_INFO,
        false
    );
} else {
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
