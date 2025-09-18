@echo off
REM CRM FMS Windows Secure Setup Batch File
REM Production-ready setup with enhanced security

echo ========================================
echo   CRM FMS Windows SECURE Setup
echo ========================================
echo.
echo This setup includes security enhancements for production use.
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo Running as Administrator - Good!
) else (
    echo ERROR: Must run as Administrator for secure setup.
    echo Right-click this file and select "Run as administrator"
    pause
    exit /b 1
)

REM Check if MySQL is running
echo Checking MySQL service...
net start | find "MySQL" >nul 2>&1
if %errorlevel% neq 0 (
    echo Starting MySQL service...
    net start mysql
    if %errorlevel% neq 0 (
        echo ERROR: Could not start MySQL service.
        pause
        exit /b 1
    )
)

echo.

REM Create secure database user
echo Creating secure database user...
mysql -u root -e "DROP USER IF EXISTS 'crmfms_user'@'localhost'; CREATE USER 'crmfms_user'@'localhost' IDENTIFIED BY 'ChangeThisPassword123!'; GRANT ALL PRIVILEGES ON faculty_attendance_system.* TO 'crmfms_user'@'localhost'; FLUSH PRIVILEGES;"
if %errorlevel% neq 0 (
    echo ERROR: Could not create secure database user.
    pause
    exit /b 1
)

REM Setup database
echo Setting up database with secure user...
mysql -u root -e "DROP DATABASE IF EXISTS faculty_attendance_system; CREATE DATABASE faculty_attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root faculty_attendance_system < setup_database_clean.sql
if %errorlevel% neq 0 (
    echo ERROR: Could not setup database.
    pause
    exit /b 1
)

REM Ask about sample data
set /p install_sample="Install sample data for testing? (y/n): "
if /i "%install_sample%"=="y" (
    echo Installing sample data...
    mysql -u root faculty_attendance_system < setup_sample_data.sql
)

REM Create directories with secure permissions
echo Creating directories with secure permissions...
if not exist "..\logs" mkdir "..\logs"
if not exist "..\public\uploads" mkdir "..\public\uploads"
if not exist "..\public\qr-codes" mkdir "..\public\qr-codes"
if not exist "..\backups" mkdir "..\backups"

REM Set restrictive permissions
echo Setting restrictive permissions...
icacls "..\logs" /grant "IIS_IUSRS":F /grant "IUSR":F /grant "Administrators":F /deny "Everyone":F >nul 2>&1
icacls "..\public\uploads" /grant "IIS_IUSRS":M /grant "IUSR":M /grant "Administrators":F >nul 2>&1
icacls "..\public\qr-codes" /grant "IIS_IUSRS":M /grant "IUSR":M /grant "Administrators":F >nul 2>&1
icacls "..\backups" /grant "Administrators":F /deny "Everyone":F >nul 2>&1

REM Update database configuration for secure user
echo Updating database configuration for secure user...
if exist "..\config\database.php" (
    echo Database config found. Please manually update it with:
    echo Username: crmfms_user
    echo Password: ChangeThisPassword123!
    echo.
    echo IMPORTANT: Change the default password immediately!
)

echo.
echo ========================================
echo SECURE SETUP COMPLETED!
echo ========================================
echo.
echo Security Features Applied:
echo =========================
echo - Created dedicated database user (crmfms_user)
echo - Applied restrictive file permissions
echo - Database user has limited privileges
echo.
echo IMPORTANT SECURITY STEPS:
echo ========================
echo 1. CHANGE DATABASE PASSWORD from default!
echo 2. Update config/database.php with new credentials
echo 3. Set up SSL certificates for HTTPS
echo 4. Configure firewall rules
echo 5. Set up regular backups
echo.
if /i "%install_sample%"=="y" (
    echo Test Credentials (change passwords after first login):
    echo ===================================================
    echo Admin: admin@crmfms.local / password
    echo (Employee ID will be CRIM001)
    echo.
)
echo Access URL: http://localhost/crmfms/public
echo.
pause