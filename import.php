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
 * Import the text of a draft plan: paste or upload it, read the preview, then save it as the draft's content.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_classprogress\local\plan_issue;
use mod_classprogress\form\import_form;
use mod_classprogress\form\import_save_form;
use mod_classprogress\local\access;
use mod_classprogress\local\plan_service;
use mod_classprogress\output\plan_preview;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'classprogress');
require_login($course, false, $cm);
require_capability(access::EDIT, $cm->context);
$plan = plan_service::get((int) $USER->id, (int) $cm->instance);

$planurl = new \core\url('/mod/classprogress/view.php', ['id' => $cm->id]);
$url = new \core\url('/mod/classprogress/import.php', ['id' => $cm->id]);
$title = get_string('importplan', 'mod_classprogress');
$PAGE->set_url($url);
$PAGE->set_title($title . ': ' . format_string($plan->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($title);

if ($plan->status !== plan_service::STATUS_DRAFT) {
    throw new \moodle_exception('error_planlocked', 'mod_classprogress', $planurl);
}
if (!plan_service::school_year($plan)) {
    throw new \moodle_exception('error_noschoolyear', 'mod_classprogress', $planurl);
}

$inputform = new import_form($url, ['maxbytes' => plan_service::max_size()]);
$saveform = new import_save_form($url);
// The text to preview, with where it came from; null to show the empty form.
$text = null;
$source = plan_service::SOURCE_PASTE;
$filename = '';
$message = null;
if ($saveform->is_cancelled()) {
    // Back to the text box, with the previewed text in it.
    $inputform->set_data(['sourcetext' => $saveform->get_submitted_data()->sourcetext ?? '']);
} else if ($data = $saveform->get_data()) {
    try {
        plan_service::import((int) $USER->id, (int) $plan->id, $data->sourcetext, $data->source, $data->filename);
        redirect($planurl, get_string('plandraftsaved', 'mod_classprogress'), null, notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_planhaserrors' && $e->errorcode !== 'error_plantoolarge') {
            throw $e;
        }
        // Something changed since the preview (the limits): show the text again with what it gives now.
        $inputform->set_data(['sourcetext' => $data->sourcetext]);
        [$text, $source, $filename] = [$data->sourcetext, $data->source, $data->filename];
    }
} else if ($inputform->get_data()) {
    [$text, $source, $filename] = $inputform->plan_text();
}

$result = null;
if ($text !== null) {
    try {
        $result = plan_service::check((int) $USER->id, (int) $plan->id, $text);
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_plantoolarge') {
            throw $e;
        }
        $message = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 3);
if ($message !== null) {
    echo $OUTPUT->notification($message, notification::NOTIFY_ERROR);
}
if ($result !== null) {
    echo $OUTPUT->heading(get_string('preview', 'mod_classprogress'), 4);
    echo $OUTPUT->render(new plan_preview($plan, $result));
    if (plan_service::count_issues($result, plan_issue::ERROR) === 0) {
        $saveform->set_data(['sourcetext' => $text, 'source' => $source, 'filename' => $filename]);
        $saveform->display();
        echo $OUTPUT->footer();
        exit;
    }
}
echo html_writer::tag('p', get_string('importplanintro', 'mod_classprogress'));
$inputform->display();
echo $OUTPUT->heading(get_string('aiinstructionsheading', 'mod_classprogress'), 4);
echo html_writer::tag('p', get_string('aiinstructionsintro', 'mod_classprogress'));
echo html_writer::tag('pre', s(get_string('aiinstructions', 'mod_classprogress')), [
    'class' => 'border p-3 bg-light',
    'data-region' => 'ai-instructions',
    'style' => 'white-space: pre-wrap',
]);
echo $OUTPUT->footer();
