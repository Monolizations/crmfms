-- CRM FMS Automated Monitoring Database Schema
-- Run this script to create monitoring tables

USE faculty_attendance_system;

-- System monitoring metrics table
CREATE TABLE IF NOT EXISTS system_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cpu_usage DECIMAL(5,2) NOT NULL, -- CPU usage percentage
    memory_usage DECIMAL(5,2) NOT NULL, -- Memory usage percentage
    memory_used_mb INT NOT NULL, -- Memory used in MB
    memory_total_mb INT NOT NULL, -- Total memory in MB
    disk_usage DECIMAL(5,2) NOT NULL, -- Disk usage percentage
    disk_used_gb DECIMAL(10,2) NOT NULL, -- Disk used in GB
    disk_total_gb DECIMAL(10,2) NOT NULL, -- Total disk in GB
    load_average_1m DECIMAL(5,2), -- System load average (1 minute)
    load_average_5m DECIMAL(5,2), -- System load average (5 minutes)
    load_average_15m DECIMAL(5,2), -- System load average (15 minutes)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_cpu_usage (cpu_usage),
    INDEX idx_memory_usage (memory_usage),
    INDEX idx_disk_usage (disk_usage)
);

-- Database monitoring metrics table
CREATE TABLE IF NOT EXISTS database_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    connections_total INT NOT NULL, -- Total connections
    connections_active INT NOT NULL, -- Active connections
    connections_max INT NOT NULL, -- Max connections
    queries_per_second DECIMAL(10,2), -- Queries per second
    slow_queries INT DEFAULT 0, -- Slow queries count
    uptime_seconds INT NOT NULL, -- MySQL uptime in seconds
    bytes_received BIGINT DEFAULT 0, -- Bytes received
    bytes_sent BIGINT DEFAULT 0, -- Bytes sent
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_connections_active (connections_active),
    INDEX idx_queries_per_second (queries_per_second)
);

-- Alert configurations table
CREATE TABLE IF NOT EXISTS alert_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL, -- 'cpu', 'memory', 'disk', 'connections', 'queries'
    threshold_value DECIMAL(10,2) NOT NULL, -- Threshold value
    threshold_operator VARCHAR(10) NOT NULL DEFAULT '>', -- '>', '<', '>=', '<=', '='
    severity VARCHAR(20) NOT NULL DEFAULT 'warning', -- 'info', 'warning', 'error', 'critical'
    enabled BOOLEAN DEFAULT 1,
    notification_email VARCHAR(255), -- Email to send alerts to
    cooldown_minutes INT DEFAULT 60, -- Minutes to wait before sending same alert again
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_alert_type (alert_type),
    INDEX idx_enabled (enabled),
    INDEX idx_severity (severity)
);

-- Alert history table
CREATE TABLE IF NOT EXISTS alert_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_config_id INT,
    alert_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    severity VARCHAR(20) NOT NULL,
    current_value DECIMAL(10,2),
    threshold_value DECIMAL(10,2),
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'resolved', 'acknowledged'
    notification_sent BOOLEAN DEFAULT 0,
    FOREIGN KEY (alert_config_id) REFERENCES alert_configurations(id) ON DELETE SET NULL,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_triggered_at (triggered_at)
);

-- Insert default alert configurations (ignore duplicates)
INSERT IGNORE INTO alert_configurations (alert_type, threshold_value, threshold_operator, severity, description) VALUES
('cpu', 80.00, '>', 'warning', 'CPU usage exceeds 80%'),
('cpu', 95.00, '>', 'critical', 'CPU usage exceeds 95%'),
('memory', 85.00, '>', 'warning', 'Memory usage exceeds 85%'),
('memory', 95.00, '>', 'critical', 'Memory usage exceeds 95%'),
('disk', 90.00, '>', 'warning', 'Disk usage exceeds 90%'),
('disk', 95.00, '>', 'critical', 'Disk usage exceeds 95%'),
('connections', 80.00, '>', 'warning', 'Database connections exceed 80% of max'),
('connections', 95.00, '>', 'critical', 'Database connections exceed 95% of max'),
('slow_queries', 10, '>', 'warning', 'Slow queries detected (more than 10 per minute)');

-- Monitoring settings table
CREATE TABLE IF NOT EXISTS monitoring_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Insert default monitoring settings (ignore duplicates)
INSERT IGNORE INTO monitoring_settings (setting_key, setting_value, description) VALUES
('monitoring_enabled', '1', 'Enable/disable automated monitoring'),
('check_interval_minutes', '5', 'How often to run monitoring checks (minutes)'),
('retention_days', '30', 'How long to keep monitoring data (days)'),
('email_enabled', '0', 'Enable email notifications'),
('smtp_host', '', 'SMTP server hostname'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP authentication username'),
('smtp_password', '', 'SMTP authentication password'),
('alert_email_from', 'noreply@crmfms.local', 'From email address for alerts'),
('alert_email_to', 'admin@crmfms.local', 'Default email address for alerts');