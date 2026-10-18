# Architecture decisions

## Phase 2 — Info Com transactions and imports

Registration, attendance and schedule changes take the conference-event row lock before participant writes. This trades some import/check-in concurrency for a simple consistent lock order and exact session validation. A browser integration check uses two real PHP processes and database connections to verify duplicate check-in behavior.

Onsite form UUIDs and unique registration source fingerprints make retries idempotent. CSV fingerprints are based on canonical header-keyed raw rows, independent of header order. Matching phone/name is a review candidate only. Human-reviewed matches append provenance and adjust the earliest registration cutoff without overwriting trusted profile fields.

Private CSV originals use Laravel Crypt. The database queue named imports processes 100 rows per transactional job, with row progress and source fingerprints supporting safe retries. Raw file data is not written to error logs. A paused chunk requires an explicit retry; invalid rows require a corrected upload. Preview limits are 5 MB and 10,000 non-empty rows. The current Info Com importer accepts explicit 2026 ISO timestamps, including offsets/fractions, but rejects future or ambiguous timestamps.

Attendance reports use the same base cohort for tables and exports; absent eligibility is as-of the earlier of now and selected-session end. Unconfigured sessions have no meaningful absent/rate result. Info Com staff have report read access; exports of personal data remain Super Admin only. A dedicated modality workflow is required before changing the safe Virtual auto-attendance default.

## Password policy amendment

At the user's explicit request, passwords require at least eight characters with no uppercase, lowercase, number or symbol requirements. This single Laravel default applies to initial administrator provisioning, staff creation and password resets. Forms and command prompts mirror it. Authentication still verifies the account's actual hashed password.

## 2026-10-18 — Phase 1 baseline

Repository inspection found documentation and three prompts, no application, Git repository, Composer configuration or environment files. PHP 8.4, Node 22 and XAMPP MariaDB 10.4 are available. Composer is invoked through `php D:\php84\composer.phar` because its Unix launcher does not run directly in PowerShell.

### Requirement map

| Requirement | Existing implementation | Phase 1 approach |
|---|---|---|
| Laravel foundation | None | Laravel 13, Blade, Livewire, Alpine bundled by Livewire, Tailwind |
| Staff authentication | None | Laravel session guard and password broker; no public account registration |
| Five roles | None | Explicit role/permission tables, gates and per-record policies |
| Independent domains | None | `info_*` and `guest_*` roots, no attendee–guest foreign keys or matching |
| Session classification | Documentation only | Shared `conference_sessions` with required independent session references |
| Audit | None | Append-only application service with metadata allowlists |
| Branding | `docs/logo.png` only | Preserve official image on navy; semantic CSS tokens |
| Test isolation | None | Dedicated MariaDB `summit_2026_test`; never refresh the application database |

### Specification precedence and resolved differences

The three root prompts and the numbered v3 documents control scope. Both copies of v2 requirements are identical and superseded where conflicting. Prompt 2 explicitly requires shared `conference_sessions`; this replaces v3's older `info_conference_sessions` name. Both attendance and guests reference this lookup independently. Day number, date and session label live on the session, avoiding editable duplicate classifications on guest records. Each guest record represents one encounter; profile reconciliation remains future work.

Import batches and rows remain separate per domain as specified by v3. The Phase 1 schema includes provenance and counters; preview/commit import interfaces belong to later phases. Roles use native Laravel Gates/Policies with explicit database permission pivots, an allowed alternative to Spatie. Data Entry defaults to its own guest entries; broader editing is not assumed.

### Safety and configuration

UTC is the application/storage timezone; Africa/Lagos is the conference presentation timezone. Session boundaries are nullable and disabled until configured. Half-open intervals prevent boundary double-counting. Schedule edits lock a shared conference-event row to serialize concurrent edits. Database checks enforce complete valid intervals; services enforce cross-row overlap rules.

Create only new, dedicated databases. XAMPP's other databases are out of scope. Local database access is loopback-only; production needs a separate least-privilege database account, HTTPS, mail delivery and backups. No sample staff credentials or live personal records are seeded.

Phase 1 delivers protected role workspaces, session administration, staff provisioning and domain foundations. Full registration/check-in, guest entry/import, assignment/contact and reporting workflows remain explicitly staged in Phases 2–5.
