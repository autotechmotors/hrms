@echo off
setlocal
cd /d "%~dp0.."

set "PHP=C:\xampp\php\php.exe"
set "HOST=192.168.1.68"
set "WEB_PORT=8000"
set "ROOT=%CD%"
set "LOGDIR=%ROOT%\storage\logs"
set "VBS=%~dp0run-hidden.vbs"
if not exist "%LOGDIR%" mkdir "%LOGDIR%"

REM 1) XAMPP MySQL first (same auto style as HRMS)
call "%~dp0ensure-xampp.bat"

REM 2) Laravel schedule
wmic process where "CommandLine like '%%schedule:work%%'" get ProcessId 2>nul | findstr /R "[0-9]" >nul
if errorlevel 1 (
  wscript.exe //B "%VBS%" "%ROOT%" "%PHP%" artisan schedule:work
)

REM 3) HRMS web
netstat -ano | findstr /C:":%WEB_PORT% " | findstr LISTENING >nul
if errorlevel 1 (
  wscript.exe //B "%VBS%" "%ROOT%" "%PHP%" artisan serve --host=%HOST% --port=%WEB_PORT%
)

endlocal
exit /b 0
