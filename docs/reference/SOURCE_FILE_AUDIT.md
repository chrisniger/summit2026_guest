# Reference file audit (metadata only)

## Info Com 2026 registration source
File: `registrations-2026-10-18.csv`.
- 1,749 registration rows.
- 18 source columns.
- 1,638 distinct original phone text values (does not equal guaranteed distinct people).
- Preference: 1,565 In Person; 184 Virtual.
- ISO timestamps in `Created` include UTC `Z` offset and fractional seconds.
- Header mapping is in `06_IMPORT_SPECIFICATIONS.md`.

## Guest Service 2025 guest source
File: `SUMMIT 2025 GUEST DATA.xlsx`.
- 9 worksheet names (1 empty `Sheet2`); 8 dated worksheet names.
- Mixed `Timestamp`/`DATE`, `EVENTS`/`EVENT` headers.
- Some columns reorder between day tabs.
- Broken Excel formula values such as `#NAME?` can appear; preview must flag them.
- Source status/comment data can conflict; retain original data and flag.
- Events belong to 2025 and do not automatically become 2026 records.

**Privacy:** Full uploaded attendee and guest data files are intentionally excluded from the distribution ZIP. Use securely shared test samples/approved source files during implementation.
