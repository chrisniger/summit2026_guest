# 01 — Product requirements

## Objective
Deliver a single responsive Laravel web application with separate operational modules:

**Info Com module** manages conference participant registration, import, phone/name search, attendance for eight conference sessions, manual check-in and attendance analytics.

**Guest Service / Follow-Up module** manages guest capture after each conference session, assignment of multiple guest records to officers, follow-up status/activity logging and follow-up reporting. Guest classification is **manual only**: a guest exists in this domain only when Guest Service Data Entry or an authorized user explicitly creates/imports a guest record. Info Com membership, first-time attendance, present/absent status and registration never automatically create guest entries.

**Shared platform** provides authentication, Super Admin, settings, logs, backups and audit controls. Sharing a login infrastructure does not mean sharing attendee/guest records.

## Roles
- **Super Admin:** unrestricted access to both modules and administration.
- **Info Com Team:** onsite participant registration, lookup and attendance check-in; optional import privilege only with explicit grant.
- **Follow-Up Admin:** guest queue, officer assignment/reassignment, follow-up monitoring and reports; manage team accounts only with delegated permission.
- **Follow-Up Data Entry:** capture/import guest-service records, select original event/session, correct own mistakes subject to policy; cannot assign or report across officers.
- **Follow-Up Officer:** view assigned guests, update own follow-up status and activities, view personal progress only.
- Optional **Report Viewer:** read-only dashboards if enabled by Super Admin; not required for MVP.

## Core nonfunctional requirements
- Responsive and optimized for low-end smartphones; browser access, no native app required.
- Laravel monolith with Blade/Livewire/Alpine/Tailwind; MySQL or PostgreSQL; Redis/queue optional when beneficial.
- Authentication, CSRF, throttling, RBAC and backend authorization checks; HTTPS; daily backups.
- Actions must be auditable: who, when, what changed, and source.
- Date handling: store UTC timestamps and present Africa/Lagos wall-clock time.
- Files: CSV for Info Com; XLSX for legacy Guest Service plus optional guest CSV.
- Imports must support validate/preview/commit, dry-run, result file and safe retries.

## Explicit exclusion
No shared attendee↔guest foreign key; no auto-guest classification; no pulling Info Com registration, attendance or absence into follow-up; no automatic propagation of guest updates into Info Com; no payment, SMS integration, QR feature or native app in MVP.

## Terminology
- **Attendee/Registrant:** Info Com participant stored in `info_attendees`.
- **Guest:** Guest Service entry stored in `guest_records`.
- **Session:** configured Info Com attendance interval (8 planned); also usable as a human-selected Guest Service event label without implying linked attendance.
- **Present:** has an Info Com attendance record for that session.
- **Absent:** has no valid Info Com attendance record for that session; derived, not pre-created.
- **Unassigned:** guest has no active follow-up assignment.
- **Follow-up status:** exactly `NOT CONTACTED`, `CONTACTED`, `UNREACHABLE`.

## Business constraints
The event is Tuesday 27 Oct through Sunday 1 Nov 2026. Days 27–30 have one General session each, 31 Oct and 1 Nov have distinct Morning and Evening sessions. Times are administrator configured and cannot overlap. Auto-attendance on registration/import applies **only to the one session containing the original registration timestamp**, never to both sessions on the same date.

Guest Service workflow is post-session capture → unassigned queue → bulk assignment → officer activity/status → manager report. Info Com flow is separate registration/search/check-in → attendance reports.
