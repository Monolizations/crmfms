-- CRM FMS Attendance Monitoring Database Schema
-- This script creates tables for the attendance monitoring system
-- Run this after the main database setup

USE faculty_attendance_system;

-- Faculty Presence Table
-- Tracks real-time faculty presence and status
CREATE TABLE IF NOT EXISTS faculty_presence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    user_id INT NULL, -- Link to users table if faculty is also a user
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
    UNIQUE KEY unique_faculty_active (faculty_id) -- Only one active record per faculty
);

-- Room Occupancy Table
-- Tracks classroom utilization and occupancy
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
    INDEX idx_last_updated (last_updated),
    UNIQUE KEY unique_room_active (room_id) -- Only one active record per room
);

-- Attendance Alerts Table
-- Stores attendance-related alerts and notifications
CREATE TABLE IF NOT EXISTS attendance_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(100) NOT NULL, -- 'late_checkin', 'empty_classroom', 'overcrowded', 'absent_faculty', etc.
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
    metadata JSON NULL, -- Store additional alert-specific data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL,

    INDEX idx_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_faculty (faculty_id),
    INDEX idx_room (room_id),
    INDEX idx_triggered (triggered_at),
    INDEX idx_acknowledged (acknowledged_at),
    INDEX idx_resolved (resolved_at)
);

-- Attendance Activity Log Table
-- Logs all attendance-related activities for audit trail
CREATE TABLE IF NOT EXISTS attendance_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NULL,
    user_id INT NULL, -- Who performed the action
    activity_type VARCHAR(100) NOT NULL, -- 'checkin', 'checkout', 'status_update', 'location_change', etc.
    description TEXT NOT NULL,
    location VARCHAR(255) NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    metadata JSON NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,

    INDEX idx_faculty (faculty_id),
    INDEX idx_user (user_id),
    INDEX idx_type (activity_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_location (location)
);

-- Alert Activity Log Table
-- Logs all alert-related activities
CREATE TABLE IF NOT EXISTS alert_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL, -- 'created', 'acknowledged', 'resolved', 'escalated', etc.
    description TEXT NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (alert_id) REFERENCES attendance_alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,

    INDEX idx_alert (alert_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
);

-- Faculty Schedule Table
-- Stores faculty class schedules for automated attendance tracking
CREATE TABLE IF NOT EXISTS faculty_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    room_id INT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject VARCHAR(255) NULL,
    class_type VARCHAR(100) DEFAULT 'lecture', -- 'lecture', 'lab', 'tutorial', etc.
    expected_students INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,

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

    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,

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

-- Update existing faculties table to add more fields needed for attendance monitoring
-- Add department_id to faculties if it doesn't exist
ALTER TABLE faculties
ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER faculty_code,
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER department_id,
ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER user_id,
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER email,
ADD COLUMN IF NOT EXISTS office_room VARCHAR(50) NULL AFTER phone,
ADD COLUMN IF NOT EXISTS designation VARCHAR(100) NULL AFTER office_room,
ADD CONSTRAINT fk_faculty_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_faculty_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- Update existing rooms table to add facilities field if it doesn't exist
ALTER TABLE rooms
ADD COLUMN IF NOT EXISTS facilities TEXT NULL AFTER capacity,
ADD COLUMN IF NOT EXISTS room_type VARCHAR(50) DEFAULT 'classroom' AFTER facilities;

-- Insert sample faculty presence data
INSERT IGNORE INTO faculty_presence (faculty_id, status, current_location, expected_checkin, expected_checkout) VALUES
(1, 'present', 'Room 101', '08:00:00', '17:00:00'),
(2, 'present', 'Room 201', '08:00:00', '17:00:00'),
(3, 'absent', NULL, '08:00:00', '17:00:00');

-- Insert sample room occupancy data
INSERT IGNORE INTO room_occupancy (room_id, occupied_count, faculty_id, subject) VALUES
(1, 85, 1, 'Computer Science 101'),
(2, 25, 2, 'Mathematics 201'),
(3, 0, NULL, NULL);

-- Insert sample faculty schedules
INSERT IGNORE INTO faculty_schedule (faculty_id, room_id, day_of_week, start_time, end_time, subject, expected_students) VALUES
(1, 1, 'monday', '09:00:00', '10:30:00', 'Computer Science 101', 85),
(1, 1, 'wednesday', '09:00:00', '10:30:00', 'Computer Science 101', 85),
(1, 1, 'friday', '09:00:00', '10:30:00', 'Computer Science 101', 85),
(2, 2, 'tuesday', '10:00:00', '11:30:00', 'Mathematics 201', 25),
(2, 2, 'thursday', '10:00:00', '11:30:00', 'Mathematics 201', 25);

-- Insert sample attendance alerts
INSERT IGNORE INTO attendance_alerts (alert_type, severity, message, faculty_id, status) VALUES
('late_checkin', 'medium', 'Faculty member has not checked in by 9:00 AM', 3, 'active'),
('empty_classroom', 'low', 'Classroom is empty during scheduled class time', NULL, 'active');

-- Insert sample attendance activity
INSERT IGNORE INTO attendance_activity (faculty_id, activity_type, description, location) VALUES
(1, 'checkin', 'Faculty checked in for the day', 'Main Entrance'),
(2, 'checkin', 'Faculty checked in for the day', 'Main Entrance'),
(1, 'location_change', 'Faculty moved to Room 101', 'Room 101');

-- Update faculties with department and user links (sample data)
UPDATE faculties SET department_id = 1 WHERE faculty_id = 1; -- Computer Science
UPDATE faculties SET department_id = 2 WHERE faculty_id = 2; -- Mathematics
UPDATE faculties SET department_id = 3 WHERE faculty_id = 3; -- Physics

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_faculty_presence_status_time ON faculty_presence (status, last_seen);
CREATE INDEX IF NOT EXISTS idx_room_occupancy_updated ON room_occupancy (last_updated);
CREATE INDEX IF NOT EXISTS idx_attendance_alerts_status_time ON attendance_alerts (status, triggered_at);
CREATE INDEX IF NOT EXISTS idx_attendance_activity_faculty_time ON attendance_activity (faculty_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_faculty_schedule_active_time ON faculty_schedule (is_active, day_of_week, start_time, end_time);

-- Create a view for active faculty presence
CREATE OR REPLACE VIEW active_faculty_presence AS
SELECT
    fp.*,
    f.faculty_name,
    f.faculty_code,
    d.department_name,
    u.first_name,
    u.last_name,
    u.email as user_email
FROM faculty_presence fp
LEFT JOIN faculties f ON fp.faculty_id = f.faculty_id
LEFT JOIN departments d ON f.department_id = d.department_id
LEFT JOIN users u ON fp.user_id = u.user_id
WHERE f.status = 'active';

-- Create a view for current room occupancy
CREATE OR REPLACE VIEW current_room_occupancy AS
SELECT
    ro.*,
    r.room_number,
    r.room_name,
    r.capacity,
    f.floor_number,
    b.building_name,
    b.building_code,
    fac.faculty_name,
    fac.faculty_code
FROM room_occupancy ro
LEFT JOIN rooms r ON ro.room_id = r.room_id
LEFT JOIN floors f ON r.floor_id = f.floor_id
LEFT JOIN buildings b ON f.building_id = b.building_id
LEFT JOIN faculties fac ON ro.faculty_id = fac.faculty_id
WHERE r.status = 'active';

-- Create a view for active attendance alerts
CREATE OR REPLACE VIEW active_attendance_alerts AS
SELECT
    aa.*,
    f.faculty_name,
    r.room_number,
    b.building_name,
    u_ack.first_name as acknowledged_by_name,
    u_res.first_name as resolved_by_name
FROM attendance_alerts aa
LEFT JOIN faculties f ON aa.faculty_id = f.faculty_id
LEFT JOIN rooms r ON aa.room_id = r.room_id
LEFT JOIN buildings b ON r.floor_id IS NOT NULL AND (
    SELECT building_id FROM floors WHERE floor_id = r.floor_id
) = b.building_id
LEFT JOIN users u_ack ON aa.acknowledged_by = u_ack.user_id
LEFT JOIN users u_res ON aa.resolved_by = u_res.user_id
WHERE aa.status = 'active'
ORDER BY
    CASE aa.severity
        WHEN 'critical' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        WHEN 'low' THEN 4
    END,
    aa.triggered_at DESC;