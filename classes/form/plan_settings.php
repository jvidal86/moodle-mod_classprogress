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

namespace mod_classprogress\form;

use local_classprogress\form\date_field;

/**
 * The plan fields of the activity form, and how they turn into plan data for the service and back.
 *
 * The form keeps the class days as date_selector timestamps (date_field) and the timetable as one hours field per
 * weekday (hours0 to hours6, Monday = 0); its hidden planversion is the timemodified the form was opened on.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_settings {
    /** @var string[] Weekday lang strings in the calendar component, Monday = 0 to Sunday = 6. */
    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** @var string[] The form fields that approval locks. */
    public const LOCKED_FIELDS = ['schoolyearid', 'startdate', 'enddate', 'hours0', 'hours1', 'hours2', 'hours3', 'hours4',
        'hours5', 'hours6'];

    /**
     * Turns submitted form data into plan data for the service.
     *
     * @param \stdClass $formdata Data from the activity form (get_data(), validation() or a generator), with dates as
     *     date_selector timestamps.
     * @return \stdClass Plan data, with the version the form was opened on.
     */
    public static function to_service_data(\stdClass $formdata): \stdClass {
        $timetable = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $timetable[$weekday] = (float) ($formdata->{'hours' . $weekday} ?? 0);
        }
        $data = (object) [
            'course' => (int) ($formdata->course ?? 0),
            'name' => (string) ($formdata->name ?? ''),
            'schoolyearid' => (int) ($formdata->schoolyearid ?? 0),
            'startdate' => date_field::to_day((int) ($formdata->startdate ?? 0)),
            'enddate' => date_field::to_day((int) ($formdata->enddate ?? 0)),
            'timetable' => $timetable,
            'timemodified' => (int) ($formdata->planversion ?? 0),
        ];
        if (isset($formdata->intro)) {
            $data->intro = $formdata->intro;
            $data->introformat = (int) ($formdata->introformat ?? FORMAT_HTML);
        }
        return $data;
    }

    /**
     * Turns a stored plan into the values of its form fields.
     *
     * @param \stdClass $plan Plan row.
     * @param float[] $timetable Its timetable, weekday => hours.
     * @return array Field => value, with dates as date_selector timestamps.
     */
    public static function from_record(\stdClass $plan, array $timetable): array {
        $values = [
            'planversion' => (int) $plan->timemodified,
            'schoolyearid' => (int) $plan->schoolyearid,
            'startdate' => date_field::to_timestamp($plan->startdate ?? null),
            'enddate' => date_field::to_timestamp($plan->enddate ?? null),
        ];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $values['hours' . $weekday] = $timetable[$weekday] ?? 0;
        }
        return $values;
    }
}
