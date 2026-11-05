# Sign-in

Login, logout, throttles, and authenticator setup stay as they are.

## Code

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/TwoFactorController.php`
- `app/Services/TwoFactorService.php`
- Guest routes in `routes/web.php`: `login`, `two-factor.setup`, `two-factor.challenge`
- `two-factor.recovery` stays behind `auth`

## Leave unchanged

- Privileged roles that require MFA still require it (`requires_mfa` on the role).
- Throttle names `login` and `two-factor` stay.
- Do not add a public registration screen.
- Do not remove the removed `employee` role.

## Visual only

Login and both two-factor screens may use the glass card and the red mark from `docs/prompts/HRMS-BRAND.md` and `docs/prompts/HRMS-LIQUID-GLASS.md`. Form fields, validation, and redirects stay.
