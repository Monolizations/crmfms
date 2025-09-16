#!/bin/bash

# CRM FMS Full Database Setup Script
echo "Setting up CRM FMS full database..."

# Database connection details
DB_HOST="127.0.0.1"
DB_NAME="faculty_attendance_system"
DB_USER="root"
DB_PASS=""

# Check if MySQL/MariaDB is running
if ! pgrep -x "mysqld" > /dev/null && ! pgrep -x "mariadbd" > /dev/null; then
    echo "Error: MySQL/MariaDB is not running. Please start XAMPP with: sudo /opt/lampp/lampp start"
    exit 1
fi

# Create database if not exists
echo "Creating database..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"

# Run the full setup SQL
echo "Creating tables and inserting data..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < setup_database_full.sql

if [ $? -eq 0 ]; then
    echo "Full database setup completed successfully!"
    echo "Test credentials:"
    echo "Admin: admin@crmfms.local / password"
    echo "Staff: emily.davis@crmfms.local / password"
    echo "Faculty: john.smith@crmfms.local / password"
else
    echo "Error: Failed to set up database."
    exit 1
fi