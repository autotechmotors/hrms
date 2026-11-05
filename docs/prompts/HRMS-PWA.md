# PWA

The same Blade site installs on iPhone and Android. Icons come only from `docs/brand/app-icon.png`. Colour: `docs/prompts/HRMS-BRAND.md`.

## Manifest

`public/manifest.webmanifest`

- `name` and `short_name` from `config('app.name')`
- `display: standalone`
- `start_url: /`
- `scope: /`
- `background_color: #ffffff`
- `theme_color` the icon red
- `orientation: portrait-primary` is a preference. Do not lock desktop.

## Icons

Generate from the brand PNG only:

- 180 apple touch
- 192
- 512
- maskable 512 with padding so the triangle is not cropped

## Head

In `resources/views/layouts/app.blade.php`: manifest link, `theme-color`, `apple-mobile-web-app-capable`, apple touch icon.

## Service worker

Cache the app shell only: CSS, JS, icons, manifest. HTML and authenticated JSON stay network-first.

Do not cache employee, payroll, bank, or attendance payloads. Do not intercept `device-events`, `hikvision-events`, or `ezapi`.

Offline is a short shell message, then the network page when the connection returns. No offline punch entry.
