# HRMS

Web HRMS foundation: organization masters, employees, role-based access, and authenticator sign-in for privileged roles.

The interface is Blade, Bootstrap 5, and plain JavaScript. There is no Vite or npm build.

## Local setup

1. Create a MySQL or MariaDB database named `hrms` (phpMyAdmin is fine).
2. Copy `.env.example` to `.env` and set `DB_*`.
3. Run:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Local demo passwords come from `SEED_ADMIN_PASSWORD` (default `Admin@12345` when that value is empty).

- Super Admin: `admin@hrms.test` (authenticator required)
- HR Admin: `hr@hrms.test`
- Manager: `manager@hrms.test`

Do not use these accounts in production.
