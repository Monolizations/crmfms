class AccessControl {
  constructor() {
    this.pageRoles = {
      'admin/admin.html': ['admin'],
      'faculties/faculties.html': ['admin', 'dean', 'secretary'],
    };
    this.init();
  }

  init() {
    document.addEventListener('DOMContentLoaded', () => this.checkAccess());
  }

  checkAccess() {
    const currentPath = window.location.pathname;
    
    if (currentPath.includes('login.html')) {
      return;
    }

    const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');

    if (!user || !user.roles || user.roles.length === 0) {
      this.redirectToLogin();
      return;
    }

    // Check if the current path requires specific roles
    let requiredRoles = null;
    
    // Check for specific page patterns
    if (currentPath.includes('admin/admin.html')) {
      requiredRoles = this.pageRoles['admin/admin.html'];
    } else if (currentPath.includes('faculties/faculties.html')) {
      requiredRoles = this.pageRoles['faculties/faculties.html'];
    }

    if (requiredRoles) {
      const hasPermission = user.roles.some(role => requiredRoles.includes(role));

      if (!hasPermission) {
        this.showAccessDenied();
        return;
      }
    }
    
    // If we get here, access is allowed
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
