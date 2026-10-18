# 03 — Role-based access and permissions

**Never rely on hidden buttons for authorization; verify policy per action on the Laravel server.** Super Admin overrides all permissions.

| Permission | Super Admin | Info Com | Follow-Up Admin | Follow-Up Data Entry | Follow-Up Officer |
|---|---|---|---|---|---|
| Info Com attendee read/search | All | Yes | No | No | No |
| Info Com attendee add/edit | Yes | Yes | No | No | No |
| Mark Info Com session present | Yes | Yes | No | No | No |
| Undo attendance | Yes | No | No | No | No |
| Manage session schedule | Yes | No | No | No | No |
| Import Info Com registrations | Yes | Optional explicit grant | No | No | No |
| Info Com analytics | Yes | Limited session counts | No | No | No |
| Guest Service guest create | Yes | No | Yes | Yes | No |
| Guest Service guest search/list | Yes | No | All | Data entry scope | Assigned only |
| Guest Service guest edit | Yes | No | Yes | Policy-permitted | No except own follow-up fields |
| Import historical guest data | Yes | No | Optional explicit grant | Optional explicit grant | No |
| Bulk assign or reassign guests | Yes | No | Yes | No | No |
| Update guest current follow-up status | Yes | No | Yes | No | Own assigned |
| Add guest contact attempts | Yes | No | Yes | No | Own assigned |
| View all follow-up reporting | Yes | No | Yes | No | No |
| View own follow-up summary | Yes | No | Yes | No | Yes |
| Manage user accounts/settings | Yes | No | Delegated team accounts only | No | No |
| Audit logs | Yes | No | Limited own domain if granted | No | No |

### Implementation
Use Spatie Laravel Permission or native policies + explicit permissions. Define separate policy classes (`InfoAttendeePolicy`, `AttendancePolicy`, `GuestRecordPolicy`, `FollowUpAssignmentPolicy`, `ReportPolicy`). Roles not equal to database ownership: authorize every query using domain and ownership scope. Require `follow_up_assignments.officer_user_id = auth()->id()` and active assignment for officers, including export endpoints and record details. A malicious hand-entered ID must not bypass scoping.

Do not implicitly grant Follow-Up Admin access to Info Com just because both can see dashboard navigation. When delegated user management is enabled, constrain assigned role types to their own team.
