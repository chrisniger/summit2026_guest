# 06 — File import specification and mapping

## Phase 2 Info Com implementation

The CSV UI implements dry-run preview, header-name mapping for all 18 fields, required duplicate review, explicit confirmation, 100-row transactional database-queue jobs, progress, safe retry and per-row result export. UTF-8 (including BOM), 5 MB, 10,000 non-empty rows. Header order is flexible. Source timestamps require an explicit offset, a valid 2026 date and no future instant. Blank optional booleans stay null; unknown values are invalid. Raw originals are encrypted on the private local disk; raw rows are never executed or logged.

Exact canonical source rows skip across batches. Similar phone/name candidates require a new/match/skip decision; reviewed matches append registration provenance without overwriting profiles. Invalid rows are skipped at confirmed commit and reported, not silently inserted. Fix invalid source data in a corrected upload. A failed chunk rolls back and can be retried; already-completed rows are retained once. All review rows must be resolved before commitment. Guest workbooks remain unsupported here and must use the independent future Guest Service importer.

## A. Info Com — 2026 CSV (verified source)
Uploaded source: `registrations-2026-10-18.csv`; 1,749 source rows; 18 columns; 1,638 distinct raw phone strings (not confirmed unique people); 1,565 `In Person`, 184 `Virtual` selections.

### Exact 18 headers
`Created`, `First Name`, `Surname`, `Phone`, `Email`, `Member`, `Summit Centre`, `Commits to Invite`, `Invite Count`, `Location`, `Abuja Area`, `First Time`, `Bus Shuttle`, `Bus Route`, `Attendance`, `Heard About`, `Feedback`, `Consent`.

### Column mapping
| CSV header | Model field |
|---|---|
| Created | `info_registration_events.registered_at_utc` and attendee initial registration timestamp |
| First Name | `info_attendees.first_name` |
| Surname | `info_attendees.last_name` |
| Phone | `info_attendees.phone_raw` + normalized search value |
| Email | `email` |
| Member | `member` |
| Summit Centre | `summit_centre` |
| Commits to Invite | `commits_to_invite` |
| Invite Count | `invite_count` |
| Location | `location` |
| Abuja Area | `abuja_area` |
| First Time | `first_time` |
| Bus Shuttle | `bus_shuttle` |
| Bus Route | `bus_route` |
| Attendance | `attendance_preference` (NOT actual check-in) |
| Heard About | `heard_about` |
| Feedback | `feedback` |
| Consent | `consent` |

`Created` examples include `2026-10-18T06:30:49.227Z` and are UTC. Parse strictly, convert UTC instant to the session timezone. Preserve source and result; never treat uploaded UTC date string as a WAT date without conversion.

Observed `Heard About` values: Church announcement, Facebook, Flyer, Someone invited me, WhatsApp, Instagram, Other, Outreach, Impact Cell Group, Evangelism, Billboard, Email, TV. Support these on the Info Com form but allow settings-managed options.

### Idempotency / review
Do not use phone as a unique key. Distinguish identical source rows (batch fingerprint/source identifier), repeat registrations, ambiguous matching and genuine new attendees. For ambiguous cases, hold for human review rather than auto merging. An import row cannot create more than one automatic check-in for one session.

## B. Guest Service — 2025 XLSX (verified source)
Uploaded source: `SUMMIT 2025 GUEST DATA.xlsx`. Nine sheets, one named `Sheet2` appears blank. Eight dated worksheets (Oct 29–Nov 2, 2025) have headings and inconsistent column order. Some start with `Timestamp` / `EVENTS`; others `DATE` / `EVENT`. All use guest name/phone, follow-up officer, gender, home address, status, comment and referral source in differing order. Do NOT assume spreadsheet letters map to fixed fields.

Canonical guest header aliases:
- `Timestamp` or `DATE` → `captured_at_original_utc` or source timestamp (timezone validated).
- `EVENTS` or `EVENT` → `event_session_label` and interpreted `event_date_local`, with explicit admin review.
- `NAME` → `full_name`.
- `PHONE` → `phone_raw`, normalized search value.
- `FOLLOW UP OFFICER` → legacy officer text; do not implicitly create user or assign to an arbitrary account.
- `GENDER` → `gender`.
- `HOME ADDRESS` → `home_address`.
- `FOLLOW UP STATUS` → normalized enum (CONTACTED, NOT CONTACTED, UNREACHABLE), with unknown/blank flagged or set to default only under approved import rules.
- `COMMENT` → entry note or imported historical activity note.
- `HOW DID YOU HEAR ABOUT THE CONFERENCE` → `heard_about`.

### Historical caveats
- Broken Excel formula/result cells, such as `#NAME?`, must be shown as invalid and not imported as names.
- A sheet name may indicate a 2025 conference session even when the row timestamp is after that session/day; retain both original timestamps and chosen event labels; never infer session solely from timestamp when ambiguous.
- Some 2025 status/comment combinations are contradictory (e.g. CONTACTED with no-answer note). Preserve original raw text and flag for review, rather than silently rewriting history.
- Cross-sheet repeated rows may be legitimate carry-forward records; preview similarity groups and choose explicit import strategy (`new_guest`, `existing_guest_update`, `skip`) within Guest Service only.
- The 2025 workbook is **historical reference/migration**; importing it must not turn 2025 events into 2026 attendance or follow-up assignments automatically.

## C. Guest Service — new 2026 capture/import
Form fields: full name or first+last, phone, event date/session, optional gender, home address, referral source, notes. Guest eligibility **only via this explicit form/import**, never via Info Com. Data Entry may enter at a later time but chooses actual guest collection session. Default follow-up status NOT CONTACTED and no active assignment.

## Upload pipeline for both domains
1. Upload encrypted-at-rest/private file with max file size, extension/MIME validation.
2. Choose **domain-specific** importer, validate header/worksheet aliases and required fields.
3. Parse and show preview: new, valid, ambiguous, duplicate, rejected, potential corrections.
4. Confirm import explicitly. Use queued chunks for scale; handle partial failures/retry.
5. Use transactional row upsert/idempotent source fingerprints and preserve original source record.
6. Generate summary, row-level error report and operator audit.
7. Restrict raw file downloads and retention by role; store outside public web root.

Never combine both import pipelines or route guest files into Info Com attendance.
