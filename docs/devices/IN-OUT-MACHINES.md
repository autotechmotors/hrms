# IN and OUT machines

Two Hikvision face terminals at the office door. The device screens are `docs/devices/TERMINALS.md`. Who may leave, and how a scan is labelled, is `docs/attendance/GATE-PASS.md`. This file is the wiring and the rules that must stay.

The live site is `https://hrms.autotechmotors.com`. A scan is stored only after the site accepts it. The attendance page already refreshes about every 1.5 seconds. A late row means the punch was not saved yet.

Both doors take the application timezone (`config/app.php`, `Asia/Kolkata`) and the server clock. `hikvision:pull` checks IN and OUT every minute and writes the terminal clock when it is off by more than 20 seconds, or the zone is not that offset. These terminals store `+05:30` as `CST-5:30:00`. The mode stays manual, so a device NTP server does not pull the clock away.

## Doors

| Door | Office address | Public forward | Register id | Firmware | Face engine |
|---|---|---|---|---|---|
| IN | `192.168.1.156` port 80 | `103.214.226.146` TCP 18080 | `HIK1921681156` | V4.38 | FR8 |
| OUT | `192.168.1.160` port 80 | `103.214.226.146` TCP 18081 | `HIK1921681160` | V3.21 | FR7 |

Both are the same hardware. The face engines are not the same. Sophos allows only the HRMS server onto 18080 and 18081. An office PC can open the LAN addresses directly. A test of 18080 from the office does not prove the server path. The office gateway is `192.168.1.1`.

`HIKVISION_*` is the IN reader. `HIKVISION_OUT_*` is the OUT reader. Each has its own host, register id, and login. The OUT password line must not end with a space, or the OUT login is refused. Do not print passwords, `APP_KEY`, or the event token. Do not rotate them. The receiver is `POST /hikvision-events/{token}`. The token is the existing device forward token.

A device row may store the public address. The scan reports the office address. The receiver matches `lan_ip`, then the register id with the dots removed, so `192.168.1.160` finds `HIK1921681160`.

## Which events are a punch

Store only a real Thank you:

- Minor 75 (`0x4b`) is a face pass.
- Minor 38 (`0x26`) is a fingerprint pass.
- Minor 1 with a finger mode, and no “fail” in the mode, is the older OUT fingerprint path.

Do not store these, even when the mode text contains `face`:

- Minor 6 is no permission. On these doors the mode is `cardOrFaceOrFp`.
- Minors 7, 8, and 9 are other denials.
- Minor 39 is a fingerprint mismatch.
- Minors 76 to 80 are authentication failed.
- A mode that contains `fail` or `invalid`.
- Minor 21 (door unlock) and minor 22 (door close) have no employee code.

`HikvisionEvents::passed` is the only filter. The push and the minute pull both use it. Do not treat “the mode contains face” as success.

One Thank you often writes two minor 75 rows one second apart, plus a door open and a door close. `DeviceSync::SAME_VISIT_SECONDS` is 120. Two punches merge only when `gate_pass_id` and `direction` are the same. A Personal in and a Personal out a minute apart both stay on the day.

Production does not create an unknown person from a scan.

## How a Thank you reaches the site

The IN host is already correct. Leave it. Host 1 is HTTPS, hostname `hrms.autotechmotors.com`, port 443, JSON, heartbeat 1, event list, minors `0x4b,0x26` only, picture type binary.

The OUT host is `0.0.0.0` port 0 on purpose. OUT firmware V3.21 has no JSON format field. It posts `multipart/form-data` with a JSON part named `event_log`. The receiver already reads that JSON. The URL on this firmware may be at most 128 characters.

On 25 Nov 2026 the OUT host was pointed at the live site the same way as IN. The machine then sent its oldest unsent success first, starting 6 April 2026, and today’s scan waited behind that queue. A slice of 6 April reached the site before the host was turned off. Check that date if those rows look new. Do not point the OUT host at the live site again until the next queued event is current. A new host does not start at the latest Thank you. Until then, `hikvision:pull` once a minute is the OUT path. That job uses `withoutOverlapping(5)`, so a stuck pull can hold the next one for five minutes.

## Faces, fingers, and block

OUT Anti-Spoofing stays General. Advanced showed “adjust face position” while the fingerprint still worked. Do not set Advanced again, and do not recapture faces for that message.

An FR8 model must not be written onto the FR7 terminal. `HikvisionCredentials::shareMatchingFace` reads the IN face first and skips a target whose engine differs. It never sends fingerprints. Do not run `hikvision:mirror-faces`.

Block and unblock change only the person type, `blackList` or `normal`, with `PUT /ISAPI/AccessControl/UserInfo/SetUp`. That is `HikvisionEnroll::setUserType`. `UserInfo/Modify` on the OUT firmware deletes the face and the fingerprint. Do not call Modify, `setEnabled`, `ensureUser` with a refresh, or `restoreFace` to block or unblock. `EmployeePush::faceAccess` changes the type only. It does not upload the face again.

Do not copy a fingerprint onto a second person. The terminal rejects it as a duplicate of the first person. Do not run `hikvision:keep-out --apply`. Do not start a live face capture.

After the first IN of the day, that person is blocklist on the OUT terminal until an approved gate pass covers the moment, or until shift end minus Settings “Minutes before out” (`company_settings.out_inactive_minutes`, default 10, from 0 to 240). Out Door Closed on the employee list keeps them blocked on OUT even inside that window. Suspend on that list blocks them on the IN terminal. Both use SetUp. Coming back before the pass ends does not close OUT. Leaving again before the end is another out of the same type. OUT blocks when the pass time is over. `faces:sync` applies this once a minute. Labels and pay stay in `docs/attendance/GATE-PASS.md`.

Do not run a full people sync onto the doors to repair a face. A sync must not rewrite a face that has no model, and it must not send fingerprints.

## Code

- `app/Services/Devices/HikvisionEvents.php` decides which minor is a punch.
- `app/Http/Controllers/Operations/HikvisionEventController.php` matches the door, then `DeviceSync::receive`.
- `app/Services/Devices/HikvisionEnroll.php` changes the person type with SetUp.
- `app/Services/Devices/HikvisionCredentials.php` copies a face only when the engines match.
- `app/Services/Devices/FaceAccess.php` chooses who is blocked on OUT.
- `app/Services/Operations/GatePassAccess.php` tags the punch as a pass in or out.
- `routes/console.php` runs `hikvision:pull`, `faces:sync`, and `gatepass:expire` every minute.
