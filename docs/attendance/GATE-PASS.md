# Gate pass

People → Gate Pass. A pass is personal work or office work. Office work is company time outside the gate. It does not cut that day’s working hours, including while the person is still outside. Personal work cuts the minutes outside the scheduled break. A personal pass that ends with no return is early leave. The hour rule is in `docs/salary/HOURS-AND-PAY.md`.

## Workflow

1. Someone with `gate_passes.create` requests a pass for a person they can see. An employee can request their own when self apply is on.
2. A child requesting their own pass stays pending when parent approval is required. The OUT machine stays closed. A parent, or a gate-pass admin, creating a pass for someone else is approved at once and the OUT machine opens. A supervisor cannot auto-approve their own pass.
3. One pending pass per person. When the setting blocks a second active pass, an approved pass that has not expired blocks a new one. Duration cannot pass the company maximum, from 15 minutes to 24 hours. Early leave is its own flag.
4. Super Admin, HR Admin, or a person above them in the reporting tree can approve or reject a pending pass. The person cannot decide their own pass. Reject needs a reason of at least 3 characters. A parent cannot delete someone else’s pass.
5. Approve opens the OUT machine until the pass expires, and the person is notified. Reject keeps the machine closed and notifies them.
6. A pass that runs out is marked expired and the OUT machine closes. Personal work that is still open when it expires is half-day early leave once that person has clocked in, and that day is recalculated. Office work is not early leave. An approved or expired pass is named on the salary day with its type and duration. See `docs/salary/SHEETS.md`.

`gate_passes.manage` sets the default duration and the longest duration, from 15 minutes to 24 hours.

## Code

- `app/Http/Controllers/Operations/GatePassController.php`
- `app/Services/Operations/GatePassService.php`
- `app/Models/GatePass.php`
- `resources/views/operations/gate-passes/`
- Routes: `gate-passes.index`, `gate-passes.create`, `gate-passes.store`, `gate-passes.show`, `gate-passes.approve`, `gate-passes.reject`, `gate-passes.settings`, `gate-passes.settings.update`

## Menu

`gate_passes.view` shows the list. Create, decide, and manage are separate. Map: `docs/roles/NAV-PERMISSIONS.md`.
