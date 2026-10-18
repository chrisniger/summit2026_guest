# Architecture decisions

## Phase 5 — local release and recoverability

The owner explicitly chose local internal operation, so no production host/deployment is configured. A guarded PowerShell launcher maintains this checkout's loopback web/import-worker/scheduler processes. Read-only admin Operations and doctor share redacted checks and database-scoped backup receipts; queue/scheduler heartbeats expire after three minutes. The scheduler does not prune or automatically back up.

Private no-store headers apply to web/error responses; generic recovery views avoid DB/auth/assets dependencies. Offline native-form handling keeps inputs only in the current DOM, without browser storage. Livewire retains ownership of its own submit lifecycle. Attendance lock/unlock is an audited configuration action independent of actual clock boundaries.

Database reservation 300 seconds exceeds import timeout 120. Failed private-file writes stop batch creation; delayed failure callbacks only change processing batches. Stalled import recovery and completed-original pruning default to dry-run, recheck under locks and audit changes; retention age is explicit and unapproved by default.

Local backups briefly pause app writes and archive SQL, existing APP_KEY and encrypted originals inside AES-256 ZIP with a hash manifest. Recovery password is separate. Verification is bounded to 32 MiB expanded for the 128 MiB local runtime, decrypts originals, restores only a new fixed isolated database and compares 21 table counts plus staff credential hashes. It refuses existing/live targets and removes only its own rehearsal DB. Backups/receipts are source-database scoped to avoid test rehearsal masking main backup state. This is a small local recovery tool; large-data streaming, automatic off-device rotation and production recovery orchestration remain future work.

## Phase 4 — ownership, contact chronology and report privacy

Bulk operations use an encrypted actor-bound confirmation snapshot with a 15-minute lifetime. Sorted guest locks, guest versions and active assignment IDs prevent stale confirmations from overwriting intervening capture/contact/ownership work. Commit is all-or-nothing and rechecks active officer eligibility. Assignment reasons remain immutable; closure_reason records why an assignment ended, with closing actor in audit. Successful changed confirmation replays conflict rather than append history.

Contact writes lock the guest/current owner and require the posted assignment ID/current version. Actor-scoped nullable unique fingerprints prevent duplicate activities. Actual WAT attempt time converts to UTC; status follows the latest actual attempt, with recording order breaking timestamp ties. Backdated earlier attempts remain in activity history but preserve the later outcome. NOTE_ONLY leaves status unchanged. Two-way/failed-attempt confirmations enforce the meaning of CONTACTED/UNREACHABLE.

Current-owner access is checked for guest reads/writes/exports. Officers' activity/status-history views are further restricted to their own actor and current assignment, so new owners do not receive another officer's private comments. Managers see complete permitted histories; Data Entry has no contact history by default.

Report definitions separate current guest/assignment state from activity period. Current workload counts only active owners; historical actor performance retains credit after reassignment within the current filtered guest cohort. Success uses distinct actual CONTACTED guests divided by distinct attempted guests; notes are excluded. No historical-as-of report is implied. Untouched means no actual attempt in the current owner's current assignment during the selected period. Queue and reports share the same guest cohort builder.

## Phase 3 — independent guest workflows

GuestCapture and GuestImport operate exclusively on guest tables and shared conference/identity infrastructure. Capture UUIDs include actor identity; event-row serialization plus fresh locking reads ensures replay safety under MariaDB REPEATABLE READ. Versioned profile edits reject stale writes. Profile validation allowlists exclude assignment/status/creator/provenance changes.

Guest import originals are encrypted on private storage. Parsing and mapping are separate from preview and queued commit. Worksheet/event descriptors are explicitly mapped to verified sessions in one event year; XLSX historical data targets 2025. Physical source row uniqueness includes worksheet name. Review can create a separate encounter, skip, or attach provenance to an authorized encounter in the same session without overwriting its trusted profile/status. Source fingerprints include canonical row content, source sheet/row and resolved session.

A bounded native OOXML reader uses already installed ZipArchive/DOM/libxml and adds no dependency. It supports literal/inline/shared/rich strings and numeric values, rejects formulas/errors/unsafe XML/macros/external links, and enforces ZIP/row/cell/mapping limits. Source local timestamps require timezone confirmation. Legacy officer/status/comment text remains reviewed source metadata; importing it does not invent actual contact or current assignments.

Imports require explicit guest.import and guest.create. Other than Super Admin, users can read/review/commit/download only their own batches; guest candidates still use normal role scopes. Queue chunks recheck active actor/permissions and commit at most 100 rows atomically. Manual retry resumes pending rows after a rolled-back chunk.

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
