// Time Clock JavaScript
document.addEventListener('DOMContentLoaded', function() {
  console.log('Checkin page loaded, initializing...');

  // Small delay to ensure DOM is fully ready
  setTimeout(() => {
    // Check if required elements exist
    const statusCard = document.getElementById('statusCard');
    const currentTime = document.getElementById('currentTime');

    if (!statusCard || !currentTime) {
      console.error('Required DOM elements not found. Page may not have loaded correctly.');
      return;
    }

    // Initialize time clock
    updateTime();
    setInterval(updateTime, 1000);

    // Check user role access (this will also load attendance status if access is granted)
    checkAccess();

    // Setup event listeners
    setupEventListeners();

    console.log('Checkin page initialization complete');
  }, 100);
});

async function checkAccess() {
  try {
    // First check if we have basic auth data
    if (!window.auth || !window.auth.isAuthenticated) {
      window.location.href = '/crmfms/public/modules/auth/login.html';
      return;
    }

    // Validate session with server
    const response = await fetch('/crmfms/api/auth/me.php', { credentials: 'include' });

    if (!response.ok) {
      throw new Error('Session expired or invalid');
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error('Authentication failed');
    }

    const allowedRoles = ['faculty', 'program head', 'secretary'];
    const hasAccess = data.roles && data.roles.some(role => allowedRoles.includes(role));

    if (!hasAccess) {
      alert('Access denied. This page is for faculty, program heads, and secretaries only.');
      window.location.href = '/crmfms/public/modules/dashboard/index.html';
      return;
    }

    // Access granted, proceed with loading attendance status
    loadAttendanceStatus();

  } catch (err) {
    console.error('Error checking access:', err);

    // Update status display to show login required
    const statusCard = document.getElementById('statusCard');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const loadingSpinner = document.getElementById('statusLoadingSpinner');
    const statusIcon = document.getElementById('statusIcon');

    if (!statusCard || !statusTitle || !statusMessage) {
      console.error('Required DOM elements not found for login prompt');
      return;
    }

    if (statusCard && statusTitle && statusMessage) {
      statusCard.className = 'card bg-danger text-white mb-4';
      statusTitle.textContent = 'Login Required';
      statusMessage.innerHTML = 'Please log in to access the attendance system.<br><br><a href="/crmfms/public/modules/auth/login.html" class="btn btn-light btn-sm me-2">Login Now</a>';
    }

    if (loadingSpinner) loadingSpinner.style.display = 'none';
    if (statusIcon) {
      statusIcon.className = 'fas fa-lock fa-3x mb-3';
      statusIcon.style.display = 'block';
    }

    const actionButtons = document.getElementById('actionButtons');
    if (actionButtons) {
      actionButtons.style.display = 'block';
    } else {
      console.warn('actionButtons element not found in checkAccess error handler');
    }

    // Don't redirect automatically, let user click login
    // window.location.href = '/crmfms/public/modules/auth/login.html';
  }
}

function updateTime() {
  try {
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

    const timeElement = document.getElementById('currentTime');
    const dateElement = document.getElementById('currentDate');

    if (timeElement) timeElement.textContent = timeString;
    if (dateElement) dateElement.textContent = dateString;
  } catch (error) {
    console.error('Error updating time:', error);
  }
}

function setupEventListeners() {
  try {
    const scanDepartmentBtn = document.getElementById('scanDepartmentBtn');
    const scanRoomBtn = document.getElementById('scanRoomBtn');
    const refreshStatusBtn = document.getElementById('refreshStatusBtn');
    const testLoginBtn = document.getElementById('testLoginBtn');
    const actionButtons = document.getElementById('actionButtons');

  if (scanDepartmentBtn) {
    scanDepartmentBtn.addEventListener('click', () => startQRScan('department'));
  } else {
    console.warn('scanDepartmentBtn element not found');
  }

  if (scanRoomBtn) {
    scanRoomBtn.addEventListener('click', () => startQRScan('room'));
  } else {
    console.warn('scanRoomBtn element not found');
  }

  if (refreshStatusBtn) {
    refreshStatusBtn.addEventListener('click', () => {
      refreshStatusBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
      refreshStatusBtn.disabled = true;
      loadAttendanceStatus().finally(() => {
        if (refreshStatusBtn) {
          refreshStatusBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Refresh Status';
          refreshStatusBtn.disabled = false;
        }
      });
    });
  } else {
    console.warn('refreshStatusBtn element not found');
  }

  if (testLoginBtn) {
    testLoginBtn.addEventListener('click', () => {
      // Use faculty1 account for testing
      localStorage.setItem('testEmail', 'faculty1@test.com');
      localStorage.setItem('testPassword', 'test123');
      window.location.href = '/crmfms/public/modules/auth/login.html';
    });
  } else {
    console.warn('testLoginBtn element not found');
  }
  } catch (error) {
    console.error('Error setting up event listeners:', error);
  }
}

async function loadAttendanceStatus() {
  console.log('Loading attendance status...');
  try {
    // First validate authentication
    console.log('Validating authentication...');
    const authResponse = await fetch('/crmfms/api/auth/me.php', {
      credentials: 'include'
    });

    if (!authResponse.ok) {
      throw new Error('Authentication failed');
    }

    let authData;
    try {
      authData = await authResponse.json();
    } catch (e) {
      console.error('Failed to parse auth response:', e);
      throw new Error('Invalid response from authentication service');
    }

    if (!authData.success) {
      throw new Error('Not authenticated');
    }

    // Now load attendance status
    const response = await fetch('/crmfms/api/attendance/status.php', {
      credentials: 'include'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    let data;
    try {
      data = await response.json();
    } catch (e) {
      console.error('Failed to parse attendance status response:', e);
      throw new Error('Invalid response from attendance service');
    }

    if (data.success === false) {
      throw new Error(data.message || 'Failed to load attendance status');
    }

    updateStatusDisplay(data);
  } catch (error) {
    console.error('Error loading attendance status:', error);
    console.log('Falling back to error display...');

    // Update status display to show error state
    const statusCard = document.getElementById('statusCard');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const refreshBtn = document.getElementById('refreshStatusBtn');

    if (!statusCard || !statusTitle || !statusMessage) {
      console.error('Required DOM elements not found for error display');
      return;
    }
    const loadingSpinner = document.getElementById('statusLoadingSpinner');
    const statusIcon = document.getElementById('statusIcon');

    if (statusCard && statusTitle && statusMessage) {
      statusCard.className = 'card bg-danger text-white mb-4';
      statusTitle.textContent = 'Authentication Required';
      statusMessage.innerHTML = 'You need to log in to access the attendance system.<br><br><a href="/crmfms/public/modules/auth/login.html" class="btn btn-light btn-sm me-2">Login</a><a href="/crmfms/public/modules/dashboard/index.html" class="btn btn-outline-light btn-sm">Go to Dashboard</a>';
    }

    if (loadingSpinner) loadingSpinner.style.display = 'none';
    if (statusIcon) {
      statusIcon.className = 'fas fa-sign-in-alt fa-3x mb-3';
      statusIcon.style.display = 'block';
    }

    const actionButtons = document.getElementById('actionButtons');
    if (actionButtons) {
      actionButtons.style.display = 'block';
    } else {
      console.warn('actionButtons element not found in error handler');
    }
    if (refreshBtn) {
      refreshBtn.style.display = 'none'; // Hide refresh button for auth errors
    }

    // Show alert for debugging
    showAlert('Error loading attendance status: ' + error.message, 'danger');
  }
}

function updateStatusDisplay(data) {
  const statusCard = document.getElementById('statusCard');
  const statusTitle = document.getElementById('statusTitle');
  const statusMessage = document.getElementById('statusMessage');
  const refreshBtn = document.getElementById('refreshStatusBtn');
  const loadingSpinner = document.getElementById('statusLoadingSpinner');
  const statusIcon = document.getElementById('statusIcon');

  // Ensure all elements exist before manipulating them
  if (!statusCard || !statusTitle || !statusMessage) {
    console.error('Required DOM elements not found for status display');
    return;
  }

  // Hide loading spinner and show appropriate icon
  if (loadingSpinner) loadingSpinner.style.display = 'none';
  if (statusIcon) statusIcon.style.display = 'block';

  if (data.checkedIn) {
    statusCard.className = 'card status-card mb-4';
    statusIcon.className = 'fas fa-clock fa-3x mb-3';
    statusTitle.textContent = 'Currently Checked In';
    statusMessage.textContent = `Checked in at ${data.checkInTime || 'Unknown time'}. Scan QR code to check out.`;
  } else {
    statusCard.className = 'card bg-light mb-4';
    statusIcon.className = 'fas fa-sign-in-alt fa-3x mb-3';
    statusTitle.textContent = 'Ready for Check-in';
    statusMessage.textContent = 'Scan Department or Classroom QR codes to record your attendance.';
  }

  // Update today's summary
  const checkInElement = document.getElementById('todayCheckIn');
  const checkOutElement = document.getElementById('todayCheckOut');
  const totalHoursElement = document.getElementById('totalHours');

  if (checkInElement) checkInElement.textContent = data.checkInTime || '--:--';
  if (checkOutElement) checkOutElement.textContent = data.checkOutTime || '--:--';
  if (totalHoursElement) totalHoursElement.textContent = data.totalHours || '0h 0m';

  // Hide action buttons on successful load
  const actionButtons = document.getElementById('actionButtons');
  if (actionButtons) {
    actionButtons.style.display = 'none';
  } else {
    console.warn('actionButtons element not found');
  }
}



let qrScanner = null;

function startQRScan(type) {
  const modal = new bootstrap.Modal(document.getElementById('qrScannerModal'));
  modal.show();

  // Initialize scanner after modal is shown
  setTimeout(() => {
    initQRScanner(type);
  }, 500);
}

function initQRScanner(type) {
  const video = document.getElementById('qrVideo');
  const status = document.getElementById('qrScanStatus');

  // Stop any existing scanner
  if (qrScanner) {
    qrScanner.stop();
  }

  qrScanner = new Instascan.Scanner({ video: video });

  qrScanner.addListener('scan', function (content) {
    handleQRCode(content, type);
  });

  Instascan.Camera.getCameras().then(function (cameras) {
    if (cameras.length > 0) {
      // Use back camera if available
      const backCamera = cameras.find(camera => camera.name.toLowerCase().includes('back'));
      qrScanner.start(backCamera || cameras[0]);
      status.textContent = 'Camera ready. Point at QR code to scan.';
    } else {
      status.textContent = 'No cameras found.';
      showAlert('No cameras found on this device.', 'danger');
    }
  }).catch(function (e) {
    console.error(e);
    status.textContent = 'Camera access denied.';
    showAlert('Camera access denied. Please allow camera access.', 'danger');
  });
}

function handleQRCode(qrData, type) {
  // Stop scanner
  if (qrScanner) {
    qrScanner.stop();
  }

  const status = document.getElementById('qrScanStatus');
  status.textContent = 'QR code detected. Processing...';

  // Get geolocation
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;

        // Send QR data with location to server
        sendQRData(qrData, latitude, longitude, type);
      },
      (error) => {
        console.warn('Geolocation error:', error);
        // Send QR data without location
        sendQRData(qrData, null, null, type);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  } else {
    // Send QR data without location
    sendQRData(qrData, null, null, type);
  }
}

function sendQRData(qrData, latitude, longitude, type) {
  const status = document.getElementById('qrScanStatus');

  // Capture client-side scan timestamp
  const scanTimestamp = new Date().toISOString();

  fetch('/crmfms/api/attendance/attendance.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify({
      code_value: qrData,
      latitude: latitude,
      longitude: longitude,
      scan_timestamp: scanTimestamp
    })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        status.textContent = data.message;
        status.style.color = 'green';

        // Close modal after 2 seconds
        setTimeout(() => {
          bootstrap.Modal.getInstance(document.getElementById('qrScannerModal')).hide();
          loadAttendanceStatus(); // Refresh status
        }, 2000);
      } else {
        status.textContent = data.message || 'Invalid QR code';
        status.style.color = 'red';
      }
    })
    .catch(error => {
      console.error('QR scan error:', error);
      status.textContent = 'Error processing QR code';
      status.style.color = 'red';
    });
}

// Stop scanner when modal is closed
document.getElementById('qrScannerModal').addEventListener('hidden.bs.modal', function () {
  if (qrScanner) {
    qrScanner.stop();
    qrScanner = null;
  }
});

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