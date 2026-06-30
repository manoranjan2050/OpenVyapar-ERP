@echo off
title OpenVyapar ERP - Build Portable Package
color 0E
setlocal EnableDelayedExpansion

echo.
echo  ===========================================================
echo   OpenVyapar ERP - Portable Package Builder
echo  ===========================================================
echo.

:: ----------------------------------------------------------------
:: Paths  (build-portable.bat lives in portable\build\)
:: ----------------------------------------------------------------
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

:: ----------------------------------------------------------------
:: STEP 1 - Prerequisites
:: ----------------------------------------------------------------
echo  [STEP 1/6] Checking prerequisites...
echo.

set ERRORS=0

node --version >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] Node.js  -  https://nodejs.org
    set /a ERRORS+=1
) else (
    for /f "delims=" %%v in ('node --version 2^>nul') do echo    [OK] Node.js %%v
)

npm --version >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] npm  (comes with Node.js)
    set /a ERRORS+=1
) else (
    for /f "delims=" %%v in ('npm --version 2^>nul') do echo    [OK] npm %%v
)

composer --version >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] Composer  -  https://getcomposer.org
    set /a ERRORS+=1
) else (
    echo    [OK] Composer found
)

powershell -Command "exit 0" >nul 2>&1
if errorlevel 1 (
    echo    [MISSING] PowerShell  (required for download + zip)
    set /a ERRORS+=1
) else (
    echo    [OK] PowerShell found
)

if not exist "%BACKEND%\artisan" (
    echo    [MISSING] Backend not found at: %BACKEND%
    set /a ERRORS+=1
) else (
    echo    [OK] Backend found
)

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

:: ----------------------------------------------------------------
:: STEP 2 - Download PHP portable
:: ----------------------------------------------------------------
echo  [STEP 2/6] PHP portable...
echo.

if exist "%PHP_DIR%\php.exe" (
    echo  PHP already present - skipping download.
) else (
    echo  Downloading PHP 8.2 NTS portable...
    call "%BUILD_DIR%download-deps.bat" silent
    if errorlevel 1 (
        echo  [ERROR] PHP download failed.
        pause
        exit /b 1
    )
)
echo  PHP OK.
echo.

:: ----------------------------------------------------------------
:: STEP 3 - Build Vue 3 frontend
:: ----------------------------------------------------------------
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
echo  Writing .env.production.local...
echo VITE_API_URL=http://localhost:8000/api> .env.production.local

echo  Running: npx vite build
call npx vite build
if errorlevel 1 (
    echo  [ERROR] Vite build failed.
    del /f ".env.production.local" >nul 2>&1
    pause
    exit /b 1
)
del /f ".env.production.local" >nul 2>&1

echo  Copying dist to portable\www\...
if exist "%WWW_DIR%" rmdir /s /q "%WWW_DIR%"
mkdir "%WWW_DIR%"
xcopy /s /e /q /y "%FRONTEND%\dist\*" "%WWW_DIR%\" >nul
echo  Frontend OK.
echo.

:: ----------------------------------------------------------------
:: STEP 4 - Prepare Laravel backend
:: ----------------------------------------------------------------
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

:: Copy source folders
for %%D in (app bootstrap config database public resources routes storage vendor) do (
    if exist "%BACKEND%\%%D" (
        xcopy /s /e /q /y "%BACKEND%\%%D" "%APP_DIR%\%%D\" >nul 2>&1
    )
)
:: Copy loose files
for %%F in (artisan composer.json composer.lock) do (
    if exist "%BACKEND%\%%F" copy /y "%BACKEND%\%%F" "%APP_DIR%\%%F" >nul 2>&1
)

:: Create upload dirs (for avatars/logos)
if not exist "%APP_DIR%\public\uploads\avatars" mkdir "%APP_DIR%\public\uploads\avatars"
if not exist "%APP_DIR%\public\uploads\logos"   mkdir "%APP_DIR%\public\uploads\logos"

:: Ensure runtime dirs exist
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

echo  Backend OK.
echo.

:: ----------------------------------------------------------------
:: STEP 5 - Config files
:: ----------------------------------------------------------------
echo  [STEP 5/6] Writing config files...
echo.

if exist "%PORTABLE%config\php.ini" (
    copy /y "%PORTABLE%config\php.ini" "%PHP_DIR%\php.ini" >nul
    echo  php.ini applied.
)

if not exist "%PORTABLE%logs" mkdir "%PORTABLE%logs"

:: Apply portable .env over backend .env
if exist "%PORTABLE%config\env.portable" (
    copy /y "%PORTABLE%config\env.portable" "%APP_DIR%\.env" >nul
    echo  .env applied from config\env.portable
)

echo  Config OK.
echo.

:: ----------------------------------------------------------------
:: STEP 6 - Create ZIP
:: ----------------------------------------------------------------
echo  [STEP 6/6] Creating portable ZIP...
echo.

set "DIST=%PORTABLE%dist"
if not exist "%DIST%" mkdir "%DIST%"

set "ZIP=%DIST%\OpenVyapar-ERP-Portable-v1.0.0-Windows.zip"
if exist "%ZIP%" del /f "%ZIP%"

echo  Compressing (this may take a minute)...
powershell -Command "$ProgressPreference='SilentlyContinue'; $paths = @('%APP_DIR%','%WWW_DIR%','%PHP_DIR%','%PORTABLE%launcher','%PORTABLE%start.bat','%PORTABLE%stop.bat','%PORTABLE%README-PORTABLE.md'); $exist = $paths | Where-Object { Test-Path $_ }; Compress-Archive -Path $exist -DestinationPath '%ZIP%' -CompressionLevel Optimal"

if errorlevel 1 (
    echo  [ERROR] ZIP creation failed.
    pause
    exit /b 1
)

for %%F in ("%ZIP%") do set ZIP_SIZE=%%~zF
set /a ZIP_MB=%ZIP_SIZE%/1048576

echo.
echo  ===========================================================
echo   BUILD COMPLETE!
echo.
echo   ZIP  : %ZIP%
echo   Size : ~%ZIP_MB% MB
echo.
echo   Extract and double-click start.bat  -- done!
echo  ===========================================================
echo.
pause
