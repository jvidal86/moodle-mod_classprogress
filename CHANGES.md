# Changes

## 0.10.0 (unreleased)

The log page and the entry form, reworked from the teacher's feedback on 0.9.0. Nothing stored or exported
changes. Needs `local_classprogress` 0.9.1, whose Markdown converter no longer hangs on the private-use characters
U+E000 and U+E001 (review R6).

- The log page starts with the session to log: today's, or else the next one, with its plan and a button to log it
  (or to log a cancellation, for a later session).
- Pending sessions come next, then the next five upcoming sessions and the latest five logged ones, newest first; the
  rest of each list is folded. The history of deleted entries is linked at the end.
- Every session row opens to show its block, hours and planned content; a session without a title shows the start of
  its plan instead, and a logged one the start of what was done.
- On a phone the log page no longer scrolls sideways: the block, status and written-on columns fold into the row.
- The entry form: the status is chosen with three radio buttons, and is fixed to "not done" for a session after
  today; the reason is labelled as required; a second button saves and opens the next pending session; the plan is
  shown once, and the written-on date, history and delete links follow the buttons.
- The history of an entry names its session and ends with a button back to the session log.
- Without the capability to log sessions, the log page shows no button or link to the entry form.

## 0.9.0 (2026-10-21)

First release, beta, for Moodle 4.5 to 5.3. Needs `local_classprogress` 0.9.0.

- The Class progress plan activity, seen only by the course's teachers and shared by all of them.
- Plan import from Markdown (pasted or uploaded), with a preview of its errors and warnings, and approval, which
  locks the plan and keeps its own copy of the school year's dates, holidays and evaluation periods.
- Daily log: each session done, partly done or not done with a reason, unplanned sessions, entries written late
  flagged, and the history of every change. Imports, approvals and entry changes are also events in the site logs.
- Export: summary tables on the page (overall, per RA or block, per evaluation period) and an XLSX file with one row
  per session.
- Backup and restore (entries and history with user data), and a course reset that keeps the plan.
- Privacy provider: export of what a teacher authored; erasure anonymises their authorship and keeps the content.
- English, Catalan and Spanish.
