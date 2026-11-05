# Custom fields

Company custom fields and the values stored on an employee stay. The profile section already renders them. The form already edits them.

## Code

- `app/Http/Controllers/Organization/CustomFieldController.php`
- Route resource: `custom-fields` except show, parameter `customField`
- Permissions: `custom_fields.view|create|edit|delete`

## Leave unchanged

Field types already supported on the employee form: text, textarea, number, date, and select. Do not add a new field type in a layout pass. Employee profile rules: `docs/employees/PROFILE.md`.
