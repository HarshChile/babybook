@echo off
title TinyTales BabyBook Server
echo ========================================================
echo Starting TinyTales BabyBook Web Server...
echo ========================================================
echo.

where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP was not found in your system PATH!
    echo.
    echo Please install PHP 8.3 in PowerShell:
    echo   winget install PHP.PHP.8.3 --accept-source-agreements --accept-package-agreements
    echo.
    pause
    exit /b
)

echo Checking for previous server instances on port 8000...
for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr :8000 ^| findstr LISTENING') do (
    echo Terminating stale process on port 8000 (PID: %%a)...
    taskkill /f /pid %%a >nul 2>nul
)

echo Running database integrity checks...
php -d display_startup_errors=0 migrate.php
echo.
echo Server running at: http://localhost:8000/home.html
echo Database: Local JSON fallback active in data/ (or MongoDB if configured)
echo.
echo NOTE: If your browser displays an older cached version, press Ctrl + Shift + R (Hard Refresh)
echo.
echo Opening your browser to http://localhost:8000/home.html ...
echo.
start "" http://localhost:8000/home.html
php -d display_startup_errors=0 -S localhost:8000
pause
