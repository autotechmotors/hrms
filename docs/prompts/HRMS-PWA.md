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

Both layouts include `resources/views/partials/pwa-head.blade.php`: manifest link, `theme-color`, `apple-mobile-web-app-capable`, apple title, and the 180px touch icon.

## Install

`resources/views/partials/pwa-install.blade.php` is the iPhone sheet. Buttons use `data-pwa-install` and stay hidden until install is possible.

- Already installed (`display-mode: standalone` or `navigator.standalone`): no button.
- Chrome and Edge: `beforeinstallprompt` opens the browser install prompt.
- iPhone and iPad: the button opens the sheet. Safari steps are Share, Add to Home Screen, Add. Any other iPhone browser is told to open Safari first.

The button sits on More and on the sign-in card. Android install still needs a secure address. iPhone Add to Home Screen uses the apple touch icon.

## Service worker

Cache the app shell only: CSS, JS, icons, manifest. HTML and authenticated JSON stay network-first.

Do not cache employee, payroll, bank, or attendance payloads. Do not intercept `device-events` or `hikvision-events`.

Offline is a short shell message, then the network page when the connection returns. No offline punch entry.
