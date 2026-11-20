# Bank transfers

Company bank accounts, transfer batches, and batch export stay. Employee payout accounts stay on the employee profile. Company accounts live only on `/bank`. Do not add a second company-account screen.

## Company account

Required: label, account holder, bank name, account number (6–30 characters), IFSC. Optional: branch, GST number. GST may be empty. A filled GST must match the Indian GSTIN shape and is stored uppercase without spaces. IFSC is stored uppercase. The full account number is encrypted. The screen shows only the last four digits.

Add company account is a top-bar button. It opens a Bootstrap modal. Validation errors reopen that modal. Saving uses a normal HTTP POST. Do not wrap account create or CSV download in ajax.

Only one company account is primary. Saving a new account as primary clears the mark on every other account for that company. The first account becomes primary even when Primary is left unchecked. Opening `/bank` also collapses any extra primary rows down to one.

## Bulk payment file

A new file needs an approved salary sheet, a company bank account, and a schedule date. Pending sheets do not appear. Each line is that person’s sheet net, from the company account into that person’s active payout account. A person with no active account still gets a line. The CSV note is `No active employee bank account`. The batch starts as draft. Download marks it exported. HRMS does not send money. Upload the CSV to the bank.

CSV keeps the original columns first: employee code, name, amount, account last 4, IFSC, note. After those: beneficiary, account number, branch, debit account, debit IFSC. Header rows also carry the company account holder, branch, and GST. Older files built from a payroll run can still be downloaded.

## Code

- `app/Http/Controllers/Operations/BankController.php`
- `app/Http/Controllers/Employees/EmployeeBankAccountController.php`
- Routes: `bank.index`, `bank.accounts.store`, `bank.batches.store`, `bank.export`
- Employee account route: `employees.bank-accounts.store`
- Columns `account_holder`, `branch_name`, `gst_number` on `company_bank_accounts`

## Permissions

Page view: `bank.view`, `bank_accounts.view`, `bank_accounts.manage`, or `payments.release`. Create account, prepare file, and download need `bank_accounts.manage` or `payments.release`. Accounts keeps bank and payments. Accounts does not gain attendance edit. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Account numbers and amounts stay on solid surfaces. Under 700px, batch tables scroll inside `.tw`.
