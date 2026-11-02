@echo off
cd /d "%~dp0.."
echo Public HTTPS share (cloudflared). Data stays on this PC.
echo Local HRMS must already be running on port 8000.
echo.
C:\xampp\php\php.exe artisan hrms:share
pause
