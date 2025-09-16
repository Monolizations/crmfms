-- CRM FMS Full Database Setup Script
-- Creates all necessary tables and inserts test data

CREATE DATABASE IF NOT EXISTS faculty_attendance_system;
USE faculty_attendance_system;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

-- Insert roles
INSERT IGNORE INTO roles (role_name) VALUES
('admin'),
('dean'),
('secretary'),
('program head'),
('faculty'),
('staff');

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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User roles junction table
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

-- Buildings table
CREATE TABLE IF NOT EXISTS buildings (
    building_id INT AUTO_INCREMENT PRIMARY KEY,
    building_name VARCHAR(100) NOT NULL,
    building_code VARCHAR(10) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Floors table
CREATE TABLE IF NOT EXISTS floors (
    floor_id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    floor_number INT NOT NULL,
    floor_name VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    UNIQUE KEY unique_building_floor (building_id, floor_number)
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    floor_id INT NOT NULL,
    room_code VARCHAR(20) NOT NULL,
    room_name VARCHAR(100),
    capacity INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (floor_id) REFERENCES floors(floor_id) ON DELETE CASCADE,
    UNIQUE KEY unique_floor_room (floor_id, room_code)
);

-- Departments table
CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(10) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Faculties table (might be separate or linked)
CREATE TABLE IF NOT EXISTS faculties (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_name VARCHAR(100) NOT NULL,
    faculty_code VARCHAR(10) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, date),
    INDEX idx_date (date)
);

-- Leaves table
CREATE TABLE IF NOT EXISTS leaves (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- QR Codes table
CREATE TABLE IF NOT EXISTS qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,
    code_type VARCHAR(50) NOT NULL,
    ref_id INT NOT NULL,
    code_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_ref (code_type, ref_id)
);

-- Schedules table
CREATE TABLE IF NOT EXISTS schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL
);

-- Insert test users
-- Admin users
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, status) VALUES
('ADMIN001', 'Admin', 'User', 'admin@crmfms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
('ADMIN002', 'Sarah', 'Johnson', 'sarah.johnson@crmfms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
('ADMIN003', 'Michael', 'Chen', 'michael.chen@crmfms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active');

-- Staff users
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, status) VALUES
('STAFF001', 'Emily', 'Davis', 'emily.davis@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'active'),
('STAFF002', 'David', 'Wilson', 'david.wilson@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'active'),
('STAFF003', 'Lisa', 'Brown', 'lisa.brown@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'active'),
('STAFF004', 'James', 'Taylor', 'james.taylor@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'active'),
('STAFF005', 'Maria', 'Garcia', 'maria.garcia@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'active');

-- Faculty users
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, status) VALUES
('FAC001', 'John', 'Smith', 'john.smith@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC002', 'Sarah', 'Williams', 'sarah.williams@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC003', 'Robert', 'Jones', 'robert.jones@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC004', 'Jennifer', 'Miller', 'jennifer.miller@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC005', 'William', 'Anderson', 'william.anderson@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC006', 'Elizabeth', 'Thomas', 'elizabeth.thomas@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC007', 'Christopher', 'Jackson', 'christopher.jackson@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC008', 'Jessica', 'White', 'jessica.white@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC009', 'Daniel', 'Harris', 'daniel.harris@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active'),
('FAC010', 'Amanda', 'Martin', 'amanda.martin@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'active');

-- Assign roles to users
-- Get role IDs
SET @admin_role = (SELECT role_id FROM roles WHERE role_name = 'admin');
SET @staff_role = (SELECT role_id FROM roles WHERE role_name = 'staff');
SET @faculty_role = (SELECT role_id FROM roles WHERE role_name = 'faculty');

-- Admin users get admin role
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.user_id, @admin_role FROM users u WHERE u.employee_id LIKE 'ADMIN%';

-- Staff users get staff role
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.user_id, @staff_role FROM users u WHERE u.employee_id LIKE 'STAFF%';

-- Faculty users get faculty role
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.user_id, @faculty_role FROM users u WHERE u.employee_id LIKE 'FAC%';

-- Insert sample buildings
INSERT IGNORE INTO buildings (building_name, building_code) VALUES
('Main Building', 'MAIN'),
('Science Building', 'SCI'),
('Library', 'LIB');

-- Insert sample floors
INSERT IGNORE INTO floors (building_id, floor_number, floor_name) VALUES
(1, 1, 'Ground Floor'),
(1, 2, 'First Floor'),
(1, 3, 'Second Floor'),
(2, 1, 'Ground Floor'),
(2, 2, 'First Floor'),
(3, 1, 'Ground Floor');

-- Insert sample rooms
INSERT IGNORE INTO rooms (floor_id, room_code, room_name, capacity) VALUES
(1, '101', 'Lecture Hall A', 100),
(1, '102', 'Classroom 1', 30),
(2, '201', 'Lecture Hall B', 80),
(2, '202', 'Classroom 2', 25),
(3, '301', 'Computer Lab', 40),
(4, '101', 'Physics Lab', 20),
(5, '201', 'Chemistry Lab', 25),
(6, '101', 'Reading Room', 50);

-- Insert sample departments
INSERT IGNORE INTO departments (department_name, department_code) VALUES
('Computer Science', 'CS'),
('Mathematics', 'MATH'),
('Physics', 'PHYS'),
('Chemistry', 'CHEM'),
('English', 'ENG');

-- Insert sample faculties
INSERT IGNORE INTO faculties (faculty_name, faculty_code) VALUES
('Faculty of Science', 'SCI'),
('Faculty of Arts', 'ARTS'),
('Faculty of Engineering', 'ENG');