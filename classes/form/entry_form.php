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
use local_classprogress\local\entry_data;
use local_classprogress\local\entry_rules;
use mod_classprogress\local\entry_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The entry of a session: status, what was done, and for a deviation its reason, note, measure and carry-over; for an
 * unplanned session, its date (a date selector, date_field) instead of a status.
 *
 * The status is three radio buttons, done chosen for a new entry. A session after today can only be cancelled in
 * advance (REQ-LOG-009), so its form shows the status as fixed text and asks only for the reason, note and measure.
 * When pending sessions are left after this one, a second button, savenext, saves and goes on to the next of them.
 *
 * Text is taken as typed. validation() reports the issues of entry_service::check() on their fields, so the rules
 * live in one place. The form carries the entry's timemodified, so that the page can refuse a save made from an older
 * version.
 *
 * Custom data: userid, the acting user; planid; itemid, the plan item, or null for an unplanned session; future,
 * whether the plan item is after today, or was when the form was opened (posted as the hidden field future);
 * nextpending, how many pending sessions are left after this one.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_form extends \moodleform {
    /** @var string[] Issue code => field it is shown on. */
    private const ISSUE_FIELDS = [
        entry_rules::BAD_STATUS => 'statusgroup',
        entry_rules::FUTURE_NOT_DONE_ONLY => 'statusgroup',
        entry_rules::CONTENT_REQUIRED => 'content',
        entry_rules::REASON_REQUIRED => 'reason',
        entry_rules::BAD_REASON => 'reason',
        entry_rules::BAD_DATE => 'date',
        entry_rules::OUT_OF_RANGE => 'date',
        entry_rules::OUTSIDE_YEAR => 'date',
        entry_rules::FUTURE_UNPLANNED => 'date',
    ];

    /**
     * Defines the fields.
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $planned = $this->_customdata['itemid'] !== null;
        $future = $planned && !empty($this->_customdata['future']);
        $nextpending = (int) ($this->_customdata['nextpending'] ?? 0);

        // The id is the activity's course module, as on every page of the activity.
        foreach (['id', 'entryid', 'itemid', 'timemodified'] as $hidden) {
            $mform->addElement('hidden', $hidden, 0);
            $mform->setType($hidden, PARAM_INT);
        }
        // The form posts its own kind, so that a cancellation in advance saved after midnight keeps its shape (R6-2).
        $mform->addElement('hidden', 'future', $future ? 1 : 0);
        $mform->setType('future', PARAM_BOOL);

        if ($future) {
            $mform->addElement('hidden', 'status', entry_rules::NOT_DONE);
            $mform->setType('status', PARAM_ALPHAEXT);
            $mform->setConstant('status', entry_rules::NOT_DONE);
            $fixed = get_string('entrystatusfixed', 'mod_classprogress');
            $mform->addElement('static', 'statusgroup', get_string('status', 'mod_classprogress'), $fixed);
        } else if ($planned) {
            $radios = [];
            foreach ([entry_rules::DONE, entry_rules::PARTIAL, entry_rules::NOT_DONE] as $status) {
                $label = get_string('entrystatus_' . $status, 'mod_classprogress');
                $radios[] = $mform->createElement('radio', 'status', '', $label, $status);
            }
            // Clearly apart, with room between them for a finger; on a narrow screen they wrap (R6-9).
            $apart = \html_writer::span('', 'mr-3 me-3');
            $mform->addGroup($radios, 'statusgroup', get_string('status', 'mod_classprogress'), $apart, false);
            $mform->setType('status', PARAM_ALPHAEXT);
            $mform->setDefault('status', entry_rules::DONE);
        } else {
            $mform->addElement('date_selector', 'date', get_string('sessiondate', 'mod_classprogress'), date_field::options());
        }

        if (!$future) {
            $label = get_string('entrycontent', 'mod_classprogress');
            $mform->addElement('textarea', 'content', $label, ['rows' => 4, 'cols' => 60]);
            $mform->setType('content', PARAM_RAW);
        }

        if ($planned) {
            $reasons = ['' => get_string('choosedots')];
            foreach (entry_rules::REASONS as $reason) {
                $reasons[$reason] = get_string('reason_' . $reason, 'mod_classprogress');
            }
            $mform->addElement('select', 'reason', get_string('entryreason', 'mod_classprogress'), $reasons);
            $mform->setType('reason', PARAM_ALPHAEXT);
            $small = ['rows' => 2, 'cols' => 60];
            $mform->addElement('textarea', 'reasonnote', get_string('entryreasonnote', 'mod_classprogress'), $small);
            $mform->setType('reasonnote', PARAM_RAW);
            $mform->addElement('textarea', 'measure', get_string('entrymeasure', 'mod_classprogress'), $small);
            $mform->setType('measure', PARAM_RAW);
            if (!$future) {
                $mform->addElement('advcheckbox', 'carry', '', get_string('entrycarry', 'mod_classprogress'));
                $mform->setType('carry', PARAM_BOOL);
                foreach (['reason', 'reasonnote', 'measure'] as $field) {
                    $mform->hideIf($field, 'status', 'eq', entry_rules::DONE);
                }
                $mform->hideIf('carry', 'status', 'neq', entry_rules::PARTIAL);
            }
        }

        $buttons = [$mform->createElement('submit', 'submitbutton', get_string('saveentry', 'mod_classprogress'))];
        if ($nextpending > 0) {
            // A long label, longer still in Catalan, wraps rather than widening a phone's screen.
            $label = $nextpending === 1 ? get_string('saveandnextone', 'mod_classprogress')
                : get_string('saveandnext', 'mod_classprogress', $nextpending);
            $wrap = ['customclassoverride' => 'btn-secondary text-wrap'];
            $buttons[] = $mform->createElement('submit', 'savenext', $label, [], false, $wrap);
        }
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Reports the issues of the entry on their fields.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Element name => error message.
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $itemid = $this->_customdata['itemid'];
        $issues = entry_service::check(
            (int) $this->_customdata['userid'],
            (int) $this->_customdata['planid'],
            $itemid,
            self::to_data((object) $data, $itemid !== null)
        );
        foreach ($issues as $code) {
            $field = self::ISSUE_FIELDS[$code] ?? 'content';
            $errors[$field] ??= get_string('entryissue_' . $code, 'mod_classprogress');
        }
        return $errors;
    }

    /**
     * Turns submitted data into entry data; an unplanned session always has the status added.
     *
     * @param \stdClass $data Data from get_data() or validation().
     * @param bool $planned Whether the entry is for a plan item.
     * @return entry_data The entry as typed.
     */
    public static function to_data(\stdClass $data, bool $planned): entry_data {
        return new entry_data(
            status: $planned ? (string) ($data->status ?? '') : entry_rules::ADDED,
            content: (string) ($data->content ?? ''),
            reason: $planned ? (string) ($data->reason ?? '') : '',
            note: $planned ? (string) ($data->reasonnote ?? '') : '',
            measure: $planned ? (string) ($data->measure ?? '') : '',
            carry: $planned && !empty($data->carry),
            date: $planned ? '' : date_field::to_day((int) ($data->date ?? 0)),
        );
    }

    /**
     * Gives the form values of a stored entry.
     *
     * @param \stdClass $entry The entry row.
     * @return array Field => value.
     */
    public static function from_record(\stdClass $entry): array {
        return [
            'entryid' => (int) $entry->id,
            'timemodified' => (int) $entry->timemodified,
            'status' => $entry->status,
            'date' => date_field::to_timestamp($entry->sessiondate),
            'content' => $entry->content,
            'reason' => $entry->reason,
            'reasonnote' => (string) $entry->reasonnote,
            'measure' => (string) $entry->measure,
            'carry' => (int) $entry->carry,
        ];
    }
}
