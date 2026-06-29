@echo off
title OpenVyapar ERP - First Time Setup
setlocal EnableDelayedExpansion

:: Called by start.bat — ROOT is portable\ (one level up from launcher\)
set "ROOT=%~dp0..\"
set "PHP=%ROOT%php\php.exe"
set "APP=%ROOT%app"

if not exist "%ROOT%logs" mkdir "%ROOT%logs"

echo  ─────────────────────────────────────────
echo   OpenVyapar ERP - First Time Setup
echo  ─────────────────────────────────────────
echo.

:: 1. Create SQLite database file
echo  [1/5] Preparing SQLite database...
if not exist "%APP%\database" mkdir "%APP%\database"
if not exist "%APP%\database\openvyapar.sqlite" (
    type nul > "%APP%\database\openvyapar.sqlite"
    echo        Created: database\openvyapar.sqlite
) else (
    echo        Already exists, skipping.
)

:: 2. Write .env (SQLite, portable config)
echo  [2/5] Writing configuration (.env)...
set "DB_PATH=%APP%\database\openvyapar.sqlite"
:: Use forward slashes for Laravel
set "DB_PATH_FWD=%DB_PATH:\=/%"

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
echo DB_DATABASE=%DB_PATH%
echo.
echo CACHE_DRIVER=file
echo SESSION_DRIVER=file
echo SESSION_LIFETIME=120
echo.
echo SANCTUM_STATEFUL_DOMAINS=localhost:8080,localhost:8000
echo SESSION_DOMAIN=localhost
echo.
echo MAIL_MAILER=log
) > "%APP%\.env"
echo        Done.

:: 3. Generate app key
echo  [3/5] Generating application key...
cd /d "%APP%"
"%PHP%" artisan key:generate --force >> "%ROOT%logs\setup.log" 2>&1
if errorlevel 1 (
    echo  [ERROR] key:generate failed. See logs\setup.log
    exit /b 1
)
echo        Done.

:: 4. Run migrations
echo  [4/5] Running database migrations...
"%PHP%" artisan migrate --force >> "%ROOT%logs\setup.log" 2>&1
if errorlevel 1 (
    echo  [ERROR] Migrations failed. See logs\setup.log
    exit /b 1
)
echo        Done.

:: 5. Seed demo data
echo  [5/5] Seeding demo company and admin user...
"%PHP%" artisan db:seed --force >> "%ROOT%logs\setup.log" 2>&1
if errorlevel 1 (
    echo  [WARN] Seeding failed (non-fatal). App will still work.
) else (
    echo        Done.
)

:: Mark installed
echo Installed on %date% %time% > "%ROOT%.installed"

echo.
echo  ─────────────────────────────────────────
echo   Setup complete!
echo   Login: admin@demo.com  /  password
echo  ─────────────────────────────────────────
echo.
exit /b 0
