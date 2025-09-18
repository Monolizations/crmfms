<?php
/**
 * CRM FMS Database Configuration Template
 *
 * Copy this file to config/database.php and update the settings below
 * for your specific environment.
 */

// Database Configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'faculty_attendance_system';
    private $username = 'root';        // Change to 'crmfms_user' for production
    private $password = '';            // Set password for production
    private $charset = 'utf8mb4';

    // For production environments, use these settings:
    // private $username = 'crmfms_user';
    // private $password = 'YourSecurePasswordHere123!';

    public function getConnection() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            return new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new PDOException("Database connection failed. Please check your configuration.");
        }
    }
}

// Environment-specific configurations
class Config {
    // Application settings
    const APP_NAME = 'CRM FMS';
    const APP_VERSION = '1.0.0';
    const DEBUG_MODE = true;  // Set to false in production

    // Security settings
    const SESSION_LIFETIME = 3600;  // 1 hour
    const PASSWORD_MIN_LENGTH = 8;
    const MAX_LOGIN_ATTEMPTS = 5;

    // File upload settings
    const MAX_UPLOAD_SIZE = 5242880;  // 5MB
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    // Email settings (configure for your SMTP server)
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'your-email@gmail.com';
    const SMTP_PASSWORD = 'your-app-password';
    const SMTP_ENCRYPTION = 'tls';

    // Paths
    const LOG_PATH = __DIR__ . '/../logs/';
    const UPLOAD_PATH = __DIR__ . '/../public/uploads/';
    const QR_PATH = __DIR__ . '/../public/qr-codes/';
    const BACKUP_PATH = __DIR__ . '/../backups/';
}

/*
 * PRODUCTION CHECKLIST:
 *
 * Before deploying to production, ensure:
 * 1. DEBUG_MODE is set to false
 * 2. Database user is not 'root'
 * 3. Strong password is set for database user
 * 4. SSL/HTTPS is enabled
 * 5. File permissions are restrictive
 * 6. Logs are monitored
 * 7. Backups are scheduled
 * 8. Firewall is configured
 * 9. Antivirus exclusions are set
 * 10. Regular security updates are scheduled
 */