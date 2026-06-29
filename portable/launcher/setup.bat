@echo off
title OpenVyapar ERP - First Time Setup
color 0B
setlocal EnableDelayedExpansion

set "ROOT=%~dp0..\"
set "PHP=%ROOT%php\php.exe"
set "APP=%ROOT%app"

echo.
echo  ─────────────────────────────────────────
echo   OpenVyapar ERP - First Time Setup
echo  ─────────────────────────────────────────
echo.

:: Create logs directory
if not exist "%ROOT%logs" mkdir "%ROOT%logs"

:: Create SQLite database file
echo  [1/5] Preparing database...
if not exist "%APP%\database" mkdir "%APP%\database"
if not exist "%APP%\database\openvyapar.sqlite" (
    type nul > "%APP%\database\openvyapar.sqlite"
    echo        SQLite database created.
) else (
    echo        Database already exists, skipping.
)

:: Write .env for portable (SQLite)
echo  [2/5] Writing configuration...
(
echo APP_NAME=OpenVyapar
echo APP_ENV=production
echo APP_KEY=
echo APP_DEBUG=false
echo APP_URL=http://localhost:8000
echo.
echo LOG_CHANNEL=single
echo LOG_LEVEL=error
echo.
echo DB_CONNECTION=sqlite
echo DB_DATABASE=%APP%\database\openvyapar.sqlite
echo.
echo CACHE_DRIVER=file
echo SESSION_DRIVER=file
echo SESSION_LIFETIME=120
echo.
echo SANCTUM_STATEFUL_DOMAINS=localhost:8080
echo SESSION_DOMAIN=localhost
echo.
echo MAIL_MAILER=log
) > "%APP%\.env"

:: Generate app key
echo  [3/5] Generating application key...
cd /d "%APP%"
"%PHP%" artisan key:generate --force >> "%ROOT%logs\setup.log" 2>&1
if errorlevel 1 (
    echo  [ERROR] Failed to generate app key. Check logs\setup.log
    exit /b 1
)
echo        Done.

:: Run migrations
echo  [4/5] Running database migrations...
"%PHP%" artisan migrate --force >> "%ROOT%logs\setup.log" 2>&1
if errorlevel 1 (
    echo  [ERROR] Migration failed. Check logs\setup.log
    exit /b 1
)
echo        Done.

:: Seed database
echo  [5/5] Seeding demo data...
"%PHP%" artisan db:seed --force >> "%ROOT%logs\setup.log" 2>&1
if errorlevel 1 (
    echo  [WARN] Seeding failed (optional). App will still work.
)
echo        Done.

:: Mark as installed
echo OpenVyapar ERP - Installed on %date% %time% > "%ROOT%.installed"

echo.
echo  ─────────────────────────────────────────
echo   Setup complete!
echo   Default login: admin@demo.com / password
echo  ─────────────────────────────────────────
echo.
