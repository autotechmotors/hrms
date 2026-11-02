@echo off
set "LINK=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\HRMS Local Server.lnk"
if exist "%LINK%" del "%LINK%"
schtasks /Delete /TN "HRMS Keep Alive" /F >nul 2>&1
echo Removed HRMS Startup + Keep Alive task.
pause
