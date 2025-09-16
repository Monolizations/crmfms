# CRM FMS Test Credentials

This document contains all the test user accounts available in the CRM FMS system for testing different roles and permissions.

## 🔐 Universal Password
**All test accounts use the same password:** `test123`

## 👥 Test User Accounts

### 🔧 Administrator Account
- **Email:** admin@test.com
- **Password:** test123
- **Role:** System Administrator
- **Permissions:** Full system access, user management, system configuration
- **Use Case:** Testing admin features, user creation, system settings

### 🎓 Dean Account
- **Email:** dean@test.com
- **Password:** test123
- **Role:** Dean
- **Permissions:** Oversight, approval capabilities, faculty management
- **Use Case:** Testing approval workflows, faculty oversight

### 📝 Secretary Account
- **Email:** secretary@test.com
- **Password:** test123
- **Role:** Administrative Secretary
- **Permissions:** Administrative functions, scheduling, basic management
- **Use Case:** Testing administrative tasks, scheduling

### 👨‍🏫 Program Head Account
- **Email:** programhead@test.com
- **Password:** test123
- **Role:** Program Head
- **Permissions:** Departmental management, faculty coordination
- **Use Case:** Testing departmental oversight, program management

### 👨‍🎓 Faculty Accounts
Use these accounts to test faculty-specific features:

1. **Email:** faculty1@test.com | **Name:** Dr. Robert Davis
2. **Email:** faculty2@test.com | **Name:** Prof. Lisa Wilson
3. **Email:** faculty3@test.com | **Name:** Dr. James Anderson
4. **Email:** faculty4@test.com | **Name:** Prof. Emily Taylor

- **Password (all):** test123
- **Role:** Faculty Member
- **Permissions:** Attendance tracking, leave requests, class scheduling
- **Use Case:** Testing attendance check-in/out, leave requests, faculty dashboard

### 👷 Staff Accounts
Use these accounts to test staff-specific features:

1. **Email:** staff1@test.com | **Name:** Michael Brown
2. **Email:** staff2@test.com | **Name:** Jennifer Garcia

- **Password (all):** test123
- **Role:** Support Staff
- **Permissions:** Basic attendance, limited administrative access
- **Use Case:** Testing basic user functionality, staff attendance

## 🧪 Testing Scenarios

### Role-Based Testing
1. **Admin Testing:** Use `admin@test.com` to test user management, system configuration
2. **Dean Testing:** Use `dean@test.com` to test approval workflows
3. **Faculty Testing:** Use any `faculty#@test.com` account to test attendance features
4. **Staff Testing:** Use any `staff#@test.com` account to test basic functionality

### Feature Testing
- **Attendance:** Use faculty accounts for check-in/check-out
- **Leave Requests:** Use faculty accounts for leave applications
- **Reports:** Use admin/dean accounts for report generation
- **User Management:** Use admin account for creating/managing users
- **Scheduling:** Use faculty/program head accounts for class scheduling

## 📋 Quick Reference Table

| Role | Email | Password | Primary Use |
|------|-------|----------|-------------|
| Admin | admin@test.com | test123 | System administration |
| Dean | dean@test.com | test123 | Oversight & approvals |
| Secretary | secretary@test.com | test123 | Administrative tasks |
| Program Head | programhead@test.com | test123 | Department management |
| Faculty 1 | faculty1@test.com | test123 | Attendance testing |
| Faculty 2 | faculty2@test.com | test123 | Leave requests |
| Faculty 3 | faculty3@test.com | test123 | Scheduling |
| Faculty 4 | faculty4@test.com | test123 | Reports |
| Staff 1 | staff1@test.com | test123 | Basic functionality |
| Staff 2 | staff2@test.com | test123 | User interface |

## 🚀 Getting Started

1. **Install CRM FMS** using the clean setup:
   ```bash
   ./setup_clean.sh
   # Choose "y" when asked to install sample data
   ```

2. **Access the application:**
   - URL: http://localhost/crmfms/public
   - Use any of the test credentials above

3. **Start testing:**
   - Log in with different accounts
   - Test role-specific features
   - Verify permissions work correctly

## 🔒 Security Note

These are **test credentials only** and should not be used in production environments. All test accounts have the same password (`test123`) for simplicity during testing.

## 📞 Support

If you encounter any issues with the test accounts:
1. Verify the sample data was installed during setup
2. Check that all database tables were created
3. Ensure the web server is running correctly
4. Review the setup logs for any errors

---

**Happy Testing! 🎉**
