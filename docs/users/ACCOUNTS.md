# User accounts

User create, edit, and delete stay on the current policies. A user's department and company scope still decide which employees they see. Do not put data scope on the role alone.

## Code

- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Requests/UserRequest.php`
- `app/Services/AccessScope.php`
- Routes: `users.*` resource except show

## Menu

`users.view`, `users.create`, `users.edit`, `users.delete` stay. Super Admin and HR Admin both have `users.delete`. Only a Super Admin can assign or remove the Super Admin role. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

The user form may use liquid-glass chrome. Scope checkboxes and role assignment stay in the same request fields.
