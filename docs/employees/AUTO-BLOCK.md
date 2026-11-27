# Auto-suspend without in / out

Settings stores the company rule. Off suspends nobody. On turns Suspend on after the saved number of full days with no in and no out, and that person is blocked on the IN face terminal.

The employee form has its own day count. A blank count uses the company number. The count starts on the joining date when there is no punch. A punch day, including a manual M day, is not an empty day. Today is not counted until it is over. Super Admin and HR Admin stay open on the IN device.

This does not use the Blocked switch. Blocked still only removes the person from attendance, leave, shifts, gate pass, pay, and device enrollment. Suspend and Out Door Closed are separate switches on the employee list.

Suspend on blocks that person on the IN terminal. Suspend off opens the IN terminal. The change is UserInfo/SetUp, so the face and the fingerprint stay. Turn Suspend off from Employees. A later punch does not clear it by itself.

Out Door Closed on keeps that person blocked on the OUT terminal even when a gate pass or the minutes-before-out window would open it. Out Door Closed off returns them to that normal OUT rule. The same SetUp call is used. See `docs/attendance/GATE-PASS.md` and `docs/devices/IN-OUT-MACHINES.md`.

Saving with the rule on suspends everyone already over their own limit. `employees:block-inactive` does the same once each day, the first time the scheduler is awake. It does not suspend again until the next date.

The office PC runs this through `php artisan schedule:work`. A server runs `php artisan schedule:run` every minute.
