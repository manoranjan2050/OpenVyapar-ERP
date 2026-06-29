@echo off
title OpenVyapar ERP - Reset
color 0C
setlocal

set "ROOT=%~dp0..\"
set "PHP=%ROOT%php\php.exe"
set "APP=%ROOT%app"

echo.
echo  ╔══════════════════════════════════════════╗
echo  ║   WARNING: This will DELETE all data!   ║
echo  ╚══════════════════════════════════════════╝
echo.
echo  This will reset OpenVyapar ERP to factory defaults.
echo  ALL invoices, customers, products, and settings will be LOST.
echo.
set /p CONFIRM="  Type YES to confirm reset: "
if /i not "%CONFIRM%"=="YES" (
    echo  Reset cancelled.
    pause
    exit /b 0
)

echo.
echo  Stopping servers...
call "%ROOT%launcher\stop.bat" silent

echo  Deleting database...
if exist "%APP%\database\openvyapar.sqlite" del /f "%APP%\database\openvyapar.sqlite"

echo  Removing installation marker...
if exist "%ROOT%.installed" del /f "%ROOT%.installed"
if exist "%ROOT%.running"  del /f "%ROOT%.running"

echo  Clearing cache...
if exist "%APP%\bootstrap\cache" (
    del /f /q "%APP%\bootstrap\cache\*.php" >nul 2>&1
)

echo.
echo  Reset complete. Run start.bat to set up fresh.
echo.
pause
