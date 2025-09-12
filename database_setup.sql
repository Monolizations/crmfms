-- CRM FMS Database Setup
-- Run this script to create the necessary database and tables

CREATE DATABASE IF NOT EXISTS faculty_attendance_system;
USE faculty_attendance_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty', 'staff') DEFAULT 'faculty',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grace_period INT DEFAULT 5,
    overtime_threshold INT DEFAULT 8,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit trail table
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, role) 
VALUES ('ADMIN001', 'Admin', 'User', 'admin@crmfms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert dummy admin users
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, role) 
VALUES 
('ADMIN002', 'Sarah', 'Johnson', 'sarah.johnson@crmfms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('ADMIN003', 'Michael', 'Chen', 'michael.chen@crmfms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert dummy staff users (password: staff123)
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, role) 
VALUES 
('STAFF001', 'Emily', 'Davis', 'emily.davis@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'staff'),
('STAFF002', 'David', 'Wilson', 'david.wilson@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'staff'),
('STAFF003', 'Lisa', 'Brown', 'lisa.brown@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'staff'),
('STAFF004', 'James', 'Taylor', 'james.taylor@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'staff'),
('STAFF005', 'Maria', 'Garcia', 'maria.garcia@crmfms.local', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyJgaT9M', 'staff');

-- Insert dummy faculty users (password: faculty123)
INSERT IGNORE INTO users (employee_id, first_name, last_name, email, password_hash, role) 
VALUES 
('FAC001', 'Dr. John', 'Smith', 'john.smith@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC002', 'Prof. Sarah', 'Williams', 'sarah.williams@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC003', 'Dr. Robert', 'Jones', 'robert.jones@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC004', 'Prof. Jennifer', 'Miller', 'jennifer.miller@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC005', 'Dr. William', 'Anderson', 'william.anderson@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC006', 'Prof. Elizabeth', 'Thomas', 'elizabeth.thomas@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC007', 'Dr. Christopher', 'Jackson', 'christopher.jackson@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC008', 'Prof. Jessica', 'White', 'jessica.white@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC009', 'Dr. Daniel', 'Harris', 'daniel.harris@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty'),
('FAC010', 'Prof. Amanda', 'Martin', 'amanda.martin@crmfms.local', '$2y$10$IkgOKGm6Q5a6T8nBzsv2.eeBd9oFQZ', 'faculty');

-- Insert default settings
INSERT IGNORE INTO system_settings (grace_period, overtime_threshold) VALUES (5, 8);

-- Insert sample buildings
INSERT IGNORE INTO buildings (name, description) VALUES
('Main Building', 'Main academic building'),
('Science Building', 'Science and laboratory building'),
('Library', 'Main library building');

-- Insert sample floors
INSERT IGNORE INTO floors (building_id, floor_number, name) VALUES
(1, 1, 'Ground Floor'),
(1, 2, 'First Floor'),
(1, 3, 'Second Floor'),
(2, 1, 'Ground Floor'),
(2, 2, 'First Floor'),
(3, 1, 'Ground Floor');

-- Insert sample rooms
INSERT IGNORE INTO rooms (floor_id, room_code, name, status) VALUES
(1, 'MB-G-101', 'Lecture Hall A', 'active'),
(1, 'MB-G-102', 'Lecture Hall B', 'active'),
(2, 'MB-1-201', 'Classroom 201', 'active'),
(2, 'MB-1-202', 'Classroom 202', 'active'),
(3, 'MB-2-301', 'Laboratory 301', 'active'),
(4, 'SB-G-101', 'Physics Lab', 'active'),
(5, 'SB-1-201', 'Chemistry Lab', 'active'),
(6, 'LIB-G-001', 'Reading Room', 'active');
