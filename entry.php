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
 * Log a session of an approved plan, edit or delete its entry, or add an unplanned session.
 *
 * Opened with the activity's id and itemid (a plan item: its entry, if it has one, is edited), entryid (an entry) or
 * neither (a new unplanned session). The heading names the session; its plan is shown read-only above the form, with
 * what the previous partial session left over, and a saved entry's written-on day, history and delete links follow the
 * form. When pending sessions are left, the form can save and go on to the oldest of them.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_classprogress\form\date_field;
use local_classprogress\local\dates;
use local_classprogress\local\session_item;
use mod_classprogress\form\entry_form;
use mod_classprogress\local\access;
use mod_classprogress\local\entry_service;
use mod_classprogress\local\plan_service;
use mod_classprogress\output\entry_context;
use mod_classprogress\output\log_view;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$entryid = optional_param('entryid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'classprogress');
require_login($course, false, $cm);
require_capability(access::EDIT, $cm->context);
$plan = plan_service::get((int) $USER->id, (int) $cm->instance);
$planid = (int) $plan->id;

$entry = null;
if ($entryid) {
    $entry = entry_service::get((int) $USER->id, $entryid);
    $itemid = (int) $entry->planitemid;
} else if ($itemid) {
    $entry = entry_service::for_item((int) $USER->id, $itemid);
}
$item = $itemid ? entry_service::item((int) $USER->id, $itemid) : null;
if (($entry && (int) $entry->planid !== $planid) || ($item && (int) $item->planid !== $planid)) {
    throw new \moodle_exception('error_notfound', 'mod_classprogress');
}

$logurl = new \core\url('/mod/classprogress/view.php', ['id' => $cm->id]);
if ($plan->status !== plan_service::STATUS_APPROVED) {
    throw new \moodle_exception('error_plannotapproved', 'mod_classprogress', $logurl);
}
if ($entry) {
    $url = new \core\url('/mod/classprogress/entry.php', ['id' => $cm->id, 'entryid' => $entry->id]);
} else {
    $url = new \core\url('/mod/classprogress/entry.php', $item ? ['id' => $cm->id, 'itemid' => $item->id] : ['id' => $cm->id]);
}
if ($item) {
    $name = log_view::session_title($item);
    $title = s($item->sessiondate . ($name === '' ? '' : ' · ' . $name));
} else {
    $title = get_string('unplannedsession', 'mod_classprogress') . ($entry ? ' · ' . s($entry->sessiondate) : '');
}
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($title);

if ($action === 'delete') {
    if (!$entry) {
        throw new \moodle_exception('error_notfound', 'mod_classprogress');
    }
    if (access::delete_confirmed()) {
        try {
            entry_service::delete((int) $USER->id, (int) $entry->id, optional_param('timemodified', 0, PARAM_INT));
        } catch (\moodle_exception $e) {
            if ($e->errorcode !== 'error_changedelsewhere') {
                throw $e;
            }
            redirect($url, get_string('error_changedelsewhere', 'mod_classprogress'), null, notification::NOTIFY_ERROR);
        }
        redirect($logurl, get_string('entrydeleted', 'mod_classprogress'), null, notification::NOTIFY_SUCCESS);
    }
    $confirmurl = new \core\url($url, ['action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey(),
        'timemodified' => $entry->timemodified]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deleteentry', 'mod_classprogress'));
    echo $OUTPUT->confirm(get_string('deleteentryconfirm', 'mod_classprogress', s($entry->sessiondate)), $confirmurl, $url);
    echo $OUTPUT->footer();
    exit;
}

// The pending sessions, oldest first, as their item ids.
$pending = function () use ($USER, $planid): array {
    $sessions = entry_service::log((int) $USER->id, $planid)->groups->pending;
    return array_map(fn(session_item $session): int => $session->id, $sessions);
};
// A form opened for a session after today posts that it was, and keeps its shape when saved after midnight (R6-2). A
// tampered flag only turns a session's form into a cancellation's: not done, with the stored text kept.
$future = $item && (dates::day_number($item->sessiondate) > dates::day_number(entry_service::today((int) $USER->id))
    || optional_param('future', 0, PARAM_BOOL));
$form = new entry_form($url, ['userid' => (int) $USER->id, 'planid' => $planid, 'itemid' => $item ? (int) $item->id : null,
    'future' => $future, 'nextpending' => count(array_diff($pending(), $item ? [(int) $item->id] : []))]);
$stale = false;
if ($form->is_cancelled()) {
    redirect($logurl);
} else if ($data = $form->get_data()) {
    if ($future && $entry) {
        // The form of a cancellation in advance does not ask what was done; a text written before is kept.
        $data->content = $entry->content;
    }
    $typed = entry_form::to_data($data, $item !== null);
    try {
        if ($entry) {
            entry_service::update((int) $USER->id, (int) $entry->id, $typed, (int) $data->timemodified);
        } else {
            entry_service::create((int) $USER->id, $planid, $item ? (int) $item->id : null, $typed);
        }
    } catch (\moodle_exception $e) {
        if ($e->errorcode !== 'error_changedelsewhere') {
            throw $e;
        }
        // Nothing was saved; the form keeps what the teacher typed, and the message asks them to reload.
        $stale = true;
    }
    if (!$stale) {
        $next = empty($data->savenext) ? null : ($pending()[0] ?? null);
        $nexturl = $next === null ? $logurl : new \core\url('/mod/classprogress/entry.php', ['id' => $cm->id, 'itemid' => $next]);
        redirect($nexturl, get_string('entrysaved', 'mod_classprogress'), null, notification::NOTIFY_SUCCESS);
    }
}
if (!$stale) {
    $values = ['id' => $cm->id, 'entryid' => 0, 'itemid' => $item ? (int) $item->id : 0, 'timemodified' => 0];
    if ($entry) {
        $values = entry_form::from_record($entry) + $values;
    } else if (!$item) {
        $values['date'] = date_field::to_timestamp(entry_service::today((int) $USER->id));
    }
    $form->set_data($values);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 3);
if ($stale) {
    echo $OUTPUT->notification(get_string('error_changedelsewhere', 'mod_classprogress'), notification::NOTIFY_ERROR);
}
$leftover = $item ? entry_service::left_over((int) $USER->id, (int) $item->id) : null;
$context = (new entry_context($plan, $item, $entry, $leftover))->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('mod_classprogress/entry_context', $context);
$form->display();
echo $OUTPUT->render_from_template('mod_classprogress/entry_written', $context);
echo $OUTPUT->footer();
