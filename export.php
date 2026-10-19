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
 * The export of an approved plan activity: its summary tables, and with download=1 the XLSX file (REQ-EXP-001).
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_classprogress\local\access;
use mod_classprogress\local\export_service;
use mod_classprogress\output\export_view;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'classprogress');
require_login($course, false, $cm);
require_capability(access::VIEW, $cm->context);

$url = new \core\url('/mod/classprogress/export.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$export = export_service::build((int) $USER->id, (int) $cm->instance);

if ($download) {
    export_service::send($export);
    exit;
}

$title = get_string('export', 'mod_classprogress');
$PAGE->set_title($title . ': ' . format_string($export->plan->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 3);
echo $OUTPUT->render(new export_view($export, new \core\url($url, ['download' => 1])));
echo $OUTPUT->continue_button(new \core\url('/mod/classprogress/view.php', ['id' => $cm->id]));
echo $OUTPUT->footer();
