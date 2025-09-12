-- Migration: Create archived leave requests table
-- This table will store completed (approved/denied) leave requests for historical purposes

USE faculty_attendance_system;

-- Create archived leave requests table with same structure as leave_requests
CREATE TABLE IF NOT EXISTS archived_leave_requests (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    original_leave_id INT NOT NULL, -- Reference to original leave request
    user_id INT NOT NULL,
    leave_type ENUM('Sick Leave','Vacation Leave','Personal Leave','Other') NOT NULL DEFAULT 'Other',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    approval_reason TEXT,
    rejection_reason TEXT,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_archived_at (archived_at),
    INDEX idx_original_leave_id (original_leave_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Add index for better performance
CREATE INDEX idx_archived_user_status ON archived_leave_requests(user_id, status);
CREATE INDEX idx_archived_reviewed_by ON archived_leave_requests(reviewed_by);
