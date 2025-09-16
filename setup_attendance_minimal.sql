-- CRM FMS Attendance Monitoring Database Schema (Minimal Version)
-- This script creates only the essential attendance monitoring tables

USE faculty_attendance_system;

-- Faculty Presence Table
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

    INDEX idx_faculty (faculty_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen),
    INDEX idx_checkin_time (checkin_time)
);

-- Room Occupancy Table
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

    INDEX idx_room (room_id),
    INDEX idx_faculty (faculty_id),
    INDEX idx_last_updated (last_updated)
);

-- Attendance Alerts Table
CREATE TABLE IF NOT EXISTS attendance_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(100) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    message TEXT NOT NULL,
    faculty_id INT NULL,
    room_id INT NULL,
    status ENUM('active', 'acknowledged', 'resolved') DEFAULT 'active',
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by INT NULL,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    notes TEXT NULL,
    metadata TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_faculty (faculty_id),
    INDEX idx_room (room_id),
    INDEX idx_triggered (triggered_at)
);

-- Attendance Activity Log Table
CREATE TABLE IF NOT EXISTS attendance_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NULL,
    user_id INT NULL,
    activity_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    metadata TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_faculty (faculty_id),
    INDEX idx_user (user_id),
    INDEX idx_type (activity_type),
    INDEX idx_timestamp (timestamp)
);

-- Alert Activity Log Table
CREATE TABLE IF NOT EXISTS alert_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_alert (alert_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
);

-- Faculty Schedule Table
CREATE TABLE IF NOT EXISTS faculty_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    room_id INT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject VARCHAR(255) NULL,
    class_type VARCHAR(100) DEFAULT 'lecture',
    expected_students INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_faculty (faculty_id),
    INDEX idx_room (room_id),
    INDEX idx_day (day_of_week),
    INDEX idx_time (start_time, end_time),
    INDEX idx_active (is_active)
);

-- System Settings for Attendance Monitoring
CREATE TABLE IF NOT EXISTS attendance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,

    INDEX idx_key (setting_key)
);

-- Insert default attendance settings
INSERT IGNORE INTO attendance_settings (setting_key, setting_value, setting_type, description) VALUES
('default_checkin_time', '08:00:00', 'string', 'Default expected check-in time for faculty'),
('default_checkout_time', '17:00:00', 'string', 'Default expected check-out time for faculty'),
('late_threshold_minutes', '15', 'integer', 'Minutes after expected time to consider as late'),
('absent_threshold_hours', '2', 'integer', 'Hours since last seen to mark as absent'),
('alert_cleanup_days', '30', 'integer', 'Days to keep resolved alerts before cleanup'),
('auto_generate_alerts', 'true', 'boolean', 'Whether to auto-generate attendance alerts'),
('alert_check_interval', '30', 'integer', 'Minutes between alert generation checks');

-- Insert sample data (only if base tables exist)
-- Check if faculties table has data
SET @faculty_count = (SELECT COUNT(*) FROM faculties);
SET @room_count = (SELECT COUNT(*) FROM rooms);

-- Insert sample faculty data if faculties table is empty
INSERT IGNORE INTO faculties (faculty_name, faculty_code, status) VALUES
('Dr. John Smith', 'CS001', 'active'),
('Prof. Sarah Williams', 'MATH001', 'active'),
('Dr. Robert Jones', 'PHYS001', 'active'),
('Prof. Jennifer Miller', 'CHEM001', 'active'),
('Dr. William Anderson', 'ENG001', 'active');

-- Insert sample faculty presence data
INSERT IGNORE INTO faculty_presence (faculty_id, status, current_location, expected_checkin, expected_checkout) VALUES
(1, 'present', 'Room 101', '08:00:00', '17:00:00'),
(2, 'present', 'Room 201', '08:00:00', '17:00:00'),
(3, 'absent', NULL, '08:00:00', '17:00:00');

-- Insert sample attendance alerts
INSERT IGNORE INTO attendance_alerts (alert_type, severity, message, faculty_id, status) VALUES
('late_checkin', 'medium', 'Faculty member has not checked in by 9:00 AM', 3, 'active'),
('empty_classroom', 'low', 'Classroom is empty during scheduled class time', NULL, 'active');

-- Insert sample attendance activity
INSERT IGNORE INTO attendance_activity (faculty_id, activity_type, description, location) VALUES
(1, 'checkin', 'Faculty checked in for the day', 'Main Entrance'),
(2, 'checkin', 'Faculty checked in for the day', 'Main Entrance'),
(1, 'location_change', 'Faculty moved to Room 101', 'Room 101');