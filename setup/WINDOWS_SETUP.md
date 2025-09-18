# CRM FMS Windows Setup Guide with XAMPP

This comprehensive guide provides step-by-step instructions for setting up CRM FMS on Windows using XAMPP, including the latest features and improvements.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Step 1: Download and Install XAMPP](#step-1-download-and-install-xampp)
- [Step 2: Download CRM FMS](#step-2-download-crm-fms)
- [Step 3: Configure PHP Settings](#step-3-configure-php-settings)
- [Step 4: Set Up MySQL Database](#step-4-set-up-mysql-database)
- [Step 5: Configure CRM FMS](#step-5-configure-crm-fms)
- [Step 6: Configure Apache Virtual Host](#step-6-configure-apache-virtual-host)
- [Step 7: Test the Installation](#step-7-test-the-installation)
- [Step 8: Post-Installation Configuration](#step-8-post-installation-configuration)
- [Troubleshooting](#troubleshooting)
- [Security Recommendations](#security-recommendations)

## 📋 Prerequisites

- **Operating System:** Windows 10/11 (64-bit recommended)
- **RAM:** Minimum 4GB (8GB recommended)
- **Disk Space:** 2GB free space
- **Administrator privileges** required
- **Internet connection** for downloads and updates

## 🚀 Quick Start

For experienced users who want to get started quickly:

1. **Download and Install XAMPP**
2. **Extract CRM FMS** to `C:\xampp\htdocs\crmfms`
3. **Run Automated Setup:**
   ```cmd
   cd C:\xampp\htdocs\crmfms
   setup_windows.bat
   ```
4. **Choose Sample Data** when prompted
5. **Access Application** at: http://localhost/crmfms/public

## Step 1: Download and Install XAMPP

### 1.1 Download XAMPP for Windows

1. **Visit the official XAMPP website:**
   - URL: https://www.apachefriends.org/download.html
   - Download the latest XAMPP version for Windows
   - Choose the **installer version** (.exe file, ~150MB)

2. **Recommended Version:**
   - XAMPP 8.2.x or later (includes PHP 8.2+)
   - Includes Apache 2.4, MySQL 8.0, PHP 8.2+

### 1.2 Install XAMPP

1. **Run the installer as Administrator**
   - Right-click the downloaded file
   - Select "Run as administrator"

2. **Installation Wizard:**
   - Choose installation directory: `C:\xampp` (default)
   - Select components to install:
     - ✅ Apache
     - ✅ MySQL
     - ✅ PHP
     - ✅ phpMyAdmin
     - ✅ OpenSSL
     - ✅ phpMyAdmin

3. **Complete Installation:**
   - Allow firewall access when prompted
   - Finish the installation

### 1.3 Start XAMPP Control Panel

1. **Launch XAMPP Control Panel:**
   - Search for "XAMPP Control Panel" in Windows search
   - Run as Administrator

2. **Start Services:**
   - Click "Start" next to Apache
   - Click "Start" next to MySQL
   - Verify both show green status indicators

3. **Test Installation:**
   - Open browser and visit: http://localhost
   - You should see the XAMPP welcome page

## Step 2: Download CRM FMS

### 2.1 Download the CRM FMS Files

1. **Download the project files**
   - Extract the CRM FMS files to: `C:\xampp\htdocs\crmfms`
   - Ensure the folder structure is maintained

2. **Verify File Structure:**
   ```
   C:\xampp\htdocs\crmfms\
   ├── api\                 # Backend API endpoints
   ├── config\              # Configuration files
   ├── public\              # Frontend files
   │   ├── modules\         # HTML modules
   │   ├── assests\         # CSS, JS, fonts
   │   └── index.html       # Main entry point
   ├── scripts\             # Setup and utility scripts
   ├── setup\               # Setup files and documentation
   ├── .htaccess           # Apache configuration
   ├── composer.json       # PHP dependencies
   └── README.md           # Project documentation
   ```

### 2.2 Verify Installation

1. **Check file permissions:**
   - Right-click on `crmfms` folder
   - Properties → Security → Edit
   - Ensure "Full Control" for your user account

## Step 3: Configure PHP Settings

### 3.1 Open PHP Configuration

1. **Navigate to PHP directory:**
   ```
   C:\xampp\php\
   ```

2. **Edit php.ini:**
   - Open `php.ini` in Notepad or any text editor
   - Make the following changes:

### 3.2 Update PHP Settings

Find and update these settings:

```ini
; Maximum execution time
max_execution_time = 300

; Maximum input time
max_input_time = 300

; Memory limit
memory_limit = 256M

; Post max size
post_max_size = 50M

; Upload max filesize
upload_max_filesize = 50M

; Display errors (for development)
display_errors = On

; Error reporting
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

### 3.3 Enable Required Extensions

Ensure these extensions are enabled (remove semicolon if present):

```ini
extension=gd
extension=mbstring
extension=mysqli
extension=pdo_mysql
extension=openssl
extension=json
extension=curl
extension=zip
extension=fileinfo
```

### 3.4 Restart Apache

1. **In XAMPP Control Panel:**
   - Stop Apache
   - Start Apache again
   - Check Apache error log if it fails to start

## Step 4: Set Up MySQL Database

### Quick Setup (Recommended)

1. **Run the Windows Setup Script:**
   ```cmd
   cd C:\xampp\htdocs\crmfms
   setup_windows.bat
   ```

2. **Choose Sample Data:**
   - When prompted: `Do you want to install sample data for testing? (y/n):`
   - Type `y` to install sample data with test accounts

3. **Test Credentials (After Sample Data Installation):**
   ```
   Admin:     admin@crmfms.local     / password
   Dean:      dean@crmfms.local      / password
   Secretary: secretary@crmfms.local / password
   Faculty:   faculty1@crmfms.local  / password
   Staff:     staff1@crmfms.local    / password
   ```

### Manual Setup Option A: Using phpMyAdmin

1. **Open phpMyAdmin:**
   - Visit: http://localhost/phpmyadmin
   - Default credentials:
     - Username: `root`
     - Password: *(leave blank)*

2. **Create Database:**
   - Click "Databases" tab
   - Database name: `faculty_attendance_system`
   - Collation: `utf8mb4_general_ci`
   - Click "Create"

3. **Import Database Schema:**
   - Select the `faculty_attendance_system` database
   - Click "Import" tab
   - Click "Choose File" and select `setup/setup_database_clean.sql`
   - Click "Go" to import

4. **Import Sample Data (Optional):**
   - Click "Import" tab again
   - Select `setup/setup_sample_data.sql`
   - Click "Go" to import sample data

### Manual Setup Option B: Using Command Line

1. **Open Command Prompt as Administrator:**
   - Press `Win + X`
   - Select "Windows Terminal (Admin)" or "Command Prompt (Admin)"

2. **Navigate to MySQL bin directory:**
   ```cmd
   cd C:\xampp\mysql\bin
   ```

3. **Create database and import schema:**
   ```cmd
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS faculty_attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
   mysql -u root faculty_attendance_system < C:\xampp\htdocs\crmfms\setup\setup_database_clean.sql
   ```

4. **Import sample data (optional):**
   ```cmd
   mysql -u root faculty_attendance_system < C:\xampp\htdocs\crmfms\setup\setup_sample_data.sql
   ```

## Step 5: Configure CRM FMS

### 5.1 Update Database Configuration

1. **Open database configuration:**
   - File: `C:\xampp\htdocs\crmfms\config\database.php`

2. **Verify settings:**
   ```php
   private $host = 'localhost';
   private $db_name = 'faculty_attendance_system';
   private $username = 'root';
   private $password = '';
   ```

### 5.2 Create Required Directories

Create these folders if they don't exist:

```cmd
mkdir C:\xampp\htdocs\crmfms\logs
mkdir C:\xampp\htdocs\crmfms\public\uploads
mkdir C:\xampp\htdocs\crmfms\public\qr-codes
mkdir C:\xampp\htdocs\crmfms\backups
```

### 5.3 Set Directory Permissions

1. **Right-click on the `crmfms` folder**
2. **Properties → Security → Edit**
3. **Add permissions:**
   - Add user: `Everyone`
   - Permissions: `Full Control`
   - Apply to: `This folder, subfolders and files`

## Step 6: Configure Apache Virtual Host (Optional but Recommended)

### 6.1 Edit Apache Configuration

1. **Open httpd.conf:**
   - File: `C:\xampp\apache\conf\httpd.conf`

2. **Enable Virtual Hosts:**
   - Find and uncomment this line (remove #):
   ```apache
   Include conf/extra/httpd-vhosts.conf
   ```

### 6.2 Edit Virtual Hosts Configuration

1. **Open httpd-vhosts.conf:**
   - File: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

2. **Add Virtual Host:**
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

### 6.3 Update Windows Hosts File

1. **Open Notepad as Administrator**
2. **Open hosts file:**
   - File: `C:\Windows\System32\drivers\etc\hosts`

3. **Add this line at the end:**
   ```
   127.0.0.1    crmfms.local
   ```

4. **Save the file**

### 6.4 Restart Apache

- In XAMPP Control Panel, stop and restart Apache

## Step 7: Test the Installation

### 7.1 Access the Application

- **With Virtual Host:** http://crmfms.local
- **Without Virtual Host:** http://localhost/crmfms/public

### 7.2 Test Database Connection

- The application should load without database errors
- Check browser developer console for any JavaScript errors

### 7.3 Test Login (if sample data was installed)

- **Email:** `admin@crmfms.local`
- **Password:** `password`

### 7.4 Test User Accounts

After installing sample data, test with these accounts:

| Role | Email | Employee ID | Password | Use Case |
|------|-------|-------------|----------|----------|
| Admin | admin@crmfms.local | CRIM001 | password | Full system access |
| Dean | dean@crmfms.local | CRIM002 | password | Approvals & oversight |
| Secretary | secretary@crmfms.local | CRIM003 | password | Administrative tasks |
| Faculty | faculty1@crmfms.local | CRIM009 | password | Attendance & scheduling |
| Staff | staff1@crmfms.local | CRIM004 | password | Basic functionality |

### 7.5 Testing Checklist

- [ ] Login with admin account - verify full access
- [ ] Login with faculty account - test attendance features
- [ ] Login with staff account - test basic functionality
- [ ] Test role-based permissions
- [ ] Verify reports are accessible
- [ ] Test attendance check-in/check-out
- [ ] Test user creation (employee IDs should auto-generate as CRIMxxx)

## Step 8: Post-Installation Configuration

### 8.1 Enable SSL (Optional but Recommended)

1. **Generate SSL Certificate:**
   ```cmd
   cd C:\xampp\apache
   bin\openssl req -new -newkey rsa:2048 -nodes -keyout crmfms.key -out crmfms.csr
   bin\openssl x509 -req -days 365 -in crmfms.csr -signkey crmfms.key -out crmfms.crt
   ```

2. **Configure SSL in Apache:**
   - Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`
   - Update certificate paths:
   ```apache
   SSLCertificateFile "C:/xampp/apache/crmfms.crt"
   SSLCertificateKeyFile "C:/xampp/apache/crmfms.key"
   ```

3. **Enable SSL Module:**
   - Edit `C:\xampp\apache\conf\httpd.conf`
   - Uncomment: `LoadModule ssl_module modules/mod_ssl.so`
   - Uncomment: `Include conf/extra/httpd-ssl.conf`

4. **Restart Apache**

### 8.2 Set Up Automated Backups

1. **Create Backup Script:**
   - Create `C:\xampp\htdocs\crmfms\backup.bat`:
   ```batch
   @echo off
   setlocal enabledelayedexpansion

   set BACKUP_DIR=C:\xampp\htdocs\crmfms\backups
   set TIMESTAMP=%DATE:~10,4%%DATE:~4,2%%DATE:~7,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%
   set TIMESTAMP=%TIMESTAMP: =0%
   set BACKUP_FILE=%BACKUP_DIR%\crmfms_backup_%TIMESTAMP%.sql

   if not exist %BACKUP_DIR% mkdir %BACKUP_DIR%

   "C:\xampp\mysql\bin\mysqldump.exe" -u root faculty_attendance_system > %BACKUP_FILE%

   echo Backup completed: %BACKUP_FILE%
   echo Timestamp: %TIMESTAMP%
   ```

2. **Schedule Automated Backups:**
   - Open Task Scheduler (search for it in Windows)
   - Create new task
   - Program: `C:\xampp\htdocs\crmfms\backup.bat`
   - Schedule: Daily at preferred time (e.g., 2:00 AM)

### 8.3 Configure Email Settings (Optional)

If you want to enable email notifications:

1. **Edit PHP Configuration:**
   - Open `C:\xampp\php\php.ini`
   - Configure SMTP settings:
   ```ini
   [mail function]
   SMTP = smtp.gmail.com
   smtp_port = 587
   sendmail_from = your-email@gmail.com
   sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
   ```

2. **Configure sendmail:**
   - Edit `C:\xampp\sendmail\sendmail.ini`
   - Update SMTP settings for your email provider

## Troubleshooting

### Common Issues

#### 1. Apache won't start
- **Symptoms:** Apache fails to start in XAMPP Control Panel
- **Solutions:**
  - Check if port 80 is being used by another application (Skype, IIS)
  - Run XAMPP as Administrator
  - Check Apache error logs: `C:\xampp\apache\logs\error.log`
  - Stop IIS if running: `net stop W3SVC`

#### 2. MySQL won't start
- **Symptoms:** MySQL fails to start
- **Solutions:**
  - Check if port 3306 is being used
  - Ensure no other MySQL instances are running
  - Check MySQL error logs: `C:\xampp\mysql\data\mysql_error.log`
  - Delete `ibdata1` file if corrupted (backup first!)

#### 3. Database connection errors
- **Symptoms:** "Database connection failed" errors
- **Solutions:**
  - Verify database credentials in `config/database.php`
  - Ensure database exists: `faculty_attendance_system`
  - Check MySQL service is running
  - Test connection via phpMyAdmin

#### 4. Permission errors
- **Symptoms:** File upload errors, directory creation failures
- **Solutions:**
  - Run XAMPP as Administrator
  - Check folder permissions (give Full Control to Everyone)
  - Disable Windows Defender temporarily for testing
  - Add XAMPP to Windows Defender exclusions

#### 5. PHP errors
- **Symptoms:** Blank pages, PHP errors
- **Solutions:**
  - Check PHP error logs: `C:\xampp\php\logs\php_error_log`
  - Verify PHP extensions are enabled
  - Check PHP version compatibility (8.1+ required)
  - Enable `display_errors` in php.ini for debugging

#### 6. Virtual Host not working
- **Symptoms:** crmfms.local shows XAMPP default page
- **Solutions:**
  - Verify hosts file entry: `127.0.0.1 crmfms.local`
  - Check httpd-vhosts.conf syntax
  - Restart Apache after changes
  - Clear browser cache/DNS cache

### Getting Help

1. **Check Application Logs:**
   - Location: `C:\xampp\htdocs\crmfms\logs\`
   - Check for PHP errors, database errors

2. **Verify Installation:**
   - Ensure all files were extracted correctly
   - Check file permissions
   - Test with sample data first

3. **System Requirements Check:**
   - Windows 10/11 64-bit
   - 4GB+ RAM available
   - Administrator privileges
   - Antivirus exclusions for XAMPP folder

## Security Recommendations

### 1. Change Default Passwords
- Update admin password immediately after installation
- Use strong passwords for all users
- Implement password complexity requirements

### 2. Database Security
- Create a dedicated MySQL user for the application:
  ```sql
  CREATE USER 'crmfms_user'@'localhost' IDENTIFIED BY 'strong_password';
  GRANT ALL PRIVILEGES ON faculty_attendance_system.* TO 'crmfms_user'@'localhost';
  FLUSH PRIVILEGES;
  ```
- Don't use root user in production
- Regularly backup the database

### 3. File System Security
- Restrict access to sensitive files
- Use proper file permissions
- Regularly scan for malware
- Move sensitive files outside web root

### 4. Network Security
- Use HTTPS in production
- Implement proper firewall rules
- Keep all software updated
- Use fail2ban or similar for brute force protection

### 5. Application Security
- Keep CRM FMS updated
- Monitor logs regularly
- Implement session timeouts
- Use prepared statements (already implemented)
- Validate all user inputs

## Next Steps

### 1. Explore the Application
- Log in with admin credentials
- Create additional users (employee IDs auto-generate as CRIMxxx)
- Set up buildings, rooms, and departments
- Configure schedules and QR codes
- Test attendance system

### 2. Customize Settings
- Update organization information
- Configure alert thresholds
- Set up automated monitoring
- Customize email templates

### 3. Training
- Train staff on system usage
- Create user manuals
- Establish backup procedures
- Set up maintenance schedules

### 4. Production Deployment
- Set up proper domain name
- Configure SSL certificates
- Implement backup strategies
- Set up monitoring and alerting

---

## 📞 Support

If you encounter issues:

1. **Check the troubleshooting section above**
2. **Review the application logs**
3. **Verify all prerequisites are met**
4. **Test with sample data first**
5. **Check the GitHub repository for updates**

## 🎉 Installation Complete!

**Congratulations!** Your CRM FMS is now ready to use.

**Access URLs:**
- Main Application: http://crmfms.local (with virtual host)
- Alternative: http://localhost/crmfms/public
- phpMyAdmin: http://localhost/phpmyadmin

**Default Admin Credentials:**
- Email: `admin@crmfms.local`
- Password: `password`

**Next Steps:**
1. Log in and explore the system
2. Create your first users (employee IDs will auto-generate)
3. Set up your buildings and rooms
4. Configure attendance settings
5. Start using the system!

---

*This setup guide was last updated for CRM FMS with auto-generating CRIM employee IDs and enhanced security features.*