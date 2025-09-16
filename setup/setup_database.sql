-- CRM FMS Database Setup Script
-- Run this script to create the necessary database tables

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS faculty_attendance_system;
USE faculty_attendance_system;

-- System Alerts Table
CREATE TABLE IF NOT EXISTS system_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info', -- 'info', 'warning', 'error', 'success'
    priority INT DEFAULT 1, -- 1=low, 2=medium, 3=high
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT 1,
    created_by INT NULL,
    INDEX idx_active (is_active),
    INDEX idx_expires (expires_at),
    INDEX idx_priority (priority)
);

-- Insert some sample alerts
INSERT INTO system_alerts (message, type, priority) VALUES
('Welcome to the CRM FMS system!', 'info', 1),
('System maintenance scheduled for this weekend', 'warning', 2),
('New features have been added to the attendance system', 'info', 1);

-- Monitoring rounds table for tracking building monitoring activities
CREATE TABLE IF NOT EXISTS monitoring_rounds (
    round_id INT AUTO_INCREMENT PRIMARY KEY,
    program_head_id INT NOT NULL,
    building_id INT NOT NULL,
    round_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    status VARCHAR(20) DEFAULT 'completed',
    FOREIGN KEY (program_head_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    INDEX idx_program_head (program_head_id),
    INDEX idx_building (building_id),
    INDEX idx_round_time (round_time)
);

-- Insert sample monitoring round
INSERT INTO monitoring_rounds (program_head_id, building_id, notes) VALUES
(1, 1, 'Initial monitoring round - all systems operational');

-- Note: Other tables (users, attendance, rooms, etc.) should already exist
-- If you need to create them, refer to the main application documentation