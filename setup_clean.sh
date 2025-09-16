#!/bin/bash

# CRM FMS Clean Setup Script
# This script sets up a fresh CRM FMS installation

set -e

echo "========================================"
echo "CRM FMS Clean Setup"
echo "========================================"

# Check if we're in the right directory
if [ ! -f "setup_database_clean.sql" ]; then
    echo "Error: setup_database_clean.sql not found. Please run this script from the CRM FMS root directory."
    exit 1
fi

# Check if MySQL is running
if ! pgrep mysqld > /dev/null; then
    echo "Warning: MySQL does not appear to be running. Please start MySQL service first."
    echo "On Linux: sudo service mysql start"
    echo "On macOS: brew services start mysql"
    exit 1
fi

echo "Step 1: Setting up fresh database schema..."
if [ -f "setup_database_clean.sql" ]; then
    echo "Creating clean database schema..."
    mysql -u root < setup_database_clean.sql
    if [ $? -eq 0 ]; then
        echo "✓ Database schema created successfully"
    else
        echo "✗ Failed to create database schema"
        exit 1
    fi
else
    echo "✗ Database schema file not found"
    exit 1
fi

echo ""
echo "Step 2: Setting up sample data (optional)..."
read -p "Do you want to install sample data for testing? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if [ -f "setup_sample_data.sql" ]; then
        mysql -u root < setup_sample_data.sql
        if [ $? -eq 0 ]; then
            echo "✓ Sample data installed successfully"
            echo ""
            echo "Test Credentials:"
            echo "=================="
            echo "Admin:     admin@test.com     / test123"
            echo "Dean:      dean@test.com      / test123"
            echo "Secretary: secretary@test.com / test123"
            echo "Faculty:   faculty1@test.com  / test123"
            echo "Staff:     staff1@test.com    / test123"
            echo ""
            echo "All test accounts use password: test123"
            echo "See TEST_CREDENTIALS.md for complete list"
        else
            echo "✗ Failed to install sample data"
        fi
    else
        echo "✗ Sample data file not found"
    fi
fi

echo ""
echo "Step 3: Creating necessary directories..."
mkdir -p logs
mkdir -p public/uploads
mkdir -p public/qr-codes
chmod 755 logs
chmod 755 public/uploads
chmod 755 public/qr-codes
echo "✓ Directories created"

echo ""
echo "Step 4: Setting up file permissions..."
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type f -name "*.js" -exec chmod 644 {} \;
find . -type f -name "*.css" -exec chmod 644 {} \;
find . -type f -name "*.html" -exec chmod 644 {} \;
find . -type f -name "*.sh" -exec chmod 755 {} \;
echo "✓ File permissions set"

echo ""
echo "Step 5: Testing database connection..."
php -r "
require_once 'config/database.php';
try {
    \$db = (new Database())->getConnection();
    echo '✓ Database connection successful' . PHP_EOL;

    // Test basic queries
    \$stmt = \$db->query('SELECT COUNT(*) as user_count FROM users');
    \$result = \$stmt->fetch();
    echo '✓ Users table accessible (' . \$result['user_count'] . ' users)' . PHP_EOL;

    \$stmt = \$db->query('SELECT COUNT(*) as role_count FROM roles');
    \$result = \$stmt->fetch();
    echo '✓ Roles table accessible (' . \$result['role_count'] . ' roles)' . PHP_EOL;
} catch (Exception \$e) {
    echo '✗ Database connection failed: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

echo ""
echo "========================================"
echo "Setup Summary:"
echo "========================================"
echo "✓ Fresh database schema created"
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "✓ Sample data installed"
fi
echo "✓ File permissions configured"
echo "✓ Database connection tested"
echo ""
echo "Next Steps:"
echo "1. Configure your web server to serve the CRM FMS application"
echo "2. Access the application at: http://localhost/crmfms"
echo "3. Default admin credentials (if sample data was installed):"
echo "   - Email: admin@crmfms.local"
echo "   - Password: password"
echo ""
echo "For production use:"
echo "1. Update database credentials in config/database.php"
echo "2. Change default passwords"
echo "3. Configure proper SSL certificates"
echo "4. Set up regular backups"
echo ""
echo "Clean setup completed successfully!"