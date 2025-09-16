-- CRM FMS Database Schema - Clean Setup
-- This file contains only the database structure without test data
-- For a fresh installation with sample data, use setup_database_full.sql

-- Drop database if it exists to ensure clean setup
DROP DATABASE IF EXISTS faculty_attendance_system;

CREATE DATABASE faculty_attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE faculty_attendance_system;

-- ===========================================
-- CORE SYSTEM TABLES
-- ===========================================

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_employee_id (employee_id)
);

-- User roles junction table
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ===========================================
-- FACILITY MANAGEMENT TABLES
-- ===========================================

-- Buildings table
CREATE TABLE IF NOT EXISTS buildings (
    building_id INT AUTO_INCREMENT PRIMARY KEY,
    building_name VARCHAR(100) NOT NULL,
    building_code VARCHAR(10) UNIQUE NOT NULL,
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_building_code (building_code)
);

-- Floors table
CREATE TABLE IF NOT EXISTS floors (
    floor_id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    floor_number INT NOT NULL,
    floor_name VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    UNIQUE KEY unique_building_floor (building_id, floor_number),
    INDEX idx_building (building_id),
    INDEX idx_status (status)
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    floor_id INT NOT NULL,
    room_code VARCHAR(20) NOT NULL,
    room_name VARCHAR(100),
    capacity INT DEFAULT 0,
    room_type ENUM('classroom', 'laboratory', 'office', 'conference', 'other') DEFAULT 'classroom',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (floor_id) REFERENCES floors(floor_id) ON DELETE CASCADE,
    UNIQUE KEY unique_floor_room (floor_id, room_code),
    INDEX idx_floor (floor_id),
    INDEX idx_status (status),
    INDEX idx_room_type (room_type)
);

-- Departments table
CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(10) UNIQUE NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_department_code (department_code)
);

-- Faculties table
CREATE TABLE IF NOT EXISTS faculties (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_name VARCHAR(100) NOT NULL,
    faculty_code VARCHAR(10) UNIQUE NOT NULL,
    department_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_department (department_id),
    INDEX idx_status (status),
    INDEX idx_faculty_code (faculty_code)
);

-- ===========================================
-- ATTENDANCE MANAGEMENT TABLES
-- ===========================================

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NULL,
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    scan_timestamp TIMESTAMP NULL,
    server_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'present',
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, date),
    INDEX idx_date (date),
    INDEX idx_room (room_id),
    INDEX idx_status (status),
    INDEX idx_check_in (check_in_time),
    INDEX idx_check_out (check_out_time)
);

-- Faculty Presence table (real-time tracking)
CREATE TABLE IF NOT EXISTS faculty_presence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    user_id INT NULL,
    status ENUM('present', 'absent', 'late', 'on_leave', 'checked_out', 'unknown') DEFAULT 'unknown',
    current_location VARCHAR(255) NULL,
    checkin_time TIMESTAMP NULL,
    checkout_time TIMESTAMP NULL,
    last_seen TIMESTAMP NULL,
    expected_checkin TIME DEFAULT '08:00:00',
    expected_checkout TIME DEFAULT '17:00:00',
    notes TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_faculty (faculty_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen),
    INDEX idx_checkin_time (checkin_time),
    UNIQUE KEY unique_faculty_active (faculty_id)
);

-- Room Occupancy table
CREATE TABLE IF NOT EXISTS room_occupancy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    occupied_count INT DEFAULT 0,
    faculty_id INT NULL,
    subject VARCHAR(255) NULL,
    class_schedule VARCHAR(255) NULL,
    notes TEXT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
    INDEX idx_room (room_id),
    INDEX idx_faculty (faculty_id),
    INDEX idx_last_updated (last_updated)
);

-- ===========================================
-- LEAVE MANAGEMENT TABLES
-- ===========================================

-- Leave Requests table
CREATE TABLE IF NOT EXISTS leave_requests (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_start_date (start_date),
    INDEX idx_end_date (end_date),
    INDEX idx_requested_at (requested_at)
);

-- ===========================================
-- SCHEDULING TABLES
-- ===========================================

-- Schedules table
CREATE TABLE IF NOT EXISTS schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject VARCHAR(100),
    class_type ENUM('lecture', 'laboratory', 'seminar', 'other') DEFAULT 'lecture',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_room (room_id),
    INDEX idx_day_of_week (day_of_week),
    INDEX idx_start_time (start_time),
    INDEX idx_end_time (end_time)
);

-- ===========================================
-- QR CODE MANAGEMENT TABLES
-- ===========================================

-- QR Codes table
CREATE TABLE IF NOT EXISTS qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,
    code_type VARCHAR(50) NOT NULL,
    ref_id INT NOT NULL,
    code_value TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type_ref (code_type, ref_id),
    INDEX idx_active (is_active),
    INDEX idx_expires_at (expires_at)
);

-- ===========================================
-- AUDIT AND LOGGING TABLES
-- ===========================================

-- Audit Trail table
CREATE TABLE IF NOT EXISTS audit_trail (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- ===========================================
-- SYSTEM MONITORING TABLES
-- ===========================================

-- System Monitoring table
CREATE TABLE IF NOT EXISTS system_monitoring (
    monitoring_id INT AUTO_INCREMENT PRIMARY KEY,
    cpu_usage DECIMAL(5,2),
    memory_usage DECIMAL(5,2),
    memory_used_mb DECIMAL(10,2),
    memory_total_mb DECIMAL(10,2),
    disk_usage DECIMAL(5,2),
    disk_used_gb DECIMAL(10,2),
    disk_total_gb DECIMAL(10,2),
    load_average_1m DECIMAL(5,2),
    load_average_5m DECIMAL(5,2),
    load_average_15m DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
);

-- Database Monitoring table
CREATE TABLE IF NOT EXISTS database_monitoring (
    monitoring_id INT AUTO_INCREMENT PRIMARY KEY,
    connections_total INT,
    connections_active INT,
    connections_max INT,
    queries_per_second DECIMAL(10,2),
    slow_queries INT,
    uptime_seconds INT,
    bytes_received BIGINT,
    bytes_sent BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
);

-- ===========================================
-- ALERT AND NOTIFICATION TABLES
-- ===========================================

-- System Alerts table
CREATE TABLE IF NOT EXISTS system_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_priority (priority),
    INDEX idx_active (is_active),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);

-- Alert History table
CREATE TABLE IF NOT EXISTS alert_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    alert_config_id INT NULL,
    alert_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    current_value DECIMAL(10,2),
    threshold_value DECIMAL(10,2),
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_triggered_at (triggered_at)
);

-- Alert Configurations table
CREATE TABLE IF NOT EXISTS alert_configurations (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL,
    threshold_operator ENUM('>', '<', '>=', '<=', '=', '!=') DEFAULT '>',
    threshold_value DECIMAL(10,2) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    enabled BOOLEAN DEFAULT TRUE,
    cooldown_minutes INT DEFAULT 60,
    notification_email VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alert_type (alert_type),
    INDEX idx_enabled (enabled)
);

-- ===========================================
-- MONITORING SETTINGS TABLE
-- ===========================================

-- Monitoring Settings table
CREATE TABLE IF NOT EXISTS monitoring_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('boolean', 'integer', 'string', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- ===========================================
-- BASIC DATA INSERTIONS
-- ===========================================

-- Insert default roles
INSERT IGNORE INTO roles (role_name, description) VALUES
('admin', 'System Administrator with full access'),
('dean', 'Dean with oversight capabilities'),
('secretary', 'Administrative secretary'),
('program head', 'Program head with departmental oversight'),
('faculty', 'Teaching faculty member'),
('staff', 'Administrative or support staff');

-- Insert default monitoring settings
INSERT IGNORE INTO monitoring_settings (setting_key, setting_value, setting_type, description) VALUES
('monitoring_enabled', '1', 'boolean', 'Enable system monitoring'),
('retention_days', '30', 'integer', 'Days to retain monitoring data'),
('cpu_threshold', '80', 'integer', 'CPU usage alert threshold (%)'),
('memory_threshold', '85', 'integer', 'Memory usage alert threshold (%)'),
('disk_threshold', '90', 'integer', 'Disk usage alert threshold (%)'),
('absent_threshold_hours', '2', 'integer', 'Hours since last seen to mark as absent');

-- Insert default alert configurations
INSERT IGNORE INTO alert_configurations (alert_type, threshold_operator, threshold_value, severity, enabled, cooldown_minutes) VALUES
('cpu', '>', 80.00, 'high', 1, 60),
('memory', '>', 85.00, 'high', 1, 60),
('disk', '>', 90.00, 'critical', 1, 60),
('connections', '>', 80.00, 'medium', 1, 120),
('slow_queries', '>', 10.00, 'medium', 1, 120);

COMMIT;