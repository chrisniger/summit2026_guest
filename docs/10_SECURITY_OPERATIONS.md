# 10 — Security, privacy, reliability and operations

## Implemented local release controls — Phase 5

The current release is local/internal at the owner's request; production checklist below remains future work. Responses, including error responses, use private no-store and browser privacy/security headers. Private storage does not expose a serving route and throws on writes; failed originals cannot create an import batch. Queue retry_after 300 exceeds the 120-second import timeout, and delayed failure callbacks cannot regress completed work. Super Admin can auditably lock/unlock attendance and inspect read-only Operations; CLI doctor and worker/scheduler heartbeats support local monitoring.

Native forms block offline saves and repeated native submission without persisting personal values in browser storage. Recovery pages hide raw exceptions. Encrypted local backup/restore rehearsal covers SQL, existing APP_KEY and original files, with 21 table-count and staff-hash checks in an isolated database. Snapshot limit 32 MiB expanded; separate recovery password, private configuration and off-device copies require operator care. Retention/recovery commands default to dry-run; no automated deletion was configured. See LOCAL_OPERATIONS_RUNBOOK.md for exact commands and limits.

Password policy follows the owner's explicit internal-app decision: minimum 8 characters, no character-type restrictions; passwords remain hashed and actual saved credentials are verified. Existing credentials and key are preserved.

## Authentication and authorization
- Strong hashed passwords (Laravel standard); login throttle; rotate sessions on authentication; user deactivation revokes access.
- Policies checked on every action and scoped query. Ownership tested for officers with object-level authorization (including exports).
- CSRF validation, secure cookie settings, HTTPS-only production, environment secrets excluded from repository.
- Restrict Super Admin role assignment; log all role changes and high-risk actions.

## Personal data
- Guest home addresses, phone numbers and remarks are sensitive personal information: role-based least privilege, restricted export, masking where appropriate, no public indexing.
- Collect applicable consent/notices where required; define retention and deletion policies compliant with local requirements; no assumption that Info Com consent covers Guest Service.
- Separate data storage access inside app via model policies and separate table namespaces; if stronger organizational isolation is needed, separate DB schemas/connections can be enabled without automatic cross-module joins.
- Never include production attendee/guest source data in an open developer ZIP or public repository.

## Upload safety
Private object/local storage; MIME/content/size checks; reject macros/executables and unsafe formulas; strip risky leading `= + - @` on exported CSV text to mitigate formula injection. Avoid logging CSV/XLSX rows to application logs. Export permission mirrors view scope.

## Auditing and concurrency
- Audit manual attendance, import commitments, session changes, guest create/edit, role changes, assignment changes and contact updates.
- Unique check-ins enforced at DB layer; transaction + row locks for follow-up reassignment, avoiding lost updates.
- Retain imported originals and row errors under retention policy; imports retry safely.

## Performance / deployment
Use indexed queries, paginated lists and debounced typeahead. Queue large imports/exports; test simultaneous check-in officers and bulk assignments. Deploy on supported PHP/Laravel release, with Composer-lock, migrations and rollback. Configure queue worker, scheduler, mail/notification only if used, caches, health checks, server disk monitoring and encrypted offsite backups.

## Release checklist
- Environment has `APP_ENV=production`, debug disabled, keys configured, HTTPS active.
- Backups/restoration verified; least-privilege DB user; scheduled backups and import retention.
- Eight session WAT start/end values configured and tested; no overlaps.
- At least one Super Admin and real officers provisioned with least privilege.
- Confirm optional policy for virtual auto-check-in; guests are explicitly captured only.
- Run imports in preview on redacted datasets; staging acceptance suite passes.
- Do dry-run onsite check-in, duplicate concurrency, XLSX import, officer reassignment and both reports.
