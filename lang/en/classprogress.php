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
 * English strings for the class progress activity.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addunplanned'] = 'Add an unplanned session';
$string['aiinstructions'] = 'Write my year plan as Markdown, in exactly the format below, so that I can import it into Moodle. Output only the plan, with nothing after it: anything after the last day would be read as part of that day.

# <module code> · <module title>
(once, as the first heading)

## <RA or block> · <title>
(one heading for each learning outcome or block)

### <date> [<hours>h] <optional short title>
<what we do that day, as a Markdown list; bold and links are allowed>

Rules:
- One ### heading for each class day, in date order. Two days on the same date are allowed.
- Write dates as 2026-09-16 or 16/09/2026. A weekday may come before the date (dl, dt, dc, dj, dv, Mon, Tue, lun, mar, ...).
- Write hours as [2h] or [1,5h]. Leave them out to use the hours of my weekly timetable.
- Everything under a ### heading, up to the next heading, is that day\'s plan.
- Do not add any other # heading. Do not use #, ## or ### headings inside a day.';
$string['aiinstructionsheading'] = 'Instructions for my AI assistant';
$string['aiinstructionsintro'] = 'To have an AI assistant write or convert your plan, copy these instructions into the chat together with your own plan, then paste its answer here.';
$string['allsessions'] = 'All sessions';
$string['approveplan'] = 'Approve plan';
$string['approveplanintro'] = 'Approval is final. Once the plan is approved, its days, dates, hours, text and timetable can no longer be changed, and the plan cannot be deleted. Check the days below before you approve it.';
$string['approveplannoerrors'] = 'The plan text has errors, so the plan cannot be approved. Import a corrected text first.';
$string['block'] = 'RA or block';
$string['calendar'] = 'Calendar';
$string['calendar_invalid_date'] = 'The first or the last class day is not a valid date.';
$string['calendar_last_before_first'] = 'The last class day is before the first, so there are no sessions.';
$string['calendar_missing_dates'] = 'Enter the first and the last class day to see the calendar.';
$string['calendar_out_of_range'] = 'The first or the last class day is not between 2000-01-01 and 2099-12-31, so there is no calendar.';
$string['calendar_too_long'] = 'The class days span more than 400 days, so there is no calendar.';
$string['calendarsummary'] = '{$a->sessions}, {$a->hours}';
$string['changetext'] = 'Change the text';
$string['chooseschoolyear'] = 'Choose a school year';
$string['classprogress:addinstance'] = 'Add a Class progress plan';
$string['classprogress:edit'] = 'Import, approve and log Class progress plans';
$string['classprogress:view'] = 'See Class progress plans and their logs';
$string['date'] = 'Date';
$string['daterange'] = '{$a->start} to {$a->end}';
$string['dates'] = 'Dates';
$string['days'] = 'Days';
$string['daytitle'] = 'Title';
$string['deletedentries'] = 'History of deleted entries';
$string['deleteduser'] = 'Deleted user';
$string['deleteentry'] = 'Delete entry';
$string['deleteentryconfirm'] = 'Delete the entry for the session of {$a}? Its history is kept.';
$string['editentry'] = 'Edit entry';
$string['entrycarry'] = 'Continue in the next session';
$string['entrycontent'] = 'What was done';
$string['entrydeleted'] = 'Entry deleted. Its history is kept.';
$string['entryissue_bad_date'] = 'Enter an existing date written yyyy-mm-dd, such as 2026-10-15.';
$string['entryissue_bad_reason'] = 'Choose one of the reasons in the list.';
$string['entryissue_bad_status'] = 'Choose one of the statuses in the list.';
$string['entryissue_content_required'] = 'Write what was done.';
$string['entryissue_future_not_done_only'] = 'This session is after today: until its day comes it can only be logged as not done, for a cancellation known in advance.';
$string['entryissue_future_unplanned'] = 'An unplanned session cannot be dated after today.';
$string['entryissue_out_of_range'] = 'Enter a date from 2000-01-01 to 2099-12-31.';
$string['entryissue_outside_year'] = 'The date must lie within the school year.';
$string['entryissue_reason_required'] = 'Choose why the session was not fully done.';
$string['entrylastedited'] = 'Last edited {$a}.';
$string['entrymeasure'] = 'Corrective measure';
$string['entryreason'] = 'Reason';
$string['entryreasonnote'] = 'Note about the reason';
$string['entrysaved'] = 'Entry saved.';
$string['entrystatus_added'] = 'Added';
$string['entrystatus_done'] = 'Done';
$string['entrystatus_not_done'] = 'Not done';
$string['entrystatus_not_logged'] = 'Not logged';
$string['entrystatus_partial'] = 'Partly done';
$string['entrystatus_upcoming'] = 'Upcoming';
$string['entrywrittenon'] = 'Written on {$a}.';
$string['error_baddate'] = 'Enable the date and pick an existing day.';
$string['error_changedelsewhere'] = 'This was changed elsewhere; reload to see the latest version';
$string['error_confirmwarnings'] = 'Confirm that you have read the warnings.';
$string['error_dateoutsideyear'] = 'The date must lie within the school year.';
$string['error_daterange'] = 'Enter a date from 2000-01-01 to 2099-12-31.';
$string['error_endbeforestart'] = 'The last day is before the first day.';
$string['error_hours'] = 'Enter a number of hours from 0 to 24.';
$string['error_name'] = 'Enter a name of up to 255 characters.';
$string['error_noschoolyear'] = 'This plan has no school year. Choose one of your school years in the activity settings first.';
$string['error_notfound'] = 'That item does not exist, or you do not have permission to see it.';
$string['error_notimported'] = 'Import the plan text before you approve the plan.';
$string['error_planhaserrors'] = 'The plan text has errors. Correct them; nothing has been saved.';
$string['error_planlocked'] = 'This plan is approved and can no longer be changed.';
$string['error_plannotapproved'] = 'Sessions can be logged and exported only for an approved plan.';
$string['error_plantoolarge'] = 'The plan text is larger than {$a} KB, the most a plan can have.';
$string['error_schoolyear'] = 'Choose one of your school years.';
$string['error_weekday'] = 'The timetable has a day that is not a weekday.';
$string['evententrycreated'] = 'Entry created';
$string['evententrydeleted'] = 'Entry deleted';
$string['evententryupdated'] = 'Entry updated';
$string['eventplanapproved'] = 'Plan approved';
$string['eventplanimported'] = 'Plan imported';
$string['export'] = 'Export';
$string['exportcol_block'] = 'Block';
$string['exportcol_carry'] = 'Continues in the next session';
$string['exportcol_date'] = 'Date';
$string['exportcol_dayslate'] = 'Days late';
$string['exportcol_done'] = 'What was done';
$string['exportcol_hours'] = 'Planned hours';
$string['exportcol_loggedby'] = 'Logged by';
$string['exportcol_measure'] = 'Corrective measure';
$string['exportcol_note'] = 'Reason note';
$string['exportcol_period'] = 'Evaluation period';
$string['exportcol_planned'] = 'Planned';
$string['exportcol_reason'] = 'Reason';
$string['exportcol_status'] = 'Status';
$string['exportcol_writtenon'] = 'Written on';
$string['exportdownload'] = 'Download the spreadsheet (XLSX)';
$string['exportfig_added'] = 'Added';
$string['exportfig_coverage'] = 'Coverage (%)';
$string['exportfig_done'] = 'Done';
$string['exportfig_group'] = 'Group';
$string['exportfig_hoursdone'] = 'Hours done';
$string['exportfig_hoursnotdone'] = 'Hours not done';
$string['exportfig_mostdayslate'] = 'Most days late';
$string['exportfig_notdone'] = 'Not done';
$string['exportfig_notlogged'] = 'Not logged';
$string['exportfig_partial'] = 'Partly done';
$string['exportfig_planned'] = 'Planned';
$string['exportfig_plannedhours'] = 'Planned hours';
$string['exportfig_upcoming'] = 'Upcoming';
$string['exportfig_writtenlate'] = 'Written late';
$string['exportgroup_all'] = 'All sessions';
$string['exportgroup_no_block'] = 'No block';
$string['exportgroup_outside'] = 'Outside evaluation periods';
$string['exportgroup_unplanned'] = 'Unplanned sessions';
$string['exportintro'] = 'The plan against what was logged, up to today, {$a}. A session not logged yet counts as not covered, a partly done one as half; upcoming sessions are left out of the coverage. The spreadsheet holds the same figures and one row per session.';
$string['exportlabel_activity'] = 'Activity';
$string['exportlabel_course'] = 'Course';
$string['exportlabel_exportedby'] = 'Exported by';
$string['exportlabel_exportedon'] = 'Exported on';
$string['exportlabel_module'] = 'Module';
$string['exportlabel_plandates'] = 'Plan dates';
$string['exportlabel_plannedhours'] = 'Planned hours';
$string['exportlabel_plannedsessions'] = 'Planned sessions';
$string['exportlabel_schoolyear'] = 'School year';
$string['exportsheet_plan'] = 'Plan vs done';
$string['exportsheet_summary'] = 'Summary';
$string['exporttable_overall'] = 'Overall';
$string['exporttable_perblock'] = 'Per block';
$string['exporttable_perperiod'] = 'Per evaluation period';
$string['firstclassday'] = 'First class day';
$string['firstclassday_help'] = 'The first and the last day of class of this module. The calendar counts the sessions between them on the weekdays of the timetable, minus the holidays of the school year. Leave them not enabled if you do not know them yet.';
$string['history'] = 'History';
$string['historyaction_create'] = 'Created';
$string['historyaction_delete'] = 'Deleted';
$string['historyaction_update'] = 'Edited';
$string['historyby'] = 'By {$a}';
$string['historyentry'] = 'History of the entry';
$string['historyintro'] = 'Every change, newest first, with the entry as it was after it (before it, for a deletion). The history cannot be changed and is kept when an entry is deleted.';
$string['historyloggedinas'] = 'By {$a->realuser}, logged in as {$a->user}';
$string['holiday'] = 'Holiday';
$string['hours'] = 'Hours';
$string['importplan'] = 'Import plan text';
$string['importplanintro'] = 'Paste the text of the plan below, or upload it as a Markdown (.md) or text file, then press Preview. Nothing is saved until you press Save draft after the preview.';
$string['issue_bad_date'] = 'The day heading "{$a->text}" has no date that can be read. Write the date as 2026-09-16 or 16/09/2026, optionally after a weekday such as dl or Mon.';
$string['issue_block_too_long'] = 'The RA or block heading above this day is longer than {$a->max} characters.';
$string['issue_empty_day'] = 'The day {$a->date} has no plan text.';
$string['issue_missing_hours'] = 'The day {$a->date} has no hours, and the timetable has none for that weekday.';
$string['issue_module_too_long'] = 'The module heading is too long: the code can have up to 100 characters and the title up to 255.';
$string['issue_multiple_modules'] = 'A second # module heading. A plan holds one module: remove this heading or import the module separately.';
$string['issue_no_block'] = 'The day {$a->date} has no ## RA or block heading above it.';
$string['issue_no_class_weekday'] = 'The day {$a->date} falls on a weekday with no class in the timetable.';
$string['issue_no_days'] = 'The plan has no days. Write each class day as a ### heading that starts with its date.';
$string['issue_no_module_heading'] = 'The plan has no # module heading, so the module code and title of this plan stay as they are.';
$string['issue_not_utf8'] = 'The text is not UTF-8 text. Save the file as UTF-8 and try again.';
$string['issue_on_holiday'] = 'The day {$a->date} is a holiday: {$a->holiday}.';
$string['issue_out_of_order'] = 'The day {$a->date} comes after {$a->previous} in the text, but it is earlier.';
$string['issue_out_of_range'] = 'The date {$a->date} is not between 2000-01-01 and 2099-12-31.';
$string['issue_outside_year'] = 'The day {$a->date} is outside the school year.';
$string['issue_title_too_long'] = 'The title of the day {$a->date} is longer than {$a->max} characters.';
$string['issue_too_many_days'] = 'The plan has more than {$a->max} days, the most a plan can have. This is the first day over the limit.';
$string['issue_too_many_hours'] = 'The day {$a->date} has {$a->hours} hours; a day can have at most 24.';
$string['issue_unclosed_fence'] = 'A code block opens here and is never closed, so it may hide the days after it.';
$string['issue_unwrapped_fence'] = 'The plan was inside a code block, so it was read from that block and the text around the block was left out.';
$string['issue_weekday_mismatch'] = 'The weekday "{$a->weekday}" is not the weekday of {$a->date}.';
$string['issueline'] = 'Line {$a->line}: {$a->message}';
$string['lastclassday'] = 'Last class day';
$string['late'] = 'Late by {$a} days';
$string['lateone'] = 'Late by 1 day';
$string['leftover'] = 'Left over from the previous session';
$string['log'] = 'Session log';
$string['logged'] = 'Logged sessions';
$string['logintro'] = 'Log what was done in each session. A session logged after its day shows how many days late it was written.';
$string['logsession'] = 'Log';
$string['maxplandays'] = 'Most days in a plan';
$string['maxplandays_desc'] = 'A plan text with more days than this is refused.';
$string['maxplansize'] = 'Largest plan text (KB)';
$string['maxplansize_desc'] = 'A pasted or uploaded plan text larger than this, in kilobytes, is refused.';
$string['module'] = 'Module';
$string['modulename'] = 'Class progress';
$string['modulename_help'] = 'A Class progress activity holds the year plan of one module of the course and the record of what was done in each session, for the inspection. Only the teachers of the course see it. Import the plan as Markdown, check it and approve it; then log each session against it. Late entries are flagged and every change is kept in a history.';
$string['modulenameplural'] = 'Class progress plans';
$string['month'] = 'Month';
$string['noblock'] = '(no RA or block)';
$string['nodeletedentries'] = 'No entry of this plan has been deleted.';
$string['noholidays'] = 'This school year has no holidays.';
$string['noitems'] = 'This plan has no days yet. Import its text to add them.';
$string['nologged'] = 'No session has been logged yet.';
$string['nopending'] = 'Nothing pending: every session up to today is logged.';
$string['noplanactivities'] = 'There are no Class progress plans in this course that you can open.';
$string['noschoolyear'] = 'This draft plan has no school year: the school year it used was deleted, or the activity was restored from a backup. Choose one of your school years in the activity settings before you import or approve the plan.';
$string['noschoolyears'] = 'You have no school years yet. A plan needs one for its holidays and dates: {$a}.';
$string['noupcoming'] = 'There are no sessions after today.';
$string['pending'] = 'Pending sessions';
$string['pendingintro'] = 'Sessions up to today with nothing logged yet, oldest first.';
$string['planapproved'] = 'Plan approved.';
$string['plandays'] = 'Plan and calendar';
$string['plandraftsaved'] = 'Draft saved. Check its days, then approve the plan.';
$string['planfile'] = 'Or upload a file';
$string['planfile_help'] = 'A Markdown (.md) or text (.txt) file, saved as UTF-8. It is read in place of the text box and is not kept.';
$string['planname'] = '{$a->code} · {$a->title}';
$string['plannedcontent'] = 'Planned content';
$string['plannedday'] = 'Planned for this session';
$string['plansettings'] = 'Plan';
$string['plantext'] = 'Plan text';
$string['pluginadministration'] = 'Class progress administration';
$string['pluginname'] = 'Class progress';
$string['preview'] = 'Preview of the plan text';
$string['previewclean'] = 'No errors or warnings.';
$string['previewerrors'] = 'Errors: the plan cannot be saved until they are corrected.';
$string['previewplan'] = 'Preview';
$string['previewreplaces'] = 'The module heading of the text replaces the module typed for this plan ({$a}).';
$string['previewwarnings'] = 'Warnings: read them; you will be asked to confirm them when you approve the plan.';
$string['privacy:metadata:classprogress'] = 'A plan activity: the year plan of one module, shared by the teachers of its course.';
$string['privacy:metadata:classprogress:approvedby'] = 'The teacher who approved the plan.';
$string['privacy:metadata:classprogress:timeapproved'] = 'When the plan was approved and locked.';
$string['privacy:metadata:classprogress_entry'] = 'What actually happened in a session, written by a teacher of the course. The text may name students.';
$string['privacy:metadata:classprogress_entry:carry'] = 'Whether unfinished content is carried over to a later session.';
$string['privacy:metadata:classprogress_entry:content'] = 'What was done in the session; it may name students.';
$string['privacy:metadata:classprogress_entry:measure'] = 'The corrective action for a deviation; it may name students.';
$string['privacy:metadata:classprogress_entry:reason'] = 'The reason given for a deviation from the plan.';
$string['privacy:metadata:classprogress_entry:reasonnote'] = 'Free text about the reason; it may name students.';
$string['privacy:metadata:classprogress_entry:sessiondate'] = 'The day the session took place.';
$string['privacy:metadata:classprogress_entry:status'] = 'Whether the planned content was done, partly done or not done.';
$string['privacy:metadata:classprogress_entry:timecreated'] = 'When the entry was created.';
$string['privacy:metadata:classprogress_entry:timemodified'] = 'When the entry was last changed.';
$string['privacy:metadata:classprogress_entry:writtenon'] = 'The day the entry was written, which shows whether it was written late.';
$string['privacy:metadata:classprogress_entry_hist'] = 'The history of every change to an entry, kept after the entry is deleted, with who made each change. Its copies of the entry hold text that may name students.';
$string['privacy:metadata:classprogress_entry_hist:action'] = 'Whether the entry was created, updated or deleted.';
$string['privacy:metadata:classprogress_entry_hist:realuserid'] = 'The real user, when the change was made by someone logged in as the user.';
$string['privacy:metadata:classprogress_entry_hist:snapshot'] = 'A copy of every field of the entry at the time of the change, including text that may name students.';
$string['privacy:metadata:classprogress_entry_hist:timecreated'] = 'When the change was made.';
$string['privacy:metadata:classprogress_entry_hist:userid'] = 'The teacher who made the change.';
$string['privacy:metadata:classprogress_import'] = 'The log of plan imports. The plan text itself is kept on the plan.';
$string['privacy:metadata:classprogress_import:contenthash'] = 'A fingerprint (SHA-1) of the imported text.';
$string['privacy:metadata:classprogress_import:daycount'] = 'The number of days read from the text.';
$string['privacy:metadata:classprogress_import:filename'] = 'The name of the uploaded file.';
$string['privacy:metadata:classprogress_import:source'] = 'Whether the text was pasted or uploaded.';
$string['privacy:metadata:classprogress_import:timecreated'] = 'When the plan was imported.';
$string['privacy:metadata:classprogress_import:userid'] = 'The teacher who imported the text.';
$string['privacy:path:approval'] = 'Approval';
$string['privacy:path:entries'] = 'Entries';
$string['privacy:path:history'] = 'History';
$string['privacy:path:imports'] = 'Imports';
$string['readwarnings'] = 'I have read the warnings';
$string['reason_incident'] = 'Incident';
$string['reason_other'] = 'Other';
$string['reason_school_event'] = 'School event';
$string['reason_strike'] = 'Strike';
$string['reason_teacher_absent'] = 'Teacher absent';
$string['reason_trip'] = 'Trip';
$string['resetentries'] = 'Delete the logged sessions and their history (the plans stay)';
$string['savedraft'] = 'Save draft';
$string['saveentry'] = 'Save entry';
$string['schoolyear'] = 'School year';
$string['schoolyear_help'] = 'One of your school years, whose dates, holidays and evaluation periods the plan uses. When the plan is approved it keeps its own copy of them, so later changes to the school year do not change it.';
$string['schoolyearkept'] = 'The current school year (of another teacher)';
$string['sessiondate'] = 'Date of the session';
$string['sessions'] = 'Sessions';
$string['sessionslost'] = 'Sessions lost to holidays';
$string['sessionslostcolumn'] = 'Sessions lost';
$string['sessionspermonth'] = 'Sessions per month';
$string['settingslocked'] = 'This plan is approved: its school year, class days and timetable can no longer be changed. Only the name and the description still can.';
$string['status'] = 'Status';
$string['statusapproved'] = 'Approved on {$a}. The plan is locked: its days, dates, hours, text and timetable can no longer be changed.';
$string['statusapprovedshort'] = 'Approved';
$string['statusdraft'] = 'Draft: the plan can still be changed, imported again or deleted.';
$string['statusdraftshort'] = 'Draft';
$string['summaryhours'] = '{$a} hours of class';
$string['summaryhoursone'] = '{$a} hour of class';
$string['summarysessions'] = '{$a} sessions';
$string['summarysessionsone'] = '{$a} session';
$string['timetable'] = 'Weekly timetable';
$string['timetableintro'] = 'Hours of class of this module on each weekday; 0 means no class that day.';
$string['unplannedsession'] = 'Unplanned session';
$string['upcoming'] = 'Upcoming sessions';
$string['upcomingintro'] = 'Until its day comes, a session can only be logged as not done, for a cancellation known in advance.';
$string['weekday'] = 'Weekday';
$string['writtenon'] = 'Written on';
