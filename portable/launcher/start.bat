@echo off
title OpenVyapar ERP - Starting...
color 0A
setlocal EnableDelayedExpansion

:: ─────────────────────────────────────────
::  OpenVyapar ERP - Portable Launcher
::  Developed by MANORANJAN (manoranjan2050)
::  https://manoranjan.dev
:: ─────────────────────────────────────────

set "ROOT=%~dp0"
set "PHP=%ROOT%php\php.exe"
set "APP=%ROOT%app"
set "WWW=%ROOT%www"
set "PORT=8000"
set "DB_FILE=%APP%\database\openvyapar.sqlite"
set "LOCK=%ROOT%.running"

cls
echo.
echo  ██████╗ ██████╗ ███████╗███╗   ██╗██╗   ██╗██╗   ██╗ █████╗ ██████╗  █████╗ ██████╗
echo  ██╔═══██╗██╔══██╗██╔════╝████╗  ██║██║   ██║╚██╗ ██╔╝██╔══██╗██╔══██╗██╔══██╗██╔══██╗
echo  ██║   ██║██████╔╝█████╗  ██╔██╗ ██║██║   ██║ ╚████╔╝ ███████║██████╔╝███████║██████╔╝
echo  ██║   ██║██╔═══╝ ██╔══╝  ██║╚██╗██║╚██╗ ██╔╝ ╚██╔╝  ██╔══██║██╔═══╝ ██╔══██║██╔══██╗
echo  ╚██████╔╝██║     ███████╗██║ ╚████║ ╚████╔╝   ██║   ██║  ██║██║     ██║  ██║██║  ██║
echo   ╚═════╝ ╚═╝     ╚══════╝╚═╝  ╚═══╝  ╚═══╝    ╚═╝   ╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝╚═╝  ╚═╝
echo.
echo  ─────────────────────────────────────────────────────────────────────
echo   Free ^& Open-Source GST-Ready ERP  ^|  Portable Edition  ^|  v1.0.0
echo   Developer: MANORANJAN  ^|  https://manoranjan.dev  ^|  AGPL v3
echo  ─────────────────────────────────────────────────────────────────────
echo.

:: Check PHP exists
if not exist "%PHP%" (
    echo  [ERROR] PHP not found at %PHP%
    echo  Please run build\download-deps.bat first to download PHP.
    echo.
    pause
    exit /b 1
)

:: Check app exists
if not exist "%APP%\artisan" (
    echo  [ERROR] Laravel app not found at %APP%
    echo  Please ensure the app folder is present.
    echo.
    pause
    exit /b 1
)

:: First-run setup
if not exist "%ROOT%.installed" (
    echo  [SETUP] First launch detected - running setup...
    echo.
    call "%ROOT%launcher\setup.bat"
    if errorlevel 1 (
        echo  [ERROR] Setup failed. Check the error above.
        pause
        exit /b 1
    )
)

:: Check if already running
if exist "%LOCK%" (
    echo  [INFO] OpenVyapar ERP is already running.
    echo  Opening browser...
    timeout /t 1 /nobreak >nul
    start "" "http://localhost:%PORT%"
    exit /b 0
)

echo  [>>] Starting OpenVyapar ERP...
echo.

:: Set environment for this session
set "PATH=%ROOT%php;%PATH%"
set "APP_ENV=production"

:: Write lock file
echo %PORT% > "%LOCK%"

:: Start PHP server (serves both API and frontend)
echo  [1/2] Starting application server on port %PORT%...
start "OpenVyapar-Server" /min cmd /c ""%PHP%" -S localhost:%PORT% -t "%APP%\public" "%APP%\public\index.php" 2>>"%ROOT%logs\server.log""

timeout /t 2 /nobreak >nul

:: Start Vue frontend (static file server on port 8080)
echo  [2/2] Starting frontend on port 8080...
start "OpenVyapar-Frontend" /min cmd /c ""%PHP%" -S localhost:8080 -t "%WWW%" 2>>"%ROOT%logs\frontend.log""

timeout /t 2 /nobreak >nul

:: Open browser
echo.
echo  ════════════════════════════════════════════════════════
echo   ✓  OpenVyapar ERP is running!
echo.
echo     App:      http://localhost:8080
echo     API:      http://localhost:%PORT%/api
echo     Login:    admin@demo.com  /  password
echo.
echo     Close this window to STOP the server.
echo  ════════════════════════════════════════════════════════
echo.

timeout /t 1 /nobreak >nul
start "" "http://localhost:8080"

:: Keep window open - closing it stops the server
echo  Press any key to stop OpenVyapar ERP...
pause >nul

:: Cleanup on exit
call "%ROOT%launcher\stop.bat" silent
echo.
echo  OpenVyapar ERP stopped. Goodbye!
timeout /t 2 /nobreak >nul
