# Work calendars

Work calendar days stay the source for weekly off in attendance. Do not change which weekday is off inside a visual pass.

## Code

- `app/Http/Controllers/Organization/WorkCalendarController.php`
- `app/Http/Requests/WorkCalendarRequest.php`
- `app/Models/WorkCalendar.php`
- Route resource: `work-calendars` except show, parameter `workCalendar`
- Permissions: `work_calendars.view|create|edit|delete`

## Leave unchanged

Attendance status `weekly_off` still comes from this calendar. See `docs/attendance/REGISTER.md`.
