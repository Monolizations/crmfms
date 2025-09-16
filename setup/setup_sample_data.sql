-- CRM FMS Sample Data
-- This file contains sample data for testing purposes
-- Run this after setup_database_clean.sql

USE faculty_attendance_system;

-- ===========================================
-- TEST CREDENTIALS FOR EACH ROLE TYPE
-- ===========================================

-- Insert test users with predictable credentials for easy testing
-- All passwords are set to "test123" for simplicity
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, status) VALUES
-- Admin User
('ADMIN001', 'System', 'Administrator', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123

-- Dean User
('DEAN001', 'Dr. Sarah', 'Johnson', 'dean@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123

-- Secretary User
('SEC001', 'Maria', 'Rodriguez', 'secretary@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123

-- Program Head User
('PH001', 'Prof. David', 'Chen', 'programhead@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123

-- Faculty Users (Multiple for testing)
('FAC001', 'Dr. Robert', 'Davis', 'faculty1@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123
('FAC002', 'Prof. Lisa', 'Wilson', 'faculty2@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123
('FAC003', 'Dr. James', 'Anderson', 'faculty3@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123
('FAC004', 'Prof. Emily', 'Taylor', 'faculty4@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123

-- Staff Users
('STAFF001', 'Michael', 'Brown', 'staff1@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'), -- password: test123
('STAFF002', 'Jennifer', 'Garcia', 'staff2@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'); -- password: test123

-- Assign roles to users
INSERT IGNORE INTO user_roles (user_id, role_id) VALUES
(1, 1), -- Admin user -> admin role
(2, 2), -- Dean user -> dean role
(3, 3), -- Secretary user -> secretary role
(4, 4), -- Program Head user -> program head role
(5, 5), -- Faculty user -> faculty role
(6, 5), -- Faculty user -> faculty role
(7, 5), -- Faculty user -> faculty role
(8, 5), -- Faculty user -> faculty role
(9, 6), -- Staff user -> staff role
(10, 6); -- Staff user -> staff role

-- ===========================================
-- SAMPLE FACILITIES
-- ===========================================

-- Insert sample buildings
INSERT IGNORE INTO buildings (building_name, building_code, address, status) VALUES
('Main Academic Building', 'MAB', '123 University Avenue', 'active'),
('Science Building', 'SCB', '456 Science Drive', 'active'),
('Library Building', 'LIB', '789 Knowledge Street', 'active');

-- Insert sample floors
INSERT IGNORE INTO floors (building_id, floor_number, floor_name, status) VALUES
(1, 1, 'Ground Floor', 'active'),
(1, 2, 'First Floor', 'active'),
(1, 3, 'Second Floor', 'active'),
(2, 1, 'Ground Floor', 'active'),
(2, 2, 'First Floor', 'active'),
(3, 1, 'Ground Floor', 'active'),
(3, 2, 'First Floor', 'active');

-- Insert sample rooms
INSERT IGNORE INTO rooms (floor_id, room_code, room_name, capacity, room_type, status) VALUES
(1, 'MAB-G-101', 'Lecture Hall A', 100, 'classroom', 'active'),
(1, 'MAB-G-102', 'Lecture Hall B', 80, 'classroom', 'active'),
(2, 'MAB-1-201', 'Classroom 201', 50, 'classroom', 'active'),
(2, 'MAB-1-202', 'Classroom 202', 40, 'classroom', 'active'),
(3, 'MAB-2-301', 'Computer Lab 1', 30, 'laboratory', 'active'),
(4, 'SCB-G-101', 'Chemistry Lab', 25, 'laboratory', 'active'),
(5, 'SCB-1-201', 'Physics Lab', 20, 'laboratory', 'active'),
(6, 'LIB-G-001', 'Reading Room', 150, 'other', 'active'),
(7, 'LIB-1-101', 'Study Area', 75, 'other', 'active');

-- ===========================================
-- SAMPLE DEPARTMENTS AND FACULTIES
-- ===========================================

-- Insert sample departments
INSERT IGNORE INTO departments (department_name, department_code, description, status) VALUES
('Computer Science', 'CS', 'Department of Computer Science and Engineering', 'active'),
('Mathematics', 'MATH', 'Department of Mathematics', 'active'),
('Physics', 'PHYS', 'Department of Physics', 'active'),
('Chemistry', 'CHEM', 'Department of Chemistry', 'active');

-- Insert sample faculties
INSERT IGNORE INTO faculties (faculty_name, faculty_code, department_id, status) VALUES
('Computer Science Faculty', 'CSF', 1, 'active'),
('Mathematics Faculty', 'MF', 2, 'active'),
('Physics Faculty', 'PF', 3, 'active'),
('Chemistry Faculty', 'CF', 4, 'active');

-- ===========================================
-- SAMPLE ATTENDANCE RECORDS
-- ===========================================

-- Insert sample attendance records for today and recent days
INSERT IGNORE INTO attendance (user_id, room_id, check_in_time, check_out_time, scan_timestamp, date, status, notes) VALUES
-- Today's records
(4, 1, '2025-01-15 08:30:00', '2025-01-15 17:15:00', '2025-01-15 08:30:00', '2025-01-15', 'present', 'Morning lecture in MAB-G-101'),
(5, 2, '2025-01-15 09:00:00', '2025-01-15 16:45:00', '2025-01-15 09:00:00', '2025-01-15', 'present', 'Morning lecture in MAB-G-102'),
(6, NULL, '2025-01-15 08:00:00', '2025-01-15 17:00:00', '2025-01-15 08:00:00', '2025-01-15', 'present', 'Department office hours'),

-- Yesterday's records
(4, 3, '2025-01-14 08:45:00', '2025-01-14 17:30:00', '2025-01-14 08:45:00', '2025-01-14', 'late', 'Late arrival - traffic'),
(5, 4, '2025-01-14 08:15:00', '2025-01-14 16:30:00', '2025-01-14 08:15:00', '2025-01-14', 'present', 'Regular class session'),
(6, NULL, '2025-01-14 08:00:00', '2025-01-14 17:00:00', '2025-01-14 08:00:00', '2025-01-14', 'present', 'Department office hours'),

-- Day before yesterday
(4, 5, '2025-01-13 08:30:00', '2025-01-13 17:00:00', '2025-01-13 08:30:00', '2025-01-13', 'present', 'Computer lab session'),
(5, NULL, '2025-01-13 08:00:00', '2025-01-13 17:00:00', '2025-01-13 08:00:00', '2025-01-13', 'present', 'Department meeting'),
(6, NULL, '2025-01-13 08:00:00', '2025-01-13 17:00:00', '2025-01-13 08:00:00', '2025-01-13', 'present', 'Department office hours');

-- ===========================================
-- SAMPLE SCHEDULES
-- ===========================================

-- Insert sample schedules
INSERT IGNORE INTO schedules (user_id, room_id, day_of_week, start_time, end_time, subject, class_type) VALUES
(4, 1, 'monday', '08:30:00', '10:30:00', 'Introduction to Programming', 'lecture'),
(4, 3, 'tuesday', '09:00:00', '11:00:00', 'Data Structures', 'lecture'),
(4, 5, 'wednesday', '10:00:00', '12:00:00', 'Computer Networks', 'laboratory'),
(5, 2, 'monday', '09:00:00', '11:00:00', 'Calculus I', 'lecture'),
(5, 4, 'tuesday', '08:30:00', '10:30:00', 'Linear Algebra', 'lecture'),
(5, NULL, 'wednesday', '11:00:00', '12:00:00', 'Office Hours', 'other');

-- ===========================================
-- SAMPLE LEAVE REQUESTS
-- ===========================================

-- Insert sample leave requests
INSERT IGNORE INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, requested_at) VALUES
(4, 'sick_leave', '2025-01-20', '2025-01-21', 'Medical appointment', 'pending', '2025-01-15 10:00:00'),
(5, 'vacation', '2025-01-25', '2025-01-30', 'Family vacation', 'approved', '2025-01-10 14:30:00'),
(6, 'personal', '2025-02-01', '2025-02-01', 'Personal matters', 'pending', '2025-01-14 09:15:00');

-- ===========================================
-- SAMPLE QR CODES
-- ===========================================

-- Insert sample QR codes
INSERT IGNORE INTO qr_codes (code_type, ref_id, code_value, is_active) VALUES
('room', 1, 'ROOM_MAB_G_101_001', 1),
('room', 2, 'ROOM_MAB_G_102_002', 1),
('room', 3, 'ROOM_MAB_1_201_003', 1),
('user', 4, 'USER_FAC001_004', 1),
('user', 5, 'USER_FAC002_005', 1),
('user', 6, 'USER_STAFF001_006', 1);

-- ===========================================
-- SAMPLE SYSTEM ALERTS
-- ===========================================

-- Insert sample system alerts
INSERT IGNORE INTO system_alerts (message, type, priority, is_active, created_at, expires_at) VALUES
('Welcome to CRM FMS! System is running normally.', 'info', 'low', 1, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
('Regular system maintenance scheduled for next Sunday 2:00 AM - 4:00 AM', 'maintenance', 'medium', 1, NOW(), DATE_ADD(NOW(), INTERVAL 6 DAY));

COMMIT;