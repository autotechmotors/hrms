# Auto-block without in / out

Settings stores the rule for the signed-in company. Off blocks nobody. On marks other people Blocked after the saved number of full days with no in and no out.

The count starts on the joining date when there is no punch. A punch day, including a manual M day, is not an empty day. Today is not counted until it is over. Super Admin and HR Admin stay active. Unblock from Employees.

Saving with the rule on blocks everyone already over the limit. `employees:block-inactive` does the same once each day, the first time the scheduler is awake, and reads the saved days. It does not block again until the next date.

The office PC runs this through `php artisan schedule:work`. A server runs `php artisan schedule:run` every minute.
