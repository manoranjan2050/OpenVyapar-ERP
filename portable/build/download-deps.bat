@echo off
title OpenVyapar ERP - Downloading PHP
color 0B
setlocal EnableDelayedExpansion

:: ─────────────────────────────────────────────────────
::  download-deps.bat
::  Downloads PHP portable for Windows (x64 NTS)
::  Can be run standalone OR called by start.bat
:: ─────────────────────────────────────────────────────

:: ROOT = portable\ folder (one level up from build\)
set "ROOT=%~dp0..\"
set "PHP_DIR=%ROOT%php"
set "PHP_EXE=%PHP_DIR%\php.exe"
set "TMP_ZIP=%TEMP%\ov-php-portable.zip"

:: PHP version to download
set "PHP_VER=8.2.27"
set "PHP_URL=https://windows.php.net/downloads/releases/php-%PHP_VER%-nts-Win32-vs16-x64.zip"

echo.
echo  ─────────────────────────────────────────────────────
echo   OpenVyapar ERP - Dependency Downloader
echo   Downloading PHP %PHP_VER% (NTS Windows x64)
echo  ─────────────────────────────────────────────────────
echo.

:: Already downloaded?
if exist "%PHP_EXE%" (
    echo  [OK] PHP already present at %PHP_EXE%
    echo  Nothing to download.
    echo.
    if not "%1"=="silent" pause
    exit /b 0
)

:: Check PowerShell (needed for download)
powershell -Command "exit 0" >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] PowerShell is required but not available.
    echo  Please install PowerShell or download PHP manually from:
    echo  %PHP_URL%
    echo  Extract into: %PHP_DIR%\
    pause
    exit /b 1
)

:: Check internet
echo  Checking internet connection...
powershell -Command "Test-NetConnection -ComputerName windows.php.net -Port 443 -InformationLevel Quiet -WarningAction SilentlyContinue" >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] Cannot reach windows.php.net
    echo  Please check your internet connection.
    pause
    exit /b 1
)

echo  Downloading PHP %PHP_VER% (~30 MB)...
echo  URL: %PHP_URL%
echo.

powershell -Command ^
    "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; " ^
    "$ProgressPreference = 'SilentlyContinue'; " ^
    "Invoke-WebRequest -Uri '%PHP_URL%' -OutFile '%TMP_ZIP%' -UseBasicParsing; " ^
    "Write-Host 'Download complete.'"

if errorlevel 1 (
    echo.
    echo  [ERROR] Download failed.
    echo  Try downloading manually:
    echo    URL  : %PHP_URL%
    echo    Save as: %TMP_ZIP%
    echo  Then re-run this script.
    pause
    exit /b 1
)

echo.
echo  Extracting PHP to %PHP_DIR%\...
if not exist "%PHP_DIR%" mkdir "%PHP_DIR%"

powershell -Command ^
    "$ProgressPreference = 'SilentlyContinue'; " ^
    "Expand-Archive -Path '%TMP_ZIP%' -DestinationPath '%PHP_DIR%' -Force; " ^
    "Write-Host 'Extraction complete.'"

if errorlevel 1 (
    echo  [ERROR] Extraction failed.
    pause
    exit /b 1
)

:: Copy our custom php.ini
if exist "%ROOT%config\php.ini" (
    copy /y "%ROOT%config\php.ini" "%PHP_DIR%\php.ini" >nul
    echo  Custom php.ini applied.
)

:: Copy php.ini-production as fallback if custom not found
if not exist "%PHP_DIR%\php.ini" (
    if exist "%PHP_DIR%\php.ini-production" (
        copy /y "%PHP_DIR%\php.ini-production" "%PHP_DIR%\php.ini" >nul
        echo  Default php.ini applied.
    )
)

:: Delete temp zip
del /f "%TMP_ZIP%" >nul 2>&1

:: Verify
if not exist "%PHP_EXE%" (
    echo  [ERROR] php.exe not found after extraction.
    echo  The ZIP may have a different structure. Check: %PHP_DIR%\
    pause
    exit /b 1
)

:: Show PHP version
echo.
"%PHP_EXE%" -r "echo 'PHP version: ' . PHP_VERSION . PHP_EOL;"
echo.
echo  ─────────────────────────────────────────────────────
echo   PHP downloaded and ready!
echo   Location: %PHP_DIR%\php.exe
echo  ─────────────────────────────────────────────────────
echo.

if not "%1"=="silent" (
    echo  You can now run start.bat
    echo.
    pause
)
exit /b 0
