# Changes

## 0.9.0 (unreleased)

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
