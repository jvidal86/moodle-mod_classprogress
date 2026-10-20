# Class progress plan (mod_classprogress)

A Moodle course activity that keeps the year plan of one module and the record of what was done in each session,
for the inspection. Only the course's teachers see it; students neither see it on the course page nor open it. It
works together with the local plugin [`local_classprogress`](https://github.com/jvidal86/moodle-local_classprogress),
which keeps each teacher's school years and the "Class progress" page in the top menu.

- **Plan:** an editing teacher adds the activity from the activity chooser, chooses one of their school years, the
  weekly timetable and the first and last class day, then imports the plan as Markdown (one `###` section per day,
  pasted or uploaded), reads the preview with its errors and warnings, and approves it. An approved plan is locked:
  its days, hours, text, school year and timetable never change, and it keeps its own copy of the school year's
  dates, holidays and evaluation periods.
- **Log:** any teacher of the course logs each session (done; partly done or not done, with a reason; or an unplanned
  session), also days later; late entries are flagged and every change is kept in a history.
- **Export:** the plan against what was done, as summary tables on the page (overall, per RA or block, per
  evaluation period, with coverage and hours) and as an XLSX file with one row per session, for the inspector.
- **Backup:** a course backup always holds the plan; entries and history only with user data. A course reset keeps
  the entries unless its "Delete the logged sessions and their history" option is ticked (it is not by default); the
  plan always stays.

## Requirements

- Moodle 4.5 to 5.3.
- PHP 8.1 or later, and at least the version your Moodle needs (Moodle 5.x needs a newer PHP of its own). Tested with
  PHP 8.1 and 8.3 on 4.5, and PHP 8.3 and 8.4 on 5.3.
- The local plugin [`local_classprogress`](https://github.com/jvidal86/moodle-local_classprogress), of the same
  release (school years, holidays, evaluation periods, the "Class progress" page in the top menu, and the plan and
  calendar library this activity uses). Install it first: Moodle refuses this activity without it.

## Install

Install the local plugin first (see its README), then this activity.

From the release zip, `mod_classprogress-<release>.zip` on the
[releases page](https://github.com/jvidal86/moodle-mod_classprogress/releases). Its only root folder is
`classprogress`. Either:

- In Moodle: Site administration > Plugins > Install plugins, upload the zip and follow the steps. This needs a web
  server that may write to Moodle's `mod` folder.
- Or by hand: unzip it into Moodle's `mod` folder, so that the activity is in `mod/classprogress` on Moodle 4.5 or
  `public/mod/classprogress` on Moodle 5.x.

From git, in the Moodle folder (on 5.x use `public/mod/classprogress`):

```bash
git clone https://github.com/jvidal86/moodle-mod_classprogress.git mod/classprogress
git -C mod/classprogress checkout v0.9.0
```

Then run the upgrade: visit Site administration > Notifications, or run `php admin/cli/upgrade.php --non-interactive`
from the Moodle folder (the `admin/cli` folder stays at the top on 5.x too; without `--non-interactive` the script waits
for a confirmation). Site administration > Plugins > Plugins overview then lists "Class progress" under Activity
modules at version 0.9.0, maturity Beta (the local plugin is listed as "Class progress" too, under Local plugins).

## Languages

English, Catalan and Spanish ship inside the plugin (`lang/en`, `lang/ca`, `lang/es`); there is nothing to copy by
hand. For users to choose Catalan or Spanish, the site needs that language pack installed (Site administration >
Language > Language packs); each user then sees the activity in their own language. The export page and the exported
file (sheet names, headers, statuses) follow the language of the user who exports, so two teachers of one course may
get the same figures in two languages.

## Settings and limits

Site administration > Plugins > Activity modules > Class progress:

| Setting | Default | What it does |
|---|---|---|
| Largest plan text (KB) (`maxplansize`) | 200 | A pasted or uploaded plan text larger than this is refused. |
| Most days in a plan (`maxplandays`) | 400 | A plan text with more days than this is refused. |

A value of 0, or one left empty, means the default.

Fixed limits, checked when the activity settings are saved and again on import and approval (so a school year
shortened later is caught before the plan is locked):

- Every date lies between 2000-01-01 and 2099-12-31, and the first and last class day lie within the school year.
- The activity name, the module title, an RA or block heading and a day's title are at most 255 characters long; the
  module code is at most 100.
- A weekday of the timetable, and a day of the plan, has at most 24 hours.
- One module per plan: a text with more than one `#` module heading is refused.

## Uninstall

Uninstall this activity first, then the local plugin. Moodle refuses the other order, and it also refuses the local
plugin while this activity's folder is still on disk, so delete each folder right after its plugin is uninstalled:

1. Uninstall this activity: Site administration > Plugins > Plugins overview, "Class progress" under Activity modules,
   Uninstall; or `php admin/cli/uninstall_plugins.php --plugins=mod_classprogress --run`. Then delete the
   `mod/classprogress` folder (`public/mod/classprogress` on 5.x).
2. Uninstall the local plugin the same way ("Class progress" under Local plugins, or `--plugins=local_classprogress`),
   then delete the `local/classprogress` folder (`public/local/classprogress` on 5.x). A folder left in place is
   installed again at the next upgrade.
3. Run the upgrade (Site administration > Notifications, or `php admin/cli/upgrade.php --non-interactive`). It clears
   the plugins' cached settings and hook; until then the site says it needs upgrading, and with developer debugging on
   its pages can stop with an error about the missing hook class.

Uninstalling deletes all of its data in every course, and it cannot be undone: every plan with its days, imports,
copies of holidays and evaluation periods, entries and history (and uninstalling the local plugin deletes the school
years). If the record must be kept, take a backup of each course with its plan activities first (with user data, so
that it holds the entries and their history), and keep each plan's exported file.

## Privacy

What the activity stores, in each plan activity: the plan (the module, its days, its text, the timetable and the copy
of the school year's dates, holidays and evaluation periods taken at approval), who approved it and when; the import
log (who imported, when, pasted or uploaded, the file name, a fingerprint of the text and the number of days; an
uploaded file is read and not kept); one entry per logged session (status, what was done, the reason and note for a
deviation, the corrective measure, whether content carries over, the day it was written); and the history of every
change to an entry, with who made it and, when someone was logged in as another user, the real user. Entries and their
history are free text written by teachers, so they may name students; the activity does not link them to student
accounts.

- **Where, and who can read it:** in the activity, which belongs to its course. The course's teachers and editing
  teachers, and managers, see and work in it; students and other roles neither see nor open it. Site administrators
  reach every activity too.
- **How long:** until the activity or its course is deleted. Deleting an entry keeps its history. A course reset can
  remove the entries and their history and keep the plan. A course backup always holds the plan, and the entries and
  history when it includes user data.
- **Not encrypted:** site administrators, and anyone who can read the database or its backups, can read the data.
- **A privacy export** (Site administration > Users > Privacy and policies > Data requests) gives, for each plan
  activity, what the user authored there: the plans they approved, their imports, the entries they first wrote and
  every history row they made.
- **An erasure** (a deletion request, or deleting the account, which acts at once) anonymises the user's authorship:
  their user id in approvals, imports and history rows is replaced by a deleted user. The content stays, because the
  plan and its log are the course's shared record for the inspection. The same request deletes the user's school
  years, which the local plugin keeps (see its README). A deletion request processed through Data requests also deletes
  the user's account afterwards: Moodle does that, not the plugin.

## Known limits

- An approved plan cannot be changed: its days, dates, hours, text, school year and timetable are locked; only the
  activity's name and description still change. Corrections and moving sessions (a strike, a trip) are planned for a
  later version; for now, log the planned session as not done with its reason, and the session that took place as an
  unplanned one.
- An editing teacher can still delete the activity, and its whole record goes with it (into the recycle bin when that
  is enabled). The plugin cannot forbid this; keep course backups and the exported file.
- One plan, of one module, per activity: add one activity for each module.
- Sessions can be logged and exported only once the plan is approved. A session after today can only be logged as not
  done (such as an announced strike), and an unplanned session cannot be logged ahead.
- A restored or duplicated activity has no school year (school years are each teacher's private data): an approved plan
  is complete with its own copies, while a draft asks for a school year again. Users who are not restored with the
  course are shown as a deleted user.
- Plans are imported from Markdown only, and the export file is XLSX only.
- The plugins add no JavaScript of their own: the instructions for an AI assistant on the import page are copied by
  hand, as there is no copy button.

## Development

Plan, requirements, documentation and test data live in the `local_classprogress` repository (`PLAN.md`,
`docs/spec/REQUIREMENTS.md`, `docs/DEV_ENVIRONMENT.md`, `docs/TESTING_STRATEGY.md`). The fixtures in `tests/fixtures/`
are a copy of its `test-data/`, made with its `tools/sync_fixtures.sh <this repository>/tests/fixtures`
(add `--check` to compare). Its `tools/check_traceability.py --extra-root <this repository>` counts the tests of this
repository too. Changes are listed in `CHANGES.md`.

## License

GNU GPL v3 or later.
