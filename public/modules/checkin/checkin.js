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
  const scanDepartmentBtn = document.getElementById('scanDepartmentBtn');
  const scanRoomBtn = document.getElementById('scanRoomBtn');

  if (scanDepartmentBtn) {
    scanDepartmentBtn.addEventListener('click', () => startQRScan('department'));
  }
  if (scanRoomBtn) {
    scanRoomBtn.addEventListener('click', () => startQRScan('room'));
  }
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

  if (data.checkedIn) {
    statusCard.className = 'card status-card mb-4';
    statusTitle.textContent = 'Currently Checked In';
    statusMessage.textContent = `Checked in at ${data.checkInTime || 'Unknown time'}. Scan QR code to check out.`;
  } else {
    statusCard.className = 'card bg-light mb-4';
    statusTitle.textContent = 'Ready for Check-in';
    statusMessage.textContent = 'Scan Department or Classroom QR codes to record your attendance.';
  }

  // Update today's summary
  document.getElementById('todayCheckIn').textContent = data.checkInTime || '--:--';
  document.getElementById('todayCheckOut').textContent = data.checkOutTime || '--:--';
  document.getElementById('totalHours').textContent = data.totalHours || '0h 0m';
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