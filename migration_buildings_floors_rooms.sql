-- Migration for Buildings, Floors, and Rooms schema

-- Step 1: Create the 'buildings' table
CREATE TABLE IF NOT EXISTS buildings (
    building_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 2: Create the 'floors' table
CREATE TABLE IF NOT EXISTS floors (
    floor_id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    floor_number INT NOT NULL,
    name VARCHAR(100),
    description TEXT,
    UNIQUE (building_id, floor_number),
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 3: Alter 'rooms' table to drop building_id and add floor_id
-- First, drop existing foreign key constraints if any, and then the column
ALTER TABLE rooms DROP FOREIGN KEY IF EXISTS fk_building_id; -- Assuming a potential FK name
ALTER TABLE rooms DROP COLUMN IF EXISTS building_id;

-- Add floor_id column
ALTER TABLE rooms ADD COLUMN floor_id INT AFTER room_id;

-- Add foreign key constraint to floors table
ALTER TABLE rooms ADD CONSTRAINT fk_floor
FOREIGN KEY (floor_id) REFERENCES floors(floor_id) ON DELETE CASCADE;

-- Step 4: Populate the 'buildings' table with initial data
INSERT IGNORE INTO buildings (name) VALUES
('PH Buildings'),
('Main West'),
('Main North'),
('Main South'),
('SHS Building');
