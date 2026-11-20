# Password reset

A staff account can ask for a reset link from the sign-in page. The link opens a page to set a new password and confirm it. Sign-in, logout, and the authenticator stay as they are. Forgot password and the new-password page use the same guest stage as sign-in: `docs/auth/SIGN-IN.md`.

## Flow

1. Sign in shows **Forgot password?**
2. The person enters the email on their staff account.
3. An active user who can already sign in gets one email. The link lasts 60 minutes and works once.
4. The link opens **Choose a new password**. New password and confirm password must match.
5. After a successful save, the person returns to sign in. A role that requires the authenticator still requires it.

An unknown email, an inactive account, and an account with no role all get the same notice. No email is sent for those. Asking again for the same account waits one minute.

## Code

- `app/Http/Controllers/Auth/PasswordResetController.php`
- `app/Notifications/ResetPasswordNotice.php`
- Guest routes `password.request`, `password.email`, `password.reset`, `password.update`
- `password_reset_tokens` already exists. No new migration.

## Mail

The message is sent during the request, not through the queue. The live server must have a real mailer or the link never leaves the log:

```
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=HRMS
```

`APP_URL` must be the public site address so the link in the email opens the right host.
