# 12 — Implementation plan and decisions

## Phase 1 — Core and isolation (delivered)
- Laravel monolith skeleton, auth, Super Admin bootstrap, roles/permissions.
- Separate Info Com and Guest Service models/tables/routes/policies.
- Seed exactly eight sessions (times remain configuration-required, no guessed defaults).
- Automated cross-domain separation tests B01–B08.

## Phase 2 — Info Com (delivered)
- Attendee form and phone normalization/search.
- Session configuration/validation, manual/automatic check-ins, concurrency uniqueness.
- 2026 CSV preview/commit/retry, WAT parsing, audit.
- Session/day/conference analytics, export and mobile workflow.

## Phase 3 — Guest Service capture (delivered)
- Guest entry form, explicit event/session selection, unassigned queue.
- Legacy 2025 XLSX import and independent source mapping.
- Field validation, duplicate candidate review, no cross-domain matching.

## Phase 4 — Follow-Up operations (delivered)
- Bulk assignment, reassignments, ownership-scoped officer dashboard.
- Activity logging/status history, call workflow, reports and exports.
- Officer performance and Follow-Up Admin controls.

## Phase 5 — Stabilization and local release (delivered)
- High concurrency and scoped authorization checks, responsive Chrome checks, encrypted backup/isolated restore, volume imports/recovery, attendance lock controls and audit coverage.
- Local server/worker/scheduler startup, Operations/doctor, heartbeat monitoring, private response/error handling and guarded manual import retention.
- Owner chose local internal operation. Production deployment is deferred; host/domain, maintained database, HTTPS, least privilege, mail, supervised processes and scheduled/off-device backups require a later production work package.
- See PHASE_5_COMPLETION_REPORT.md and LOCAL_OPERATIONS_RUNBOOK.md. Real session times/staff, approved retention and physical-device/network rehearsal remain owner setup.

## Definitions of done
- All required use cases work on staging with representative redacted samples.
- Acceptance test suite passes, routes/policies audited, no automatic guest creation from Info Com.
- Import preview explains invalid/ambiguous rows and never silently skips errors.
- Reports reconcile with row-level lists and don’t mix domains.
- Admin can set actual session times, activate sessions and provision officers.

## Decisions administrators must supply/configure (do not invent)
1. Exact local start/end time of each of eight sessions.
2. Whether virtual registration may ever auto-mark a session present; safer default is no.
3. Guest Service form required fields beyond name and phone; recommended also actual event date/session.
4. How much of historical 2025 XLSX should be migrated versus archived, given repeated rows/status inconsistencies.
5. Whether Data Entry users can edit each other's guest records or only their own.
6. Whether a separate read-only Report Viewer role is needed.
7. Retention periods, staff onboarding and allowed attendance correction window.

## Practical starting order for coding agent
Run migration/schema tests → policies → core forms → session engine → CSV import → Guest Service form → assignment → follow-up statuses → reports → full acceptance suite → deploy. Avoid adding speculative integrations.
