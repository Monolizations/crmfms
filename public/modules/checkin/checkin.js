// Time Clock JavaScript
document.addEventListener('DOMContentLoaded', function() {
  // Check user role access
  checkAccess();

  // Initialize time clock
  updateTime();
  setInterval(updateTime, 1000);

  // Load attendance status
  loadAttendanceStatus();

  // Setup event listeners
  setupEventListeners();
});

function checkAccess() {
  // Check if user has faculty, program head, or secretary role
  if (!window.auth || !window.auth.isAuthenticated) {
    window.location.href = '/crmfms/public/modules/auth/login.html';
    return;
  }

  // Get user roles
  fetch('/crmfms/api/auth/me.php', { credentials: 'include' })
    .then(r => r.json())
    .then(data => {
      const allowedRoles = ['faculty', 'program head', 'secretary'];
      const hasAccess = data.roles && data.roles.some(role => allowedRoles.includes(role));

      if (!hasAccess) {
        alert('Access denied. This page is for faculty, program heads, and secretaries only.');
        window.location.href = '/crmfms/public/modules/dashboard/index.html';
      }
    })
    .catch(err => {
      console.error('Error checking access:', err);
      window.location.href = '/crmfms/public/modules/auth/login.html';
    });
}

function updateTime() {
  const now = new Date();
  const timeString = now.toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  const dateString = now.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });

  document.getElementById('currentTime').textContent = timeString;
  document.getElementById('currentDate').textContent = dateString;
}

function setupEventListeners() {
  const checkInBtn = document.getElementById('checkInBtn');
  const checkOutBtn = document.getElementById('checkOutBtn');

  checkInBtn.addEventListener('click', handleCheckIn);
  checkOutBtn.addEventListener('click', handleCheckOut);
}

async function loadAttendanceStatus() {
  try {
    const response = await fetch('/crmfms/api/attendance/status.php', {
      credentials: 'include'
    });
    const data = await response.json();

    updateStatusDisplay(data);
  } catch (error) {
    console.error('Error loading attendance status:', error);
    showAlert('Error loading attendance status', 'danger');
  }
}

function updateStatusDisplay(data) {
  const statusCard = document.getElementById('statusCard');
  const statusTitle = document.getElementById('statusTitle');
  const statusMessage = document.getElementById('statusMessage');
  const checkInBtn = document.getElementById('checkInBtn');
  const checkOutBtn = document.getElementById('checkOutBtn');

  if (data.checkedIn) {
    statusCard.className = 'card status-card mb-4';
    statusTitle.textContent = 'Currently Checked In';
    statusMessage.textContent = `Checked in at ${data.checkInTime || 'Unknown time'}`;
    checkInBtn.disabled = true;
    checkOutBtn.disabled = false;
  } else {
    statusCard.className = 'card bg-light mb-4';
    statusTitle.textContent = 'Not Checked In';
    statusMessage.textContent = 'Please check in to start your attendance.';
    checkInBtn.disabled = false;
    checkOutBtn.disabled = true;
  }

  // Update today's summary
  document.getElementById('todayCheckIn').textContent = data.checkInTime || '--:--';
  document.getElementById('todayCheckOut').textContent = data.checkOutTime || '--:--';
  document.getElementById('totalHours').textContent = data.totalHours || '0h 0m';
}

async function handleCheckIn() {
  try {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });

    if (confirm(`Check in at ${timeString}?`)) {
      const response = await fetch('/crmfms/api/attendance/checkin.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          timestamp: now.toISOString()
        })
      });

      const data = await response.json();

      if (data.success) {
        showAlert('Successfully checked in!', 'success');
        loadAttendanceStatus(); // Refresh status
      } else {
        showAlert(data.message || 'Check-in failed', 'danger');
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

    if (confirm(`Check out at ${timeString}?`)) {
      const response = await fetch('/crmfms/api/attendance/checkout.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          timestamp: now.toISOString()
        })
      });

      const data = await response.json();

      if (data.success) {
        showAlert('Successfully checked out!', 'success');
        loadAttendanceStatus(); // Refresh status
      } else {
        showAlert(data.message || 'Check-out failed', 'danger');
      }
    }
  } catch (error) {
    console.error('Check-out error:', error);
    showAlert('Error during check-out', 'danger');
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