// Attendance Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
  // Initialize attendance management
  loadAttendanceData();
  setupEventListeners();
});

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
}

async function loadAttendanceData() {
  try {
    // Load today's attendance summary
    await loadAttendanceSummary();
    
    // Load attendance records
    await loadAttendanceRecords();
  } catch (error) {
    console.error('Error loading attendance data:', error);
    showAlert('Error loading attendance data', 'danger');
  }
}

async function loadAttendanceSummary() {
  // Mock data - replace with actual API calls
  const summary = {
    todayHours: '8h 30m',
    presentToday: 45,
    lateArrivals: 3,
    absentToday: 2
  };
  
  document.getElementById('todayHours').textContent = summary.todayHours;
  document.getElementById('presentToday').textContent = summary.presentToday;
  document.getElementById('lateArrivals').textContent = summary.lateArrivals;
  document.getElementById('absentToday').textContent = summary.absentToday;
}

async function loadAttendanceRecords() {
  // Mock data - replace with actual API calls
  const records = [
    {
      date: '2024-01-15',
      checkIn: '08:30 AM',
      checkOut: '05:15 PM',
      hoursWorked: '8h 45m',
      status: 'Present',
      statusClass: 'success'
    },
    {
      date: '2024-01-14',
      checkIn: '09:15 AM',
      checkOut: '05:30 PM',
      hoursWorked: '8h 15m',
      status: 'Late',
      statusClass: 'warning'
    },
    {
      date: '2024-01-13',
      checkIn: '08:00 AM',
      checkOut: '05:00 PM',
      hoursWorked: '9h 0m',
      status: 'Present',
      statusClass: 'success'
    }
  ];
  
  const tbody = document.getElementById('attendanceTable');
  tbody.innerHTML = '';
  
  records.forEach(record => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${record.date}</td>
      <td>${record.checkIn}</td>
      <td>${record.checkOut}</td>
      <td>${record.hoursWorked}</td>
      <td><span class="badge bg-${record.statusClass}">${record.status}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-primary" data-permission-button="attendance.manage">
          <i class="fas fa-edit"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger" data-permission-button="attendance.manage">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    `;
    tbody.appendChild(row);
  });
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
        body: JSON.stringify({
          timestamp: now.toISOString()
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        showAlert('Successfully checked in!', 'success');
        loadAttendanceData(); // Refresh data
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
    
    // Show confirmation
    if (confirm(`Check out at ${timeString}?`)) {
      // Make API call to check out
      const response = await fetch('/crmfms/api/attendance/checkout.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          timestamp: now.toISOString()
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        showAlert('Successfully checked out!', 'success');
        loadAttendanceData(); // Refresh data
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
