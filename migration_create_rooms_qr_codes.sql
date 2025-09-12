-- Migration to create missing rooms and qr_codes tables

-- Step 1: Create the 'rooms' table
CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT, -- Temporarily keep this for now, will be dropped later
    room_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 2: Create the 'qr_codes' table
CREATE TABLE IF NOT EXISTS qr_codes (
    code_id INT AUTO_INCREMENT PRIMARY KEY,
    code_type VARCHAR(50) NOT NULL, -- e.g., 'room', 'faculty'
    ref_id INT NOT NULL, -- Reference to room_id or user_id
    code_value VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
