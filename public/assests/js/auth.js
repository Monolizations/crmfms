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
      this.user = JSON.parse(userData);
      this.isAuthenticated = true;
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

    // Time Clock for faculty, program head, secretary
    if (this.hasRole('faculty') || this.hasRole('program head') || this.hasRole('secretary')) {
      menu.push({
        title: 'Time Clock',
        icon: 'fas fa-clock',
        href: '/crmfms/public/modules/checkin/checkin.html',
        roles: ['faculty', 'program head', 'secretary']
      });
    }

    if (this.hasRole('admin') || this.hasRole('dean') || this.hasRole('secretary')) {
      menu.push({
        title: 'User Management',
        icon: 'fas fa-users',
        href: '/crmfms/public/modules/faculties/faculties.html',
        roles: ['admin', 'dean', 'secretary']
      });
    }

    if (this.hasRole('admin') || this.hasRole('faculty')) {
      menu.push({
        title: 'Attendance',
        icon: 'fas fa-clock',
        href: '/crmfms/public/modules/attendance/attendance.html',
        roles: ['admin', 'faculty']
      });
    }

    if (this.hasRole('admin') || this.hasRole('faculty')) {
      menu.push({
        title: 'Leave Requests',
        icon: 'fas fa-calendar-times',
        href: '/crmfms/public/modules/leaves/leaves.html',
        roles: ['admin', 'faculty']
      });
    }

    if (this.hasRole('admin')) {
      menu.push({
        title: 'Rooms & QR',
        icon: 'fas fa-qrcode',
        href: '/crmfms/public/modules/rooms/rooms.html',
        roles: ['admin']
      });
    }

    if (this.hasRole('admin') || this.hasRole('faculty')) {
      menu.push({
        title: 'Schedules',
        icon: 'fas fa-calendar-alt',
        href: '/crmfms/public/modules/schedules/schedules.html',
        roles: ['admin', 'faculty']
      });
    }

    if (this.hasRole('admin')) {
      menu.push({
        title: 'Monitoring',
        icon: 'fas fa-eye',
        href: '/crmfms/public/modules/monitoring/monitoring.html',
        roles: ['admin']
      });
    }

    if (this.hasRole('admin')) {
      menu.push({
        title: 'Reports',
        icon: 'fas fa-chart-bar',
        href: '/crmfms/public/modules/reports/reports.html',
        roles: ['admin']
      });
    }

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