@echo off
REM Ensure XAMPP MySQL (and Apache if installed as service) are up — no windows.
setlocal
set "XAMPP=C:\xampp"
set "VBS=%~dp0run-hidden.vbs"

REM --- MySQL :3306 ---
netstat -ano | findstr /C:":3306 " | findstr LISTENING >nul
if not errorlevel 1 goto apache

net start mysql >nul 2>&1
net start MySQL >nul 2>&1
ping -n 3 127.0.0.1 >nul

netstat -ano | findstr /C:":3306 " | findstr LISTENING >nul
if not errorlevel 1 goto apache

REM Service missing / failed — start mysqld hidden (same as XAMPP)
if exist "%XAMPP%\mysql\bin\mysqld.exe" (
  wscript.exe //B "%VBS%" "%XAMPP%\mysql" "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini"
  ping -n 4 127.0.0.1 >nul
)

:apache
REM --- Apache :80 (only if Windows service exists) ---
sc query Apache2.4 >nul 2>&1
if errorlevel 1 goto done
netstat -ano | findstr /C:":80 " | findstr LISTENING >nul
if not errorlevel 1 goto done
net start Apache2.4 >nul 2>&1

:done
endlocal
exit /b 0
