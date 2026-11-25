# Gate pass

People → Gate Pass. A pass is personal work or office work. Office work is factory work (`work_type` office). It never cuts that day’s pay, inside the break or outside it, including while the person is still outside: the clock end stands in as the out. Personal work cuts only the minutes outside that date’s break. The break slice stays free. No saved break means the whole personal outing is cut. A personal pass with no return is cut from the out time to the window end, and the break is still free. A late in stays a late cut. A free break pass does not remove it. Only an approved or expired pass counts. Pending and rejected do nothing. The salary day names every counted pass with its type, out, in, and “no cutoff” or the minutes cut. See `docs/salary/SHEETS.md` and `docs/salary/HOURS-AND-PAY.md`.

## Workflow

1. Someone with `gate_passes.create` requests a pass for a person they can see. An employee can request their own when self apply is on.
2. A child requesting their own pass stays pending when parent approval is required. The OUT machine stays closed. A parent, or a gate-pass admin, creating a pass for someone else is approved at once and the OUT machine opens. A supervisor cannot auto-approve their own pass.
3. One pending pass per person. When the setting blocks a second active pass, an approved pass that has not expired blocks a new one. Duration cannot pass the company maximum, from 15 minutes to 24 hours. Early leave is its own flag.
4. Super Admin, HR Admin, or a person above them in the reporting tree can approve or reject a pending pass. The person cannot decide their own pass. Reject needs a reason of at least 3 characters. Edit is only a pending pass: the person can edit their own, and a gate-pass admin can edit a pending pass they may see. A parent cannot edit or delete someone else’s pass. The owner can delete their own pending pass. A gate-pass admin can delete a pass they may see; an approved pass closes the OUT machine first.
5. Approve opens the OUT machine until the pass expires, and the person is notified. Reject keeps the machine closed and notifies them.
6. A pass that runs out is marked expired and the OUT machine closes. Personal work that is still open when it expires is half-day early leave once that person has clocked in, and that day is recalculated. Office work is not early leave. An approved or expired pass is named on the salary day with its type and duration. See `docs/salary/SHEETS.md`.

`gate_passes.manage` sets the default duration and the longest duration, from 15 minutes to 24 hours.

IN can be at any time. A late in is cut after `attendance_rules.grace_minutes`. After the first IN on a day, that person is a blocklist user on the OUT terminal until an approved gate pass covers the moment, or the saved minutes before the shift end (`company_settings.out_inactive_minutes`, Settings, default 10, from 0 to 240), or any time after the shift end, including the next morning before the next shift starts. Shift end 18:00 and 10 minutes means OUT opens at 17:50. After 18:00 they can out any time. A face before 17:50 without a pass stays an in and does not match as an out. After the first IN, that person becomes a blocklist user on the OUT terminal. An approved gate pass, or the out window, sets them back to a normal user on that terminal. The change uses UserInfo/SetUp, so the face and the fingerprint stay on the machine. The attendance day lists a gate scan as Personal out, Personal in, Company out, or Company in. A normal clock scan stays In or Out. The IN terminal stays a normal user. `faces:sync` and `hikvision:watch` keep this person-wise. A pending or rejected pass does not open OUT. This does not block the person on the employee list and does not switch the whole door terminal off for everyone. A scan on the IN terminal stays an in punch, including while a pass is approved. The gate-out time is recorded only from the OUT terminal, or from a gate terminal when one is set.

An approved early-leave gate pass fills the day’s Out with the machine let-out time. A return pass still keeps that let-out in the Gate pass popup only.

Personal work cuts the machine minutes between let out and let in, not the hour that was requested. One hour asked and 30 minutes outside cuts 30 minutes. A personal pass that sits entirely inside that date’s break is shown and cuts nothing. A personal pass with no return is cut from let out to the shift end, and the break stays free. When they come back, the cut is the actual out-to-in minutes. Factory work never cuts, at the break or at any other time, and not while they are still outside. A late in stays a late cut. A free break pass does not remove it.

## Code

- `app/Http/Controllers/Operations/GatePassController.php`
- `app/Services/Operations/GatePassService.php`
- `app/Models/GatePass.php`
- `resources/views/operations/gate-passes/`
- Routes: `gate-passes.index`, `gate-passes.create`, `gate-passes.store`, `gate-passes.show`, `gate-passes.update`, `gate-passes.destroy`, `gate-passes.approve`, `gate-passes.reject`, `gate-passes.settings`, `gate-passes.settings.update`

## Menu

`gate_passes.view` shows the list. Create, decide, and manage are separate. Map: `docs/roles/NAV-PERMISSIONS.md`.
