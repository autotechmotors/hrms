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
- A user with the `employee` role can sign in. That login sees only their own employee row. A user with no role still cannot sign in.

## Visual only

Login, forgot password, reset password, and both two-factor screens share one guest stage in `layouts/guest.blade.php`. The plant scene `public/assets/images/auth-plant.jpg` covers the left side from the top, bottom, and left edges. Its right edge is curved and carries a soft dark board shadow onto the white form. Under 900px the scene is a full-width band, curved along the bottom. Fields stay solid white with a visible border. The red mark is `docs/brand/app-icon.png`. Form fields, validation, and redirects stay. Error pages stay a single card on the wash, without the plant scene.
