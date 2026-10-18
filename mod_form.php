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
 * The activity form: name, description, school year, class days and weekly timetable of a plan activity.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_classprogress\form\date_field;
use mod_classprogress\form\plan_settings;
use mod_classprogress\local\plan_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * The activity form (REQ-MOD-001).
 *
 * The school year is one of the adding teacher's school years; a plan keeps the one it has, whoever owns it, without
 * showing that teacher's data. Once the plan is approved its school year, class days and timetable are frozen
 * (REQ-LCK-001) and only the name and description change. Validation is the plan service's, so the form refuses
 * exactly what the service refuses; a save from a form opened before another change is refused (REQ-DAT-003).
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_classprogress_mod_form extends moodleform_mod {
    /**
     * Defines the fields.
     */
    #[\Override]
    public function definition(): void {
        global $CFG, $DB;

        $mform = $this->_form;
        $plan = $this->_instance ? $DB->get_record('classprogress', ['id' => $this->_instance]) : null;
        $approved = $plan && $plan->status === plan_service::STATUS_APPROVED;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule(
            'name',
            get_string('maximumchars', '', plan_service::MAX_TITLE),
            'maxlength',
            plan_service::MAX_TITLE,
            'client'
        );
        $this->standard_intro_elements();

        $mform->addElement('header', 'planheader', get_string('plansettings', 'mod_classprogress'));
        $mform->setExpanded('planheader');
        if ($approved) {
            $mform->addElement('static', 'lockednote', '', get_string('settingslocked', 'mod_classprogress'));
        }
        $years = self::year_options($plan);
        if (!$approved && count($years) === 1) {
            $link = html_writer::link(
                new \core\url('/local/classprogress/schoolyear.php'),
                get_string('addschoolyear', 'local_classprogress')
            );
            $mform->addElement('static', 'noyears', '', get_string('noschoolyears', 'mod_classprogress', $link));
        }
        $mform->addElement('select', 'schoolyearid', get_string('schoolyear', 'mod_classprogress'), $years);
        $mform->setType('schoolyearid', PARAM_INT);
        $mform->addHelpButton('schoolyearid', 'schoolyear', 'mod_classprogress');
        if (!$approved) {
            $mform->addRule('schoolyearid', null, 'required', null, 'client');
        }

        $optional = date_field::options(true);
        $mform->addElement('date_selector', 'startdate', get_string('firstclassday', 'mod_classprogress'), $optional);
        $mform->addHelpButton('startdate', 'firstclassday', 'mod_classprogress');
        $mform->addElement('date_selector', 'enddate', get_string('lastclassday', 'mod_classprogress'), $optional);

        $mform->addElement(
            'static',
            'timetableintro',
            get_string('timetable', 'mod_classprogress'),
            get_string('timetableintro', 'mod_classprogress')
        );
        foreach (plan_settings::WEEKDAYS as $weekday => $name) {
            $mform->addElement('float', 'hours' . $weekday, get_string($name, 'calendar'), ['size' => 4]);
            // The float element reads the number in the user's language (1,5 or 1.5) and gives a float.
            $mform->setType('hours' . $weekday, PARAM_RAW_TRIMMED);
            $mform->setDefault('hours' . $weekday, 0);
        }
        $mform->addElement('hidden', 'planversion', 0);
        $mform->setType('planversion', PARAM_INT);
        if ($approved) {
            $mform->hardFreeze(plan_settings::LOCKED_FIELDS);
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Fills the plan fields of an existing activity.
     *
     * @param array $defaultvalues Values of the form, by reference.
     */
    #[\Override]
    public function data_preprocessing(&$defaultvalues): void {
        global $DB;

        parent::data_preprocessing($defaultvalues);
        if (empty($this->_instance)) {
            return;
        }
        $plan = $DB->get_record('classprogress', ['id' => $this->_instance], '*', MUST_EXIST);
        $timetable = [];
        foreach ($DB->get_records('classprogress_slot', ['planid' => $plan->id]) as $slot) {
            $timetable[(int) $slot->weekday] = (float) $slot->hours;
        }
        $defaultvalues = plan_settings::from_record($plan, $timetable) + $defaultvalues;
    }

    /**
     * Checks the data with the plan service and shows its errors next to the fields.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Element name => error message.
     */
    #[\Override]
    public function validation($data, $files): array {
        global $DB, $USER;

        $errors = parent::validation($data, $files);
        $id = (int) ($data['instance'] ?? 0);
        $plandata = plan_settings::to_service_data((object) $data);
        $stored = $id ? $DB->get_field('classprogress', 'timemodified', ['id' => $id]) : false;
        if ($stored !== false && (int) $stored !== $plandata->timemodified) {
            $errors['name'] = get_string('error_changedelsewhere', 'mod_classprogress');
        }
        foreach (plan_service::validate((int) $USER->id, $plandata, $id) as $field => $code) {
            if (str_starts_with($field, 'timetable-')) {
                $field = 'hours' . substr($field, strlen('timetable-'));
            } else if ($field === 'timetable') {
                $field = 'hours0';
            }
            $errors[$field] ??= get_string($code, 'mod_classprogress');
        }
        return $errors;
    }

    /**
     * The school years the plan may use: the current user's own, plus the one the plan keeps when it is another
     * teacher's (named without showing that teacher's data), after a "choose" entry when it has none.
     *
     * @param \stdClass|null $plan The plan being edited; null for a new one.
     * @return string[] School year id => name, safe to print.
     */
    private static function year_options(?\stdClass $plan): array {
        global $USER;

        $options = [0 => get_string('choosedots')];
        // A user who may not keep school years has none to offer.
        if (\local_classprogress\local\access::qualifies((int) $USER->id)) {
            foreach (\local_classprogress\local\schoolyear_service::list((int) $USER->id) as $year) {
                $options[(int) $year->id] = s($year->name);
            }
        }
        $current = $plan ? plan_service::school_year($plan) : null;
        if ($current && !isset($options[(int) $current->id])) {
            $options[(int) $current->id] = get_string('schoolyearkept', 'mod_classprogress');
        }
        return $options;
    }
}
