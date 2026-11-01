@echo off
title SmartPark - Local Development Server
echo ========================================================
echo   SmartPark - Vehicle Parking Management System
echo   Starting Built-In Local PHP Web Server...
echo ========================================================
echo.
echo Application will be accessible at:
echo   http://localhost:8000
echo.
echo Default Admin Credentials:
echo   Username: admin
echo   Password: admin123
echo.
echo Press Ctrl+C in this window to stop the server.
echo ========================================================
echo.

set PHP_PATH="C:\Users\KANISHKA GIRI\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

if exist %PHP_PATH% (
    %PHP_PATH% -S 127.0.0.1:8000
) else (
    php -S 127.0.0.1:8000
)

pause
