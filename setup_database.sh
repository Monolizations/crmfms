#!/bin/bash

# CRM FMS Database Setup Script
echo "Setting up CRM FMS database..."

# Database connection details
DB_HOST="127.0.0.1"
DB_NAME="faculty_attendance_system"
DB_USER="root"
DB_PASS=""

# Function to run SQL file
run_sql() {
    local file=$1
    echo "Running $file..."
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < $file
    if [ $? -eq 0 ]; then
        echo "✓ $file completed successfully"
    else
        echo "✗ Error running $file"
        exit 1
    fi
}

# Run migrations in order
run_sql "database_setup.sql"
run_sql "migration_user_roles.sql"
run_sql "migration_buildings_floors_rooms.sql"
run_sql "migration_create_rooms_qr_codes.sql"
run_sql "migration_create_attendance_table.sql"
run_sql "migration_create_leave_requests_table.sql"
run_sql "migration_create_archived_leave_requests.sql"
run_sql "add_main_south_floors.sql"
run_sql "migration_remove_staff_role.sql"

echo "Database setup complete!"
echo "You can now access the application and the faculty/room dropdowns should work."