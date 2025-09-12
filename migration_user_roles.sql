-- Migration to a many-to-many role-based access control system

-- Step 1: Create the new 'roles' table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

-- Step 2: Create the new 'user_roles' pivot table
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

-- Step 3: Populate the 'roles' table
INSERT IGNORE INTO roles (role_name) VALUES
('admin'),
('dean'),
('secretary'),
('program head'),
('faculty'),
('staff');

-- Step 4: Migrate existing users to the new system
INSERT INTO user_roles (user_id, role_id)
SELECT u.user_id, r.role_id
FROM users u
JOIN roles r ON u.role = r.role_name
WHERE u.role IS NOT NULL AND u.role <> ''
ON DUPLICATE KEY UPDATE user_id=u.user_id, role_id=r.role_id;

-- Step 5: Drop the old 'role' column from the 'users' table
ALTER TABLE users DROP COLUMN role;
