@echo off
REM No title / no visible loop window when launched via hrms-watchdog-hidden.vbs
cd /d "%~dp0"
:loop
call "%~dp0hrms-local-service.bat" >nul 2>&1
ping -n 31 127.0.0.1 >nul
goto loop
