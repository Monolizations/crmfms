# CRM Faculty Management System (FMS)

A comprehensive web-based system for managing faculty attendance, scheduling, and monitoring in educational institutions.

## Features

### Core Functionality
- **Real-time Attendance Tracking**: QR code-based check-in/check-out system
- **Faculty Management**: Complete faculty profile and role management
- **Room & Facility Management**: Building, floor, and room management
- **Scheduling System**: Automated class scheduling and conflict detection
- **Leave Management**: Comprehensive leave request and approval system
- **Reporting & Analytics**: Advanced reporting with multiple report types

### Advanced Features
- **System Monitoring**: Real-time system health monitoring
- **Alert System**: Automated alerts for system events and attendance issues
- **QR Code Integration**: Seamless QR code generation and scanning
- **Role-based Access Control**: Granular permissions system
- **Audit Trail**: Complete activity logging and tracking
- **Mobile Responsive**: Works on all devices

## Quick Start

### Clean Installation (Recommended)

1. **Clone or Download the Repository**
   ```bash
   git clone <repository-url>
   cd crmfms
   ```

2. **Run Clean Setup**
   ```bash
   # For Linux/macOS
   ./setup_clean.sh

   # For Windows (see WINDOWS_SETUP.md)
   # Follow the Windows setup guide
   ```

3. **Access the Application**
   - URL: http://localhost/crmfms/public
   - **Test Credentials:** See `TEST_CREDENTIALS.md` for all test accounts
   - **Quick Test:** `admin@test.com` / `test123` (all test accounts use `test123`)

### Manual Setup

1. **Database Setup**
   ```bash
   # Import complete schema (includes database creation)
   mysql -u root < setup_database_clean.sql

   # Optional: Import sample data
   mysql -u root faculty_attendance_system < setup_sample_data.sql
   ```

2. **Web Server Configuration**
   - Ensure Apache/Nginx serves from the `public` directory
   - Enable URL rewriting
   - Set proper file permissions

3. **Access Application**
   - Visit: http://your-domain/crmfms/public

## System Requirements

### Minimum Requirements
- **PHP**: 8.1 or higher
- **MySQL/MariaDB**: 5.7 or higher
- **Web Server**: Apache/Nginx
- **Browser**: Modern browser with JavaScript enabled
- **Disk Space**: 100MB minimum

### Recommended Specifications
- **PHP**: 8.2+
- **MySQL**: 8.0+
- **RAM**: 2GB minimum
- **CPU**: Dual-core processor
- **Storage**: SSD recommended

## Installation Options

### Option 1: Clean Setup (Recommended)
- Uses `setup_clean.sh` script
- Creates fresh database schema
- Optional sample data installation
- Automated permission setup

### Option 2: Full Setup (Development)
- Uses `setup_database_full.sql`
- Includes test data and users
- Pre-configured for development

### Option 3: Windows with XAMPP
- Follow `WINDOWS_SETUP.md` guide
- XAMPP integrated setup
- Step-by-step Windows instructions
- Includes test credentials for all roles

## Directory Structure

```
crmfms/
├── api/                    # Backend API endpoints
│   ├── attendance/        # Attendance management
│   ├── auth/             # Authentication
│   ├── reports/          # Report generation
│   └── monitoring/       # System monitoring
├── config/               # Configuration files
│   ├── database.php      # Database configuration
│   ├── cors.php         # CORS settings
│   └── security.php     # Security functions
├── public/              # Web root directory
│   ├── assets/         # Static assets (CSS, JS, images)
│   ├── modules/        # Frontend modules
│   └── index.html      # Main entry point
├── scripts/            # Utility scripts
├── logs/              # Application logs
├── setup_*.sql       # Database setup files
└── setup_*.sh       # Setup scripts
```

## Database Schema

The system uses a comprehensive database schema with the following main tables:

- **Core Tables**: `users`, `roles`, `user_roles`
- **Facility Tables**: `buildings`, `floors`, `rooms`
- **Attendance Tables**: `attendance`, `faculty_presence`
- **Scheduling Tables**: `schedules`, `leave_requests`
- **System Tables**: `audit_trail`, `system_alerts`, `qr_codes`

## User Roles & Permissions

### Available Roles
- **Admin**: Full system access
- **Dean**: Oversight and approval capabilities
- **Secretary**: Administrative functions
- **Program Head**: Departmental management
- **Faculty**: Teaching staff
- **Staff**: Support staff

### Permission Levels
- **View**: Read-only access
- **Edit**: Modify records
- **Create**: Add new records
- **Delete**: Remove records
- **Approve**: Approve requests
- **Manage**: Full administrative control

## API Documentation

### Authentication Endpoints
- `POST /api/auth/login.php` - User login
- `POST /api/auth/logout.php` - User logout
- `GET /api/auth/me.php` - Get current user info

### Attendance Endpoints
- `POST /api/attendance/checkin.php` - Check in
- `POST /api/attendance/checkout.php` - Check out
- `GET /api/attendance/status.php` - Get attendance status

### Report Endpoints
- `POST /api/reports/reports.php` - Generate reports
- `GET /api/reports/reports.php?action=latest_date` - Get latest date

## Configuration

### Database Configuration
Edit `config/database.php`:
```php
private $host = 'localhost';
private $db_name = 'faculty_attendance_system';
private $username = 'your_db_user';
private $password = 'your_db_password';
```

### System Settings
Configure via database tables:
- `monitoring_settings` - System monitoring preferences
- `alert_configurations` - Alert thresholds and rules

## Security Features

- **Password Hashing**: Bcrypt password encryption
- **Session Management**: Secure session handling
- **CSRF Protection**: Cross-site request forgery prevention
- **Input Validation**: Comprehensive input sanitization
- **Audit Logging**: Complete activity tracking
- **Role-based Access**: Granular permission system

## Backup & Recovery

### Automated Backups
```bash
# Create backup script
mysqldump -u username -p faculty_attendance_system > backup.sql

# Schedule with cron (Linux)
0 2 * * * mysqldump -u username -p faculty_attendance_system > /path/to/backups/backup_$(date +\%Y\%m\%d).sql
```

### Recovery
```bash
# Restore from backup
mysql -u username -p faculty_attendance_system < backup.sql
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials
   - Verify MySQL service is running
   - Check database exists

2. **Permission Errors**
   - Set proper file permissions
   - Check web server user permissions
   - Verify directory ownership

3. **QR Code Not Working**
   - Check GD extension is enabled
   - Verify file permissions on QR directory
   - Check PHP memory limit

4. **Reports Not Loading**
   - Check database connectivity
   - Verify user permissions
   - Check PHP error logs

### Debug Mode
Enable debug logging in PHP:
```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

## Development

### Local Development Setup
1. Clone repository
2. Run `./setup_clean.sh`
3. Install sample data
4. Access at `http://localhost/crmfms/public`

### Code Standards
- **PHP**: PSR-12 coding standards
- **JavaScript**: ESLint configuration
- **CSS**: BEM methodology
- **Database**: Consistent naming conventions

### Testing
```bash
# Run setup verification
php scripts/test_setup.php

# Test with sample data
# All test accounts use password: test123
# See TEST_CREDENTIALS.md for complete list
```

### Test User Accounts
When you install sample data, these test accounts are available:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@test.com | test123 |
| Dean | dean@test.com | test123 |
| Secretary | secretary@test.com | test123 |
| Program Head | programhead@test.com | test123 |
| Faculty | faculty1@test.com | test123 |
| Staff | staff1@test.com | test123 |

**Full list:** See `TEST_CREDENTIALS.md` for all test accounts and testing scenarios.

## Contributing

1. Fork the repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Submit pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

### Documentation
- [API Documentation](./docs/api.md)
- [User Manual](./docs/user-manual.md)
- [Installation Guide](./WINDOWS_SETUP.md)

### Getting Help
- Check application logs in `logs/` directory
- Review PHP error logs
- Verify database connectivity
- Test with sample data first

## Changelog

### Version 2.0.0 (Current)
- Complete system rewrite
- Clean database schema
- Improved user interface
- Enhanced security features
- Mobile responsive design
- Comprehensive reporting system

### Version 1.x Legacy
- Basic attendance tracking
- Simple reporting
- Limited user roles

---

**For detailed setup instructions, see:**
- [Windows Setup Guide](./WINDOWS_SETUP.md)
- [Linux Setup Guide](./setup_clean.sh)
- [API Documentation](./docs/api.md)