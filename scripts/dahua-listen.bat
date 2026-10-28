@echo off
REM Dahua EZAPI HTTPS listener — replaces AutoRegisterAndPush_Person.exe
REM Keep this window open. Point the terminal AutoRegister server to DAHUA_PUSH_HOST:DAHUA_PUSH_PORT.
cd /d "%~dp0.."
php artisan dahua:listen
pause
