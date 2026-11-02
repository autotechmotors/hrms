@echo off
cd /d "%~dp0"
wscript.exe //B "%~dp0hrms-watchdog-hidden.vbs"
echo HRMS started in background (no CMD windows).
echo Open: http://192.168.1.68:8000
timeout /t 3 >nul
