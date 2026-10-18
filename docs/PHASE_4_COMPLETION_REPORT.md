# Phase 4 — Follow-Up operations

Implemented locally on 18 October 2026 in D:\Apps\summit2026_plus. Phases 1–3 and both independent data domains are preserved. Phase 5 stabilization/release remains.

## Delivered workflows

### Reviewed assignment, reassignment and unassignment

Guest records is now the working assignment queue. Its session/day/status/assignment/year filters remain authoritative. Administrators select encounters, an action, an active officer (except unassignment), and a reason. A separate review screen shows the exact guest count, actual sessions, existing officers, proposed target and reason. No assignment writes happen until explicit confirmation.

The encrypted confirmation is actor bound, expires after 15 minutes, and contains ordered guest IDs, guest versions and active-assignment IDs. Commit revalidates the actor, each permitted/nonarchived guest, its version/current owner and the target officer. It locks guests in ascending ID order and saves all selected changes in one transaction. Any unavailable or changed guest rejects the entire selection. Assign only accepts unassigned guests; Reassign explicitly permits replacing current ownership. Reassigning to the same active owner is unchanged. Unassignment closes the current assignment without deleting history.

The one-active-owner database constraint remains in force. Closing an assignment preserves its original assigning actor/reason and adds closure_reason; the closing actor is recorded in audit. Guest current contact status is preserved across ownership changes. Versions increment on changed ownership, invalidating stale capture/contact/assignment forms. A successful changed selection replay returns 409 and does not append assignment history again. The UI displays up to 25 encounters per page; the service accepts at most 200 explicit IDs per confirmed operation.

Only authorized guest.assign users can use these endpoints, with normal GuestRecord visibility still enforced. Defaults permit Super Admin and Follow-Up Admin. Staff provisioning remains the existing Super Admin screen; no implicit delegated staff-management privilege was added.

### Officer work and contact activity

The officer dashboard now shows current assignments, progress and actionable guest cards with encounter sessions. Detail retains safe tap-to-call and now provides method, actual WAT attempt time, outcome, comment, and explicit outcome confirmation.

- NOTE_ONLY records a note without changing current status or counting as an actual contact attempt.
- UNREACHABLE requires confirmation that an actual attempt failed.
- CONTACTED requires confirmation of actual two-way contact. A sent message or an unanswered call is insufficient.
- Methods: call, sms, whatsapp, visit, other.
- Required comment; valid minute-precision WAT datetime between year 2000 and now; UTC storage.
- Contact requests cannot set actor, guest, previous/new status, creator or provenance.
- Guest row and current assignment are locked, visibility/contact authorization is rechecked, and the posted assignment ID must still match. Ordinary officers must be the active owner.
- A new UUID submission requires the current guest version. Actor-scoped fingerprints replay a successfully saved request once; retry after reassignment is denied. Reusing a submission against another guest/assignment conflicts.
- Activity, current status, version, status-history record and audit save together. An interrupted transaction rolls back all of them.
- Notes do not add a status transition. Non-note attempts append status history, including repeated same-status outcomes.
- Current status follows the most recent actual attempt time; ties follow recording order. Earlier dated attempts remain actual attempts in activity history but cannot replace a later outcome. The status-history reason and user feedback explain this.

Activities/status history are paginated and immutable through ordinary screens; no deletion/edit or arbitrary reset-to-NOT-CONTACTED endpoint was added.

Reassignment immediately revokes former officers' guest/detail/write/report/export access through the current-owner scope. A new officer sees the guest profile/current status and only their own activities under the current assignment. Prior officers' private comments and status-history entries are restricted. Managers see authorized complete contact/assignment histories. Data Entry retains profile capture/edit access and cannot read contact histories or post outcomes by default.

### Reporting and exports

Follow-up reports supplies current total/assigned/unassigned and three-status counts, actual attempts versus distinct attempted guests, distinct successful guests, note counts, contact success, current officer workloads, current-status breakdown, untouched assignments, actor-credited performance, last activity, activity methods, session breakdown, roster and timeline.

Filters: name/normalized phone, year, encounter session/day/date range, current officer, current status, assigned/unassigned, manual/import source, active/archived, WAT activity-date range and activity actor. Guest queue filters share the report cohort builder. Explicit actual encounter sessions, not data-entry timestamps, define event/year membership. Historical data stays outside the default 2026 cohort.

Definitions:
- Success = distinct guests with actual CONTACTED outcomes / distinct guests with non-NOTE_ONLY attempts in the same activity period and guest cohort. Empty denominator is n/a.
- Guest status and ownership are current. Activity dates filter attempts, not historical status/ownership snapshots.
- Current workload counts active assignments once. Closed assignments do not inflate it.
- Activity performance credits the recorded actor, even after reassignment, within the selected guest cohort/period. Distinct guest totals across different officers can overlap and are labelled accordingly.
- Untouched means no actual attempt by the current owner under the current assignment in the selected activity period.
- Activity-method breakdown includes notes and actual attempts.
- Inactive officers with current workload/history remain visible to managers and can be filtered; they cannot receive new assignments.

CSV downloads: guest roster, current assignment roster (including unassigned encounters), summary metrics, officer performance and activity timeline. They use the same filtered/authorized scopes and the shared formula-injection-safe CSV formatter. Exports are audited. Officers have personal reports/exports only; manager-level access uses guest.report.view plus guest.access. No cross-domain or mixed attendee/guest metric exists.

## Source and schema

- FollowUpAssignments: selection review, active officer eligibility, encrypted snapshot, ordered locks and transactional ownership changes.
- FollowUpContact and FollowUpActivityRequest: strict outcomes, WAT validation, active ownership, UUID/version retry and atomic history/status.
- FollowUpReports: shared scoped cohort, activity privacy, definitions, summary, session/method/performance queries.
- FollowUpController and FollowUpReportController: review/commit/activity and report/export endpoints.
- Existing models add actor/activity relationships and assignment closure reason. ReportPolicy and a follow-up.reports Gate permit authorized managers and personal officers.
- GuestController uses shared cohort filters and scoped timelines; DashboardController adds progress/current assignment cards.
- Views: guest/assignment-confirm, guest/contact-panel, guest/reports; existing guest index/detail, dashboard and navigation updated. Existing branding/light theme is preserved.
- New migration: 2026_10_18_193725_extend_follow_up_operations.php. follow_up_activities adds nullable unique source_fingerprint and (guest_record_id, attempted_at_utc) index; follow_up_assignments adds nullable closure_reason. No participant linkage or new dependency.

Actual endpoints:
- POST /follow-up/assignments/preview
- POST /follow-up/assignments/commit (encrypted token plus confirm=1)
- POST /follow-up/guests/{guest}/activities
- GET /follow-up/reports
- GET /follow-up/reports/export?type=guests|assignments|status|officers|activities

Existing /follow-up, /my-guests, guest list/detail and Guest Service capture/import routes remain.

## Verified evidence

- PHPUnit: 154 tests passed, 936 assertions on the dedicated guarded summit_2026_test database, including 29 new Phase 4 cases.
- Coverage includes a 20-guest reviewed batch; unconfirmed/expired/altered/cross-account tokens; atomic stale selection rejection; role/active assignee changes; preserved/closed ownership history; version and UUID replay; former/new officer privacy; outcome confirmations; chronological status; WAT boundaries; interrupted transaction rollback; stale deactivated actor; filters; empty rates; inactive workload; formula-safe exports; current-workload/actor-credit reconciliation; independent Info Com tables.
- All prior browser regressions were rerun successfully: five Phase 3 groups and eleven Phase 1–2 groups. Together with the seven new groups, 23 real browser/concurrency groups passed.
- Seven Phase 4 real Chrome/concurrency groups passed: two simultaneous contacts create one activity; competing bulk confirmations produce one owner and a stale conflict; contact racing reassignment produces an atomic valid outcome; desktop/mobile exact bulk review; officer note/failed/success activity and history; scoped reporting/export and reassignment privacy; unassignment preserving status/history.
- Screens inspected at 1440px desktop and 360px mobile. No horizontal page overflow or JavaScript errors. Large tables scroll within their containers.
- Browser scripts use random test-only accounts and clean fixtures. Phase 4 evidence: storage/app/follow-up-browser-checks/results.json and screenshots (private/ignored).
- Pint --dirty --format agent, Composer strict validation, Vite production build, Blade view compilation, route inspection and migration status passed.
- Additive migration applied to summit_2026. Before/after comparisons preserved all existing table counts, administrator email/password hash/active state and APP_KEY. Application state: one user, eight sessions, zero attendees, registrations, attendance, guests, assignments, activities and status-history records.
- Local login returns HTTP 200 at http://127.0.0.1:8000. No production deployment, commit or push was performed.

Run tests sequentially with browser scripts because they share the disposable database. Phase 4 runner: node tests/Browser/follow-up-smoke.mjs; PLAYWRIGHT_MODULE may reference an existing external Playwright module. No application package or lock file change is needed. The runner starts a temporary test server on 8003 and terminates it after checks.

## Remaining release work

Phase 5: physical Android/iOS/Safari and accessibility acceptance; intermittent network/recovery; sustained representative-volume multioperator checks; approved real/redacted 2025 workbook acceptance; production mail/HTTPS/secure cookies; least-privilege supported database/server; supervised workers and monitoring; encrypted backups and tested restoration; retention/cleanup; actual 2026 session clock times; real staff onboarding and approved attendance correction policies.

Current reports deliberately describe current ownership/status plus filtered activity, not historical as-of snapshots. Cross-page selection, delegated staff administration, destructive history editing, automatic legacy contact/assignment conversion and arbitrary status-reset workflows are outside this delivered contract. No real guests or officers were invented in the application database.
