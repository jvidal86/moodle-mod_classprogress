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
 * The history of an entry (entryid), which outlives the entry, or of the deleted entries of a plan activity, newest
 * first.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_classprogress\local\access;
use mod_classprogress\local\entry_service;
use mod_classprogress\local\plan_service;
use mod_classprogress\output\history_view;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$entryid = optional_param('entryid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'classprogress');
require_login($course, false, $cm);
require_capability(access::VIEW, $cm->context);
$plan = plan_service::get((int) $USER->id, (int) $cm->instance);

if ($entryid) {
    $rows = entry_service::history((int) $USER->id, $entryid);
    $entryplan = $rows ? (int) reset($rows)->planid : (int) entry_service::get((int) $USER->id, $entryid)->planid;
    if ($entryplan !== (int) $plan->id) {
        throw new \moodle_exception('error_notfound', 'mod_classprogress');
    }
    $title = get_string('historyentry', 'mod_classprogress');
    $url = new \core\url('/mod/classprogress/history.php', ['id' => $cm->id, 'entryid' => $entryid]);
} else {
    $rows = entry_service::deleted_history((int) $USER->id, (int) $plan->id);
    $title = get_string('deletedentries', 'mod_classprogress');
    $url = new \core\url('/mod/classprogress/history.php', ['id' => $cm->id]);
}
$logurl = new \core\url('/mod/classprogress/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_title($title . ': ' . format_string($plan->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 3);
echo html_writer::tag('p', get_string('historyintro', 'mod_classprogress'));
if (!$rows && !$entryid) {
    echo html_writer::tag('p', get_string('nodeletedentries', 'mod_classprogress'));
}
echo $OUTPUT->render(new history_view($plan, $rows));
echo $OUTPUT->continue_button($logurl);
echo $OUTPUT->footer();
