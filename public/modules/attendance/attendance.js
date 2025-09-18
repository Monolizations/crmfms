// Attendance Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
  // Initialize attendance management
  checkAuthenticationStatus();
  loadAttendanceData();
  setupEventListeners();
});

async function checkAuthenticationStatus() {
  try {
    console.log('Checking authentication status...');
    const response = await fetch('/crmfms/api/auth/me.php', { credentials: 'include' });
    console.log('Auth check response:', response.status, response.statusText);
    const data = await response.json();
    console.log('Auth check data:', data);
    
    if (data.success === false) {
      console.log('User not authenticated, redirecting to login');
      window.location.href = '/crmfms/public/modules/auth/login.html';
      return;
    }
    
    console.log('User is authenticated');
  } catch (error) {
    console.error('Error checking authentication:', error);
    window.location.href = '/crmfms/public/modules/auth/login.html';
  }
}

function setupEventListeners() {
  // Check in/out buttons
  const checkInBtn = document.querySelector('[data-permission-button="attendance.checkin"]');
  const checkOutBtn = document.querySelector('[data-permission-button="attendance.checkout"]');

  if (checkInBtn) {
    checkInBtn.addEventListener('click', handleCheckIn);
  }

  if (checkOutBtn) {
    checkOutBtn.addEventListener('click', handleCheckOut);
  }

  // Filter buttons
  document.getElementById('filterAll').addEventListener('click', () => setFilter('all'));
  document.getElementById('filterDepartment').addEventListener('click', () => setFilter('department'));
  document.getElementById('filterClassroom').addEventListener('click', () => setFilter('classroom'));

  // Pagination buttons (will be added dynamically, but we can set up the container)
  // Event listeners for pagination are added in updatePaginationControls function
}

function setFilter(filter) {
  currentFilter = filter;

  // Update button states
  document.getElementById('filterAll').classList.toggle('active', filter === 'all');
  document.getElementById('filterDepartment').classList.toggle('active', filter === 'department');
  document.getElementById('filterClassroom').classList.toggle('active', filter === 'classroom');

  applyFilter();
}

let currentFilter = 'all';
let allRecords = [];
let currentPage = 1;
const itemsPerPage = 10;

async function loadAttendanceData() {
  try {
    console.log('Loading attendance data...');
    
    // Check if user is admin
    let userData = localStorage.getItem('user') || sessionStorage.getItem('user') || '{}';
    if (userData === 'null' || userData === null) userData = '{}';
    let user = {};
    try {
      user = JSON.parse(userData);
      console.log('User data:', user);
    } catch (e) {
      console.error('Error parsing user data:', e);
      user = {};
    }

    // Check if user is authenticated
    if (!user || !user.user_id) {
      console.log('User not authenticated, redirecting to login');
      const tbody = document.getElementById('attendanceTable');
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Please log in to view attendance records.</td></tr>';
      return;
    }

    const isAdmin = user.roles && user.roles.includes('admin');
    console.log('Is admin:', isAdmin);

    // Update UI based on user role
    updateUIBasedOnRole(isAdmin);

    if (isAdmin) {
      // Load admin attendance data
      console.log('Loading admin attendance data...');
      await loadAdminAttendanceData();
    } else {
      // Load regular user attendance data
      console.log('Loading user attendance data...');
      await loadUserAttendanceData();
    }
  } catch (error) {
    console.error('Error loading attendance data:', error);
    showAlert('Error loading attendance data', 'danger');
  }
}

function updateUIBasedOnRole(isAdmin) {
  const tableHeader = document.getElementById('tableHeader');

  if (isAdmin) {
    // Admin view: add Employee column
    tableHeader.innerHTML = `
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Employee</th>
        <th>Location</th>
        <th>Scan Time</th>
        <th>Check In</th>
        <th>Check Out</th>
        <th>Hours Worked</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    `;

    // Update summary card titles for admin view
    const cardTitles = document.querySelectorAll('.card-title');
    if (cardTitles.length >= 4) {
      cardTitles[0].textContent = 'Currently Present';
      cardTitles[1].textContent = 'Present Today';
      cardTitles[2].textContent = 'Checked Out Today';
      cardTitles[3].textContent = 'Total Users';
    }
  } else {
    // Regular user view
    tableHeader.innerHTML = `
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Location</th>
        <th>Scan Time</th>
        <th>Check In</th>
        <th>Check Out</th>
        <th>Hours Worked</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    `;
  }
}

async function loadUserAttendanceData() {
  try {
    console.log('Loading user attendance data...');
    
    // Load today's attendance summary
    console.log('Loading attendance summary...');
    await loadAttendanceSummary();

    // Load personal attendance records
    console.log('Loading attendance records...');
    await loadAttendanceRecords();
  } catch (error) {
    console.error('Error loading user attendance data:', error);
    throw error;
  }
}

async function loadAdminAttendanceData() {
  try {
    // Load admin statistics
    await loadAdminStats();

    // Load all attendance records for admin view
    await loadAllAttendanceRecords();
  } catch (error) {
    console.error('Error loading admin attendance data:', error);
    throw error;
  }
}

async function loadAttendanceSummary() {
  try {
    console.log('Fetching attendance stats...');

    const response = await fetch('/crmfms/api/attendance/attendance.php?action=stats', {
      method: 'GET',
      credentials: 'include'
    });
    console.log('Stats response:', response.status, response.statusText);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    console.log('Stats data:', data);

    // Update summary cards with user's personal stats
    document.getElementById('todayHours').textContent = '0h 0m'; // Individual users don't have aggregate hours
    document.getElementById('presentToday').textContent = data.total_checkins || 0;
    document.getElementById('lateArrivals').textContent = '0'; // Not calculated for individual users
    document.getElementById('absentToday').textContent = '0'; // Not applicable for individual users

    console.log('Updated summary with user stats');
  } catch (error) {
    console.error('Error loading summary:', error);
    // Fallback to mock data
    document.getElementById('todayHours').textContent = '0h 0m';
    document.getElementById('presentToday').textContent = '0';
    document.getElementById('lateArrivals').textContent = '0';
    document.getElementById('absentToday').textContent = '0';
  }
}

async function loadAttendanceRecords() {
  try {
    console.log('Loading user attendance records from attendance API');

    const response = await fetch('/crmfms/api/attendance/attendance.php', {
      method: 'GET',
      credentials: 'include'
    });

    console.log('API response status:', response.status, response.statusText);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('API error response:', errorText);
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    console.log('API response data:', data);

    if (data.error) {
      throw new Error(data.error);
    }

    // Transform the data to match the expected format for rendering
    allRecords = (data.items || []).map(record => ({
      attendance_id: record.attendance_id,
      check_in_time: record.check_in_time,
      check_out_time: record.check_out_time,
      scan_timestamp: record.scan_timestamp,
      location: record.location_label || 'Unknown',
      status: record.check_out_time ? 'Checked Out' : 'Present',
      location_type: record.location_type
    }));

    console.log('Loaded', allRecords.length, 'records');
    currentPage = 1; // Reset to first page when new data is loaded
    applyFilter();
  } catch (error) {
    console.error('Error loading records:', error);
    const tbody = document.getElementById('attendanceTable');
    const user = JSON.parse(localStorage.getItem('user') || sessionStorage.getItem('user') || '{}');
    const isAdmin = user.roles && user.roles.includes('admin');
    const colspan = isAdmin ? '10' : '9';
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted">Error loading records: ${error.message}</td></tr>`;
  }
}

function applyFilter() {
  let filteredRecords = allRecords;

  if (currentFilter === 'department') {
    filteredRecords = allRecords.filter(record => record.location_type === 'department' || record.location === 'Department Office');
  } else if (currentFilter === 'classroom') {
    filteredRecords = allRecords.filter(record => record.location_type === 'room');
  }

  // Reset to first page when filter changes
  currentPage = 1;
  renderRecords(filteredRecords);
}

function updatePaginationControls(totalPages) {
  const paginationControls = document.getElementById('paginationControls');
  const prevPageBtn = document.getElementById('prevPage');
  const nextPageBtn = document.getElementById('nextPage');

  // Clear existing page numbers
  const pageItems = paginationControls.querySelectorAll('.page-number');
  pageItems.forEach(item => item.remove());

  // Add page number buttons
  const prevBtn = paginationControls.querySelector('.page-item:first-child');
  const nextBtn = paginationControls.querySelector('.page-item:last-child');

  // Calculate page range to show (max 5 pages)
  const maxPagesToShow = 5;
  let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
  let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

  // Adjust start page if we're near the end
  if (endPage - startPage + 1 < maxPagesToShow) {
    startPage = Math.max(1, endPage - maxPagesToShow + 1);
  }

  // Add page number buttons
  for (let i = startPage; i <= endPage; i++) {
    const pageItem = document.createElement('li');
    pageItem.className = `page-item page-number ${i === currentPage ? 'active' : ''}`;
    pageItem.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
    paginationControls.insertBefore(pageItem, nextBtn);
  }

  // Update prev/next button states
  prevBtn.classList.toggle('disabled', currentPage === 1);
  nextBtn.classList.toggle('disabled', currentPage === totalPages || totalPages === 0);

  // Add event listeners
  document.querySelectorAll('.page-link').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const page = parseInt(this.getAttribute('data-page'));
      if (page) {
        goToPage(page);
      } else if (this.id === 'prevPage' && currentPage > 1) {
        goToPage(currentPage - 1);
      } else if (this.id === 'nextPage' && currentPage < totalPages) {
        goToPage(currentPage + 1);
      }
    });
  });
}

function goToPage(page) {
  currentPage = page;
  applyFilter(); // Re-render with new page
}



async function handleCheckIn() {
  try {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });

    // Show confirmation
    if (confirm(`Check in at ${timeString}?`)) {
      // Make API call to check in
      const response = await fetch('/crmfms/api/attendance/checkin.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          timestamp: now.toISOString(),
          scan_timestamp: now.toISOString()
        })
      });

      const data = await response.json();

      if (data.success) {
        showAlert('Successfully checked in!', 'success');
        currentPage = 1; // Reset to first page after check-in
        loadAttendanceData(); // Refresh data
      } else {
        // Handle duplicate detection with specific messaging
        if (data.message && data.message.includes('Duplicate')) {
          showAlert(data.message, 'warning');
        } else {
          showAlert(data.message || 'Check-in failed', 'danger');
        }
      }
    }
  } catch (error) {
    console.error('Check-in error:', error);
    showAlert('Error during check-in', 'danger');
  }
}

async function handleCheckOut() {
  try {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });

    // Show confirmation
    if (confirm(`Check out at ${timeString}?`)) {
      // Make API call to check out
      const response = await fetch('/crmfms/api/attendance/checkout.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          timestamp: now.toISOString(),
          scan_timestamp: now.toISOString()
        })
      });

      const data = await response.json();

      if (data.success) {
        showAlert('Successfully checked out!', 'success');
        currentPage = 1; // Reset to first page after check-out
        loadAttendanceData(); // Refresh data
      } else {
        // Handle duplicate detection with specific messaging
        if (data.message && data.message.includes('Duplicate')) {
          showAlert(data.message, 'warning');
        } else {
          showAlert(data.message || 'Check-out failed', 'danger');
        }
      }
    }
  } catch (error) {
    console.error('Check-out error:', error);
    showAlert('Error during check-out', 'danger');
  }
}

async function loadAdminStats() {
  try {
    const response = await fetch('/crmfms/api/attendance/admin.php?action=stats', { credentials: 'include' });
    const data = await response.json();

    if (data.success) {
      document.getElementById('todayHours').textContent = data.data.currently_present + ' present';
      document.getElementById('presentToday').textContent = data.data.present_today;
      document.getElementById('lateArrivals').textContent = '0'; // Could be calculated separately
      document.getElementById('absentToday').textContent = data.data.total_users - data.data.present_today;
    }
  } catch (error) {
    console.error('Error loading admin stats:', error);
  }
}

async function loadAllAttendanceRecords() {
  try {
    const today = new Date();
    const end_date = today.getUTCFullYear() + '-' + String(today.getUTCMonth() + 1).padStart(2, '0') + '-' + String(today.getUTCDate()).padStart(2, '0');
    const start_date = '2025-01-01'; // Updated to include 2025 data

    console.log('Loading admin attendance records from', start_date, 'to', end_date);

    const response = await fetch(`/crmfms/api/attendance/admin.php?action=list&start_date=${start_date}&end_date=${end_date}`, { credentials: 'include' });

    console.log('Admin API response status:', response.status);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('Admin API error response:', errorText);
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    console.log('Admin API response data:', data);

    if (data.success === false) {
      throw new Error(data.message || 'API error');
    }

    allRecords = data.data.items || [];
    console.log('Loaded', allRecords.length, 'admin records');
    currentPage = 1; // Reset to first page when new data is loaded
    applyFilter();
  } catch (error) {
    console.error('Error loading all attendance records:', error);
    const tbody = document.getElementById('attendanceTable');
    const user = JSON.parse(localStorage.getItem('user') || sessionStorage.getItem('user') || '{}');
    const isAdmin = user.roles && user.roles.includes('admin');
    const colspan = isAdmin ? '10' : '9';
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted">Error loading records: ${error.message}</td></tr>`;
  }
}

function renderRecords(records) {
  const tbody = document.getElementById('attendanceTable');
  let user = {};
  try {
    const userData = localStorage.getItem('user') || sessionStorage.getItem('user') || '{}';
    if (userData === 'null' || userData === null) userData = '{}';
    user = JSON.parse(userData);
  } catch (e) {
    console.error('Error parsing user data in renderRecords:', e);
    user = {};
  }
  const isAdmin = user && user.roles && user.roles.includes('admin');

  tbody.innerHTML = '';

  // Calculate pagination
  const totalRecords = records.length;
  const totalPages = Math.ceil(totalRecords / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = Math.min(startIndex + itemsPerPage, totalRecords);
  const currentRecords = records.slice(startIndex, endIndex);

  // Update pagination info
  const paginationInfo = document.getElementById('paginationInfo');
  if (totalRecords > 0) {
    paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalRecords} records`;
  } else {
    paginationInfo.textContent = 'Showing 0 to 0 of 0 records';
  }

  // Update pagination controls
  updatePaginationControls(totalPages);

  currentRecords.forEach(record => {
      // Normalize field names for both user and admin data
      const checkInTimeField = record.check_in_time || record.time_in;
      const checkOutTimeField = record.check_out_time || record.time_out;
      const scanTimeField = record.scan_timestamp;

      // Parse dates safely
      const parseDate = (dateStr) => {
        if (!dateStr) return null;
        try {
          // Handle different date formats
          if (dateStr.includes(' ')) {
            // Convert 'YYYY-MM-DD HH:MM:SS' to 'YYYY-MM-DDTHH:MM:SS' for better parsing
            const isoStr = dateStr.replace(' ', 'T');
            return new Date(isoStr);
          } else {
            // Already in ISO format or other format
            return new Date(dateStr);
          }
        } catch (e) {
          console.error('Error parsing date:', dateStr, e);
          return null;
        }
      };

      const checkInDate = parseDate(checkInTimeField);
      const checkOutDate = parseDate(checkOutTimeField);
      const scanDate = parseDate(scanTimeField);

      const checkInTime = checkInDate ? checkInDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '--:--';
      const scanTime = scanDate ? scanDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '--:--';
      const isClassroom = record.location && record.location.includes('Room');
      const checkOutTime = isClassroom ? 'Session Active' : (checkOutDate ? checkOutDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '--:--');
      const date = checkInDate ? checkInDate.toLocaleDateString('en-US') : 'Unknown';

      // Determine type and location
      const type = isClassroom ? 'Classroom' : 'Department';
      const location = record.location || 'Unknown';

      // Calculate hours worked (only for department check-ins/check-outs)
      let hoursWorked = '--';
      if (!isClassroom && checkInDate && checkOutDate) {
        hoursWorked = record.hours_worked || '--';
        if (!record.hours_worked) {
          const diff = checkOutDate - checkInDate;
          const hours = Math.floor(diff / (1000 * 60 * 60));
          const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
          hoursWorked = `${hours}h ${minutes}m`;
        }
      } else if (isClassroom) {
        hoursWorked = 'Session';
      }

      let statusClass = 'danger';
      let displayStatus = record.status;

      if (isClassroom) {
        statusClass = 'info';
        displayStatus = 'Class Session';
      } else {
        statusClass = record.status === 'Present' ? 'success' : record.status === 'Checked Out' ? 'primary' : 'danger';
      }

      const row = document.createElement('tr');
      if (isAdmin) {
        // Admin view with employee details
        row.innerHTML = `
          <td>${date}</td>
          <td><span class="badge bg-info">${type}</span></td>
          <td>${record.name || 'Unknown'}<br><small class="text-muted">${record.employee_id || ''}</small></td>
          <td>${location}</td>
          <td>${scanTime}</td>
          <td>${checkInTime}</td>
          <td>${checkOutTime}</td>
          <td>${hoursWorked}</td>
          <td><span class="badge bg-${statusClass}">${displayStatus}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteAttendanceRecord(${record.attendance_id})">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        `;
      } else {
        // Regular user view
        row.innerHTML = `
          <td>${date}</td>
          <td><span class="badge bg-info">${type}</span></td>
          <td>${location}</td>
          <td>${scanTime}</td>
          <td>${checkInTime}</td>
          <td>${checkOutTime}</td>
          <td>${hoursWorked}</td>
          <td><span class="badge bg-${statusClass}">${displayStatus}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" data-permission-button="attendance.manage">
              <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" data-permission-button="attendance.manage">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        `;
      }
      tbody.appendChild(row);
    });

  // Show message if no records
  if (currentRecords.length === 0 && records.length > 0) {
    const row = document.createElement('tr');
    const colspan = isAdmin ? '10' : '9';
    row.innerHTML = `<td colspan="${colspan}" class="text-center text-muted">No records found for this page.</td>`;
    tbody.appendChild(row);
  }
}

async function deleteAttendanceRecord(attendanceId) {
  if (!confirm('Are you sure you want to delete this attendance record?')) {
    return;
  }

  try {
    const response = await fetch('/crmfms/api/attendance/admin.php?action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'include',
      body: `attendance_id=${attendanceId}`
    });

    const data = await response.json();

    if (data.success) {
      showAlert('Attendance record deleted successfully', 'success');
      currentPage = 1; // Reset to first page after deletion
      loadAttendanceData(); // Refresh data
    } else {
      showAlert(data.message || 'Failed to delete record', 'danger');
    }
  } catch (error) {
    console.error('Error deleting attendance record:', error);
    showAlert('Error deleting record', 'danger');
  }
}

function showAlert(message, type) {
  // Create alert element
  const alert = document.createElement('div');
  alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
  alert.style.top = '20px';
  alert.style.right = '20px';
  alert.style.zIndex = '9999';
  alert.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;

  document.body.appendChild(alert);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (alert.parentNode) {
      alert.remove();
    }
  }, 5000);
}
