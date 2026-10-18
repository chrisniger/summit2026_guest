# 11 — Acceptance and QA scenarios

The development agent must implement automated feature tests plus mobile manual smoke tests. All rows should pass before go-live.

| ID | Test | Expected result |
|---|---|---|
| B01 | Info Com registers Mike | Info Com attendee saved; no guest created |
| B02 | Guest Data Entry registers Mike separately | Guest saved; no Info Com data modified |
| B03 | Same phone in both modules | Both records remain independent |
| B04 | Follow-Up Admin opens Info Com endpoint | 403 without specific independent permission |
| B05 | Info Com role requests guest records | 403 |
| B06 | New Info Com non-member / first-time participant | No guest automatically created |
| B07 | Info Com attendee marked Present | Guest list, status and assignments unchanged |
| B08 | Guest marked CONTACTED | Info Com check-ins unchanged |
| A01 | Registration Oct 27 within configured active session | Present for Oct 27 General only |
| A02 | Registration Oct 31 during Morning | Oct 31 Morning present, Evening absent |
| A03 | Registration Oct 31 during Evening | Oct 31 Evening present, Morning absent |
| A04 | Registration Nov 1 during Evening | Nov 1 Evening present only |
| A05 | Registration outside all active windows | Registered, not auto-present |
| A06 | UTC timestamp near local date boundary | WAT conversion selects correct session |
| A07 | Upload CSV during Oct 31 containing earlier Created date | Uses original Created, not upload instant |
| A08 | Repeat same CSV import | No duplicate registration events or check-ins |
| A09 | Two officers concurrently mark same attendee/session | Exactly one check-in row; second gets already_present |
| A10 | One attendee checks in all 8 sessions | Unique attendee = 1; total check-ins = 8 |
| A11 | Session start/end times overlap | Configuration rejected |
| A12 | Officer tries locked session | Rejected unless privileged correction |
| A13 | Raw number 080… vs +23480… search | Correct normalized matches |
| A14 | Two registrants share phone | UI offers distinct choices, no automatic merge |
| A15 | Import row indicates Virtual | Auto-check-in follows explicit approved policy; no false physical attendance |
| G01 | Follow-Up Data Entry submits guest after event day | Selected event/session saved; entry timestamp remains current time |
| G02 | Guest creation | Defaults NOT CONTACTED and UNASSIGNED |
| G03 | Bulk assign 20 guests | All 20 assigned to selected officer and logged |
| G04 | Reassign guest | Only one active assignment; original retained |
| G05 | Officer attempts another officer's guest | 403 on read/write/export |
| G06 | Officer records no-answer call | UNREACHABLE, attempt logged, not CONTACTED |
| G07 | Officer later reaches same guest | Current CONTACTED, earlier attempt preserved |
| G08 | Contact success calculation | Distinct contacted with attempts / distinct attempted guests |
| G09 | Legacy XLSX has mixed column orders | Correct header mapping independent of position |
| G10 | XLSX contains `#NAME?` as guest name | Invalid preview row; no silent insert |
| G11 | Legacy status conflicts with comment | Original preserved, flagged for review |
| G12 | Historical 2025 import | No 2026 Info Com attendance generated |
| G13 | Same guest across two XLSX tabs | Review duplicates inside Guest domain only |
| P01 | Invalid CSRF/login | Request denied |
| P02 | Role changes or attendance correction | Audit log records actor, time and change |
| P03 | CSV/Excel exports | Authorized rows only; formula-injection characters escaped |
| P04 | Empty report denominator | Show n/a, no divide-by-zero |
| P05 | Failed import chunk then retry | Idempotent recovery; row-level summary correct |

## Device acceptance
Test 360px Android view, iOS Safari equivalent, intermittent network, keyboard/search focus after check-in, large name/phone match list, responsive guest form, and accessibility labels. Use realistic volume, concurrent officers and import batching.

## Session classification extension
- S01: eight authoritative sessions have the exact six day numbers, dates and General/Morning/Evening classifications.
- S02: both attendance and guest schemas require a valid session foreign key; no cross-domain participant key exists.
- S03: each guest retains selected session even when input occurs later; batch entry preserves selected session.
- S04: guest filtering, admin bulk assignment, officer details and reports use the guest session.
- S05: attendance allows one attendee in multiple sessions, but rejects duplicate attendee/session rows.
- S06: empty/disabled/unconfigured sessions never become current; overlap and half-configured windows are rejected; adjacent windows are allowed.

Phase 1 verifies S01, S02, S05 and S06 plus policy/model foundations. S03–S04 operational UI tests are due in Phases 3–5.
