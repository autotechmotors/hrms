# Gate pass column and overtime after the shift

Use this with `docs/prompts/HRMS-PAY-DAYS.md`. That file still owns Sunday off, holidays, leave, and the minute rate. This file owns the attendance table, the OUT face, and pay after the shift has ended.

Read `docs/prompts/HRMS-LIVE-UI.md`, `docs/attendance/REGISTER.md`, `docs/attendance/GATE-PASS.md`, and `docs/salary/SHEETS.md` first.

The OUT window is already `company_settings.out_inactive_minutes`, labelled “Minutes before out” on `/settings`. Do not add a second field. Punch photos are `attendance_punches.image` through `AttendancePunch::imageUri()`. A pass links by `gate_pass_id`.

## Attendance table

On `/attendance/{employee}?date=`, the month table is `resources/views/operations/attendance/show.blade.php`. Replace the Break column with Gate pass.

- The cell is an icon only. No icon when that date has no approved or expired gate pass.
- Pending and rejected passes do not show an icon.
- The icon is a button. Click opens a popup on this page. Do not navigate away. Do not reload the layout.
- The popup shows: when the pass was taken, reason, type (Personal or Factory), let out, let in, the machine OUT photo, and the machine IN photo. Photos come from the punches on that pass. A missing photo is an empty frame, not a broken image.
- More than one pass on a date: one icon, and the popup lists each pass.
- The phone card for that day uses the same icon and the same popup.
- The day feed that already refreshes this table must refresh the icon too.

Let out is the machine out time. Let in is the machine return time. Do not show the requested duration as if it were the time they were out.

## Actual time out, not the requested hour

A personal pass cuts the minutes between let out and let in. It does not cut the hour they asked for.

They ask for 1 hour and come back in 30 minutes: cut 30 minutes. The other 30 minutes stay in the working hours.

Scheduled break, for example 13:00–14:00 on a 09:00–18:00 day:

- Personal let out and let in both inside that break: show the pass, cut nothing.
- Personal time outside that break: cut only the actual minutes outside the break.
- A second personal outing at 11:00–12:00, which is not the saved break: cut those actual minutes. Confirm the type is personal before cutting.
- Factory work (`work_type` office) never cuts, inside the break or outside it, and not when they are still out. This is the main rule.
- No return yet on a personal pass: cut from let out to the shift end, and still forgive minutes that sit inside the break. Stop that cut when the return punch arrives, then use the actual out-to-in minutes.
- No break saved on that clock: the whole personal out-to-in is cut.
- A late in is still a late cut. A free break pass does not remove it.

## When the face is OUT

Any role. IN can be at any time.

A machine face is OUT only in two cases:

- An approved gate pass allows that out, or
- The scan is inside the minutes saved in Settings before the scheduled end, or any time after the scheduled end.

Example. Shift ends 18:00. Settings are 10 minutes. The OUT face opens at 17:50. From 17:50 they can leave at 17:50, at 18:00, or the next morning. A face before 17:50 stays an IN. It does not close the shift.

Do not turn the terminal off. The earlier face is stored as in.

## Overtime

The shift closes at its scheduled end. If OUT has not come by then, every minute from that end until the OUT face is overtime.

Example. Day shift 09:00–18:00. Break 13:00–14:00. On 24 Nov the IN is 10:00. The OUT face is 25 Nov at 07:00. Late is still the hour from 09:00 to 10:00. Overtime is 24 Nov 18:00 through 25 Nov 07:00. It is not cut as early leave.

Sunday counts as a full day for this, not as a half day. A Sunday on the 09:00–18:00 shift uses those same 9 hours. Extra time after 18:00 is overtime. Do not use the short Sunday window as the overtime length.

```
hour rate = monthly salary / (days in that month × full shift hours)
overtime pay = overtime hours × hour rate
```

Full shift hours are the Monday–Saturday length of that day’s kind. 09:00–18:00 is 9 hours. If that rate makes one hour 100, nine extra hours are 900. A 30-day month divides by 30. A 31-day month divides by 31. February uses 28 or 29.

Overtime pay is a separate line from the day pay. It does not change the late, early, or personal cutoff. Factory-work time is not overtime and is not a cutoff. Time inside the window is not overtime. Staying past the end without an OUT is overtime until the OUT arrives. Arriving early is not overtime.

## Where it shows

Salary sheet, for the generated range:

- A separate Overtime section
- Each day: date, shift end, OUT time, extra hours, rupees
- The total extra hours and the overtime salary

Attendance month table, same person page:

- An Overtime column
- That day’s extra time, or a dash when there is none
- 24 Nov in the example shows the hours from 18:00 to the next morning’s OUT

The sheet, Excel, and PDF all include the overtime section. A saved sheet does not change until it is generated again.

## Do not change

- Who may approve a gate pass or a leave
- `out_inactive_minutes` as the only OUT-window setting
- Device push, Hikvision, and ezapi
- `AccessScope`, auth, and MFA
- Sunday off, public holiday, and paid leave still pay the day with no cutoff (`docs/prompts/HRMS-PAY-DAYS.md`)

Update `docs/attendance/REGISTER.md`, `docs/attendance/GATE-PASS.md`, and `docs/salary/SHEETS.md` in the same change.

## Checks

- No pass that day: the Gate pass cell has no icon.
- Approved personal pass: icon opens reason, type, let out, let in, and both machine photos.
- Asked 60 minutes, back in 30: cutoff is 30, and the other 30 stay worked.
- 09:00–18:00, break 13:00–14:00, personal pass inside 13:00–14:00: listed, 0 cutoff.
- Personal pass 11:00–12:00: cutoff is the actual minutes outside the break.
- Factory pass at any time: 0 cutoff.
- Settings 10, shift end 18:00: a face at 17:40 stays IN. A face at 17:50 or later is OUT.
- IN 24 Nov 10:00, OUT 25 Nov 07:00: overtime from 24 Nov 18:00 to 25 Nov 07:00, on the attendance row and on the overtime section.
- Sunday uses the full 09:00–18:00 length, not a half day, for the hour rate.
