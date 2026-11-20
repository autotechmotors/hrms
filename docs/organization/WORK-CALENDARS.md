# Work calendars

An active work calendar treats Monday through Sunday as working. If a company has no calendar, every weekday is working. A public holiday and a cutoff holiday are company-wide non-working days. Do not turn Saturday or Sunday off inside a visual pass.

## Code

- `app/Http/Controllers/Organization/WorkCalendarController.php`
- `app/Http/Requests/WorkCalendarRequest.php`
- `app/Models/WorkCalendar.php`
- Route resource: `work-calendars` except show, parameter `workCalendar`
- Permissions: `work_calendars.view|create|edit|delete`

## Leave unchanged

Attendance status `weekly_off` still comes from this calendar. See `docs/attendance/REGISTER.md`.
