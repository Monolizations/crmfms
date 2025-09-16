class AccessControl {
  constructor() {
    this.pageRoles = {
      // Admin only
      'admin/admin.html': ['admin'],

      // User/Faculty management
      'faculties/faculties.html': ['admin', 'dean', 'secretary', 'program head'],

      // Attendance and monitoring
      'attendance/attendance.html': ['admin', 'faculty', 'program head'],
      'monitoring/monitoring.html': ['admin', 'secretary'],

      // Leave management
      'leaves/leaves.html': ['admin', 'dean', 'secretary', 'program head', 'faculty'],

      // Room and QR management
      'rooms/rooms.html': ['admin', 'secretary'],



      // Reporting
      'reports/reports.html': ['admin', 'dean', 'secretary'],

      // Department management
      'departments/departments.html': ['admin', 'dean'],

      // Check-in/Time clock
      'checkin/checkin.html': ['admin', 'dean', 'secretary', 'program head', 'faculty'],

      // Dashboard (all authenticated users)
      'dashboard/index.html': ['admin', 'dean', 'secretary', 'program head', 'faculty', 'staff'],
    };
    this.init();
  }

  init() {
    document.addEventListener('DOMContentLoaded', () => this.checkAccess());
  }

  checkAccess() {
    const currentPath = window.location.pathname;

    // Allow access to login page
    if (currentPath.includes('login.html') || currentPath.includes('test_')) {
      return;
    }

    const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');

    if (!user || !user.roles || user.roles.length === 0) {
      this.redirectToLogin();
      return;
    }

    // Check if the current path requires specific roles
    const requiredRoles = this.getRequiredRoles(currentPath);

    if (requiredRoles && requiredRoles.length > 0) {
      const hasPermission = this.hasRequiredRole(user.roles, requiredRoles, currentPath);

      if (!hasPermission) {
        this.showAccessDenied();
        return;
      }
    }

    // Additional checks for specific pages
    if (currentPath.includes('faculties/faculties.html')) {
      this.checkFacultyManagementAccess(user.roles);
    }

    // If we get here, access is allowed
  }

  getRequiredRoles(path) {
    // Check for specific page patterns
    for (const [pagePattern, roles] of Object.entries(this.pageRoles)) {
      if (path.includes(pagePattern)) {
        return roles;
      }
    }
    return null;
  }

  hasRequiredRole(userRoles, requiredRoles, path) {
    // Check if user has any of the required roles
    const hasBasicPermission = userRoles.some(role => requiredRoles.includes(role));

    if (!hasBasicPermission) {
      return false;
    }

    // Special handling for combined roles and restricted access
    if (path.includes('faculties/faculties.html')) {
      // Secretary can only manage program head and faculty
      if (userRoles.includes('secretary') && !userRoles.includes('admin') && !userRoles.includes('dean')) {
        // This is handled in the API, but we allow access here
        return true;
      }
    }

    return true;
  }

  checkFacultyManagementAccess(userRoles) {
    // Additional client-side validation for faculty management
    // This is mainly handled server-side, but we can add client-side warnings
    if (userRoles.includes('secretary') && !userRoles.includes('admin') && !userRoles.includes('dean')) {
      console.log('Secretary access: Limited to Program Head and Faculty management only');
    }
  }

  redirectToLogin() {
    window.location.href = '/crmfms/public/modules/auth/login.html';
  }

  showAccessDenied() {
    document.body.innerHTML = `
      <div class="d-flex align-items-center justify-content-center vh-100">
        <div class="text-center">
          <h1 class="display-1 fw-bold">403</h1>
          <p class="fs-3"> <span class="text-danger">Opps!</span> Forbidden.</p>
          <p class="lead">
            You don't have permission to access this page.
          </p>
          <a href="/crmfms/index.html" class="btn btn-primary">Go Home</a>
        </div>
      </div>
    `;
  }
}

new AccessControl();
