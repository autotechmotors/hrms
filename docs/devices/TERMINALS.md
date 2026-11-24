# Terminals

Devices is the hub for Hikvision terminals. Enroll, block, and terminal users stay. Do not change tokens, throttles, CSRF exceptions, or payload handling.

The IN terminal and the OUT terminal are separate devices. `HIKVISION_IN_*` is the in reader. `HIKVISION_OUT_*` is the out reader. Each keeps its own register id, host, and login. Face capture reads each terminal’s `CaptureFaceData` capabilities: some accept `url`, others only `binary`. A person who has punched in is a blocklist user on the OUT terminal until an approved gate pass or the minutes-before-out window, when they become a normal user again. See `docs/attendance/GATE-PASS.md`.

## Code

- `app/Http/Controllers/Operations/DeviceController.php`
- `app/Http/Controllers/Operations/DeviceEventController.php`
- `app/Http/Controllers/Operations/HikvisionEventController.php`
- `app/Services/Devices/DeviceSync.php`
- `app/Services/Devices/EmployeePush.php` (Hikvision ISAPI)
- Public routes: `device-events`, `hikvision-events/{token}`
- Brand: Register ID starting `HIK` = Hikvision ISAPI

## Menu

The authenticated device screens stay behind `devices.manage`. Device Administrator keeps employee view plus devices. Manager and Accounts do not gain this menu. Map: `docs/roles/NAV-PERMISSIONS.md`.

## PWA

The service worker must not intercept `device-events` or `hikvision-events`. See `docs/prompts/HRMS-PWA.md`.

## Visual

The device list and device detail may use liquid-glass chrome. Credential enroll and the local PC start/stop actions stay on solid buttons with the same routes.
