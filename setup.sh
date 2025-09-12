#!/bin/bash

# CRM FMS Setup Script
echo "Setting up CRM FMS with virtual host..."

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script with sudo"
    exit 1
fi

# Copy virtual host configuration
echo "Copying virtual host configuration..."
cp /home/mono/crmfms/crmfms.conf /etc/apache2/sites-available/

# Enable the site
echo "Enabling the site..."
a2ensite crmfms.conf

# Enable required Apache modules
echo "Enabling required Apache modules..."
a2enmod rewrite
a2enmod headers

# Add entry to hosts file
echo "Adding entry to /etc/hosts..."
echo "127.0.0.1 crmfms.local" >> /etc/hosts

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /home/mono/crmfms
chmod -R 755 /home/mono/crmfms

# Restart Apache
echo "Restarting Apache..."
systemctl restart apache2

echo "Setup complete!"
echo "You can now access the application at: http://crmfms.local"
echo ""
echo "Make sure you have:"
echo "1. MySQL/MariaDB running with database 'faculty_attendance_system'"
echo "2. PHP installed with PDO MySQL extension"
echo "3. Created the necessary database tables"
