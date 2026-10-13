@echo off
title TinyTales BabyBook Server
echo ========================================================
echo Starting TinyTales BabyBook Web Server...
echo ========================================================
echo.
echo Make sure your MongoDB connection is configured in .env
echo Photos are stored on disk in uploads/
echo Database pointers and metadata are stored in MongoDB
echo.
echo Server running at: http://localhost:8000/home.html
echo Press Ctrl+C to stop the server.
echo.
php -S localhost:8000
pause
