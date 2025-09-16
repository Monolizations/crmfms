#!/bin/bash

# CRM FMS Automated Alerts Setup Script
# This script sets up automated system monitoring and alert generation

echo "Setting up automated alerts for CRM FMS..."

# Check if we're in the right directory
if [ ! -f "scripts/generate_system_alerts.php" ]; then
    echo "Error: Please run this script from the CRM FMS root directory"
    exit 1
fi

# Create cron job for automated alert generation (every 5 minutes)
CRON_JOB="*/5 * * * * /usr/bin/php /opt/lampp/htdocs/crmfms/scripts/generate_system_alerts.php"

# Check if cron job already exists
if crontab -l | grep -q "generate_system_alerts.php"; then
    echo "Automated alerts cron job already exists"
else
    # Add the cron job
    (crontab -l ; echo "$CRON_JOB") | crontab -
    echo "Added automated alerts cron job (runs every 5 minutes)"
fi

# Create cron job for system monitoring data collection (every 2 minutes)
MONITOR_CRON="*/2 * * * * curl -s 'http://localhost/crmfms/api/monitoring/system_monitor.php?action=store'"

if crontab -l | grep -q "system_monitor.php"; then
    echo "System monitoring cron job already exists"
else
    (crontab -l ; echo "$MONITOR_CRON") | crontab -
    echo "Added system monitoring cron job (runs every 2 minutes)"
fi

# Create cron job for database monitoring (every 3 minutes)
DB_MONITOR_CRON="*/3 * * * * curl -s 'http://localhost/crmfms/api/monitoring/database_monitor.php?action=store'"

if crontab -l | grep -q "database_monitor.php"; then
    echo "Database monitoring cron job already exists"
else
    (crontab -l ; echo "$DB_MONITOR_CRON") | crontab -
    echo "Added database monitoring cron job (runs every 3 minutes)"
fi

echo "Automated alerts setup completed!"
echo ""
echo "The following automated tasks have been configured:"
echo "- System metrics collection: Every 2 minutes"
echo "- Database metrics collection: Every 3 minutes"
echo "- Alert generation: Every 5 minutes"
echo ""
echo "Alerts will be automatically created when system thresholds are exceeded:"
echo "- CPU usage > 80% (warning) or > 95% (error)"
echo "- Memory usage > 85% (warning) or > 95% (error)"
echo "- Disk usage > 90% (warning) or > 95% (error)"
echo "- System load > 2.0 (warning)"
echo "- Database connections > 80% (warning) or > 95% (error)"
echo "- Slow queries > 10 (warning)"
echo ""
echo "You can view these alerts in the admin dashboard under 'System Alerts'"