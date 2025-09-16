# CRM FMS Windows Setup Guide with XAMPP

This guide provides step-by-step instructions for setting up CRM FMS on Windows using XAMPP.

## Prerequisites

- Windows 10/11
- Administrator privileges
- Internet connection for downloads
- **Quick Start:** Use `setup_windows.bat` for automated setup with test data

## Step 1: Download and Install XAMPP

1. **Download XAMPP for Windows**
   - Visit: https://www.apachefriends.org/download.html
   - Download the latest XAMPP version for Windows (PHP 8.1 or later recommended)
   - Choose the installer version (.exe file)

2. **Install XAMPP**
   - Run the downloaded installer as Administrator
   - Choose installation directory (default: `C:\xampp`)
   - Select components to install:
     - ✅ Apache
     - ✅ MySQL
     - ✅ PHP
     - ✅ phpMyAdmin
     - ✅ OpenSSL
     - ✅ phpMyAdmin
   - Complete the installation

3. **Start XAMPP Control Panel**
   - Launch XAMPP Control Panel as Administrator
   - Start Apache and MySQL services
   - Verify services are running (green status)

## Step 2: Download CRM FMS

1. **Download the CRM FMS files**
   - Extract the CRM FMS files to: `C:\xampp\htdocs\crmfms`
   - Ensure the folder structure is maintained

2. **Verify file structure**
   ```
   C:\xampp\htdocs\crmfms\
   ├── api\
   ├── config\
   ├── public\
   ├── scripts\
   ├── setup_database_clean.sql
   ├── setup_sample_data.sql
   ├── setup_clean.sh (for reference)
   └── WINDOWS_SETUP.md (this file)
   ```

## Step 3: Configure PHP Settings

1. **Open PHP Configuration**
   - Open `C:\xampp\php\php.ini` in a text editor

2. **Update PHP Settings**
   - Find and update the following settings:
     ```ini
     max_execution_time = 300
     max_input_time = 300
     memory_limit = 256M
     post_max_size = 50M
     upload_max_filesize = 50M
     ```

3. **Enable Required Extensions**
   - Ensure these extensions are enabled (remove semicolon if present):
     ```ini
     extension=gd
     extension=mbstring
     extension=mysqli
     extension=pdo_mysql
     extension=openssl
     extension=json
     extension=curl
     ```

4. **Restart Apache**
   - In XAMPP Control Panel, stop and restart Apache

## Step 4: Set Up MySQL Database

### Quick Setup (Recommended for Testing)
If you just want to get started quickly for testing:

1. **Run the Windows Setup Script**
   ```cmd
   cd C:\xampp\htdocs\crmfms
   setup_windows.bat
   ```

2. **Choose Sample Data**
   - When prompted, type `y` to install sample data
   - This will create test user accounts automatically

3. **Test Credentials (After Sample Data Installation)**
   ```
   Admin:     admin@test.com     / test123
   Dean:      dean@test.com      / test123
   Secretary: secretary@test.com / test123
   Faculty:   faculty1@test.com  / test123
   Staff:     staff1@test.com    / test123
   ```
   **All test accounts use password:** `test123`

### Option A: Manual Setup with phpMyAdmin

1. **Open phpMyAdmin**
   - Visit: http://localhost/phpmyadmin
   - Default credentials:
     - Username: `root`
     - Password: (leave blank)

2. **Import Database Schema**
   - Click "Import" tab
   - Click "Choose File" and select `setup_database_clean.sql`
   - Click "Go" to import (this will create the database automatically)

4. **Import Sample Data (Optional)**
   - Click "Import" tab again
   - Select `setup_sample_data.sql`
   - Click "Go" to import sample data

### Option B: Using Command Line

1. **Open Command Prompt as Administrator**
   - Press Win + X, select "Windows Terminal (Admin)" or "Command Prompt (Admin)"

2. **Navigate to MySQL bin directory**
   ```cmd
   cd C:\xampp\mysql\bin
   ```

3. **Import database schema**
   ```cmd
   mysql -u root < C:\xampp\htdocs\crmfms\setup_database_clean.sql
   ```

4. **Import sample data (optional)**
   ```cmd
   mysql -u root faculty_attendance_system < C:\xampp\htdocs\crmfms\setup_sample_data.sql
   ```

## Step 5: Configure CRM FMS

1. **Update Database Configuration**
   - Open `C:\xampp\htdocs\crmfms\config\database.php`
   - Verify the database settings:
     ```php
     private $host = 'localhost';
     private $db_name = 'faculty_attendance_system';
     private $username = 'root';
     private $password = '';
     ```

2. **Create Required Directories**
   - Create these folders if they don't exist:
     ```
     C:\xampp\htdocs\crmfms\logs\
     C:\xampp\htdocs\crmfms\public\uploads\
     C:\xampp\htdocs\crmfms\public\qr-codes\
     ```

3. **Set Directory Permissions**
   - Right-click on the `crmfms` folder
   - Properties → Security → Edit
   - Add "Everyone" with Full Control permissions
   - Apply to all subfolders

## Step 6: Configure Apache Virtual Host (Optional but Recommended)

1. **Edit Apache Configuration**
   - Open `C:\xampp\apache\conf\httpd.conf`
   - Uncomment this line (remove #):
     ```apache
     Include conf/extra/httpd-vhosts.conf
     ```

2. **Edit Virtual Hosts Configuration**
   - Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
   - Add at the end:
     ```apache
     <VirtualHost *:80>
         ServerName crmfms.local
         DocumentRoot "C:/xampp/htdocs/crmfms/public"
         <Directory "C:/xampp/htdocs/crmfms/public">
             Options Indexes FollowSymLinks
             AllowOverride All
             Require all granted
         </Directory>
         ErrorLog logs/crmfms_error.log
         CustomLog logs/crmfms_access.log common
     </VirtualHost>
     ```

3. **Update Windows Hosts File**
   - Open Notepad as Administrator
   - Open `C:\Windows\System32\drivers\etc\hosts`
   - Add this line at the end:
     ```
     127.0.0.1    crmfms.local
     ```

4. **Restart Apache**
   - In XAMPP Control Panel, stop and restart Apache

## Step 7: Test the Installation

1. **Access the Application**
   - If using virtual host: http://crmfms.local
   - If not using virtual host: http://localhost/crmfms/public

2. **Test Database Connection**
   - The application should load without database errors

3. **Test Login (if sample data was installed)**
   - Email: `admin@crmfms.local`
   - Password: `password`

## Step 8: Test the Installation with Sample Data

### Test User Accounts
After installing the sample data, you can test the system with these accounts:

| Role | Email | Password | Use Case |
|------|-------|----------|----------|
| Admin | admin@test.com | test123 | Full system access |
| Dean | dean@test.com | test123 | Approvals & oversight |
| Secretary | secretary@test.com | test123 | Administrative tasks |
| Program Head | programhead@test.com | test123 | Department management |
| Faculty | faculty1@test.com | test123 | Attendance & scheduling |
| Staff | staff1@test.com | test123 | Basic functionality |

**All test accounts use password:** `test123`

### Testing Checklist
- [ ] Login with admin account - verify full access
- [ ] Login with faculty account - test attendance features
- [ ] Login with staff account - test basic functionality
- [ ] Test role-based permissions
- [ ] Verify reports are accessible
- [ ] Test attendance check-in/check-out

## Step 9: Post-Installation Configuration

### Enable SSL (Optional but Recommended)

1. **Generate SSL Certificate**
   - Open XAMPP Shell or Command Prompt
   - Run:
     ```cmd
     cd C:\xampp\apache
     bin\openssl req -new -newkey rsa:2048 -nodes -keyout crmfms.key -out crmfms.csr
     bin\openssl x509 -req -days 365 -in crmfms.csr -signkey crmfms.key -out crmfms.crt
     ```

2. **Configure SSL in Apache**
   - Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`
   - Update paths:
     ```apache
     SSLCertificateFile "C:/xampp/apache/crmfms.crt"
     SSLCertificateKeyFile "C:/xampp/apache/crmfms.key"
     ```

3. **Enable SSL Module**
   - Edit `C:\xampp\apache\conf\httpd.conf`
   - Uncomment: `LoadModule ssl_module modules/mod_ssl.so`
   - Uncomment: `Include conf/extra/httpd-ssl.conf`

4. **Restart Apache**

### Set Up Automated Backups

1. **Create Backup Script**
   - Create `C:\xampp\htdocs\crmfms\backup.bat`:
     ```batch
     @echo off
     set BACKUP_DIR=C:\xampp\htdocs\crmfms\backups
     set TIMESTAMP=%DATE:~10,4%%DATE:~4,2%%DATE:~7,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%
     set BACKUP_FILE=%BACKUP_DIR%\crmfms_backup_%TIMESTAMP%.sql

     if not exist %BACKUP_DIR% mkdir %BACKUP_DIR%

     "C:\xampp\mysql\bin\mysqldump.exe" -u root faculty_attendance_system > %BACKUP_FILE%

     echo Backup completed: %BACKUP_FILE%
     ```

2. **Schedule Automated Backups**
   - Open Task Scheduler
   - Create new task
   - Program: `C:\xampp\htdocs\crmfms\backup.bat`
   - Schedule: Daily at preferred time

## Troubleshooting

### Common Issues

1. **Apache won't start**
   - Check if port 80/443 is being used by another application
   - Run XAMPP as Administrator
   - Check Apache error logs: `C:\xampp\apache\logs\error.log`

2. **MySQL won't start**
   - Check if port 3306 is being used
   - Ensure no other MySQL instances are running
   - Check MySQL error logs: `C:\xampp\mysql\data\mysql_error.log`

3. **Database connection errors**
   - Verify database credentials in `config/database.php`
   - Ensure database exists and is accessible
   - Check MySQL service is running

4. **Permission errors**
   - Run XAMPP as Administrator
   - Check folder permissions
   - Disable Windows Defender temporarily for testing

5. **PHP errors**
   - Check PHP error logs: `C:\xampp\php\logs\php_error_log`
   - Verify PHP extensions are enabled
   - Check PHP version compatibility

### Getting Help

- Check the application logs: `C:\xampp\htdocs\crmfms\logs\`
- Verify all files were extracted correctly
- Test with sample data first before adding custom data

## Security Recommendations

1. **Change Default Passwords**
   - Update admin password immediately
   - Use strong passwords for all users

2. **Database Security**
   - Create a dedicated MySQL user for the application
   - Don't use root user in production
   - Regularly backup the database

3. **File System Security**
   - Restrict access to sensitive files
   - Use proper file permissions
   - Regularly scan for malware

4. **Network Security**
   - Use HTTPS in production
   - Implement proper firewall rules
   - Keep all software updated

## Next Steps

1. **Explore the Application**
   - Log in with admin credentials
   - Create additional users
   - Set up buildings, rooms, and departments
   - Configure schedules and QR codes

2. **Customize Settings**
   - Update organization information
   - Configure alert thresholds
   - Set up automated monitoring

3. **Training**
   - Train staff on system usage
   - Create user manuals
   - Establish backup procedures

---

**Installation completed successfully!** 🎉

Access your CRM FMS at: http://crmfms.local (or http://localhost/crmfms/public)