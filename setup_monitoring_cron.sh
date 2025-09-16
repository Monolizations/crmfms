#!/bin/bash

# CRM FMS Monitoring Cron Setup Script
# This script sets up automated monitoring via cron jobs

echo "Setting up CRM FMS automated monitoring..."

# Check if we're running as root or with sudo
if [[ $EUID -eq 0 ]]; then
    CRON_USER="root"
else
    CRON_USER=$(whoami)
fi

# Path to the monitoring script
MONITOR_SCRIPT="/opt/lampp/htdocs/crmfms/scripts/monitor_scheduler.php"
PHP_BINARY="/usr/bin/php"

# Check if PHP binary exists
if [ ! -f "$PHP_BINARY" ]; then
    echo "PHP binary not found at $PHP_BINARY"
    # Try to find PHP
    PHP_BINARY=$(which php)
    if [ -z "$PHP_BINARY" ]; then
        echo "PHP not found. Please install PHP or update PHP_BINARY path in this script."
        exit 1
    fi
fi

# Check if monitoring script exists
if [ ! -f "$MONITOR_SCRIPT" ]; then
    echo "Monitoring script not found at $MONITOR_SCRIPT"
    exit 1
fi

# Create cron job command
CRON_COMMAND="$PHP_BINARY $MONITOR_SCRIPT"

# Add to crontab (every 5 minutes)
CRON_JOB="*/5 * * * * $CRON_COMMAND"

# Check if cron job already exists
EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$MONITOR_SCRIPT" || true)

if [ -n "$EXISTING_CRON" ]; then
    echo "Monitoring cron job already exists. Skipping..."
else
    # Add the cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "Monitoring cron job added successfully!"
    echo "The monitoring script will run every 5 minutes."
fi

# Display current crontab
echo ""
echo "Current cron jobs for user $CRON_USER:"
crontab -l

echo ""
echo "To modify the monitoring interval, edit the cron job with: crontab -e"
echo "To disable monitoring, comment out or remove the monitoring line."
echo ""
echo "Monitoring setup complete!"