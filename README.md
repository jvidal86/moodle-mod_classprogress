# Class progress plan (mod_classprogress)

A Moodle course activity that keeps the year plan of one module and the record of what was done in each session,
for the inspection. Only the course's teachers see it; students neither see it on the course page nor open it.

- **Plan:** an editing teacher adds the activity from the activity chooser, chooses one of their school years, the
  weekly timetable and the first and last class day, then imports the plan as Markdown (one `###` section per day,
  pasted or uploaded), reads the preview with its errors and warnings, and approves it. An approved plan is locked:
  its days, hours, text, school year and timetable never change, and it keeps its own copy of the school year's
  dates, holidays and evaluation periods.
- **Log:** any teacher of the course logs each session (done, partly done, not done with a reason, or an unplanned
  session), also days later; late entries are flagged and every change is kept in a history.
- **Export:** the plan against what was done, as summary tables on the page (overall, per RA or block, per
  evaluation period, with coverage and hours) and as an XLSX file with one row per session, for the inspector.
- **Backup:** a course backup always holds the plan; entries and history only with user data. A course reset can
  remove the entries and keep the plan.

## Requirements

- Moodle 4.5 to 5.3, PHP 8.1 or later.
- [`local_classprogress`](https://github.com/jvidal86/moodle-local_classprogress) (school years, holidays, evaluation
  periods, the "Class progress" page in the top menu, and the plan and calendar library this activity uses). Install it
  first.

## Install

Copy this repository to `mod/classprogress` (`public/mod/classprogress` on Moodle 5.x) and run the upgrade. The
largest plan text and the most days a plan can have are admin settings of the activity.

## Privacy

Plans, entries and their history belong to the activity, in its module context. A privacy export holds what the user
authored there (approvals, imports, entries and history rows). Erasing a user, or deleting their account, replaces their
user id by a deleted-user placeholder and keeps the content, since the plan and its log are the course's shared record;
the data is fully deleted only with the activity or the course.

## Development

Plan, requirements, documentation and test data live in the `local_classprogress` repository (`PLAN.md`,
`docs/spec/REQUIREMENTS.md`, `docs/DEV_ENVIRONMENT.md`). The fixtures in `tests/fixtures/` are a copy of its
`test-data/`, made with its `tools/sync_fixtures.sh ~/PROJECTS/moodle-mod_classprogress/tests/fixtures` (add `--check`
to compare). Its `tools/check_traceability.py --extra-root ~/PROJECTS/moodle-mod_classprogress` counts the tests of
this repository too.

## License

GNU GPL v3 or later.
