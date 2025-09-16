#!/bin/bash

# CRM FMS Database Setup Script
echo "Setting up CRM FMS database tables..."

# Database connection details
DB_HOST="127.0.0.1"
DB_NAME="faculty_attendance_system"
DB_USER="root"
DB_PASS=""

# Check if MySQL/MariaDB is running
if ! pgrep -x "mysqld" > /dev/null && ! pgrep -x "mariadbd" > /dev/null; then
    echo "Error: MySQL/MariaDB is not running. Please start the database service first."
    exit 1
fi

# Run the clean setup SQL (includes database creation)
echo "Setting up database and creating tables..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" < setup_database_clean.sql

# Ask if user wants sample data
echo ""
read -p "Do you want to install sample data for testing? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Installing sample data..."
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < setup_sample_data.sql
    echo "Sample data installed successfully!"
fi

if [ $? -eq 0 ]; then
    echo "Database setup completed successfully!"
    echo "You can now access the application with database-backed system alerts."
else
    echo "Error: Failed to set up database. Please check your MySQL credentials and try again."
    exit 1
fi