# 09 — Analytics and report definitions

## Phase 4 implemented reporting contract

The working /follow-up/reports UI and five CSV exports use FollowUpReports. Current guest/status/assignment counts describe the filtered current cohort; activity dates do not reconstruct historical state. Historical-as-of reports are not implemented.

Actor performance retains activity credit after reassignment within this same cohort/period; current workload counts active assignment rows only. Untouched means no non-NOTE_ONLY attempt by the current owner under the current assignment in the selected activity period. Officer personal views expose only their own current-assignment activity; manager histories retain all permitted actors. Consequently personal activity success can differ from a guest's current status inherited from a previous owner.

Success = distinct actual CONTACTED guests / distinct guests with actual non-NOTE_ONLY attempts in the same scope/period. A guest with multiple attempts contributes once to each distinct denominator/numerator. Counts across different officers may overlap and are labelled. Method breakdown includes all notes/attempts. Inactive officers retain manager-visible workload/history and cannot receive new assignments.

## Info Com dashboard — independent
- `registered` = count of Info Com attendee profiles satisfying date/filter criteria.
- `session_present` = distinct attendee IDs with present record for specified session.
- `session_absent` = eligible registered attendees for specified session − session_present. State denominator/cutoff prominently.
- `unique_conference_present` = count distinct Info Com attendee IDs with at least one check-in in selected conference.
- `total_check_ins` = count present Info Com attendee/session pairs.
- `session_attendance_rate` = session_present / eligible registered attendees × 100 (show n/a when denominator zero).
- Breakdown: 8 sessions, in-person/virtual preference, member, first-time, source/referral, with missing category tracked.
- Exports: attendee-session matrix, per-session present list, aggregate daily and conference reports.

No Guest Service records enter these totals.

## Follow-Up dashboard — independent
- `guest_total` = distinct guest record IDs in scope.
- `unassigned` = guest records without active assignment.
- `assigned` = guest records with current active officer assignment.
- `status_not_contacted`, `status_contacted`, `status_unreachable` = current status counts; sum equals guest_total within same report scope.
- `attempted_guests` = distinct guest IDs with at least one contact attempt in date range (exclude `NOTE_ONLY`).
- `contact_success_rate` = distinct guests contacted successfully / distinct guests with attempt, × 100; n/a if zero attempts. Label time and scope consistently.
- `officer_workload` = active assignments per officer (not merely lifetime assignment count).
- `officer_performance` = assignments in scope, distinct attempted guests, contacted, unreachable, untouched, activities by method, last activity.
- `follow_up_activities` = activity records by day, officer, method, outcome and assigned guest.
- Report filters: guest event date/session, officer, assigned/unassigned, current status, activity date range, imported/manual source.
- Exports: guest list, assignment roster, officer report, status summary, activity timeline.

Do not count one guest multiple times because of reassignment history. Report historical as-of snapshots only if point-in-time reconstruction is implemented explicitly.

## Super Admin
May display two clearly separated tiles/sections: **Info Com Attendance** and **Guest Service Follow-Up**. Never compute mixed user count or a percentage with attendee + guest records in same denominator. Charts and reports should show refresh timestamp, timezone, filters and definitions.

## Session dimensions
Attendance and follow-up reports independently filter/group by `conference_session_id` and derive day number/date/label from `conference_sessions`. Session/day guest totals refer to encounter session, not entry date. Officer workload and bulk-assignment queues honor the same guest-session filter. Conference event/year is an independent scope: future historical imports must reference historical sessions. No combined module totals are allowed.
