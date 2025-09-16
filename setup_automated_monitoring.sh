#!/bin/bash

# CRM FMS Automated Monitoring Setup Script
# This script sets up the complete automated monitoring system

echo "========================================"
echo "CRM FMS Automated Monitoring Setup"
echo "========================================"

# Check if we're in the right directory
if [ ! -f "setup_monitoring.sql" ]; then
    echo "Error: setup_monitoring.sql not found. Please run this script from the CRM FMS root directory."
    exit 1
fi

echo "Step 1: Setting up database tables..."
if [ -f "setup_monitoring.sql" ]; then
    mysql -u root -S /opt/lampp/var/mysql/mysql.sock < setup_monitoring.sql
    if [ $? -eq 0 ]; then
        echo "✓ Database tables created successfully"
    else
        echo "✗ Failed to create database tables"
        exit 1
    fi
else
    echo "✗ Database setup file not found"
    exit 1
fi

echo ""
echo "Step 2: Setting up cron job for automated monitoring..."
if [ -f "setup_monitoring_cron.sh" ]; then
    chmod +x setup_monitoring_cron.sh
    ./setup_monitoring_cron.sh
    if [ $? -eq 0 ]; then
        echo "✓ Cron job setup completed"
    else
        echo "✗ Failed to setup cron job"
        exit 1
    fi
else
    echo "✗ Cron setup script not found"
    exit 1
fi

echo ""
echo "Step 3: Creating logs directory..."
mkdir -p logs
chmod 755 logs
echo "✓ Logs directory created"

echo ""
echo "Step 4: Testing monitoring script..."
if [ -f "scripts/monitor_scheduler.php" ]; then
    php scripts/monitor_scheduler.php
    if [ $? -eq 0 ]; then
        echo "✓ Monitoring script test completed"
    else
        echo "✗ Monitoring script test failed"
    fi
else
    echo "✗ Monitoring script not found"
fi

echo ""
echo "========================================"
echo "Setup Summary:"
echo "========================================"
echo "✓ Database tables for monitoring created"
echo "✓ Automated monitoring script configured"
echo "✓ Cron job scheduled (runs every 5 minutes)"
echo "✓ System and database metrics collection enabled"
echo "✓ Alert system configured with default thresholds"
echo ""
echo "Access the monitoring dashboard at:"
echo "http://localhost/crmfms/public/modules/monitoring/monitoring.html"
echo ""
echo "To view monitoring logs:"
echo "tail -f /opt/lampp/htdocs/crmfms/logs/monitor_scheduler.log"
echo ""
echo "To modify alert thresholds, visit the monitoring dashboard"
echo "and use the Alert Configurations section."
echo ""
echo "Setup completed successfully!"