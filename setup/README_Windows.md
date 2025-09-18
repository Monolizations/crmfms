# CRM FMS Windows Setup - Quick Reference

## 🚀 Quick Start (3 minutes)

1. **Install XAMPP** (Apache + MySQL + PHP)
2. **Extract CRM FMS** to `C:\xampp\htdocs\crmfms`
3. **Run setup:**
   ```cmd
   cd C:\xampp\htdocs\crmfms
   setup_windows.bat
   ```
4. **Choose "y"** for sample data
5. **Access:** http://localhost/crmfms/public

## 🔐 Default Login

- **Email:** `admin@crmfms.local`
- **Password:** `password`
- **Employee ID:** `CRIM001` (auto-generated)

## 📁 File Structure

```
C:\xampp\htdocs\crmfms\
├── setup\                 # Setup files
│   ├── setup_windows.bat  # Quick setup script
│   ├── setup_windows_secure.bat  # Production setup
│   ├── WINDOWS_SETUP.md   # Detailed guide
│   └── config_template.php # Config template
├── config\                # Configuration files
├── public\                # Web files (DocumentRoot)
├── api\                   # Backend API
├── scripts\               # Utility scripts
└── logs\                  # Application logs
```

## 🛠️ Available Setup Scripts

### Quick Setup (Development)
```cmd
setup_windows.bat
```
- Creates database with sample data
- Sets up test users
- Basic permissions

### Secure Setup (Production)
```cmd
setup_windows_secure.bat
```
- Creates dedicated database user
- Restrictive file permissions
- Security hardening

## 🔧 Manual Setup Steps

If automated setup fails:

1. **Start XAMPP** (Apache + MySQL)
2. **Create database:**
   ```sql
   CREATE DATABASE faculty_attendance_system;
   ```
3. **Import schema:**
   ```cmd
   mysql -u root faculty_attendance_system < setup/setup_database_clean.sql
   ```
4. **Import sample data:**
   ```cmd
   mysql -u root faculty_attendance_system < setup/setup_sample_data.sql
   ```
5. **Set permissions** on folders
6. **Access:** http://localhost/crmfms/public

## ⚙️ Configuration Files

### Database Config
- **File:** `config/database.php`
- **Template:** `setup/config_template.php`
- **Default:** root user, no password

### PHP Config
- **File:** `C:\xampp\php\php.ini`
- **Required:** GD, MySQLi, PDO extensions
- **Memory:** 256MB minimum

## 🔑 Test Accounts

| Role | Email | Employee ID | Password |
|------|-------|-------------|----------|
| Admin | admin@crmfms.local | CRIM001 | password |
| Dean | dean@crmfms.local | CRIM002 | password |
| Secretary | secretary@crmfms.local | CRIM003 | password |
| Staff | staff1@crmfms.local | CRIM004 | password |
| Faculty | faculty1@crmfms.local | CRIM009 | password |

## 🚨 Troubleshooting

### Common Issues

1. **"Access denied" errors:**
   - Run XAMPP as Administrator
   - Check folder permissions

2. **Database connection failed:**
   - Ensure MySQL is running
   - Check credentials in `config/database.php`

3. **Blank page:**
   - Check PHP error logs: `C:\xampp\php\logs\`
   - Enable `display_errors` in `php.ini`

4. **Port conflicts:**
   - Apache: Port 80 (check Skype/IIS)
   - MySQL: Port 3306 (check other MySQL instances)

### Logs Location
- **PHP Errors:** `C:\xampp\php\logs\php_error_log`
- **Apache Errors:** `C:\xampp\apache\logs\error.log`
- **MySQL Errors:** `C:\xampp\mysql\data\mysql_error.log`
- **App Logs:** `C:\xampp\htdocs\crmfms\logs\`

## 🔒 Security Checklist

- [ ] Change default admin password
- [ ] Create dedicated database user (not root)
- [ ] Set up HTTPS/SSL
- [ ] Configure firewall
- [ ] Set restrictive file permissions
- [ ] Schedule regular backups
- [ ] Keep software updated

## 📚 Additional Resources

- **Detailed Guide:** `setup/WINDOWS_SETUP.md`
- **Test Credentials:** `TEST_CREDENTIALS.md`
- **API Documentation:** `api/README.md`
- **Troubleshooting:** Check logs folder

## 🎯 Next Steps

1. **Login** with admin credentials
2. **Create users** (Employee IDs auto-generate as CRIMxxx)
3. **Set up buildings** and rooms
4. **Configure schedules**
5. **Test attendance system**

## 📞 Support

- Check `WINDOWS_SETUP.md` for detailed instructions
- Review logs for error details
- Ensure all prerequisites are met
- Test with sample data first

---

**Happy CRM FMS Installation! 🎉**