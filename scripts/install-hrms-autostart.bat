@echo off
cd /d "%~dp0"
echo === 1/2 XAMPP MySQL auto (needs Admin once) ===
echo If UAC asks, Allow.
powershell -NoProfile -Command "Start-Process -FilePath '%~dp0install-xampp-autostart.bat' -Verb RunAs -Wait" 2>nul
if errorlevel 1 (
  echo Skipped or denied. You can later run install-xampp-autostart.bat as Admin.
)
echo.
echo === 2/2 HRMS background watchdog ===
C:\xampp\php\php.exe "%~dp0install-startup.php"
echo.
pause
