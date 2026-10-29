@echo off
REM One-click local HRMS: web UI + scheduler (auto-starts dahua:listen, no separate window)
cd /d "%~dp0.."
start "HRMS schedule" cmd /c "php artisan schedule:work"
start "HRMS web" cmd /c "php artisan serve --host=192.168.1.68 --port=8000"
php artisan dahua:ensure-listen
echo.
echo Local HRMS started.
echo   UI:       http://192.168.1.68:8000
echo   Device:   Host IP 192.168.1.68  Port 60002  (Server 1 ON, Server 2 OFF)
echo   Listener: auto via schedule:work (DAHUA_AUTO_LISTEN=true)
echo.
pause
