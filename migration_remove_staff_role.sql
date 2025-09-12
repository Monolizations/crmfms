-- Migration to remove 'staff' role

-- Step 1: Get the role_id for 'staff'
SET @staff_role_id = (SELECT role_id FROM roles WHERE role_name = 'staff');

-- Step 2: Delete entries from user_roles associated with 'staff'
DELETE FROM user_roles WHERE role_id = @staff_role_id;

-- Step 3: Delete the 'staff' role from the roles table
DELETE FROM roles WHERE role_name = 'staff';
