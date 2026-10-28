@echo off
cd /d "D:\Darshit Goti\hrms\hrms"
if not exist storage\logs mkdir storage\logs
:loop
netstat -ano | findstr /C:":60002" | findstr "LISTENING" >nul
if not errorlevel 1 (
  ping -n 6 127.0.0.1 >nul
  goto loop
)
echo [%date% %time%] starting>> storage\logs\dahua-listener.log
"C:\xampp\php\php.exe" scripts\dahua-listen.php 60002 >> storage\logs\dahua-listener.log 2>&1
echo [%date% %time%] stopped>> storage\logs\dahua-listener.log
ping -n 3 127.0.0.1 >nul
goto loop
