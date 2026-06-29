@echo off
title OpenVyapar ERP - Build Portable Package
color 0E
setlocal EnableDelayedExpansion

:: ─────────────────────────────────────────────────────────────────
::  BUILD SCRIPT - Run this as developer to create the portable ZIP
::  Requirements: Node.js, Composer, curl, PowerShell
::
::  Output: dist\OpenVyapar-ERP-Portable-v1.0.0.zip
:: ─────────────────────────────────────────────────────────────────

set "SCRIPT_DIR=%~dp0"
set "ROOT=%SCRIPT_DIR%..\"
set "REPO_ROOT=%ROOT%..\"
set "DIST=%ROOT%dist"
set "PKG=%DIST%\OpenVyapar-ERP-Portable"
set "VERSION=1.0.0"

:: PHP version to bundle
set "PHP_VERSION=8.2.27"
set "PHP_URL=https://windows.php.net/downloads/releases/php-%PHP_VERSION%-nts-Win32-vs16-x64.zip"
set "PHP_ZIP=%DIST%\php-portable.zip"

echo.
echo  ══════════════════════════════════════════════════════════════
echo   OpenVyapar ERP - Portable Package Builder v%VERSION%
echo   Developer: MANORANJAN  ^|  https://manoranjan.dev
echo  ══════════════════════════════════════════════════════════════
echo.

:: Clean previous build
echo  [CLEAN] Removing previous build...
if exist "%PKG%" rmdir /s /q "%PKG%"
if not exist "%DIST%" mkdir "%DIST%"
mkdir "%PKG%"
mkdir "%PKG%\app"
mkdir "%PKG%\www"
mkdir "%PKG%\php"
mkdir "%PKG%\logs"
mkdir "%PKG%\launcher"
mkdir "%PKG%\config"

echo.
echo  ─── STEP 1: Download PHP %PHP_VERSION% Portable ───────────────────────
if not exist "%PHP_ZIP%" (
    echo  Downloading PHP %PHP_VERSION% (NTS Windows x64)...
    powershell -Command "Invoke-WebRequest -Uri '%PHP_URL%' -OutFile '%PHP_ZIP%' -UseBasicParsing"
    if errorlevel 1 (
        echo  [ERROR] PHP download failed. Check your internet connection.
        echo  Manual download: %PHP_URL%
        pause
        exit /b 1
    )
    echo  Download complete.
) else (
    echo  PHP archive already downloaded, skipping.
)

echo  Extracting PHP...
powershell -Command "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath '%PKG%\php' -Force"
echo  PHP extracted to package\php\

echo.
echo  ─── STEP 2: Copy PHP config ──────────────────────────────────
copy "%ROOT%config\php.ini" "%PKG%\php\php.ini" /Y >nul
echo  php.ini copied.

echo.
echo  ─── STEP 3: Build Vue frontend ──────────────────────────────
echo  Running npm install + npm run build...
cd /d "%REPO_ROOT%frontend"
call npm install --silent
if errorlevel 1 (
    echo  [ERROR] npm install failed.
    pause
    exit /b 1
)

:: Set API URL to relative for portable (PHP serves both)
echo VITE_API_URL=http://localhost:8000/api > .env.production.local
call npm run build
if errorlevel 1 (
    echo  [ERROR] npm build failed.
    pause
    exit /b 1
)

echo  Copying frontend dist to package\www\...
xcopy /s /e /q /y "%REPO_ROOT%frontend\dist\*" "%PKG%\www\" >nul
echo  Frontend build complete.

echo.
echo  ─── STEP 4: Copy Laravel backend ────────────────────────────
echo  Copying app files (excluding dev files)...
cd /d "%REPO_ROOT%backend"

:: Run composer install with no-dev for production
echo  Running composer install --no-dev --optimize-autoloader...
composer install --no-dev --optimize-autoloader --quiet
if errorlevel 1 (
    echo  [ERROR] Composer install failed.
    pause
    exit /b 1
)

:: Copy backend (exclude dev/runtime dirs)
xcopy /s /e /q /y "%REPO_ROOT%backend\*" "%PKG%\app\" ^
    /EXCLUDE:"%SCRIPT_DIR%xcopy-exclude.txt" >nul

:: Create required directories
mkdir "%PKG%\app\storage\app\public" 2>nul
mkdir "%PKG%\app\storage\framework\cache\data" 2>nul
mkdir "%PKG%\app\storage\framework\sessions" 2>nul
mkdir "%PKG%\app\storage\framework\views" 2>nul
mkdir "%PKG%\app\storage\logs" 2>nul
mkdir "%PKG%\app\bootstrap\cache" 2>nul
mkdir "%PKG%\app\database" 2>nul

:: Set writable .gitkeep files
echo. > "%PKG%\app\storage\logs\.gitkeep"
echo. > "%PKG%\app\database\.gitkeep"

echo  Backend copied.

echo.
echo  ─── STEP 5: Copy launcher scripts ───────────────────────────
copy "%ROOT%launcher\start.bat"  "%PKG%\start.bat"  /Y >nul
copy "%ROOT%launcher\stop.bat"   "%PKG%\stop.bat"   /Y >nul
copy "%ROOT%launcher\setup.bat"  "%PKG%\launcher\setup.bat"  /Y >nul
copy "%ROOT%launcher\reset.bat"  "%PKG%\launcher\reset.bat"  /Y >nul
echo  Launcher scripts copied.

echo.
echo  ─── STEP 6: Copy README ──────────────────────────────────────
copy "%ROOT%README-PORTABLE.md" "%PKG%\README.txt" /Y >nul
echo  README copied.

echo.
echo  ─── STEP 7: Create final ZIP ────────────────────────────────
set "ZIP_NAME=OpenVyapar-ERP-Portable-v%VERSION%-Windows.zip"
set "ZIP_PATH=%DIST%\%ZIP_NAME%"
if exist "%ZIP_PATH%" del /f "%ZIP_PATH%"
echo  Compressing to %ZIP_NAME%...
powershell -Command "Compress-Archive -Path '%PKG%\*' -DestinationPath '%ZIP_PATH%' -CompressionLevel Optimal"
if errorlevel 1 (
    echo  [ERROR] ZIP creation failed.
    pause
    exit /b 1
)

:: Show size
for %%F in ("%ZIP_PATH%") do set ZIP_SIZE=%%~zF
set /a ZIP_MB=%ZIP_SIZE%/1048576

echo.
echo  ══════════════════════════════════════════════════════════════
echo   BUILD COMPLETE!
echo.
echo   Output: dist\%ZIP_NAME%
echo   Size:   ~%ZIP_MB% MB
echo.
echo   Users: download ZIP, extract anywhere, run start.bat
echo  ══════════════════════════════════════════════════════════════
echo.
pause
