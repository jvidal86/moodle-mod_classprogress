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

namespace mod_classprogress\local;

use local_classprogress\local\calendar;
use local_classprogress\local\calendar_result;
use local_classprogress\local\dates;
use local_classprogress\local\holiday_entry;
use local_classprogress\local\holiday_parser;
use local_classprogress\local\plan_day;
use local_classprogress\local\plan_issue;
use local_classprogress\local\plan_parser;
use local_classprogress\local\plan_result;
use local_classprogress\local\schoolyear_service;
use mod_classprogress\event\plan_approved;
use mod_classprogress\event\plan_imported;

/**
 * The plan of a plan activity: its settings (school year, class days, weekly timetable), its calendar, the import of
 * its Markdown text into a draft, and its approval.
 *
 * A plan is shared by the teachers of its course (access::plan()): reading needs the view capability and importing or
 * approving the edit capability, checked live in the activity's module context; a plan the user may not open gives
 * the same exception as a missing one, error_notfound. The activity settings (create(), update()) come from the
 * activity form, which Moodle only shows to users who manage the course's activities.
 *
 * Plan data, as taken by validate(), create() and update(): course (for create()), name, intro and introformat,
 * schoolyearid, startdate and enddate (ISO yyyy-mm-dd, or empty or null while unknown), optionally timetable, weekday
 * => hours with Monday = 0 to Sunday = 6, where 0 hours means no class, and optionally timemodified, the version the
 * change was made from, which update() refuses when the stored plan is newer. The school year must be one of the acting
 * user's (REQ-OWN-001), except that a plan keeps the one it has, whoever owns it; the dates, when given, lie within it.
 * Creating a plan or moving it into a school year holds that school year's lock (schoolyear_service::lock()), so the
 * school year cannot be deleted at the same moment (REQ-DAT-006).
 *
 * Import (check(), import()): the text is read by plan_parser with the holidays of the plan's school year, the plan's
 * timetable (none when it has no slots, so class days are not checked) and the school year's dates. To the parser's
 * issues come errors for what could not be stored (the codes below), so they show in the preview instead of failing the
 * save. A text with any error is not saved. Saving replaces the draft's text and items and adds a row to the import
 * log; the # heading's code and title replace the stored ones, each where the heading gives it (imported_module()).
 * Line ends are stored as line feeds. A draft's items follow a later timetable change where the text gives no hours.
 * A draft whose school year is missing is neither read nor approved until it gets one (error_noschoolyear).
 *
 * Approval (check_stored(), approve()) reads the stored text again, now, and goes ahead only if it has no errors, is
 * what the teacher saw (fingerprint()), and its warnings were confirmed. It copies the school year's dates, holidays and
 * evaluation periods into the plan, which then depends on no school year (REQ-LCK-004), and records who approved it.
 * It is final: an approved plan refuses every change to its school year, dates, timetable, text and items
 * (error_planlocked); only its name and description still change. Import and approval hold the plan's lock and its
 * school year's lock, run in one delegated transaction, and trigger their event after it commits.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_service {
    /** @var string Status of a plan that can still change. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Status of an approved plan, locked for good. */
    public const STATUS_APPROVED = 'approved';

    /** @var string Import source: text pasted into the form. */
    public const SOURCE_PASTE = 'paste';

    /** @var string Import source: an uploaded file, read and not stored. */
    public const SOURCE_UPLOAD = 'upload';

    /** @var int Largest plan text by default, in KB (admin setting maxplansize). */
    public const DEFAULT_MAX_SIZE = 200;

    /** @var int Most days in a plan by default (admin setting maxplandays). */
    public const DEFAULT_MAX_DAYS = 400;

    /** @var string Error added to the parser's: the text has no day. No parameters. */
    public const NO_DAYS = 'no_days';

    /** @var string Error: more days than max_days(), at the first day over the limit. Parameter: max. */
    public const TOO_MANY_DAYS = 'too_many_days';

    /** @var string Error: the module code is longer than MAX_CODE or the title than MAX_TITLE. No parameters. */
    public const MODULE_TOO_LONG = 'module_too_long';

    /** @var string Error: the block above a day is longer than MAX_TITLE, at its first day. Parameter: max. */
    public const BLOCK_TOO_LONG = 'block_too_long';

    /** @var string Error: a day title longer than MAX_TITLE. Parameters: date, max. */
    public const TITLE_TOO_LONG = 'title_too_long';

    /** @var string Error: a day with more than MAX_HOURS hours. Parameters: date, hours. */
    public const TOO_MANY_HOURS = 'too_many_hours';

    /** @var string Error: a day outside dates::FIRST_DAY to dates::LAST_DAY. Parameter: date. */
    public const OUT_OF_RANGE = 'out_of_range';

    /** @var int Longest module code, in characters. */
    public const MAX_CODE = 100;

    /** @var int Longest module title, and longest activity name, in characters. */
    public const MAX_TITLE = 255;

    /** @var float Most hours of class on one weekday. */
    public const MAX_HOURS = 24.0;

    /** @var string Plan table: the activity instances. */
    private const PLAN = 'classprogress';

    /** @var string Timetable slot table. */
    private const SLOT = 'classprogress_slot';

    /** @var string Plan item table: one row per day of the plan text. */
    private const ITEM = 'classprogress_item';

    /** @var string Import log table. */
    private const IMPORT = 'classprogress_import';

    /** @var string Holidays copied at approval. */
    private const HOLIDAY = 'classprogress_holiday';

    /** @var string Evaluation periods copied at approval. */
    private const EVALUATION = 'classprogress_evaluation';

    /** @var string[] Tables of the rows that belong to a plan through their planid, deleted with it. */
    public const CHILDREN = [
        'classprogress_entry_hist',
        'classprogress_entry',
        self::ITEM,
        self::IMPORT,
        self::SLOT,
        self::HOLIDAY,
        self::EVALUATION,
    ];

    /**
     * Creates a draft plan (an activity instance) with its timetable.
     *
     * @param int $userid The acting user, whose school year the plan uses.
     * @param \stdClass $data Plan data (see the class description) with the course.
     * @return int The new plan id.
     * @throws \moodle_exception With the first error of validate() when the data is not valid, or locktimeout.
     */
    public static function create(int $userid, \stdClass $data): int {
        global $DB;

        $lock = schoolyear_service::lock((int) ($data->schoolyearid ?? 0));
        try {
            self::require_valid($userid, $data, null);
            return self::in_transaction(function () use ($DB, $data): int {
                $now = time();
                $id = (int) $DB->insert_record(self::PLAN, (object) (self::fields($data) + [
                    'course' => (int) $data->course,
                    'modulecode' => '',
                    'moduletitle' => '',
                    'status' => self::STATUS_DRAFT,
                    'sourcetext' => null,
                    'timeapproved' => 0,
                    'approvedby' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]));
                self::write_timetable($id, $data, $now);
                return $id;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Changes a plan's settings; a timetable given in the data replaces the stored one.
     *
     * A draft takes every setting; the change holds the plan's lock and the lock of its (new) school year, and when
     * the timetable changes, the items of an imported draft take their hours from it where the text gives none. An
     * approved plan takes only its name and description, and refuses data that would change its school year, dates or
     * timetable. Either way timemodified becomes later than the one it replaces.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param \stdClass $data Plan data (see the class description).
     * @throws \moodle_exception error_notfound, error_planlocked, error_changedelsewhere, the first error of validate(), or
     *     locktimeout.
     * @throws \required_capability_exception When the user may not manage the course's activities.
     */
    public static function update(int $userid, int $id, \stdClass $data): void {
        global $DB;

        self::require_manager($userid, self::find($id));
        $work = function (\stdClass $plan) use ($DB, $userid, $id, $data): void {
            if (!empty($data->timemodified) && (int) $data->timemodified !== (int) $plan->timemodified) {
                throw new \moodle_exception('error_changedelsewhere', 'mod_classprogress');
            }
            self::require_valid($userid, $data, $plan);
            $now = max(time(), (int) $plan->timemodified + 1);
            if ($plan->status !== self::STATUS_DRAFT) {
                if (self::changes_locked_settings($plan, $data)) {
                    throw new \moodle_exception('error_planlocked', 'mod_classprogress');
                }
                $DB->update_record(self::PLAN, (object) (self::description($data) + ['id' => $id, 'timemodified' => $now]));
                return;
            }
            self::in_transaction(function () use ($DB, $id, $data, $now, $plan): void {
                $DB->update_record(self::PLAN, (object) (self::fields($data) + ['id' => $id, 'timemodified' => $now]));
                if (self::write_timetable($id, $data, $now) && $plan->sourcetext !== null) {
                    $timetable = self::timetables([$id])[$id];
                    self::write_items($id, (new plan_parser())->parse($plan->sourcetext, null, $timetable ?: null)->days, $now);
                }
            });
        };
        $draft = self::find($id)->status === self::STATUS_DRAFT;
        self::locked($id, $draft ? (int) ($data->schoolyearid ?? 0) : null, $work);
    }

    /**
     * Deletes a plan with every row that hangs from it, whatever its status: what deleting the activity does.
     *
     * @param int $id Plan id.
     * @return bool False when there is no such plan.
     */
    public static function delete(int $id): bool {
        global $DB;

        if (!$DB->record_exists(self::PLAN, ['id' => $id])) {
            return false;
        }
        self::in_transaction(function () use ($DB, $id): void {
            foreach (self::CHILDREN as $table) {
                $DB->delete_records($table, ['planid' => $id]);
            }
            $DB->delete_records(self::PLAN, ['id' => $id]);
        });
        return true;
    }

    /**
     * Reads a plan text for a draft plan as import() would, and saves nothing.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param string $text The plan text, as pasted or uploaded.
     * @return plan_result What the text gives, with the errors for what could not be stored.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_planlocked, error_noschoolyear, or error_plantoolarge (with the
     *     limit in KB).
     */
    public static function check(int $userid, int $id, string $text): plan_result {
        $plan = access::plan($userid, $id, access::EDIT);
        self::require_draft($plan);
        return self::read($plan, self::normalise($text));
    }

    /**
     * Saves a plan text as the content of a draft plan: its text, one item per day and a row of the import log, replacing
     * any earlier content; the # heading's code and title replace the plan's (imported_module()).
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @param string $text The plan text, as pasted or uploaded.
     * @param string $source self::SOURCE_PASTE or self::SOURCE_UPLOAD.
     * @param string $filename Name of the uploaded file; empty for pasted text.
     * @return int Id of the import log row.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_planlocked, error_noschoolyear, error_plantoolarge,
     *     error_planhaserrors when check() gives an error, or locktimeout.
     */
    public static function import(
        int $userid,
        int $id,
        string $text,
        string $source = self::SOURCE_PASTE,
        string $filename = ''
    ): int {
        access::plan($userid, $id, access::EDIT);
        if ($source !== self::SOURCE_PASTE && $source !== self::SOURCE_UPLOAD) {
            throw new \coding_exception("Unknown import source '{$source}'.");
        }
        $text = self::normalise($text);
        $log = ['userid' => $userid, 'source' => $source, 'filename' => \core_text::substr($filename, 0, 255),
            'contenthash' => sha1($text)];
        $work = function (\stdClass $plan) use ($text, $log): int {
            self::require_draft($plan);
            $result = self::read($plan, $text);
            if (self::count_issues($result, plan_issue::ERROR)) {
                throw new \moodle_exception('error_planhaserrors', 'mod_classprogress');
            }
            return self::in_transaction(fn(): int => self::write_content($plan, $text, $result, $log));
        };
        $importid = self::locked($id, null, $work);

        plan_imported::create([
            'objectid' => $id,
            'userid' => $userid,
            'context' => access::context(self::find($id)),
            'other' => ['importid' => $importid],
        ])->trigger();
        return $importid;
    }

    /**
     * Reads the stored text of a draft plan again, now, with the current holidays, timetable and school year: what
     * approval checks.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return plan_result What the stored text gives now.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_planlocked, error_notimported, error_noschoolyear, or
     *     error_plantoolarge when the limit was lowered.
     */
    public static function check_stored(int $userid, int $id): plan_result {
        $plan = access::plan($userid, $id, access::EDIT);
        self::require_draft($plan);
        if ($plan->sourcetext === null) {
            throw new \moodle_exception('error_notimported', 'mod_classprogress');
        }
        return self::read($plan, $plan->sourcetext);
    }

    /**
     * Approves a draft plan, for good: it copies its school year's dates, holidays and evaluation periods and then
     * refuses every change.
     *
     * @param int $userid The acting user, recorded as the one who approved.
     * @param int $id Plan id.
     * @param string $seen fingerprint() of the check_stored() result the teacher was shown.
     * @param bool $warningsread Whether the teacher confirmed that they read the warnings.
     * @throws \required_capability_exception When the user may not edit the plan.
     * @throws \moodle_exception error_notfound, error_planlocked, error_notimported, error_noschoolyear,
     *     error_planhaserrors, error_changedelsewhere when the stored text now gives something else than what was seen,
     *     error_confirmwarnings, error_plantoolarge, or locktimeout.
     */
    public static function approve(int $userid, int $id, string $seen, bool $warningsread): void {
        global $DB;

        access::plan($userid, $id, access::EDIT);
        self::locked($id, null, function (\stdClass $plan) use ($DB, $userid, $id, $seen, $warningsread): void {
            self::require_draft($plan);
            if ($plan->sourcetext === null) {
                throw new \moodle_exception('error_notimported', 'mod_classprogress');
            }
            $result = self::read($plan, $plan->sourcetext);
            if (self::count_issues($result, plan_issue::ERROR)) {
                throw new \moodle_exception('error_planhaserrors', 'mod_classprogress');
            }
            if (!hash_equals(self::fingerprint($result), $seen)) {
                throw new \moodle_exception('error_changedelsewhere', 'mod_classprogress');
            }
            if (self::count_issues($result, plan_issue::WARNING) && !$warningsread) {
                throw new \moodle_exception('error_confirmwarnings', 'mod_classprogress');
            }
            $year = self::require_year($plan);
            $now = max(time(), (int) $plan->timemodified + 1);
            self::in_transaction(function () use ($DB, $id, $year, $userid, $now): void {
                self::copy_school_year($id, $year);
                $DB->update_record(self::PLAN, (object) ['id' => $id, 'status' => self::STATUS_APPROVED,
                    'yearstart' => $year->startdate, 'yearend' => $year->enddate, 'timeapproved' => $now,
                    'approvedby' => $userid, 'timemodified' => $now]);
            });
        });

        plan_approved::create([
            'objectid' => $id,
            'userid' => $userid,
            'context' => access::context(self::find($id)),
        ])->trigger();
    }

    /**
     * Returns one plan.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return \stdClass The plan row.
     * @throws \moodle_exception error_notfound.
     */
    public static function get(int $userid, int $id): \stdClass {
        return access::plan($userid, $id);
    }

    /**
     * Returns the items of a plan: one per day of its text.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return \stdClass[] Plan item rows keyed by id, in the order of the text.
     * @throws \moodle_exception error_notfound.
     */
    public static function items(int $userid, int $id): array {
        global $DB;

        access::plan($userid, $id);
        return $DB->get_records(self::ITEM, ['planid' => $id], 'sortorder, id');
    }

    /**
     * Returns a plan's weekly timetable.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return float[] Weekday => hours, Monday = 0 to Sunday = 6, only weekdays with class, in weekday order.
     * @throws \moodle_exception error_notfound.
     */
    public static function timetable(int $userid, int $id): array {
        access::plan($userid, $id);
        return self::timetables([$id])[$id];
    }

    /**
     * Computes a plan's calendar: its sessions from the first to the last class day, minus its holidays (holidays()).
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return calendar_result The calendar; its lost sessions are keyed as holidays() keys the holidays.
     * @throws \moodle_exception error_notfound.
     */
    public static function calendar(int $userid, int $id): calendar_result {
        $plan = access::plan($userid, $id);
        return calendar::compute($plan->startdate, $plan->enddate, self::timetables([$id])[$id], self::plan_holidays($plan));
    }

    /**
     * Returns the holidays that apply to a plan: for a draft those of its school year (none when it is missing), for an
     * approved plan the copy it took at approval.
     *
     * @param int $userid The acting user.
     * @param int $id Plan id.
     * @return holiday_entry[] Holidays keyed by holiday (or copy) id, in the order of the school year's text.
     * @throws \moodle_exception error_notfound.
     */
    public static function holidays(int $userid, int $id): array {
        return self::plan_holidays(access::plan($userid, $id));
    }

    /**
     * Returns the school year of a draft plan, if it still exists.
     *
     * @param \stdClass $plan The plan row.
     * @return \stdClass|null The school year row; null when the plan has none or it was deleted.
     */
    public static function school_year(\stdClass $plan): ?\stdClass {
        return (int) $plan->schoolyearid > 0 ? schoolyear_service::find((int) $plan->schoolyearid) : null;
    }

    /**
     * The module of a plan as its # heading gave it: code and title, the title alone, or nothing before an import.
     *
     * @param \stdClass $plan A plan row, or an object with modulecode and moduletitle.
     * @return string The module as typed, not escaped: print it with s() or {{ }}.
     */
    public static function module_name(\stdClass $plan): string {
        if ($plan->modulecode === '' || $plan->moduletitle === '') {
            return trim($plan->modulecode . ' ' . $plan->moduletitle);
        }
        return get_string('planname', 'mod_classprogress', (object) ['code' => $plan->modulecode, 'title' => $plan->moduletitle]);
    }

    /**
     * Identifies what a reading of a plan gives, so that approval can tell whether it is still what the teacher saw.
     *
     * @param plan_result $result A result of check() or check_stored().
     * @return string A SHA-1 of the module, blocks, days and issues.
     */
    public static function fingerprint(plan_result $result): string {
        return sha1(json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * Counts the issues of one level.
     *
     * @param plan_result $result A result of check() or check_stored().
     * @param string $level plan_issue::ERROR or plan_issue::WARNING.
     * @return int The number of issues of that level.
     */
    public static function count_issues(plan_result $result, string $level): int {
        return count(array_filter($result->issues, fn(plan_issue $issue): bool => $issue->level === $level));
    }

    /**
     * The module code and title that importing a text stores: those of its # heading where it gives them, else the
     * plan's own.
     *
     * @param \stdClass $plan The plan row.
     * @param plan_result $result What the text gives.
     * @return string[] [code, title].
     */
    public static function imported_module(\stdClass $plan, plan_result $result): array {
        $code = $result->module?->code ?? '';
        $title = $result->module?->title ?? '';
        return [$code !== '' ? $code : $plan->modulecode, $title !== '' ? $title : $plan->moduletitle];
    }

    /**
     * The largest plan text, from the admin setting maxplansize.
     *
     * @return int Bytes.
     */
    public static function max_size(): int {
        $kb = (int) get_config('mod_classprogress', 'maxplansize');
        return ($kb > 0 ? $kb : self::DEFAULT_MAX_SIZE) * 1024;
    }

    /**
     * The most days a plan can have, from the admin setting maxplandays.
     *
     * @return int Days.
     */
    public static function max_days(): int {
        $days = (int) get_config('mod_classprogress', 'maxplandays');
        return $days > 0 ? $days : self::DEFAULT_MAX_DAYS;
    }

    /**
     * Checks plan data, including that the school year is one the user may use.
     *
     * Error keys: name, schoolyearid, startdate, enddate, timetable (for a weekday that does not exist) and timetable-N
     * for the hours of weekday N. For an approved plan only the name is checked: the rest is locked.
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data (see the class description).
     * @param int $id The plan being changed, which keeps its school year whoever owns it; 0 for a new one.
     * @return string[] Field => lang string id of the error, in field order; empty when the data is valid.
     */
    public static function validate(int $userid, \stdClass $data, int $id = 0): array {
        return self::errors($userid, $data, $id ? self::find($id) : null);
    }

    /**
     * Checks plan data; see validate().
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data.
     * @param \stdClass|null $plan The stored plan being changed; null for a new one.
     * @return string[] Field => lang string id of the error.
     */
    private static function errors(int $userid, \stdClass $data, ?\stdClass $plan): array {
        $errors = [];
        if (!schoolyear_service::is_name((string) ($data->name ?? ''), self::MAX_TITLE)) {
            $errors['name'] = 'error_name';
        }
        if ($plan && $plan->status !== self::STATUS_DRAFT) {
            return $errors;
        }
        $yearid = (int) ($data->schoolyearid ?? 0);
        $year = $yearid > 0 ? schoolyear_service::find($yearid) : null;
        $kept = $plan && $yearid === (int) $plan->schoolyearid;
        if (!$year || (!$kept && (int) $year->userid !== $userid)) {
            $errors['schoolyearid'] = 'error_schoolyear';
            $year = null;
        }
        $start = (string) ($data->startdate ?? '');
        $end = (string) ($data->enddate ?? '');
        foreach (['startdate' => $start, 'enddate' => $end] as $field => $date) {
            if ($date === '') {
                continue;
            }
            $error = schoolyear_service::date_error($date);
            $afterstart = $field === 'enddate' && $start !== '' && !isset($errors['startdate']);
            if ($error === null && $afterstart && dates::check_range($start, $end) === dates::REVERSED) {
                $error = 'error_endbeforestart';
            }
            if ($error === null && $year && !dates::within($date, $year->startdate, $year->enddate)) {
                $error = 'error_dateoutsideyear';
            }
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }
        foreach ($data->timetable ?? [] as $weekday => $hours) {
            if (!is_int($weekday) || $weekday < 0 || $weekday > 6) {
                $errors['timetable'] = 'error_weekday';
            } else if (!is_numeric($hours) || !is_finite((float) $hours) || $hours < 0 || $hours > self::MAX_HOURS) {
                $errors["timetable-{$weekday}"] = 'error_hours';
            }
        }
        return $errors;
    }

    /**
     * Throws the first validation error, if there is one.
     *
     * @param int $userid The acting user.
     * @param \stdClass $data Plan data.
     * @param \stdClass|null $plan The stored plan being changed; null for a new one.
     * @throws \moodle_exception With the lang string id of the first error.
     */
    private static function require_valid(int $userid, \stdClass $data, ?\stdClass $plan): void {
        $errors = self::errors($userid, $data, $plan);
        if ($errors) {
            throw new \moodle_exception(reset($errors), 'mod_classprogress');
        }
    }

    /**
     * Refuses a user who may not change the activity's settings, as Moodle's activity form requires.
     *
     * @param int $userid The acting user.
     * @param \stdClass $plan The plan row.
     * @throws \required_capability_exception When the user may not manage the course's activities.
     */
    private static function require_manager(int $userid, \stdClass $plan): void {
        $context = access::context($plan) ?? \context_course::instance($plan->course);
        if (!has_capability('moodle/course:manageactivities', $context, $userid)) {
            throw new \required_capability_exception($context, 'moodle/course:manageactivities', 'nopermissions', '');
        }
    }

    /**
     * Refuses a change to a plan that is not a draft.
     *
     * @param \stdClass $plan The plan row.
     * @throws \moodle_exception error_planlocked.
     */
    private static function require_draft(\stdClass $plan): void {
        if ($plan->status !== self::STATUS_DRAFT) {
            throw new \moodle_exception('error_planlocked', 'mod_classprogress');
        }
    }

    /**
     * Returns the school year of a draft, which it needs to be read or approved.
     *
     * @param \stdClass $plan The plan row.
     * @return \stdClass The school year row.
     * @throws \moodle_exception error_noschoolyear when it has none or it was deleted.
     */
    private static function require_year(\stdClass $plan): \stdClass {
        $year = self::school_year($plan);
        if (!$year) {
            throw new \moodle_exception('error_noschoolyear', 'mod_classprogress');
        }
        return $year;
    }

    /**
     * Whether plan data would change what approval locked: the school year, the class days or the timetable.
     *
     * @param \stdClass $plan The approved plan row.
     * @param \stdClass $data Plan data.
     * @return bool True when the data gives other values than the stored ones.
     */
    private static function changes_locked_settings(\stdClass $plan, \stdClass $data): bool {
        if (isset($data->schoolyearid) && (int) $data->schoolyearid !== (int) $plan->schoolyearid) {
            return true;
        }
        foreach (['startdate', 'enddate'] as $field) {
            if (isset($data->$field) && (string) $data->$field !== (string) $plan->$field) {
                return true;
            }
        }
        if (!isset($data->timetable)) {
            return false;
        }
        $stored = self::timetables([(int) $plan->id])[(int) $plan->id];
        $given = array_filter(array_map(fn($hours): float => round((float) $hours, 2), $data->timetable));
        ksort($given);
        return $given != $stored;
    }

    /**
     * Returns a plan by id, with no access check: for the activity callbacks, which Moodle has already checked.
     *
     * @param int $id Plan id.
     * @return \stdClass The plan row.
     * @throws \moodle_exception error_notfound.
     */
    private static function find(int $id): \stdClass {
        global $DB;

        $plan = $DB->get_record(self::PLAN, ['id' => $id]);
        if (!$plan) {
            throw new \moodle_exception('error_notfound', 'mod_classprogress');
        }
        return $plan;
    }

    /**
     * Runs a change holding the plan's lock and then a school year's lock, with the plan as read under the lock.
     *
     * @param int $id Plan id, already checked to exist.
     * @param int|null $yearid School year whose lock to hold; null for the plan's own.
     * @param callable $work The change; it gets the plan row, and what it returns is returned.
     * @return mixed What the change returns.
     * @throws \moodle_exception error_notfound when the plan went in the meantime, or locktimeout.
     */
    private static function locked(int $id, ?int $yearid, callable $work): mixed {
        $factory = \core\lock\lock_config::get_lock_factory('mod_classprogress');
        $planlock = $factory->get_lock('plan-' . $id, schoolyear_service::LOCK_TIMEOUT);
        if (!$planlock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $plan = self::find($id);
            $yearlock = schoolyear_service::lock($yearid ?? (int) $plan->schoolyearid);
            try {
                return $work($plan);
            } finally {
                $yearlock->release();
            }
        } finally {
            $planlock->release();
        }
    }

    /**
     * Reads a plan text for a draft, with the errors for what could not be stored.
     *
     * @param \stdClass $plan The plan row.
     * @param string $text The text, with normalised line ends.
     * @return plan_result What the text gives.
     * @throws \moodle_exception error_noschoolyear, or error_plantoolarge with the limit in KB.
     */
    private static function read(\stdClass $plan, string $text): plan_result {
        $year = self::require_year($plan);
        $max = self::max_size();
        if (strlen($text) > $max) {
            throw new \moodle_exception('error_plantoolarge', 'mod_classprogress', '', intdiv($max, 1024));
        }
        $holidays = holiday_parser::expand(schoolyear_service::holidays_of((int) $year->id));
        $timetable = self::timetables([(int) $plan->id])[(int) $plan->id];
        $result = (new plan_parser())->parse($text, $holidays, $timetable ?: null, [$year->startdate, $year->enddate]);
        return self::with_storage_errors($result);
    }

    /**
     * Adds to a reading the errors for what the plan tables cannot hold or the date limits do not allow.
     *
     * A text that is not UTF-8 keeps its single error.
     *
     * @param plan_result $result What the parser read.
     * @return plan_result The same, with those errors among the issues, ordered by line.
     */
    private static function with_storage_errors(plan_result $result): plan_result {
        foreach ($result->issues as $issue) {
            if ($issue->code === plan_issue::NOT_UTF8) {
                return $result;
            }
        }
        $error = fn(string $code, int $line, array $params = []): plan_issue =>
            new plan_issue(plan_issue::ERROR, $code, $line, $params);
        $issues = [];
        $module = $result->module;
        $codelength = \core_text::strlen($module?->code ?? '');
        if ($module && ($codelength > self::MAX_CODE || \core_text::strlen($module->title) > self::MAX_TITLE)) {
            $issues[] = $error(self::MODULE_TOO_LONG, $module->line);
        }
        if (!$result->days) {
            $issues[] = $error(self::NO_DAYS, 1);
        }
        $maxdays = self::max_days();
        $blocks = [];
        foreach ($result->days as $index => $day) {
            if ($index === $maxdays) {
                $issues[] = $error(self::TOO_MANY_DAYS, $day->line, ['max' => (string) $maxdays]);
            }
            if ($day->block !== null && !isset($blocks[$day->block]) && \core_text::strlen($day->block) > self::MAX_TITLE) {
                $issues[] = $error(self::BLOCK_TOO_LONG, $day->line, ['max' => (string) self::MAX_TITLE]);
            }
            $blocks[$day->block ?? ''] = true;
            if (\core_text::strlen($day->title) > self::MAX_TITLE) {
                $issues[] = $error(self::TITLE_TOO_LONG, $day->line, ['date' => $day->date, 'max' => (string) self::MAX_TITLE]);
            }
            if ($day->hours !== null && $day->hours > self::MAX_HOURS) {
                $issues[] = $error(self::TOO_MANY_HOURS, $day->line, ['date' => $day->date, 'hours' => (string) $day->hours]);
            }
            if (!dates::in_window($day->date)) {
                $issues[] = $error(self::OUT_OF_RANGE, $day->line, ['date' => $day->date]);
            }
        }
        if (!$issues) {
            return $result;
        }
        $issues = array_merge($result->issues, $issues);
        // Stable, so the parser's issues on a line come before these.
        usort($issues, fn(plan_issue $a, plan_issue $b): int => $a->line <=> $b->line);
        return new plan_result($result->module, $result->blocks, $result->days, $issues);
    }

    /**
     * Stores line ends as line feeds and drops null bytes, as Moodle does for posted text.
     *
     * @param string $text A plan text.
     * @return string The same text.
     */
    private static function normalise(string $text): string {
        return str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
    }

    /**
     * Returns the holidays that apply to a plan; see holidays().
     *
     * @param \stdClass $plan The plan row.
     * @return holiday_entry[] Holidays keyed by id.
     */
    private static function plan_holidays(\stdClass $plan): array {
        global $DB;

        if ($plan->status === self::STATUS_DRAFT) {
            $year = self::school_year($plan);
            return $year ? schoolyear_service::holidays_of((int) $year->id) : [];
        }
        $entries = [];
        foreach ($DB->get_records(self::HOLIDAY, ['planid' => $plan->id], 'id') as $row) {
            $entries[$row->id] = new holiday_entry(count($entries) + 1, $row->startdate, $row->enddate, $row->label);
        }
        return $entries;
    }

    /**
     * Copies a school year's holidays and evaluation periods into a plan, replacing any earlier copy.
     *
     * @param int $id Plan id.
     * @param \stdClass $year The school year row.
     */
    private static function copy_school_year(int $id, \stdClass $year): void {
        global $DB;

        $DB->delete_records(self::HOLIDAY, ['planid' => $id]);
        $DB->delete_records(self::EVALUATION, ['planid' => $id]);
        $DB->insert_records(self::HOLIDAY, array_map(fn(holiday_entry $holiday): array => [
            'planid' => $id,
            'startdate' => $holiday->start,
            'enddate' => $holiday->end,
            'label' => $holiday->label,
        ], array_values(schoolyear_service::holidays_of((int) $year->id))));
        $DB->insert_records(self::EVALUATION, array_map(fn(\stdClass $evaluation): array => [
            'planid' => $id,
            'name' => $evaluation->name,
            'startdate' => $evaluation->startdate,
            'enddate' => $evaluation->enddate,
        ], array_values(schoolyear_service::evaluations_of((int) $year->id))));
    }

    /**
     * The plan fields that valid data sets, cleaned for storage.
     *
     * @param \stdClass $data Valid plan data.
     * @return array Field => value.
     */
    private static function fields(\stdClass $data): array {
        return self::description($data) + [
            'schoolyearid' => (int) $data->schoolyearid,
            'startdate' => empty($data->startdate) ? null : $data->startdate,
            'enddate' => empty($data->enddate) ? null : $data->enddate,
        ];
    }

    /**
     * The plan fields that an approved plan still takes: its name and description.
     *
     * @param \stdClass $data Valid plan data.
     * @return array Field => value.
     */
    private static function description(\stdClass $data): array {
        $fields = ['name' => trim($data->name)];
        if (isset($data->intro)) {
            $fields['intro'] = $data->intro;
            $fields['introformat'] = (int) ($data->introformat ?? FORMAT_HTML);
        }
        return $fields;
    }

    /**
     * Reads the timetables of plans.
     *
     * @param int[] $ids Plan ids.
     * @return array Plan id => (weekday => hours), every id present, weekdays in order.
     */
    private static function timetables(array $ids): array {
        global $DB;

        $timetables = array_fill_keys($ids, []);
        [$insql, $params] = $DB->get_in_or_equal($ids);
        foreach ($DB->get_records_select(self::SLOT, "planid {$insql}", $params, 'planid, weekday') as $slot) {
            $timetables[$slot->planid][(int) $slot->weekday] = (float) $slot->hours;
        }
        return $timetables;
    }

    /**
     * Replaces the timetable when the data gives one: one slot per weekday with hours above zero.
     *
     * @param int $id Plan id.
     * @param \stdClass $data Valid plan data.
     * @param int $now Time of the change.
     * @return bool Whether the data gave a timetable.
     */
    private static function write_timetable(int $id, \stdClass $data, int $now): bool {
        global $DB;

        if (!isset($data->timetable)) {
            return false;
        }
        $rows = [];
        foreach ($data->timetable as $weekday => $hours) {
            $hours = round((float) $hours, 2);
            if ($hours > 0) {
                $rows[] = ['planid' => $id, 'weekday' => $weekday, 'hours' => $hours, 'timecreated' => $now,
                    'timemodified' => $now];
            }
        }
        $DB->delete_records(self::SLOT, ['planid' => $id]);
        $DB->insert_records(self::SLOT, $rows);
        return true;
    }

    /**
     * Writes a checked text as the content of a draft: the plan's module and text, its items and a row of the import log.
     *
     * @param \stdClass $plan The plan row, read under its lock.
     * @param string $text The text, with normalised line ends.
     * @param plan_result $result What the text gives, without errors.
     * @param array $log Fields of the import log row: userid, source, filename and contenthash.
     * @return int Id of the import log row.
     */
    private static function write_content(\stdClass $plan, string $text, plan_result $result, array $log): int {
        global $DB;

        [$code, $title] = self::imported_module($plan, $result);
        $now = max(time(), (int) $plan->timemodified + 1);
        $DB->update_record(self::PLAN, (object) ['id' => $plan->id, 'modulecode' => $code, 'moduletitle' => $title,
            'sourcetext' => $text, 'timemodified' => $now]);
        self::write_items((int) $plan->id, $result->days, $now);
        return (int) $DB->insert_record(self::IMPORT, (object) ($log + [
            'planid' => $plan->id,
            'daycount' => count($result->days),
            'timecreated' => $now,
        ]));
    }

    /**
     * Replaces the items of a plan with one per day.
     *
     * @param int $id Plan id.
     * @param plan_day[] $days The days of its text, in order.
     * @param int $now Time of the change.
     */
    private static function write_items(int $id, array $days, int $now): void {
        global $DB;

        $rows = [];
        foreach (array_values($days) as $index => $day) {
            $rows[] = [
                'planid' => $id,
                'sortorder' => $index + 1,
                'sessiondate' => $day->date,
                'block' => $day->block ?? '',
                'title' => $day->title,
                'content' => $day->content,
                'hours' => $day->hours === null ? null : round($day->hours, 2),
                'timecreated' => $now,
            ];
        }
        $DB->delete_records(self::ITEM, ['planid' => $id]);
        $DB->insert_records(self::ITEM, $rows);
    }

    /**
     * Runs writes in one delegated transaction: all of them are stored, or none.
     *
     * @param callable $writes The writes; what they return is returned.
     * @return mixed What the writes return.
     */
    private static function in_transaction(callable $writes): mixed {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $writes();
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            // Rolls back and throws the exception again.
            $transaction->rollback($e);
        }
    }
}
