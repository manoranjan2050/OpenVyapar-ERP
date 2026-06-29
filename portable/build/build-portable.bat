@echo off
title OpenVyapar ERP - Build Portable Package
color 0E
setlocal EnableDelayedExpansion

echo.
echo  ══════════════════════════════════════════════════════════
echo   OpenVyapar ERP - Portable Package Builder
echo  ══════════════════════════════════════════════════════════
echo.

:: ── Paths ──────────────────────────────────────────────────────
:: build-portable.bat lives in: portable\build\
:: So portable root is:         portable\
:: And repo root is:             portable\..\

set "BUILD_DIR=%~dp0"
set "PORTABLE=%BUILD_DIR%..\"
set "REPO=%PORTABLE%..\\"
set "BACKEND=%REPO%backend"
set "FRONTEND=%REPO%frontend"
set "PHP_DIR=%PORTABLE%php"
set "APP_DIR=%PORTABLE%app"
set "WWW_DIR=%PORTABLE%www"

echo  Paths:
echo    Portable root : %PORTABLE%
echo    Backend       : %BACKEND%
echo    Frontend      : %FRONTEND%
echo.

:: ── STEP 1: Check prerequisites ────────────────────────────────
echo  [STEP 1/6] Checking prerequisites...
echo.

set ERRORS=0

:: Check Node.js
node --version >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] Node.js - Download from https://nodejs.org
    set /a ERRORS+=1
) else (
    for /f %%v in ('node --version 2^>nul') do echo    [OK] Node.js %%v
)

:: Check npm
npm --version >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] npm - comes with Node.js
    set /a ERRORS+=1
) else (
    for /f %%v in ('npm --version 2^>nul') do echo    [OK] npm %%v
)

:: Check Composer
composer --version >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] Composer - Download from https://getcomposer.org
    set /a ERRORS+=1
) else (
    echo    [OK] Composer found
)

:: Check PowerShell (for download + zip)
powershell -Command "exit 0" >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] PowerShell - Required for downloading and zipping
    set /a ERRORS+=1
) else (
    echo    [OK] PowerShell found
)

:: Check backend exists
if not exist "%BACKEND%\artisan" (
    echo    [MISSING] Backend not found at: %BACKEND%
    set /a ERRORS+=1
) else (
    echo    [OK] Backend found
)

:: Check frontend exists
if not exist "%FRONTEND%\package.json" (
    echo    [MISSING] Frontend not found at: %FRONTEND%
    set /a ERRORS+=1
) else (
    echo    [OK] Frontend found
)

echo.
if %ERRORS% GTR 0 (
    echo  [ERROR] %ERRORS% prerequisite(s) missing. Fix them and re-run.
    echo.
    pause
    exit /b 1
)
echo  All prerequisites OK.
echo.
pause

:: ── STEP 2: Download PHP portable ─────────────────────────────
echo.
echo  [STEP 2/6] PHP portable...
echo.

if exist "%PHP_DIR%\php.exe" (
    echo  PHP already downloaded. Skipping.
) else (
    echo  Downloading PHP 8.2 portable...
    call "%BUILD_DIR%download-deps.bat" silent
    if errorlevel 1 (
        echo  [ERROR] PHP download failed.
        pause
        exit /b 1
    )
)
echo  Done.
echo.
pause

:: ── STEP 3: Build Vue frontend ─────────────────────────────────
echo.
echo  [STEP 3/6] Building Vue 3 frontend...
echo.

cd /d "%FRONTEND%"
echo  Running: npm install
call npm install
if errorlevel 1 (
    echo  [ERROR] npm install failed.
    pause
    exit /b 1
)

echo.
echo  Writing .env.production.local (API URL = localhost:8000)...
echo VITE_API_URL=http://localhost:8000/api> .env.production.local

echo  Running: npm run build
call npm run build
if errorlevel 1 (
    echo  [ERROR] npm run build failed.
    pause
    exit /b 1
)

echo  Copying dist to portable\www\...
if exist "%WWW_DIR%" rmdir /s /q "%WWW_DIR%"
mkdir "%WWW_DIR%"
xcopy /s /e /q /y "%FRONTEND%\dist\*" "%WWW_DIR%\" >nul
del /f ".env.production.local" >nul 2>&1
echo  Done.
echo.
pause

:: ── STEP 4: Build Laravel backend (no-dev) ────────────────────
echo.
echo  [STEP 4/6] Preparing Laravel backend...
echo.

cd /d "%BACKEND%"
echo  Running: composer install --no-dev --optimize-autoloader
call composer install --no-dev --optimize-autoloader
if errorlevel 1 (
    echo  [ERROR] composer install failed.
    pause
    exit /b 1
)

echo.
echo  Copying backend to portable\app\...
if exist "%APP_DIR%" rmdir /s /q "%APP_DIR%"
mkdir "%APP_DIR%"

:: Copy everything except runtime/dev dirs
for %%D in (app bootstrap config database public resources routes storage vendor artisan composer.json) do (
    if exist "%BACKEND%\%%D" (
        xcopy /s /e /q /y "%BACKEND%\%%D" "%APP_DIR%\%%D\" >nul 2>&1
        if exist "%BACKEND%\%%D" if not exist "%BACKEND%\%%D\" (
            copy /y "%BACKEND%\%%D" "%APP_DIR%\%%D" >nul
        )
    )
)

:: Also copy artisan file (it's not a directory)
copy /y "%BACKEND%\artisan" "%APP_DIR%\artisan" >nul 2>&1

:: Create required empty runtime dirs
for %%D in (
    storage\app\public
    storage\framework\cache\data
    storage\framework\sessions
    storage\framework\views
    storage\logs
    bootstrap\cache
    database
) do (
    if not exist "%APP_DIR%\%%D" mkdir "%APP_DIR%\%%D"
)

echo  Done.
echo.
pause

:: ── STEP 5: Create config files ───────────────────────────────
echo.
echo  [STEP 5/6] Writing config files...
echo.

:: Copy php.ini
copy /y "%PORTABLE%config\php.ini" "%PHP_DIR%\php.ini" >nul
echo  php.ini applied.

:: Create logs dir
if not exist "%PORTABLE%logs" mkdir "%PORTABLE%logs"

echo  Done.
echo.
pause

:: ── STEP 6: Create ZIP ────────────────────────────────────────
echo.
echo  [STEP 6/6] Creating portable ZIP...
echo.

set "DIST=%PORTABLE%dist"
if not exist "%DIST%" mkdir "%DIST%"

set "ZIP=%DIST%\OpenVyapar-ERP-Portable-v1.0.0-Windows.zip"
if exist "%ZIP%" del /f "%ZIP%"

echo  Compressing... (this may take a minute)
powershell -Command ^
    "$ProgressPreference='SilentlyContinue';" ^
    "Compress-Archive -Path '%PORTABLE%app','%PORTABLE%www','%PORTABLE%php','%PORTABLE%launcher','%PORTABLE%build','%PORTABLE%config','%PORTABLE%start.bat','%PORTABLE%stop.bat','%PORTABLE%README-PORTABLE.md' -DestinationPath '%ZIP%' -CompressionLevel Optimal"

if errorlevel 1 (
    echo  [ERROR] ZIP creation failed.
    pause
    exit /b 1
)

for %%F in ("%ZIP%") do set ZIP_SIZE=%%~zF
set /a ZIP_MB=%ZIP_SIZE%/1048576

echo.
echo  ══════════════════════════════════════════════════════════
echo   BUILD COMPLETE!
echo.
echo   ZIP : %ZIP%
echo   Size: ~%ZIP_MB% MB
echo.
echo   Share this ZIP with users.
echo   They extract it and double-click start.bat — done!
echo  ══════════════════════════════════════════════════════════
echo.
pause
