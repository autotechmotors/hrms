# Company branding

The branding screen edits the company record an admin is allowed to change. It is not the PWA icon.

## Code

- `app/Http/Controllers/Organization/BrandingController.php`
- Routes: `branding.edit`, `branding.update`
- Permission: `branding.edit`

## Two marks

| Mark | File | Use |
|---|---|---|
| Product icon | `docs/brand/app-icon.png` | PWA, sidebar, login |
| Company branding | Saved on the company from this screen | Company name and company logo fields that already exist |

Do not replace the company logo with the product icon, and do not replace the product icon with an uploaded company logo. Product colour and glass: `docs/prompts/HRMS-BRAND.md`.
