@echo off
setlocal
set "ROOT=%~dp0..\"

if "%1"=="silent" goto :kill

echo.
echo  Stopping OpenVyapar ERP...

:kill
:: Kill PHP server processes on our ports
for /f "tokens=5" %%a in ('netstat -ano 2^>nul ^| findstr ":8000 " ^| findstr LISTENING') do (
    taskkill /PID %%a /F >nul 2>&1
)
for /f "tokens=5" %%a in ('netstat -ano 2^>nul ^| findstr ":8080 " ^| findstr LISTENING') do (
    taskkill /PID %%a /F >nul 2>&1
)

:: Remove lock file
if exist "%ROOT%.running" del /f "%ROOT%.running" >nul 2>&1

if not "%1"=="silent" (
    echo  Stopped.
    echo.
    timeout /t 2 /nobreak >nul
)
