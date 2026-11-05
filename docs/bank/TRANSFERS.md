# Bank transfers

Company bank accounts, transfer batches, and batch export stay. Employee payout accounts on the employee profile stay. Do not change export columns that a bank file already uses.

## Code

- `app/Http/Controllers/Operations/BankController.php`
- `app/Http/Controllers/Employees/EmployeeBankAccountController.php`
- Routes: `bank.index`, `bank.accounts.store`, `bank.batches.store`, `bank.export`
- Employee account route: `employees.bank-accounts.store`

## Permissions

Keep `bank_accounts.view`, `bank_accounts.manage`, and `payments.release`. The Bank menu item uses `bank.view` once that slug exists. Accounts keeps bank and payments. Accounts does not gain attendance edit. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Account numbers and amounts stay on solid surfaces. Under 700px, batch tables scroll inside `.tw`.
