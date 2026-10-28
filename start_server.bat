@echo off
title TinyTales BabyBook Server
echo ========================================================
echo Starting TinyTales BabyBook Web Server...
echo ========================================================
echo.

REM 1. Check if PHP is installed and accessible in PATH
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP was not found in your system PATH!
    echo.
    echo Please install PHP 8.3 in PowerShell:
    echo   winget install PHP.PHP.8.3 --accept-source-agreements --accept-package-agreements
    echo.
    echo If already installed via winget, restart your command prompt or run:
    echo   set PATH=%%PATH%%;%%LOCALAPPDATA%%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe
    echo.
    pause
    exit /b 1
)

REM 2. Terminate any previous stale processes listening on port 8000
echo Checking for previous server instances on port 8000...
for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr :8000 ^| findstr LISTENING') do (
    echo Terminating stale process on port 8000 with PID %%a
    taskkill /f /pid %%a >nul 2>nul
)

REM 3. Run database migrations to ensure all data has verified ownership
echo Running database integrity checks...
php -d display_startup_errors=0 migrate.php
echo.

REM 4. Launch the application
echo ========================================================
echo TinyTales server is ready!
echo URL: http://localhost:8000/home.html
echo ========================================================
echo.
echo Opening browser...
start "" "http://localhost:8000/home.html"

echo Starting PHP built-in web server on port 8000...
echo (Press Ctrl+C in this window anytime to stop the server)
echo.
php -d display_startup_errors=0 -S localhost:8000 router.php
pause
