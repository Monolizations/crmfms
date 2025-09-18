@echo off
REM CRM FMS Windows Setup Batch File
REM Enhanced version with CRIM employee ID support and improved error handling

echo ========================================
echo   CRM FMS Windows Setup Helper
echo ========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo Running as Administrator - Good!
) else (
    echo WARNING: Not running as Administrator.
    echo Some operations may fail. Please run as Administrator.
    echo.
)

REM Check if MySQL is running
echo Checking MySQL service...
net start | find "MySQL" >nul 2>&1
if %errorlevel% neq 0 (
    echo WARNING: MySQL service not found. Please ensure XAMPP is running.
    echo Starting MySQL service...
    net start mysql
    if %errorlevel% neq 0 (
        echo ERROR: Could not start MySQL service.
        echo Please start XAMPP Control Panel and start MySQL manually.
        pause
        exit /b 1
    )
)

echo.

REM Setup database and import schema
echo Setting up database and importing schema...
echo This may take a few moments...
mysql -u root -e "DROP DATABASE IF EXISTS faculty_attendance_system; CREATE DATABASE faculty_attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
if %errorlevel% neq 0 (
    echo ERROR: Could not create database.
    echo Please ensure MySQL is running and you have proper permissions.
    pause
    exit /b 1
)

mysql -u root faculty_attendance_system < setup_database_clean.sql
if %errorlevel% neq 0 (
    echo ERROR: Could not import database schema.
    echo Please check the setup_database_clean.sql file.
    pause
    exit /b 1
)

echo Database schema imported successfully.
echo.

REM Ask user about sample data
set /p install_sample="Do you want to install sample data for testing? (y/n): "
if /i "%install_sample%"=="y" (
    echo.
    echo Installing sample data...
    echo This includes test users with CRIM employee IDs...
    mysql -u root faculty_attendance_system < setup_sample_data.sql
    if %errorlevel% neq 0 (
        echo ERROR: Could not import sample data.
        echo Please check the setup_sample_data.sql file.
        pause
        exit /b 1
    )
    echo.
    echo ========================================
    echo SAMPLE DATA INSTALLED SUCCESSFULLY!
    echo ========================================
    echo.
    echo Test Credentials (All passwords: password):
    echo ===========================================
    echo Admin:     admin@crmfms.local     (CRIM001)
    echo Dean:      dean@crmfms.local      (CRIM002)
    echo Secretary: secretary@crmfms.local (CRIM003)
    echo Staff:     staff1@crmfms.local    (CRIM004)
    echo Faculty:   faculty1@crmfms.local  (CRIM009)
    echo.
    echo All test accounts use password: password
    echo See TEST_CREDENTIALS.md for complete list
    echo.
    echo NOTE: New users will get auto-generated CRIM employee IDs
    echo.
)

REM Create necessary directories
echo Creating necessary directories...
if not exist "..\logs" mkdir "..\logs"
if not exist "..\public\uploads" mkdir "..\public\uploads"
if not exist "..\public\qr-codes" mkdir "..\public\qr-codes"
if not exist "..\backups" mkdir "..\backups"

REM Set permissions (basic attempt)
echo Setting directory permissions...
icacls "..\logs" /grant Everyone:F >nul 2>&1
icacls "..\public\uploads" /grant Everyone:F >nul 2>&1
icacls "..\public\qr-codes" /grant Everyone:F >nul 2>&1
icacls "..\backups" /grant Everyone:F >nul 2>&1

echo.
echo ========================================
echo SETUP COMPLETED SUCCESSFULLY!
echo ========================================
echo.
echo Installation Summary:
echo ====================
echo - Database: faculty_attendance_system (created)
echo - Schema: Imported successfully
if /i "%install_sample%"=="y" (
    echo - Sample Data: Installed with test users
    echo - Test Users: 18 users with CRIM employee IDs
) else (
    echo - Sample Data: Not installed
)
echo - Directories: Created (logs, uploads, qr-codes, backups)
echo - Permissions: Set for web access
echo.
echo Next Steps:
echo ===========
echo 1. Access the application at: http://localhost/crmfms/public
if /i "%install_sample%"=="y" (
    echo 2. Use the test credentials above to login
    echo 3. Start testing the system features
) else (
    echo 2. Create your first admin user manually
    echo 3. Employee IDs will auto-generate as CRIM001, CRIM002, etc.
)
echo 4. Configure your buildings, rooms, and departments
echo 5. Set up attendance monitoring
echo.
echo For detailed setup instructions, see WINDOWS_SETUP.md
echo.
echo Troubleshooting:
echo ================
echo - If you get permission errors, run this script as Administrator
echo - If database errors occur, ensure MySQL is running in XAMPP
echo - Check the logs folder for error details
echo.
pause