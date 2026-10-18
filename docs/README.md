# SUMMIT 2026 — Complete Laravel Application Documentation

**Documentation version:** 3.0 • **Prepared:** 18 October 2026 • **Conference:** 27 October–1 November 2026 • **Timezone:** Africa/Lagos.

## Non-negotiable operational boundary

The **Info Com** registration and session attendance domain and **Guest Service / Follow-Up** domain are independent. Records never cross-create, cross-update, synchronize, merge, or automatically classify each other. Mike may exist in both systems independently. Both modules use common authentication and a Super Admin, not common participant tables. Do not introduce foreign keys or automatic matching between these two domains.

## Quick start for developers

1. Read `01_PRODUCT_REQUIREMENTS.md` and `02_WORKFLOWS.md`.
2. Implement the authorization rules in `03_ROLES_PERMISSIONS.md` and schema in `04_DATABASE_DESIGN.md`.
3. Build the session engine in `05_ATTENDANCE_RULES.md`; configure actual session clock times before launch.
4. Implement the two **separate** import pipelines in `06_IMPORT_SPECIFICATIONS.md`.
5. Follow `07_UI_SCREEN_SPECIFICATIONS.md`, `08_API_CONTRACTS.md`, `09_ANALYTICS_REPORTS.md`.
6. Meet `10_SECURITY_OPERATIONS.md` and pass `11_ACCEPTANCE_TESTS.md`.
7. Execute `12_DEVELOPMENT_PLAN.md` and paste `CODEX_IMPLEMENTATION_PROMPT.md` into your coding agent.

## Contents

- `01_PRODUCT_REQUIREMENTS.md` — scope, roles, functional requirements, definitions.
- `02_WORKFLOWS.md` — both independent business processes and Mermaid diagrams.
- `03_ROLES_PERMISSIONS.md` — explicit permissions and server enforcement.
- `04_DATABASE_DESIGN.md` — table/column design and constraints.
- `05_ATTENDANCE_RULES.md` — eight sessions, UTC/WAT normalization, auto check-in rules.
- `06_IMPORT_SPECIFICATIONS.md` — mapping of actual CSV/XLSX source files and dry-run behavior.
- `07_UI_SCREEN_SPECIFICATIONS.md` — responsive screens and UX states.
- `08_API_CONTRACTS.md` — route contracts and validation.
- `09_ANALYTICS_REPORTS.md` — exact metric definitions and reporting boundaries.
- `10_SECURITY_OPERATIONS.md` — privacy, auditability, operations and release.
- `11_ACCEPTANCE_TESTS.md` — detailed testable scenarios.
- `12_DEVELOPMENT_PLAN.md` — phases, dependencies, go-live checklist.
- `CODEX_IMPLEMENTATION_PROMPT.md` — end-to-end coding handoff.
- `schemas/` — import template and allowed enums (synthetic; no personal records).
- `reference/Requirements_v2.md` — prior specification, superseded by v3 on conflicts.
- `reference/SOURCE_FILE_AUDIT.md` — actual file structure validation.

**Data handling:** Uploaded live attendee/guest files contain personal data and are intentionally not included inside this developer handoff ZIP. Supply them through authorized secure test/import channels if needed. Example templates contain no real personal details.

**Configuration still required:** actual start/end times for eight sessions, guest form required-field policy, whether virtual registrations auto-check-in, staff account/permissions setup, and any approved backdated attendance policy. Defaults and safe behavior are described in the relevant sections.
