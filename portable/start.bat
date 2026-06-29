@echo off
title OpenVyapar ERP - Starting...
color 0A
chcp 65001 >nul
setlocal EnableDelayedExpansion

:: ─────────────────────────────────────────
::  OpenVyapar ERP - Portable Launcher
::  Developer: MANORANJAN
::  https://manoranjan.dev | AGPL v3
:: ─────────────────────────────────────────

:: ROOT is the folder this .bat lives in
set "ROOT=%~dp0"
set "PHP=%ROOT%php\php.exe"
set "APP=%ROOT%app"
set "WWW=%ROOT%www"
set "PORT_API=8000"
set "PORT_UI=8080"

cls
echo.
echo   ___                 __   __
echo  / _ \ _ __  ___ _ _ \ \ / /_ _ __ _ _ __  __ _ _ _
echo ^| (_) ^| '_ \/ -_) ' \ \ V / _` / _` ^| '_ \/ _` ^| '_^|
echo  \___/^| .__/\___ _^|^|_^| \_/\__,_\__,_^| .__/\__,_^|_^|
echo       ^|_^|                             ^|_^|
echo.
echo  ─────────────────────────────────────────────────────────────
echo   Free ^& Open-Source GST ERP  ^|  Portable Edition  ^|  v1.0.0
echo   Developer : MANORANJAN  ^|  https://manoranjan.dev
echo   License   : AGPL v3    ^|  github.com/manoranjan2050
echo  ─────────────────────────────────────────────────────────────
echo.

:: ── Check PHP ──────────────────────────────────────────────────
if not exist "%PHP%" (
    echo  [!] PHP not found. Downloading now...
    echo.
    call "%ROOT%build\download-deps.bat"
    if errorlevel 1 (
        echo.
        echo  [ERROR] PHP download failed.
        echo  Please check your internet connection and try again,
        echo  or manually download PHP from: https://windows.php.net/download
        echo  Extract into the php\ folder next to this start.bat
        echo.
        pause
        exit /b 1
    )
)

:: ── Check app ──────────────────────────────────────────────────
if not exist "%APP%\artisan" (
    echo  [ERROR] Laravel app folder not found.
    echo  Expected: %APP%\artisan
    echo.
    echo  This portable package may be incomplete.
    echo  Download the full package from:
    echo  https://github.com/manoranjan2050/OpenVyapar-ERP/releases
    echo.
    pause
    exit /b 1
)

:: ── Check frontend ─────────────────────────────────────────────
if not exist "%WWW%\index.html" (
    echo  [ERROR] Frontend folder not found.
    echo  Expected: %WWW%\index.html
    echo.
    pause
    exit /b 1
)

:: ── First-run setup ────────────────────────────────────────────
if not exist "%ROOT%.installed" (
    echo  [SETUP] First launch detected. Running setup...
    echo.
    call "%ROOT%launcher\setup.bat"
    if errorlevel 1 (
        echo.
        echo  [ERROR] Setup failed. See logs\setup.log for details.
        echo.
        pause
        exit /b 1
    )
    echo.
)

:: ── Already running? ───────────────────────────────────────────
if exist "%ROOT%.running" (
    echo  [i] OpenVyapar ERP is already running.
    start "" "http://localhost:%PORT_UI%"
    exit /b 0
)

:: ── Set PHP in PATH ────────────────────────────────────────────
set "PATH=%ROOT%php;%PATH%"

:: ── Create logs dir ────────────────────────────────────────────
if not exist "%ROOT%logs" mkdir "%ROOT%logs"

:: ── Write lock ─────────────────────────────────────────────────
echo running > "%ROOT%.running"

:: ── Start API server ───────────────────────────────────────────
echo  [1/2] Starting API server   ^> http://localhost:%PORT_API% ...
start "OV-API" /min cmd /c ^
    "cd /d "%APP%" && "%PHP%" -S localhost:%PORT_API% -t public public\index.php >> "%ROOT%logs\api.log" 2>&1"

:: ── Start frontend static server ───────────────────────────────
echo  [2/2] Starting UI  server   ^> http://localhost:%PORT_UI% ...
start "OV-UI" /min cmd /c ^
    ""%PHP%" -S localhost:%PORT_UI% -t "%WWW%" >> "%ROOT%logs\ui.log" 2>&1"

:: ── Wait for servers ───────────────────────────────────────────
echo.
echo  Waiting for servers to be ready...
timeout /t 3 /nobreak >nul

:: ── Open browser ───────────────────────────────────────────────
start "" "http://localhost:%PORT_UI%"

:: ── Ready banner ───────────────────────────────────────────────
echo.
echo  ════════════════════════════════════════════════════════════
echo.
echo    OpenVyapar ERP is running!
echo.
echo    App   :  http://localhost:%PORT_UI%
echo    API   :  http://localhost:%PORT_API%
echo.
echo    Login :  admin@demo.com
echo    Pass  :  password
echo.
echo    Close this window OR press any key to STOP the server.
echo.
echo  ════════════════════════════════════════════════════════════
echo.

pause >nul

:: ── Stop on exit ───────────────────────────────────────────────
call "%ROOT%stop.bat" silent
echo.
echo  OpenVyapar ERP stopped. Goodbye!
timeout /t 2 /nobreak >nul
