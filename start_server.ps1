# TinyTales BabyBook Server Startup Script (PowerShell)
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "Starting TinyTales BabyBook Web Server..." -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Verify PHP availability
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    # Check winget standard install path
    $wingetPhp = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
    if (Test-Path "$wingetPhp\php.exe") {
        $env:PATH += ";$wingetPhp"
        $phpCmd = "$wingetPhp\php.exe"
    } else {
        Write-Host "[ERROR] PHP was not found in your system PATH!" -ForegroundColor Red
        Write-Host "Please install PHP 8.3 via winget in PowerShell:" -ForegroundColor Yellow
        Write-Host "  winget install PHP.PHP.8.3 --accept-source-agreements --accept-package-agreements"
        Write-Host ""
        Read-Host "Press Enter to exit"
        exit 1
    }
}

# 2. Kill any stale process listening on port 8000
Write-Host "Checking for previous server instances on port 8000..." -ForegroundColor Gray
$connections = Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | Where-Object { $_.State -eq 'Listen' }
if ($connections) {
    foreach ($conn in $connections) {
        Write-Host "Terminating stale process on port 8000 with PID $($conn.OwningProcess)..." -ForegroundColor Yellow
        Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
    }
}

# 3. Run safe migration
Write-Host "Running database integrity checks..." -ForegroundColor Gray
php -d display_startup_errors=0 migrate.php
Write-Host ""

# 4. Start Server
Write-Host "========================================================" -ForegroundColor Green
Write-Host "TinyTales server is ready!" -ForegroundColor Green
Write-Host "URL: http://localhost:8000/home.html" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Opening browser..." -ForegroundColor Cyan
Start-Process "http://localhost:8000/home.html"

Write-Host "Starting PHP built-in web server on port 8000 (router active)..." -ForegroundColor White
Write-Host "(Press Ctrl+C to stop the server anytime)" -ForegroundColor Gray
Write-Host ""
php -d display_startup_errors=0 -S localhost:8000 router.php
