@echo off
REM CRM FMS System Requirements Checker
REM This script checks if the system meets minimum requirements

echo ========================================
echo   CRM FMS Requirements Checker
echo ========================================
echo.

REM Check Windows version
echo Checking Windows version...
ver
echo.

REM Check if running as administrator
echo Checking administrator privileges...
net session >nul 2>&1
if %errorLevel% == 0 (
    echo ✓ Running as Administrator
) else (
    echo ✗ Not running as Administrator
    echo   Please run this script as Administrator for accurate results.
)
echo.

REM Check available RAM
echo Checking available memory...
systeminfo | find "Total Physical Memory"
systeminfo | find "Available Physical Memory"
echo.

REM Check disk space
echo Checking disk space...
dir C:\ | find "bytes free"
echo.

REM Check if XAMPP is installed
echo Checking for XAMPP installation...
if exist "C:\xampp\xampp-control.exe" (
    echo ✓ XAMPP found at C:\xampp
) else (
    echo ✗ XAMPP not found at C:\xampp
    echo   Please install XAMPP to C:\xampp
)
echo.

REM Check XAMPP services
if exist "C:\xampp\xampp-control.exe" (
    echo Checking XAMPP services...
    net start | find "Apache" >nul 2>&1
    if %errorlevel% == 0 (
        echo ✓ Apache service is running
    ) else (
        echo ✗ Apache service not found
    )

    net start | find "MySQL" >nul 2>&1
    if %errorlevel% == 0 (
        echo ✓ MySQL service is running
    ) else (
        echo ✗ MySQL service not found
    )
)
echo.

REM Check PHP version
echo Checking PHP version...
if exist "C:\xampp\php\php.exe" (
    C:\xampp\php\php.exe -v | find "PHP"
) else (
    echo ✗ PHP not found
)
echo.

REM Check MySQL version
echo Checking MySQL version...
if exist "C:\xampp\mysql\bin\mysql.exe" (
    C:\xampp\mysql\bin\mysql.exe -V
) else (
    echo ✗ MySQL not found
)
echo.

REM Check required PHP extensions
echo Checking PHP extensions...
if exist "C:\xampp\php\php.exe" (
    echo PHP Extensions Status:
    C:\xampp\php\php.exe -m | find "gd" >nul 2>&1
    if %errorlevel% == 0 (
        echo ✓ GD extension enabled
    ) else (
        echo ✗ GD extension not found
    )

    C:\xampp\php\php.exe -m | find "mysqli" >nul 2>&1
    if %errorlevel% == 0 (
        echo ✓ MySQLi extension enabled
    ) else (
        echo ✗ MySQLi extension not found
    )

    C:\xampp\php\php.exe -m | find "pdo_mysql" >nul 2>&1
    if %errorlevel% == 0 (
        echo ✓ PDO MySQL extension enabled
    ) else (
        echo ✗ PDO MySQL extension not found
    )
)
echo.

REM Check ports availability
echo Checking port availability...
netstat -an | find "0.0.0.0:80" >nul 2>&1
if %errorlevel% == 0 (
    echo ✗ Port 80 (Apache) is in use
) else (
    echo ✓ Port 80 (Apache) is available
)

netstat -an | find "0.0.0.0:3306" >nul 2>&1
if %errorlevel% == 0 (
    echo ✗ Port 3306 (MySQL) is in use
) else (
    echo ✓ Port 3306 (MySQL) is available
)
echo.

REM Summary
echo ========================================
echo   REQUIREMENTS SUMMARY
echo ========================================
echo.
echo Minimum Requirements:
echo - Windows 10/11 64-bit
echo - 4GB RAM (8GB recommended)
echo - 2GB free disk space
echo - Administrator privileges
echo - XAMPP with Apache, MySQL, PHP 8.1+
echo.
echo If any requirements are not met, please:
echo 1. Install/update XAMPP
echo 2. Run as Administrator
echo 3. Free up system resources
echo 4. Check port conflicts
echo.
echo For detailed setup instructions, see WINDOWS_SETUP.md
echo.
pause