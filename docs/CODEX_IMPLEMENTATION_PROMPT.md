# Codex implementation prompt — SUMMIT 2026

You are a senior Laravel architect and full-stack engineer. Implement the SUMMIT 2026 Conference Registration, Attendance and Guest Follow-Up application from this package. Treat `README.md` and sections `01`–`12` as controlling functional/technical requirements. Inspect any existing repository before editing. First produce an architecture/checklist mapping requirements to existing code; then implement in testable stages and run available tests, migrations, static checks and authorization audit. Do not claim anything finished unless verified.

## Critical constraint — enforce independent data domains
Info Com registration and check-in data have nothing to do with Guest Service guest records and follow-up even when names and phones match. **Never** auto-create guest records from Info Com, never link attendee-to-guest IDs, never infer guest classification from membership or first-time answers, never sync changes, and never make Guest Service records contribute to attendance reports. Sharing authentication, infrastructure, navigation and Super Admin is allowed.

## Product requirements
Five required roles: Super Admin (full), Info Com Team (registration+attendance), Follow-Up Admin (guest queue+bulk assignment+follow-up reporting), Follow-Up Data Entry (capture guests after sessions), Follow-Up Officer (own assigned guest contact work). Implement least privilege via Laravel policies. Build responsive Blade/Livewire/Tailwind screens.

Info Com: eight sessions Oct 27–Nov 1 2026, configurable WAT start/end, Oct 31 and Nov 1 AM/PM separately. Save UTC instants, determine active session by exact timestamp, **only** auto-mark the one active session. Derived absent status. Upload and map all 18 columns of the supplied 2026 registration CSV, parse original `Created` timestamp for import auto attendance, not upload time. Safe duplicate handling and database unique attendee+session check-in key; search Nigerian phone variants.

Guest Service: separate guest form with name, phone, event date/session and optional fields; Data Entry captures guest records after each event. New guest defaults NOT CONTACTED, unassigned. Follow-Up Admin selects multiple guests and assigns/reassigns to officers. Officers can only view and update guests assigned to them, logging attempt method, outcome, timestamp and notes; statuses exactly CONTACTED / NOT CONTACTED / UNREACHABLE. Preserve all activity, status and assignment history. Support a separate XLSX import mapping old 2025 workbook headers by name, not fixed column order; preview invalid rows, duplicates and ambiguous historical timestamps/statuses.

Dashboards and reports must be distinct for Info Com and Follow-Up; Super Admin can view both without mixing denominators. Add secure export, import audit logs, corrections, error handling, pagination, concurrency safeguards, mobile UX and privacy protections.

## Engineering deliverables
- Laravel source code, migrations, seeders, policies, controllers/Livewire components, validation rules, services, queue jobs as appropriate, tests, deployment/env example and README setup.
- No production secrets and no personal source datasets committed to source control.
- Session time configuration is mandatory for auto attendance; do not invent times.
- Expose interface and tests for import preview/commit and row error diagnostics.
- Implement acceptance tests enumerated in `11_ACCEPTANCE_TESTS.md`; report pass/fail per ID.
- After each phase report files changed, migrations/tests run, blockers and what remains.

Start by reading every Markdown specification file, extract a coherent build plan, and then implement Phase 1 without breaking any pre-existing functionality.
