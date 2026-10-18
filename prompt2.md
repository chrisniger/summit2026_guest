Update the SUMMIT 2026 application specification and implementation to support conference day/session classification across both Info Com and Guest Service modules.
- Day 1 — October 27, 2026
- Day 2 — October 28, 2026
- Day 3 — October 29, 2026
- Day 4 — October 30, 2026
- Day 5 Morning — October 31, 2026
- Day 5 Evening — October 31, 2026
- Day 6 Morning — November 1, 2026
- Day 6 Evening — November 1, 2026
1. Every attendance record must reference the appropriate conference session.
2. Automatically associate new attendance with the currently active session.
3. Allow attendees to be present at multiple sessions without duplicate records for the same session.
4. Provide attendance filters and reporting for each day/session.
5. Maintain existing automatic attendance and session timing rules.
1. Every guest record must have a conference day/session field.
2. Follow-Up Data Entry must select the correct session when entering guest records.
3. Support batch data entry under one selected session.
4. Follow-Up Admin must be able to filter and bulk-assign guests by session.
5. Follow-Up Officers should see the session associated with each assigned guest.
6. Follow-up reports must support session-based filtering and breakdowns.
Use the existing conference_sessions table as the authoritative reference for day numbers, session labels, dates and times.
Reference the session in the Info Com attendance records and independently in Guest Service guest records.
Do not link, merge or synchronize Info Com attendee records with Guest Service guest records.
Update the appropriate files inside docs/, including the database design, attendance rules, guest workflows, UI specifications, reports and acceptance tests.
Implement the session model and relationships within the current development phase, and test all eight conference sessions.
Preserve all previously confirmed business rules.  