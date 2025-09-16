@echo off
REM CRM FMS Windows Setup Batch File
REM This script helps Windows users set up CRM FMS with XAMPP

echo ========================================
echo CRM FMS Windows Setup Helper
echo ========================================
echo.

REM Check if MySQL is running
echo Checking MySQL service...
net start | find "MySQL" >nul 2>&1
if %errorlevel% neq 0 (
    echo WARNING: MySQL service not found. Please ensure XAMPP is running.
    echo.
)

REM Setup database and import schema
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

REM Create directories
echo Creating necessary directories...
if not exist "logs" mkdir logs
if not exist "public\uploads" mkdir "public\uploads"
if not exist "public\qr-codes" mkdir "public\qr-codes"

echo.
echo ========================================
echo SETUP COMPLETED SUCCESSFULLY!
echo ========================================
echo.
echo Next steps:
echo 1. Access the application at: http://localhost/crmfms/public
echo 2. Use the test credentials above to login
echo 3. Start testing the system
echo.
if /i "%install_sample%"=="y" (
    echo Your test accounts are ready to use!
) else (
    echo No sample data installed. You can add users manually.
)
echo.
pause