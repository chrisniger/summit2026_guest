# 05 — Info Com session and attendance engine

## Implemented Phase 2 behavior

Onsite submissions carry a UUID idempotency token and use the server instant. Check-in and registration serialize with schedule edits through a shared conference-event row lock. Confirmed CSV matches append original-time registration events, never overwrite trusted profile details, and preserve the earliest registration cutoff. Exact repeated source rows do not create more registration events or check-ins.

Ordinary writes never restore voided attendance. Super Admin void/restore requires a reason and preserves the same attendee/session pair. The general backdate/transfer UI is deferred pending operational correction policy. Info Com users land on the check-in desk; every action still revalidates permissions/session on the server. Search resets and regains focus after successful confirmation.

## Session inventory
| Code | Local date | Name | Clock start/end |
|---|---|---|---|
| 2026-10-27-G | 2026-10-27 | General | Set by Super Admin |
| 2026-10-28-G | 2026-10-28 | General | Set by Super Admin |
| 2026-10-29-G | 2026-10-29 | General | Set by Super Admin |
| 2026-10-30-G | 2026-10-30 | General | Set by Super Admin |
| 2026-10-31-AM | 2026-10-31 | Morning | Set by Super Admin |
| 2026-10-31-PM | 2026-10-31 | Evening | Set by Super Admin |
| 2026-11-01-AM | 2026-11-01 | Morning | Set by Super Admin |
| 2026-11-01-PM | 2026-11-01 | Evening | Set by Super Admin |

Store UTC boundaries calculated from configured WAT clock times. Start inclusive, end exclusive: `start <= event_time < end`. If no unique session matches, do not auto-check-in and log the reason. Reject configuration with overlapping intervals.

## Register onsite
1. Validate required first/last name/phone and normalize phone for searching.
2. Save an Info Com registration independently from Guest Service.
3. Capture original server-side submission instant and `registration_event`.
4. Find **exactly one** active Info Com session at original time.
5. If exactly one and the auto-registration policy is enabled for this registration category, create present for **that one session** using idempotent insert; otherwise registered-only.
6. Return registration result including session, whether checked in, and why if not.

If the applicant selected Virtual, do not silently count them as physically present; keep a Super Admin policy deciding if auto-check-in covers virtual as well. Safer default: only In Person or unspecified onsite registration auto-checks in; virtual attendees remain registered until explicitly recorded with an approved virtual attendance workflow.

## CSV import auto check-in
Use the source `Created` timestamp parsed with explicit timezone information, **not upload time**. Convert to UTC instant; compare against configured session UTC bounds. The calendar date by itself is insufficient. A created timestamp in a gap between morning/evening must not trigger a check-in. Valid events before 27 Oct or after 1 Nov have no auto check-in. Retry must be idempotent.

## Manual check-in
Officer selects one active session (default active unique session) and confirms correct attendee. Use transaction + database UNIQUE key; duplicate attempts return `already_present` without extra attendance. After check-in, reset search field; phone lookup supports `080`, `23480`, `+23480` forms. Multiple matches must be visually distinguishable.

## Attendance status and reporting
Absent for a session = eligible registered Info Com records with no corresponding active attendance record. Never populate an `absent` row for everyone by default. Distinguish:
- Session present = distinct attendees checked into that session.
- Session absent = eligible participants − session present.
- Unique conference attendees = distinct `info_attendee_id` present at least once.
- Total session check-ins = count of unique attendee/session attendance pairs.

Eligibility for session absent denominator must be explicitly defined and labelled (recommend all registered Info Com attendees up to report cutoff; additional filter by registration cohort available). Do not count guest-only records.

## Corrections
Only Super Admin or a specifically authorized permission may backdate, transfer, void or restore a record, with required reason and audit trail. Attendance should lock when the session ends, with a privileged override path.

## Day/session classification
The eight sessions are labelled Day 1, Day 2, Day 3, Day 4, Day 5 Morning, Day 5 Evening, Day 6 Morning, Day 6 Evening. The shared `conference_sessions` lookup owns day numbers, dates and labels. New manual attendance defaults to the unique current session and revalidates it on write. Registration/import auto-attendance continues to use the original registration instant. A person may attend all eight sessions, once per session. Filters and reports must accept a session ID and support day rollups without counting a person twice within the day.
