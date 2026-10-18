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
 * Callbacks of the class progress activity for Moodle core.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_classprogress\form\plan_settings;
use mod_classprogress\local\access;
use mod_classprogress\local\plan_service;

/**
 * Tells Moodle which features the activity supports: a description, backup, no grades and no completion rules.
 *
 * @param string $feature A FEATURE_xx constant.
 * @return mixed True or false for a feature, the purpose for FEATURE_MOD_PURPOSE, null for an unknown feature.
 */
function classprogress_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ADMINISTRATION;
        default:
            return null;
    }
}

/**
 * Creates a plan activity: a draft plan with the school year, class days and timetable of the activity form.
 *
 * @param stdClass $data Data from the activity form.
 * @param mod_classprogress_mod_form|null $mform The form.
 * @return int The new instance id.
 */
function classprogress_add_instance($data, $mform = null) {
    global $USER;

    return plan_service::create((int) $USER->id, plan_settings::to_service_data($data));
}

/**
 * Saves the activity form over a plan activity; an approved plan takes only its name and description.
 *
 * @param stdClass $data Data from the activity form.
 * @param mod_classprogress_mod_form|null $mform The form.
 * @return bool True when saved.
 */
function classprogress_update_instance($data, $mform = null) {
    global $USER;

    plan_service::update((int) $USER->id, (int) $data->instance, plan_settings::to_service_data($data));
    return true;
}

/**
 * Deletes a plan activity with all of its data: plan, days, imports, copies, entries and history (REQ-MOD-007).
 *
 * @param int $id Instance id.
 * @return bool False when there is no such instance.
 */
function classprogress_delete_instance($id) {
    return plan_service::delete((int) $id);
}

/**
 * Hides the activity on the course page, and closes it, for every user who may not view it: students and other roles
 * without the view capability (REQ-MOD-002).
 *
 * @param cm_info $cm The course module, as seen by the user its course information was built for.
 */
function classprogress_cm_info_dynamic(cm_info $cm) {
    if (!has_capability(access::VIEW, $cm->context, $cm->get_modinfo()->get_user_id())) {
        $cm->set_user_visible(false);
    }
}

/**
 * Adds the reset option of the activity to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form.
 */
function classprogress_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'classprogressheader', get_string('modulenameplural', 'mod_classprogress'));
    $mform->addElement('advcheckbox', 'reset_classprogress_entries', get_string('resetentries', 'mod_classprogress'));
}

/**
 * The default of the reset option: the entries are removed with the other user data.
 *
 * @param stdClass $course The course.
 * @return array Option => default.
 */
function classprogress_reset_course_form_defaults($course) {
    return ['reset_classprogress_entries' => 1];
}

/**
 * Removes the entries and their history of every plan activity of a course when the reset asks for it; the plans
 * stay (REQ-MOD-007).
 *
 * @param stdClass $data The reset form data, with courseid.
 * @return array Status rows for the reset report.
 */
function classprogress_reset_userdata($data) {
    global $DB;

    if (empty($data->reset_classprogress_entries)) {
        return [];
    }
    $plans = 'SELECT id FROM {classprogress} WHERE course = :course';
    $params = ['course' => $data->courseid];
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records_select('classprogress_entry_hist', "planid IN ({$plans})", $params);
    $DB->delete_records_select('classprogress_entry', "planid IN ({$plans})", $params);
    $transaction->allow_commit();
    return [[
        'component' => get_string('modulenameplural', 'mod_classprogress'),
        'item' => get_string('resetentries', 'mod_classprogress'),
        'error' => false,
    ]];
}
