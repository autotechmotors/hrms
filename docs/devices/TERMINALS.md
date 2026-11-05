# Terminals

Dahua devices, local PC bridge, enroll, block, and terminal users stay. Hikvision and ezapi ingest stay. Do not change tokens, throttles, CSRF exceptions, or payload handling.

## Code

- `app/Http/Controllers/Operations/DeviceController.php`
- `app/Http/Controllers/Operations/DeviceEventController.php`
- `app/Http/Controllers/Operations/HikvisionEventController.php`
- `app/Http/Controllers/Operations/EzapiController.php`
- `app/Services/Devices/DeviceSync.php`
- Public routes: `device-events`, `hikvision-events/{token}`, `ezapi/push/{action}`

## Menu

The authenticated device screens stay behind `devices.manage`. Device Administrator keeps employee view plus devices. Manager and Accounts do not gain this menu. Map: `docs/roles/NAV-PERMISSIONS.md`.

## PWA

The service worker must not intercept `device-events`, `hikvision-events`, or `ezapi`. See `docs/prompts/HRMS-PWA.md`.

## Visual

The device list and device detail may use liquid-glass chrome. Credential enroll and the local PC start/stop actions stay on solid buttons with the same routes.
