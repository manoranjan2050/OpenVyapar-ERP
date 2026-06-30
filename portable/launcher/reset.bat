@echo off
title OpenVyapar ERP - Reset to Factory Defaults
color 0C
setlocal

:: ROOT = portable\ (one level up from launcher\)
set "ROOT=%~dp0..\"
set "APP=%ROOT%app"

echo.
echo  ╔══════════════════════════════════════════════╗
echo  ║   WARNING: This will DELETE ALL YOUR DATA!  ║
echo  ╚══════════════════════════════════════════════╝
echo.
echo  This will permanently delete:
echo    - All invoices, payments, credit notes
echo    - All customers, suppliers, products
echo    - All settings and user accounts
echo    - All backup files stored in the app
echo.
echo  Your PHP installation will NOT be touched.
echo.
set /p CONFIRM="  Type  YES  to confirm reset: "
if /i not "%CONFIRM%"=="YES" (
    echo.
    echo  Reset cancelled. No data was deleted.
    pause
    exit /b 0
)

echo.
echo  Stopping servers...
call "%ROOT%stop.bat" silent

echo  Deleting database...
if exist "%APP%\database\openvyapar.sqlite" (
    del /f "%APP%\database\openvyapar.sqlite"
    echo  Database deleted.
)

echo  Removing app .env...
if exist "%APP%\.env" del /f "%APP%\.env"

echo  Clearing Laravel caches...
if exist "%APP%\bootstrap\cache" (
    del /f /q "%APP%\bootstrap\cache\*.php" >nul 2>&1
)
if exist "%APP%\storage\framework\sessions" (
    del /f /q "%APP%\storage\framework\sessions\*" >nul 2>&1
)
if exist "%APP%\storage\framework\cache\data" (
    rd /s /q "%APP%\storage\framework\cache\data" >nul 2>&1
)

echo  Removing installation marker...
if exist "%ROOT%.installed" del /f "%ROOT%.installed"
if exist "%ROOT%.running"   del /f "%ROOT%.running"

echo.
echo  ══════════════════════════════════════════════
echo   Reset complete!
echo   Run start.bat to set up fresh.
echo  ══════════════════════════════════════════════
echo.
pause
