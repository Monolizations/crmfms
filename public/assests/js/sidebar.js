// Sidebar Navigation Management
class SidebarManager {
  constructor() {
    this.sidebar = null;
    this.overlay = null;
    this.toggleBtn = null;
    this.mobileMenuBtn = null;
    this.navList = null;
  }

  setupSidebar() {
    this.sidebar = document.getElementById('sidebar');
    this.overlay = document.getElementById('sidebarOverlay');
    this.toggleBtn = document.getElementById('sidebarToggle');
    this.mobileMenuBtn = document.getElementById('mobileMenuBtn');
    this.navList = document.getElementById('navList');

    if (!this.sidebar || !this.navList) {
      console.warn('Sidebar elements not found');
      return;
    }

    this.setupEventListeners();
    this.loadSidebarContent();
    this.setActiveMenuItem();
  }

  setupEventListeners() {
    // Mobile menu toggle
    if (this.mobileMenuBtn) {
      this.mobileMenuBtn.addEventListener('click', () => this.toggleSidebar());
    }

    // Sidebar close button
    if (this.toggleBtn) {
      this.toggleBtn.addEventListener('click', () => this.hideSidebar());
    }

    // Overlay click to close
    if (this.overlay) {
      this.overlay.addEventListener('click', () => this.hideSidebar());
    }

    // Window resize handler
    window.addEventListener('resize', () => this.handleResize());

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 991.98 && 
          this.sidebar.classList.contains('show') && 
          !this.sidebar.contains(e.target) && 
          !e.target.closest('.mobile-menu-btn')) {
        this.hideSidebar();
      }
    });
  }

  loadSidebarContent() {
    // Update user info
    this.updateUserInfo();
    
    // Load navigation menu
    this.loadNavigationMenu();
  }

  updateUserInfo() {
    const userNameEl = document.getElementById('userName');
    const userRoleEl = document.getElementById('userRole');

    if (userNameEl) {
      userNameEl.textContent = window.auth.getDisplayName();
    }

    if (userRoleEl) {
      userRoleEl.textContent = window.auth.getRoleDisplayName();
    }
  }

  loadNavigationMenu() {
    if (!this.navList || !window.auth.isAuthenticated) return;

    const menuItems = window.auth.getNavigationMenu();
    this.navList.innerHTML = '';

    menuItems.forEach(item => {
      const li = document.createElement('li');
      li.className = 'nav-item';
      
      const a = document.createElement('a');
      a.className = 'nav-link';
      a.href = item.href;
      a.innerHTML = `
        <i class="${item.icon}"></i>
        <span>${item.title}</span>
      `;
      
      // Add click handler for mobile
      a.addEventListener('click', (e) => {
        if (window.innerWidth <= 991.98) {
          this.hideSidebar();
        }
      });
      
      li.appendChild(a);
      this.navList.appendChild(li);
    });
  }

  setActiveMenuItem() {
    if (!this.navList) return;

    const currentPath = window.location.pathname;
    const navLinks = this.navList.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
      link.classList.remove('active');
      if (link.getAttribute('href') === currentPath) {
        link.classList.add('active');
      }
    });
  }

  toggleSidebar() {
    if (this.sidebar) {
      this.sidebar.classList.toggle('show');
      if (this.overlay) {
        this.overlay.classList.toggle('show');
      }
    }
  }

  showSidebar() {
    if (this.sidebar) {
      this.sidebar.classList.add('show');
      if (this.overlay) {
        this.overlay.classList.add('show');
      }
    }
  }

  hideSidebar() {
    if (this.sidebar) {
      this.sidebar.classList.remove('show');
      if (this.overlay) {
        this.overlay.classList.remove('show');
      }
    }
  }

  handleResize() {
    // Auto-hide sidebar on desktop
    if (window.innerWidth > 991.98) {
      this.hideSidebar();
    }
  }

  // Refresh sidebar content (useful after login/logout)
  refresh() {
    this.loadSidebarContent();
    this.setActiveMenuItem();
  }
}

// Initialize sidebar when DOM is ready
window.sidebarManager = new SidebarManager();

// Refresh sidebar when auth state changes
if (window.auth) {
  const originalLogout = window.auth.logout.bind(window.auth);
  window.auth.logout = async function() {
    await originalLogout();
    if (window.sidebarManager) {
      window.sidebarManager.refresh();
    }
  };
}