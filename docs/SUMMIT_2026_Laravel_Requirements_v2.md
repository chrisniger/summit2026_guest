# SUMMIT 2026 — Integrated Operations Platform
## Laravel Functional Requirements and Technical Specification · Version 2.0

**Event:** 27 October–1 November 2026  
**Local timezone:** Africa/Lagos (WAT / UTC+01:00)  
**Status:** Revised scope approved in principle; session clock times and certain operational policies are configuration items.  
**Inputs:** `registrations-2026-10-18.csv` (Info Com registration import); `SUMMIT 2025 GUEST DATA.xlsx` (historical Guest Service / Follow-Up import reference).  
**Technology:** Laravel monolith, Blade + Livewire + Alpine.js, Tailwind CSS, MySQL or PostgreSQL, queue-backed imports, Laravel Policies/Gates, audit logging.

## 1. Executive summary and hard boundaries

Build ONE authenticated application supporting TWO **independent datasets and workflows**:

1. **Info Com — Registration & Attendance.** Records all conference registrants; checks attendance by session; handles onsite registration and historical registration CSV imports; produces attendance reports.
2. **Guest Service / Follow-Up — Guest Capture & Follow-Up.** Guest Service staff (the **Follow-Up Data Entry** role) independently capture guest records after each conference session/day using a different form and data fields. The **Follow-Up Admin** assigns one or more of these *guest records* in bulk to Follow-Up Officers. Officers contact assigned guests, document attempts, and update follow-up statuses. Follow-Up Admin gets dedicated operational reports.

**Mandatory separation:** The appearance of a person in Info Com and Guest Service records is incidental. A registrant named Mike may also be entered by Guest Service, and BOTH records must coexist independently. Neither data flow creates, classifies, modifies, merges, assigns, or marks any record in the other flow. **NO automatic guest eligibility, NO membership/first-time inference, NO phone-based cross-dataset upsert, NO automatic cross-dataset linking or syncing.** Do not use a shared `attendees`/`guest_profiles` identity model for the two teams. Super Admin may view both datasets through separate navigation and aggregate widgets, but totals must never be silently combined or deduplicated across the two datasets. A future explicit linking feature would require a separate approved change request; it is out of scope now.

### 1.1 Confirmed user roles

- **Super Admin:** Full system access to both independent operational units, their reports and settings, user and role management, corrections and imports. Must be auditable.
- **Info Com Team:** Search/register conference attendees, mark attendance for the active session, access scoped Info Com reports and activity. No guest follow-up records or assignment screens.
- **Follow-Up Admin:** View/manage all Guest Service guest records, bulk assign/reassign to officers, manage team workload and follow-up reporting, correct guest statuses with audit, access permitted Guest Service import capabilities. Cannot modify Info Com attendance or registration records.
- **Follow-Up Data Entry:** Enter, edit (within controlled permissions) and import Guest Service guest data, select day/session for historical entry, view their own entry queue and duplicate alerts. Cannot make officer assignments or manipulate Info Com data. Entry after an event is expected.
- **Follow-Up Officer:** View ONLY own assigned guest records; log contact attempts/comments; change follow-up status for assigned guests; see personal activity summary; no bulk export, other officers' guests or Info Com data.
- **Optional Report Viewer (legacy role):** Independent read-only reports with explicitly selected Info Com and/or Guest Service scope; do not implicitly grant cross-team access.

Accounts are separate from people in either dataset. The term "guest" always means a Guest Service record, not a subset or classification of Info Com attendees.

## 2. Conference sessions (Info Com)

Eight configurable sessions:

| Date (2026) | Session |
|---|---|
| Tue 27 Oct | General |
| Wed 28 Oct | General |
| Thu 29 Oct | General |
| Fri 30 Oct | General |
| Sat 31 Oct | Morning |
| Sat 31 Oct | Evening |
| Sun 1 Nov | Morning |
| Sun 1 Nov | Evening |

For each, Super Admin sets local start/end time, optional attendance cutoff/grace, and whether session is open/closed. Persist UTC instants, render in Africa/Lagos. Reject overlapping active time windows. Never assume unknown actual clock times. Only one session can be active at any instant. Validate active session again server-side when writing attendance.

**Confirmed auto-attendance behavior** (Info Com ONLY): When an Info Com registration is created during an active session, check in its registrant to that session, once. For imported Info Com CSV rows, use the original `Created` instant and match the configured session at that time, NOT upload time. On 31 Oct and 1 Nov, only Morning **or** Evening is marked, never both on one registration. Outside any active time window, register but do not mark present. Manual check-in is allowed only for current active session except audited authorized corrections.

Attendance absent status is derived: an eligible registration with no valid attendance record for a session is absent; do not pre-create absent rows. Info Com attendance has no implications for guest capture or follow-up.

## 3. Info Com registration form, search, check-in and import

### 3.1 Minimum onsite registration form

- First Name **required**
- Last Name **required**
- Phone Number **required**, retain raw and normalized searchable version
- Are you a member of The Summit Bible Church? Yes / No / Unanswered **optional**
- First time attending the conference? Yes / No / Unanswered **optional**
- How did you hear about the conference? **optional**, categories in existing CSV and configurable

Existing optional fields from CSV must remain importable/preservable even if omitted from onsite form. Explicit empty choices persist as `null`, never converted to false.

### 3.2 Search and check-in UX

Mobile-first: officer signs in > sees current session > types phone/name/other indexed field > receives matching Info Com registration cards > confirms identity > taps Mark Present > sees success or Already Present > input autofocus reset. Phone normalization handles common Nigerian +234/234/0 formats and does not merge different people who share a phone. Search is debounced/paginated; do not expose Guest Service records in results. Session ID selected on server, not trusted from client. `UNIQUE(registrant_id, conference_session_id)` and transactional idempotence protect simultaneous officers and retries. Audit admin reversal/correction.

### 3.3 Import schema and controls

`registrations-2026-10-18.csv`: baseline identified as **1,749 registration rows**, **18 columns**. Columns:

`Created`, `First Name`, `Surname`, `Phone`, `Email`, `Member`, `Summit Centre`, `Commits to Invite`, `Invite Count`, `Location`, `Abuja Area`, `First Time`, `Bus Shuttle`, `Bus Route`, `Attendance`, `Heard About`, `Feedback`, `Consent`.

Import wizard: select **Info Com Registrations** template > upload CSV > headers/field map > preview count and invalid rows > reconcile repeat records within Info Com dataset only > confirm > queue import > show accepted/updated/skipped/errors and any auto-check-ins. Original source event timestamp retained, with UTC-to-WAT conversion used solely for session matching. `Attendance=In Person/Virtual` in imported data is **attendance preference**, not actual marked presence; distinguish modality from presence. Re-uploading identical rows is idempotent via source event fingerprints. If source conflict needs manual review, never silently overwrite trusted data. The 2025 Guest workbook is NOT an Info Com import.

## 4. Guest Service / Follow-Up: independent workflow

### 4.1 Operational sequence (the intended Mike example)

1. Mike arrives. **Info Com** optionally creates a registrant record for Mike and checks him into the active conference session. Only Info Com tables are touched.
2. The Guest Service team independently identifies Mike as a guest and collects the Guest Service form. This does NOT read, require, auto-populate, or update Info Com registration data.
3. After the day's conference session, a **Follow-Up Data Entry** user enters Mike's **separate Guest Service record**, selecting which event date/session the paper or service data relates to. The database creation timestamp is the actual later entry time; the captured guest encounter date/session is a distinct field.
4. Newly entered Guest Service records default to `NOT CONTACTED` and `UNASSIGNED`; they appear in the Follow-Up Admin's unassigned guest queue.
5. The **Follow-Up Admin** selects many guests, chooses one Follow-Up Officer, and assigns in one transactional bulk action. The admin may repeat this across officers and may reassign individual or bulk guest records. Track history.
6. Assigned **Follow-Up Officer** sees only own guests, records attempts and a note/comment, and sets current status `CONTACTED`, `NOT CONTACTED`, or `UNREACHABLE` as permitted.
7. Follow-Up Admin views guest counts, assignments, contacted/unreachable/not-contacted, officers' activity and reports. Info Com registration/check-in data is unaffected throughout.

### 4.2 Guest Service form (separate schema)

Use fields reflected in `SUMMIT 2025 GUEST DATA.xlsx`, with a user-friendly redesigned form:

- Guest Full Name **required** (can internally store parsed names optionally; preserve full name)
- Guest Phone Number **required**, with raw and normalized variants; allow shared phone numbers, warn on likely repeat Guest Service record
- Event / Conference Day **required**, selected from conference dates
- Session (General/Morning/Evening) **required where date has multiple sessions**; do not guess from data-entry timestamp
- Gender optional
- Home Address optional
- How Did You Hear About the Conference? optional, configurable text/choice
- Initial Comment optional
- Original capture date/time optional (different from database creation time)
- Follow-Up Officer **not entered as part of ordinary Data Entry capture**; assignment is controlled by Follow-Up Admin
- Follow-Up Status assigned by system as `NOT CONTACTED`; Data Entry does not set it to contacted based on import assumptions

The source workbook refers to `Timestamp/Date`, `Events`, `Name`, `Phone`, `Follow Up Officer`, `Gender`, `Home Address`, `Follow Up Status`, `Comment`, and `How Did You Hear About the Conference`. Worksheets can vary. Historical import must map source officer/status where valid into **historical** guest assignment/activity with provenance, not automatically create new staff accounts or silently accept unknown officers. 2025 historical data must remain associated with the 2025 event, not counted in 2026 dashboards.

**Guest identity scope:** Possible duplicate checks operate only among Guest Service guest records for the same conference/event. Flag close name+phone+encounter matches, let authorized users resolve; do not auto-merge on phone alone. If same guest is recorded on multiple days, preserve encounter records while supporting an administrative decision whether they belong to one guest profile; make all encounter dates reportable. NO lookup or match against Info Com registrants.

### 4.3 Follow-up statuses (exact permitted set)

- `NOT CONTACTED` — **system default** on every new guest; may have no attempt yet, or an attempt recorded with no successful contact. Absence of attempts must be measured separately.
- `UNREACHABLE` — one or more failed attempts; guest could not be reached. Require a logged attempt/reason when transitioning to this status.
- `CONTACTED` — actual two-way contact established; do not treat outbound unanswered calls or SMS sent as successful contact. Require logged successful contact activity.

Each attempt is a separate, immutable/logged activity (`attempted_at`, `method`: call/SMS/WhatsApp/other, `outcome`: successful/no_answer/invalid_number/other, comment, officer, timestamp). Current status reflects the latest authorized state and status changes remain auditable. `NOT CONTACTED` is NOT synonymous with “no attempts.” Officer can edit only own assigned guests, admin can correct with reason. Initial system creation of Guest Service records is not physical attendance and must never increment Info Com counts.

### 4.4 Assignment engine

- Unassigned guest queue with filters for 2026 date, session, data-entry batch, guest details and status.
- Checkboxes/select page/select filtered results with explicit confirmation of number of records; batch size safety limit and background jobs for very large selections.
- Choose one active officer and assign selected guests. A guest encounter may have at most one **active primary assignment** at a time in v1; preserve prior assignment history.
- Support reassignment, unassignment and reason/history, with atomic updates and concurrency checks (no double-active assignment race).
- Display officer workload (total active assigned, uncontacted, contacted, unreachable, recent activity), including configurable capacity guidance but no forced fair-share automation.
- Importing a guest does not auto-assign it merely because source contains a name; explicitly confirmed historical assignments only, under a documented import mode.
- Officer data access is revoked on reassignment (historical activity remains visible to admins).

## 5. Role permissions (server-enforced)

Legend: **F** = full, **E** = operational edit, **V** = view, **O** = own assigned records, **–** = denied; optional Report Viewer is scoped explicitly.

| Capability | Super Admin | Info Com | Follow-Up Admin | Follow-Up Data Entry | Follow-Up Officer |
|---|---|---|---|---|---|
| Info Com registrants/search | F | E | – | – | – |
| Info Com onsite registration | F | E | – | – | – |
| Info Com current session check-in | F | E | – | – | – |
| Info Com attendance corrections | F | – | – | – | – |
| Info Com CSV upload | F | Optional grant | – | – | – |
| Info Com attendance reporting | F | Limited | – | – | – |
| Guest Service entry / edit | F | – | F | E | – |
| Guest Service import | F | – | Optional grant | Optional grant | – |
| View all Guest Service records | F | – | F | E | – |
| Guest bulk assignment/reassignment | F | – | F | – | – |
| Update own assigned follow-up status | F | – | F | – | O |
| Log follow-up attempts/comments | F | – | F | – | O |
| Follow-Up Admin reports/exports | F | – | F | – | – |
| Officer own performance summary | F | – | F | – | O |
| Manage users/global roles/settings | F | – | Team user requests only* | – | – |
| View global audit log | F | – | Restricted guest logs | – | – |

* Default: only Super Admin creates/activates accounts and assigns roles. Follow-Up Admin can manage **guest assignments** and optionally request team access; delegated user-account management requires explicit permission switch, never assumed.

Use policies, middleware and query scoping, not merely hiding sidebar items. Logins are per staff member with hashed passwords and account revocation. All private exports restricted and logged.

## 6. UI / navigation

### Super Admin

Cross-module overview (two **separately labeled** panels, never joined user totals); Info Com registrations; attendance; sessions; Info Com imports; Guest Service guest records; guest data import; bulk assignments; follow-up activity; Info Com reports; Follow-Up reports; staff and roles; audit logs; settings.

### Info Com Team (phone-first)

Current Session / Check-In (landing screen), Register Attendee, Search Registrants, Today's Check-ins, limited attendance summary. No guest menu items or guest search results.

### Follow-Up Data Entry (phone/tablet/desktop)

Enter Guest, Guest Records, Entry Batches, My Entries, Duplicate Review; choose event day/session for records entered later. No attendance present/absent controls; no assignment controls.

### Follow-Up Admin

Overview, All Guests, Unassigned Guests, Bulk Assign, Officers & Workload, Assignments History, Follow-Up Activity, Follow-Up Reports, permitted Guest Import.

### Follow-Up Officer (phone-first)

My Assigned Guests; guest profile and tap-to-call/contact actions; Log Contact Attempt; Update Follow-Up Status; My Progress. No other officers' records.

All interfaces: fast search, explicit empty/error/saving states, role-aware sidebar, accessible controls, HTTPS, low-bandwidth-friendly paging, search debounce, and explicit current event/year.

## 7. Reporting definitions (datasets cannot be mixed)

### 7.1 Info Com attendance dashboard

- Total Info Com registrations (distinct confirmed registrants, not CSV rows); selected session present; eligible absent; session attendance rate; unique attendees per day; unique attendees at least once during 2026; total session check-ins; daily morning/evening separately on Oct 31/Nov 1; breakdown by first-time, membership, preference, source.
- `Present(session)` = count distinct registrant IDs with valid unvoided attendance records in that session.
- `Absent(session)` = registrants eligible as of end of that session without valid presence for that session. Clearly state denominator. No persisted absent row.
- `Unique conference attendance` counts each registrant once, even with many check-ins; `check-ins` counts one per session.
- CSV data fields indicating virtual/in-person intent do not by themselves prove physical attendance. Display modality clearly.
- Export Info Com attendance matrix (eight columns) and date/session summaries with permitted PII controls.

### 7.2 Follow-Up Admin dashboard

- 2026 guest entries by encounter date/session (not automatically by data entry date), total guest encounters, unique guest profiles if explicitly reconciled **within Guest Service**, unassigned count, active assigned count, number and percentage contacted, not contacted, unreachable, number attempted, contact success rate, overdue/unworked assignments, officer workload.
- `Assigned` = guest encounters with current active primary assignment.
- `Contacted` = current status `CONTACTED`; `Unreachable` = current status `UNREACHABLE`; `Not Contacted` = current status `NOT CONTACTED`, all including unassigned where applicable.
- `Attempted` = encounters with >=1 logged attempt; report the number of attempts separately.
- `Contact success rate` = distinct guest encounters successfully contacted / distinct guest encounters with >=1 contact attempt × 100 (N/A when denominator = 0).
- Officer performance includes assigned, attempted, contacted, unreachable, not contacted, recent activity, number of calls/messages, last action time, and reassignments (avoid double-counting historical assignees in current workload).
- Reports: Guest Summary; By Date/Session; Unassigned Queue; Status Distribution; Officer Performance; Officer Detail; Activity Timeline; Historical Import Quality; exports to CSV/XLSX with role checks.
- Filters: year/conference, encounter date/session, entry timestamp, officer, current status, assigned/unassigned, entry source.

**No combined participant total or cross-module contact/attendance conversion rate**, because independent datasets have no validated shared identity. The Super Admin landing can place two cards side-by-side labeled Info Com and Guest Service without mathematical combination.

## 8. Relational data model

**Use DISTINCT roots for both units**. Naming examples follow.

### Shared infrastructure

- `users`: id, name, login/email, password_hash, active, timestamps.
- `roles`, `permissions`, `model_has_roles`, etc. (Spatie Permission or equivalent server-enforced policy setup).
- `conference_events`: id, name, year, starts_on, ends_on, timezone; support historical 2025 vs current 2026.
- `conference_sessions`: id, event_id, date_local, label, type, starts_at_utc, ends_at_utc, status, timestamps. Relevant to Info Com active attendance AND may be selected as **event descriptor** for Guest Service, but selection does not imply a dataset link.
- `import_batches`: id, event_id, template (`infocom_registrations_csv` or `guest_service_xlsx`), source_file_hash, imported_by, status, counters, timestamp.
- `import_rows`: id, batch_id, sheet_name nullable, row_number, fingerprint, raw_payload, validation_errors, disposition, destination_type/id nullable.
- `audit_logs`: entity/module/action/actor/timestamp/old/new values/reason/metadata (redact secrets).

### Info Com ONLY

- `infocom_registrants`: id, first_name, last_name, phone_raw, phone_normalized, nullable member/first_time/heard_about, optional CSV fields, original metadata, created_at, updated_at.
- `infocom_registration_events`: id, registrant_id, occurred_at_utc, source, import_row_id nullable, entered_by_user_id nullable, fingerprint, raw_payload.
- `infocom_attendance_records`: id, registrant_id, session_id, occurred_at_utc, recorded_at_utc, source (`manual`,`registration_auto`,`csv_auto`,`admin_adjustment`), actor_user_id nullable, registration_event_id nullable, voided_at nullable, correction metadata; database unique `(registrant_id, session_id)` or equivalent active-row-safe invariant.

### Guest Service / Follow-Up ONLY

- `guest_service_guests`: id, event_id, full_name, phone_raw, phone_normalized, gender nullable, home_address nullable, heard_about nullable, source_metadata, created_by_user_id, timestamps. NOT linked with `infocom_registrants`.
- `guest_service_encounters`: id, guest_id, event_id, session_id nullable (required where multiple sessions that day), event_date_local, captured_at_utc nullable, initial_comment nullable, entered_by_user_id, source/import_row_id nullable, timestamps. Allows one independently captured Guest Service person to have one or more days/sessions; can initially create one guest+encounter per form with admin-controlled within-Guest-Service reconciliation.
- `follow_up_assignments`: id, encounter_id, officer_user_id, assigned_by_user_id, assigned_at_utc, ended_at_utc nullable, end_reason nullable, notes nullable; guarantee one active primary assignment per encounter via locked transaction + suitable DB constraint/strategy.
- `follow_up_activities`: id, encounter_id, officer_user_id, assignment_id, attempted_at_utc, method, outcome, comment, created_at. Retain audit/history even after reassignment.
- `follow_up_statuses`: encounter_id (unique), status enum `NOT_CONTACTED`/`CONTACTED`/`UNREACHABLE`, updated_by_user_id nullable, status_at_utc, timestamps. Initialize NOT CONTACTED on creation; status transitions audited in `follow_up_status_history` or immutable audit events.
- `follow_up_status_history`: id, encounter_id, old_status, new_status, actor_user_id, reason, related_activity_id nullable, changed_at_utc.

Avoid unrestricted cascading deletion of guests/encounters with audit or contact activity; prefer soft-deletion with permission and retention policy. Index Info Com name/phone/session and separately Guest Service name/phone/event/session/status/assignment for speed. Cross-domain joins are not allowed in application services or reports.

## 9. Guest Service historical XLSX import

Supported separately from Info Com CSV, via **Guest Service Historical Workbook** import type. The provided `SUMMIT 2025 GUEST DATA.xlsx` contains daily worksheets with sometimes varying formats/headers. Offer sheet selection, header matching/mapping, per-sheet preview, broken formula (`#NAME?`) rejection/quarantine, blank row filtering, date and event mapping, duplicate review within Guest Service, validation of `Follow Up Officer` names against staff roster, and legacy status normalization.

- Preserve raw status/comment/officer in import provenance; **do not infer CONTACTED from "SMS sent" or "not picking"**. Prefer explicit verified status, and list conflicts for admin review.
- Historical source dates/sessions populate **2025 event**; data entry/import timestamp is current but does not move legacy encounters into 2026 metrics.
- Do not generate Info Com registrants, attendance, or guest↔Info Com matches during import.
- Repeated import of same sheet/row/source fingerprint idempotent; distinguish genuine distinct dates/encounters from repeated guest rows.
- Legacy assignments/statuses may be retained as unverified historical metadata or imported as canonical assignments ONLY after an explicit admin preview/confirmation and valid mapped officer account; document mode and audit all changes.

## 10. Security, integrity, privacy and performance

- Laravel authentication with secure cookies, CSRF, password hashing, rate limiting; per-role policies and per-record officer ownership enforcement.
- HTTPS, backups and tested recovery, queue workers for imports/exports, scheduler for cleanup/report jobs; versioned migrations and seeders for roles/event/sessions/statuses.
- Prevent IDOR: officer must not request another officer's guest by changing a URL; Info Com staff must not access guest endpoints even when guessing IDs.
- Search across modules never happens: Info Com search => Info Com only, Guest Service search => Guest Service only.
- PII displayed minimally, logged safely, audited where sensitive; apply organization-approved retention/consent policy.
- Atomic writes, optimistic locks or row locks for assignment and duplicate check-in races. Activity/status updates must be coherent transactionally.
- Export CSV injection mitigations, upload MIME/extension/content validation, 2025 workbook formula errors handled as data quality errors, import row limits and background processing.
- Report counts derived from a single documented query definition and tested against exports; no silent mixing of event years.

## 11. Routes (illustrative)

| Area | Routes / navigation |
|---|---|
| Super Admin | `/admin`, `/admin/users`, `/admin/events`, `/admin/sessions`, `/admin/imports`, `/admin/audit-logs`, `/admin/settings` |
| Info Com | `/infocom/check-in`, `/infocom/registrants`, `/infocom/register`, `/infocom/attendance`, `/infocom/reports`, `/infocom/imports` |
| Follow-Up Data Entry | `/guest-service/new`, `/guest-service/entries`, `/guest-service/imports` |
| Follow-Up Admin | `/follow-up/dashboard`, `/follow-up/guests`, `/follow-up/unassigned`, `/follow-up/assignments`, `/follow-up/officers`, `/follow-up/reports` |
| Follow-Up Officer | `/follow-up/my-guests`, `/follow-up/my-guests/{encounter}`, `/follow-up/my-activity` |

Common role-appropriate dashboard after login, with Super Admin switching between separate Info Com and Follow-Up modules. Avoid exposing data through shared global search.

## 12. Acceptance criteria — must pass

### Dataset separation

1. Mike registered by Info Com exists ONLY in Info Com dataset and does not create a Guest Service record or assignment.
2. Mike entered later by Guest Service exists ONLY in Guest Service dataset and does not create/change an Info Com registrant or attendance record.
3. Matching name/phone across two datasets never triggers automatic linking, merging or update.
4. Guest Service registration doesn't mark Info Com present/absent; Info Com registration/check-in doesn't change any follow-up status or workload.
5. Super Admin sees both in separately labeled modules/reports, with no cross-dataset unique-person number.

### Info Com attendance

6. Eight event sessions seed correctly with configurable non-overlapping times; no invented times.
7. On 31 Oct and 1 Nov, an Info Com registration checks in ONLY to the active Morning or Evening window, never both.
8. Registration outside any active session creates no automatic attendance record.
9. Import uses original `Created` timestamp, not file upload timestamp, and correct WAT conversion.
10. Repeated source import/check-in or simultaneous officers create at most one valid presence per registrant/session.
11. Shared phone does not merge different registrants; search finds expected name and normalized phone variations.
12. Absent counts and conference unique attendance distinguish registrants, daily attendance and total check-ins.
13. Info Com staff cannot read Guest Service lists, details, reports or API responses.

### Guest Service data entry

14. Follow-Up Data Entry can enter guest on Nov 2 relating to Oct 31 Evening by explicitly choosing Oct 31 Evening; encounter appears in Oct 31 Evening reports, NOT Nov 2.
15. New Guest Service encounter initializes `NOT CONTACTED` and `UNASSIGNED`, independent of Info Com records.
16. Guest Service can preserve full name, phone, gender, address, heard-about source, encounter date/session and comment even though Info Com form differs.
17. Same guest phone number on two separate Guest Service entries raises an in-module duplicate warning but does not auto-merge records.
18. Follow-Up Data Entry cannot assign or change follow-up status.

### Assignment and follow-up

19. Follow-Up Admin bulk assigns many unassigned encounters to one selected active officer, with single atomic transaction/audit.
20. Reassigning revokes previous officer access while retaining original activities/assignment history and avoiding two active owners.
21. Officer can see/update own assigned encounters ONLY, and cannot access others by manipulated IDs or export.
22. Status starts NOT CONTACTED; UNREACHABLE requires a failed attempt; CONTACTED requires a successful two-way contact activity.
23. Logging multiple attempts retains full history; never equate SMS sent/unanswered with CONTACTED.
24. Super Admin can perform all actions and audit corrections; Follow-Up Admin can access full Follow-Up reports but no Info Com editing.
25. Guest reporting calculations (assigned, unassigned, status, attempts, rate, per officer) agree with CSV/XLSX exports.

### Imports/security

26. 2025 Guest XLSX imports through distinct Guest Service template and does not populate 2026 totals or Info Com tables.
27. Blank rows, varied sheet headers and spreadsheet formula errors are previewed/quarantined; reimport is idempotent.
28. Unauthorized module cross-access, guessed guest IDs, unapproved import routes and export PII access are denied by server authorization.
29. Audit logs capture source, actor, before/after or event data, and timestamps for assignments, statuses, corrections and imports.
30. Standard check-in and follow-up pages are usable from Android browsers on modest bandwidth.

## 13. Phased implementation

**Phase 1 — Laravel Foundation:** authentication, roles/permissions, conferences and sessions, layout/navigation, audit infrastructure, independent Info Com/Guest Service domain migrations.  
**Phase 2 — Info Com:** 2026 CSV importer, registration, phone/name search, session check-in, auto-attendance, attendance dashboard/export.  
**Phase 3 — Guest Service Data Capture:** guest form with historical event/session selection, guest-only duplicate detection, guest entry queue and 2025 XLSX template/preview.  
**Phase 4 — Follow-Up Operations:** Admin bulk assignment/reassignment, officer queue, attempt activity, exact three-status state machine, history, access controls.  
**Phase 5 — Reporting and Launch:** separate dashboards/exports, automated tests, security/PII review, production queues/backups, user acceptance and device tests.

## 14. Administrator-configured go-live decisions (not invented)

1. Exact 2026 clock start/end for each of the eight sessions.
2. Whether Info Com virtual-preference imported registration records qualify for an automatic *virtual* present event (physical attendance remains separate). Safest default: virtual preference alone does NOT prove onsite presence.
3. Whether Info Com role may upload CSV or only Super Admin.
4. Whether Follow-Up Data Entry may import historical guest sheets or imports require Follow-Up Admin/Super Admin approval.
5. How to map unknown legacy officer names and contradictory historic status/comment values (default: quarantine for review).
6. Whether guest entry should represent each day/session encounter separately when the same guest attends multiple days (recommended yes, with in-module optional guest profile reconciliation only).

## 15. Mandatory direction for implementing AI/developers

Treat separation as an architectural invariant, not a user-interface preference. Build separate Eloquent models/services/repositories, independent validation rules and separate authorization scopes for `infocom_*` versus `guest_service_*` / `follow_up_*`. A matching phone or name is never a cross-module foreign key or trigger. Add tests explicitly proving zero unintended writes across modules. Keep all historical guest worksheet data in Guest Service domain and do not repurpose it to infer attendance. Avoid feature creep. Implement and test working features in the phase order above.
