# 08 — Controller / API contracts (implementation guide)

Laravel monolith with server-rendered Livewire screens is preferred. These endpoints are illustrative JSON contracts for Livewire actions/controllers and must be authenticated, CSRF-protected for browser writes, validated, authorized, audited, and rate-limited.

## Info Com routes
| Method | Route | Action | Permission |
|---|---|---|---|
| GET | `/info-com/attendees?search=` | Search own-domain registrations, paginated | info.attendee.view |
| POST | `/info-com/attendees` | Register participant and conditional session auto-check-in | info.attendee.create |
| GET | `/info-com/attendees/{id}` | Attendee view/attendance list | info.attendee.view |
| POST | `/info-com/sessions/{id}/check-ins` | Manual Mark Present | info.attendance.mark |
| GET | `/info-com/sessions/current` | Session active at server instant | info.attendance.view |
| GET | `/info-com/reports` | Aggregates/exports | info.report.view |
| POST | `/info-com/imports` | Upload/preview registration CSV | info.import |
| POST | `/info-com/imports/{id}/commit` | Confirm import | info.import |

### Example check-in request
`POST /info-com/sessions/7/check-ins` → `{ "info_attendee_id": 42 }`.

Success: `{ "status":"present", "session_code":"2026-10-31-AM", "already_present":false }`; idempotent duplicate: same with `already_present:true`; inactive locked session: `409 SESSION_NOT_ACTIVE`; unknown attendee: `404`; forbidden: `403`.

### Example onsite register request
`{ "first_name":"Sample", "last_name":"Attendee", "phone":"08000000000", "member":null, "first_time":null, "heard_about":null }`.

Response: attendee ID, source timestamp, `auto_attendance` result and current session if any. Do **not** return/create any Guest Service record.

## Guest Service routes
| Method | Route | Action | Permission |
|---|---|---|---|
| GET | `/follow-up/guests` | Search/list by source event/date/status/officer | followup.guest.view_scope |
| POST | `/follow-up/guests` | Explicitly create guest record | followup.guest.create |
| GET | `/follow-up/guests/{id}` | Guest details scoped by role | followup.guest.view_scope |
| PATCH | `/follow-up/guests/{id}` | Edit non-contact guest fields | followup.guest.edit |
| POST | `/follow-up/assignments/bulk` | Bulk assign or reassign | followup.assign |
| POST | `/follow-up/guests/{id}/activities` | Log contact attempt/remark | followup.activity.create |
| PATCH | `/follow-up/guests/{id}/status` | Change current status with audit | followup.status.update |
| GET | `/follow-up/reports` | Metrics and export | followup.report.view |
| POST | `/follow-up/imports` | Historical XLSX import preview | followup.import |
| POST | `/follow-up/imports/{id}/commit` | Confirm historical XLSX import | followup.import |

### Example guest create
`{ "full_name":"Sample Guest", "phone":"08000000000", "event_date":"2026-10-31", "event_session":"Morning", "gender":null, "home_address":null, "heard_about":null, "entry_notes":null }`.

Response guest ID, `current_status:"NOT CONTACTED"`, `assignment_status:"UNASSIGNED"`. Do **not** create an Info Com attendee/check-in.

### Example assignment
`{ "guest_ids":[101,102,103], "officer_user_id":15, "reason":"Assigned after Saturday morning" }`.

Validate IDs and role; lock guest rows in consistent order; reject or explicitly handle guests assigned to others; complete all-or-nothing or documented per-row success; return `assigned_count`, `skipped_count`, conflict IDs and audit IDs. Never conceal reassignment.

### Example activity
`{ "method":"call", "outcome":"UNREACHABLE", "comment":"No answer", "attempted_at":"2026-10-31T16:45:00+01:00" }`.

On successful outcome update current status and append status history transactionally. Free-text activity alone must not implicitly set `CONTACTED`.

## Common errors and contracts
Validation 422 keyed field errors, unauthorized 401, forbidden 403, not found 404, conflict 409, throttled 429, server 500 with opaque request ID. JSON responses should never disclose another role's hidden personal information. Pagination uses stable cursor or page+perPage, reasonable max page size. Reports and exports must apply identical authorization filters.

## Session classification amendment
New guest requests require `conference_session_id` (existing session); event date and label are derived from the selected authoritative session. Attendance uses the same lookup through its own required foreign key. List/report filters use `conference_session_id` within the authorized domain. Day labels are returned from the session relationship. The older event_date/event_session free-text create example is superseded by this explicit ID contract.
