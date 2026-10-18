# 07 — Screen-by-screen UI requirements

## Layout
Mobile-first responsive interface; compact cards and accessible form inputs; large tap targets; persistent active session indicator on Info Com pages; no intrusive modal for each check-in. Search and action must work at common low-end Android viewport widths and slow connections.

## Super Admin
1. **Overview:** separate Info Com metrics and Guest Service metrics, labelled by domain, no mixed totals.
2. **Users & Roles:** create, deactivate, reset, assign role, view team membership.
3. **Session Setup:** create/edit eight session intervals, validate no overlap, show WAT and UTC.
4. **Imports:** separate Info Com CSV and Guest Service XLSX import history.
5. **Audit:** search by actor/domain/action/time.
6. **Settings:** time rules, delegation, data retention, import policies, locked sessions.

## Info Com Team
1. **Quick Check-in:** prominent phone/name search field, debounce (~250–350 ms), matching list with unambiguous name/phone, current session, present/absent indicator; select attendee; single Mark Present action; success feedback; immediate ready-for-next-person state.
2. **Onsite Registration:** first name*, last name*, phone*; optional member, first time, heard about; extra optional fields only if configured. Confirm registration and any auto-check-in for active session.
3. **Attendee Detail:** profile, registration provenance, attendance history, read-only check-in history.
4. **Today's Attendance:** list/filter by active session, summary counts and optional limited exports.

## Follow-Up Data Entry
1. **Guest Entry:** guest name*, phone*, selected actual event date/session*; optional gender/address/referral/comment. Saves with NOT CONTACTED and UNASSIGNED.
2. **Guest Entry History:** browse entered guest records and identify duplicates *within Guest Service*.
3. **Guest Import** if permission explicitly granted: preview mappings and import outcome; no assignment functionality.

## Follow-Up Admin
1. **Follow-Up Dashboard:** guests total, unassigned, assigned, status breakdown, contact success rate, overdue/untouched if configured.
2. **Guest Queue:** multi-select, date/session/status filters, assignment state, search.
3. **Bulk Assignment:** select many → choose one active officer → confirm number → transactional assignment; show officer workload; non-destructive reassignment and audit.
4. **Officer Management:** officer list, assigned/attempted/contacted/unreachable metrics, optional delegated account creation.
5. **Reports:** selected date/session/officer/status; guest list, performance, activity timeline; CSV/Excel export.

## Follow-Up Officer
1. **My Assigned Guests:** list own assignments only; easy search and tap-to-call via `tel:` after selecting.
2. **Guest Detail:** name, phone, optional address/referral, entered event label, notes and timeline.
3. **Record Follow-Up:** call/SMS/WhatsApp/visit/other, outcome, comment, when; update allowed statuses, prevent cross-officer access.
4. **My Progress:** assigned count, contacted count, unreachable count, not contacted count, activity summary.

## Shared UI states
Empty list and no matches; invalid data; network/timeouts; duplicate check-in; concurrent assignment conflict; session ended or not active; permission denied; large import queued; import partly rejected; confirmation for destructive or irreversible actions. Accessibility: labels, high contrast, keyboard navigation and readable sizes.

## Confirmed session UI and Phase 1 scope
All roles use the official brand described in DESIGN_SYSTEM.md. Session selectors display day label and local date. Guest entry requires a selection, preserved for batch entry; guest queues, assignments and reports use this same session filter. Officer detail cards show session. Attendance filters distinguish morning/evening. Phase 1 provides protected role workspace shells and working session/staff administration; operational registration, guest entry, assignments and reports are staged in the development plan and must not be represented by fake enabled controls.
