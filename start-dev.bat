@echo off
echo ==============================
echo  OpenVyapar ERP — Dev Server
echo ==============================

echo.
echo [1/2] Starting Laravel Backend on :8000 ...
start "OpenVyapar Backend" cmd /k "cd /d %~dp0backend && php artisan serve --port=8000"

timeout /t 2 /nobreak >nul

echo [2/2] Starting Vue Frontend on :5173 ...
start "OpenVyapar Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo.
echo Backend: http://localhost:8000
echo Frontend: http://localhost:5173
echo.
pause
