// Role-Based Access Control (RBAC) system
class AuthManager {
  constructor() {
    this.user = null;
    this.isAuthenticated = false;
    this.loadUser();
  }

  loadUser() {
    const userData = localStorage.getItem('user') || sessionStorage.getItem('user');
    const authStatus = localStorage.getItem('isAuthenticated') || sessionStorage.getItem('isAuthenticated');

    if (userData && authStatus === 'true') {
      try {
        this.user = JSON.parse(userData);
        this.isAuthenticated = true;
      } catch (e) {
        // Invalid user data, clear it
        localStorage.removeItem('user');
        localStorage.removeItem('isAuthenticated');
        sessionStorage.removeItem('user');
        sessionStorage.removeItem('isAuthenticated');
        this.user = null;
        this.isAuthenticated = false;
      }
    }
  }

  async logout() {
    try {
      const response = await fetch('/crmfms/api/auth/logout.php', {
        method: 'POST',
        credentials: 'include'
      });
      
      const data = await response.json();
      
      if (data.success) {
        localStorage.removeItem('user');
        localStorage.removeItem('isAuthenticated');
        sessionStorage.removeItem('user');
        sessionStorage.removeItem('isAuthenticated');
        
        this.user = null;
        this.isAuthenticated = false;
        
        window.location.href = '/crmfms/public/modules/auth/login.html';
      }
    } catch (error) {
      console.error('Logout error:', error);
      localStorage.clear();
      sessionStorage.clear();
      window.location.href = '/crmfms/public/modules/auth/login.html';
    }
  }

  requireAuth() {
    if (!this.isAuthenticated) {
      window.location.href = '/crmfms/public/modules/auth/login.html';
      return false;
    }
    return true;
  }

  // Check if user has a specific role
  hasRole(roleName) {
    return this.user && this.user.roles && this.user.roles.includes(roleName);
  }

  isAdmin() {
    return this.hasRole('admin');
  }

  isDean() {
    return this.hasRole('dean');
  }

  isSecretary() {
    return this.hasRole('secretary');
  }

  isProgramHead() {
    return this.hasRole('program head');
  }

  isFaculty() {
    return this.hasRole('faculty');
  }

  isStaff() {
    return this.hasRole('staff');
  }

  getUser() {
    return this.user;
  }

  // Navigation menu based on roles
  getNavigationMenu() {
    if (!this.isAuthenticated) return [];

    const menu = [
      {
        title: 'Dashboard',
        icon: 'fas fa-tachometer-alt',
        href: '/crmfms/public/modules/dashboard/index.html',
        roles: ['admin', 'dean', 'secretary', 'program head', 'faculty', 'staff']
      }
    ];

    // Time Clock - Department check-in for faculty, program head, secretary
    if (this.hasRole('faculty') || this.hasRole('program head') || this.hasRole('secretary')) {
      menu.push({
        title: 'Time Clock',
        icon: 'fas fa-clock',
        href: '/crmfms/public/modules/checkin/checkin.html',
        roles: ['faculty', 'program head', 'secretary']
      });
    }

    // User Management - Admin, Dean, Secretary (with restrictions)
    if (this.hasRole('admin') || this.hasRole('dean') || this.hasRole('secretary')) {
      menu.push({
        title: 'User Management',
        icon: 'fas fa-users',
        href: '/crmfms/public/modules/faculties/faculties.html',
        roles: ['admin', 'dean', 'secretary']
      });
    }

    // Faculty Management - Program Head specific
    if (this.hasRole('program head')) {
      menu.push({
        title: 'My Faculty',
        icon: 'fas fa-users-cog',
        href: '/crmfms/public/modules/faculties/faculties.html',
        roles: ['program head']
      });
    }

    // Attendance - Admin, Secretary, Program Head (Faculty cannot access)
    if (this.hasRole('admin') || this.hasRole('secretary') || this.hasRole('program head')) {
      menu.push({
        title: 'Attendance',
        icon: 'fas fa-chart-line',
        href: '/crmfms/public/modules/attendance/attendance.html',
        roles: ['admin', 'secretary', 'program head']
      });
    }

    // Leave Requests - All roles can view/manage leaves based on permissions
    if (this.hasRole('admin') || this.hasRole('dean') || this.hasRole('secretary') || this.hasRole('program head') || this.hasRole('faculty')) {
      const leaveTitle = this.hasRole('faculty') ? 'My Leaves' :
                        this.hasRole('admin') || this.hasRole('dean') ? 'Leave Approvals' :
                        'Leave Requests';
      menu.push({
        title: leaveTitle,
        icon: 'fas fa-calendar-times',
        href: '/crmfms/public/modules/leaves/leaves.html',
        roles: ['admin', 'dean', 'secretary', 'program head', 'faculty']
      });
    }

    // Rooms & QR Management - Admin, Secretary
    if (this.hasRole('admin') || this.hasRole('secretary')) {
      menu.push({
        title: 'Rooms & QR',
        icon: 'fas fa-qrcode',
        href: '/crmfms/public/modules/rooms/rooms.html',
        roles: ['admin', 'secretary']
      });
    }



    // Monitoring - Admin, Secretary (for classroom monitoring)
    if (this.hasRole('admin') || this.hasRole('secretary')) {
      menu.push({
        title: 'Monitoring',
        icon: 'fas fa-eye',
        href: '/crmfms/public/modules/monitoring/monitoring.html',
        roles: ['admin', 'secretary']
      });
    }

    // Reports - Admin, Secretary, Dean
    if (this.hasRole('admin') || this.hasRole('secretary') || this.hasRole('dean')) {
      const reportTitle = this.hasRole('dean') ? 'Department Reports' :
                         this.hasRole('secretary') ? 'Reports' :
                         'Reports';
      menu.push({
        title: reportTitle,
        icon: 'fas fa-chart-bar',
        href: '/crmfms/public/modules/reports/reports.html',
        roles: ['admin', 'dean', 'secretary']
      });
    }

    // Department Management - Admin, Dean
    if (this.hasRole('admin') || this.hasRole('dean')) {
      menu.push({
        title: 'Department',
        icon: 'fas fa-building',
        href: '/crmfms/public/modules/departments/departments.html',
        roles: ['admin', 'dean']
      });
    }

    // Admin Settings - Admin only
    if (this.hasRole('admin')) {
      menu.push({
        title: 'Admin Settings',
        icon: 'fas fa-cog',
        href: '/crmfms/public/modules/admin/admin.html',
        roles: ['admin']
      });
    }

    return menu;
  }

  getDisplayName() {
    return this.user ? this.user.name : 'Guest';
  }

  getRoleDisplayName() {
    if (!this.user || !this.user.roles || this.user.roles.length === 0) return 'Guest';
    // Display the first role for simplicity in the UI, or you can concatenate them
    return this.user.roles[0].charAt(0).toUpperCase() + this.user.roles[0].slice(1);
  }
}

window.auth = new AuthManager();

document.addEventListener('DOMContentLoaded', function() {
  if (!window.location.pathname.includes('login.html')) {
    window.auth.requireAuth();
  }
});