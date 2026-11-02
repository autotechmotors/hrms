@echo off
REM ONE TIME — Run as Administrator.
REM XAMPP MySQL = Windows service Automatic (boot ON, like HRMS watchdog).
cd /d "%~dp0"

net session >nul 2>&1
if errorlevel 1 (
  echo Right-click this file - Run as administrator.
  pause
  exit /b 1
)

set "MYSQL=C:\xampp\mysql"
if not exist "%MYSQL%\bin\mysqld.exe" (
  echo XAMPP MySQL not found at %MYSQL%
  pause
  exit /b 1
)

sc query mysql >nul 2>&1
if not errorlevel 1 (
  echo MySQL service already installed — set Automatic + start.
  sc config mysql start= auto >nul
  net start mysql
  goto done
)

echo Installing MySQL as Windows service...
if exist "%MYSQL%\bin\my.ini" copy /Y "%MYSQL%\bin\my.ini" "%windir%\my.ini" >nul
"%MYSQL%\bin\mysqld.exe" --install mysql --defaults-file="%MYSQL%\bin\my.ini"
if errorlevel 1 (
  echo Install failed. Open XAMPP Control Panel - MySQL - red Svc checkbox once.
  pause
  exit /b 1
)
sc config mysql start= auto >nul
net start mysql
if errorlevel 1 (
  echo Service installed but start failed. Check XAMPP / my.ini.
  pause
  exit /b 1
)

:done
echo.
echo OK — MySQL service Automatic.
echo PC boot = MySQL ON. If stopped, HRMS watchdog starts it again (ensure-xampp).
echo No need to open XAMPP Control Panel daily.
pause
