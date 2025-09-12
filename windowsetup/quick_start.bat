@echo off
echo ========================================
echo CRM FMS Quick Start Script for Windows
echo ========================================
echo.

echo Checking XAMPP installation...
if not exist "C:\xampp\xampp-control.exe" (
    echo ERROR: XAMPP not found in C:\xampp\
    echo Please install XAMPP first.
    pause
    exit /b 1
)

echo Starting XAMPP services...
start "" "C:\xampp\xampp-control.exe"

echo.
echo Instructions:
echo 1. In XAMPP Control Panel, start Apache and MySQL
echo 2. Open browser to: http://localhost/crmfms
echo 3. Use test credentials from test_login.html
echo.

echo Opening phpMyAdmin for database setup...
start http://localhost/phpmyadmin

echo.
echo Press any key to continue...
pause >nul

echo Opening CRM FMS...
start http://localhost/crmfms

echo.
echo Setup complete! Check the windows_setup.txt for detailed instructions.