@echo off
REM CRM FMS Complete Setup Batch File for Windows/XAMPP
REM This script sets up the entire CRM FMS system in one go

echo ========================================
echo CRM FMS Complete Setup
echo ========================================
echo.

REM Check if XAMPP/MySQL is running
echo Checking MySQL service...
net start | find "MySQL" >nul 2>&1
if %errorlevel% neq 0 (
    echo WARNING: MySQL service not found. Please ensure XAMPP Control Panel is running.
    echo Press any key to continue anyway, or Ctrl+C to abort...
    pause >nul
)

REM Setup database and import schema
echo.
echo Setting up database and importing schema...
mysql -u root < setup_database_clean.sql
if %errorlevel% neq 0 (
    echo ERROR: Could not setup database and schema.
    echo Please ensure MySQL is running and you have proper permissions.
    pause
    exit /b 1
)

echo.
set /p install_sample="Do you want to install sample data for testing? (y/n): "
if /i "%install_sample%"=="y" (
    echo Installing sample data...
    mysql -u root faculty_attendance_system < setup_sample_data.sql
    if %errorlevel% neq 0 (
        echo ERROR: Could not import sample data.
        pause
        exit /b 1
    )
    echo.
    echo ========================================
    echo SAMPLE DATA INSTALLED SUCCESSFULLY!
    echo ========================================
    echo.
    echo Test Credentials:
    echo ================
    echo Admin:     admin@test.com     / test123
    echo Dean:      dean@test.com      / test123
    echo Secretary: secretary@test.com / test123
    echo Faculty:   faculty1@test.com  / test123
    echo Staff:     staff1@test.com    / test123
    echo.
    echo All test accounts use password: test123
    echo See TEST_CREDENTIALS.md for complete list
    echo.
)

REM Create necessary directories
echo Creating necessary directories...
if not exist "logs" mkdir logs
if not exist "public\uploads" mkdir "public\uploads"
if not exist "public\qr-codes" mkdir "public\qr-codes"
if not exist "public\assets\css\webfonts" mkdir "public\assets\css\webfonts"

REM Copy configuration if needed
echo Setting up configuration files...
if exist "crmfms.conf" (
    echo Note: Virtual host config (crmfms.conf) is for Linux Apache.
    echo For XAMPP, access via http://localhost/crmfms/public
)

echo.
echo ========================================
echo SETUP COMPLETED SUCCESSFULLY!
echo ========================================
echo.
echo Your CRM FMS system is now ready!
echo.
echo Access the application at: http://localhost/crmfms/public
echo.
if /i "%install_sample%"=="y" (
    echo Test accounts are ready to use with the credentials above.
) else (
    echo No sample data installed. You can add users manually through the admin panel.
)
echo.
echo Next steps:
echo 1. Open XAMPP Control Panel and ensure Apache and MySQL are running
echo 2. Open your browser and go to: http://localhost/crmfms/public
echo 3. Login with the test credentials above (if sample data was installed)
echo 4. Start using the Faculty Management System
echo.
echo For detailed documentation, see README.md
echo.
pause