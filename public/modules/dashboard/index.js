// /modules/dashboard/index.js
const API_BASE = "/crmfms/api";

async function apiGet(path) {
  const response = await fetch(`${API_BASE}${path}`, { credentials: "include" });
  const rawText = await response.text();
  if (!response.ok) {
    // Surface server message for easier debugging
    throw new Error(`HTTP ${response.status}: ${rawText.slice(0, 200)}`);
  }
  try {
    return JSON.parse(rawText);
  } catch (e) {
    console.error(`Non-JSON response from ${path}:`, rawText);
    throw new Error(`Invalid JSON from ${path}`);
  }
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function renderPresent(list) {
  const box = document.getElementById("presentList");
  const empty = document.getElementById("presentEmpty");
  box.innerHTML = "";
  if (!list || list.length === 0) {
    empty.style.display = "block";
    return;
  }
  empty.style.display = "none";
  list.forEach(item => {
    const div = document.createElement("div");
    div.className = "list-group-item";
    div.innerHTML = `
      <div class="d-flex justify-content-between">
        <div>
          <strong>${item.name}</strong>
          <div class="text-muted">${item.location_type}: ${item.location_label}</div>
        </div>
        <div class="text-end">
          <div class="small">${item.time_in_human}</div>
          <span class="badge bg-secondary">${item.status}</span>
        </div>
      </div>`;
    box.appendChild(div);
  });
}

function renderAlerts(items) {
  const box = document.getElementById("alertsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No alerts.</span>';
    return;
  }
  items.forEach(a => {
    const div = document.createElement("div");
    div.className = "mb-2";
    div.innerHTML = `<span class="badge bg-warning text-dark me-2">Alert</span>${a.message}`;
    box.appendChild(div);
  });
}

function renderSuggestions(items) {
  const box = document.getElementById("suggestionsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No suggestions right now.</span>';
    return;
  }
  items.forEach(s => {
    const div = document.createElement("div");
    div.className = "mb-2";
    div.innerHTML = `<span class="badge bg-info me-2">${s.building}</span>${s.note}`;
    box.appendChild(div);
  });
}

async function loadAll() {
  try {
    // Stop any existing auto-refresh
    stopAutoRefresh();

    const me = await apiGet("/auth/me.php");
    const roles = me.roles || [];
    const primaryRole = roles.length > 0 ? roles[0] : "guest";
    const roleTag = document.getElementById("roleTag");
    if (roleTag) {
      roleTag.textContent = primaryRole.toUpperCase();
    }

    // Hide all dashboards
    document.getElementById("adminDashboard").style.display = "none";
    document.getElementById("deanDashboard").style.display = "none";
    document.getElementById("programHeadDashboard").style.display = "none";
    document.getElementById("facultyDashboard").style.display = "none";
    document.getElementById("staffDashboard").style.display = "none";

    // Determine primary dashboard based on role hierarchy (highest privilege first)
    let primaryDashboard = null;

    // Role hierarchy: admin > dean > secretary > program head > faculty > staff
    if (roles.includes("admin")) {
      primaryDashboard = "admin";
    } else if (roles.includes("dean")) {
      primaryDashboard = "dean";
    } else if (roles.includes("secretary")) {
      primaryDashboard = "secretary";
    } else if (roles.includes("program head")) {
      primaryDashboard = "programHead";
    } else if (roles.includes("faculty")) {
      primaryDashboard = "faculty";
    } else if (roles.includes("staff")) {
      primaryDashboard = "staff";
    } else {
      // Default to dean dashboard for unknown roles
      primaryDashboard = "dean";
    }

    // Load the appropriate dashboard
    switch (primaryDashboard) {
      case "admin":
        await loadAdminDashboard();
        break;
      case "dean":
        await loadDeanDashboard();
        break;
      case "secretary":
        await loadSecretaryDashboard();
        break;
      case "programHead":
        await loadProgramHeadDashboard();
        break;
      case "faculty":
        await loadFacultyDashboard();
        break;
      case "staff":
        await loadStaffDashboard();
        break;
      default:
        await loadDeanDashboard();
    }
  } catch (e) {
    console.error(e);
    alert("Failed to load dashboard.");
  }
}

async function loadAdminDashboard() {
  document.getElementById("adminDashboard").style.display = "block";
  try {
    // Load comprehensive admin data
    const [stats, present, leaves, alerts, suggestions, activity] = await Promise.all([
      apiGet("/admin/admin.php?action=stats"),
      apiGet("/attendance/present.php"),
      apiGet("/leaves/leaves.php?action=pending"),
      apiGet("/reports/alerts.php"),
      apiGet("/monitoring/suggestions.php"),
      apiGet("/admin/admin.php?action=recent_activity"), // Need to implement
    ]);

    // Update KPIs
    setText("adminTotalUsers", stats.total_users || 0);
    setText("adminActiveToday", present.items?.length || 0);
    setText("adminPendingLeaves", leaves.items?.length || 0);
    setText("adminSystemAlerts", alerts.items?.length || 0);

    // Render alerts
    renderAdminAlerts(alerts.items);

    // Render suggestions
    renderAdminSuggestions(suggestions.items);

    // Render recent activity
    renderRecentActivity(activity.items);

    // Start auto-refresh for alerts and suggestions
    startAutoRefresh();

  } catch (e) {
    console.error("Admin dashboard error:", e);
  }
}

function renderAdminAlerts(items) {
  const box = document.getElementById("adminAlertsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No system alerts.</span>';
    return;
  }

  // Sort by created_at descending and take recent 5
  const recentItems = items
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    .slice(0, 5);

  recentItems.forEach(a => {
    const div = document.createElement("div");
    div.className = "mb-2 p-2 border rounded";

    // Determine badge color based on alert type
    let badgeClass = 'bg-info'; // default
    let badgeText = 'Info';

    switch(a.type) {
      case 'warning':
        badgeClass = 'bg-warning text-dark';
        badgeText = 'Warning';
        break;
      case 'error':
        badgeClass = 'bg-danger';
        badgeText = 'Error';
        break;
      case 'success':
        badgeClass = 'bg-success';
        badgeText = 'Success';
        break;
      case 'info':
      default:
        badgeClass = 'bg-info';
        badgeText = 'Info';
        break;
    }

    // Add priority indicator
    let priorityIcon = '';
    if (a.priority >= 3) {
      priorityIcon = '<i class="fas fa-exclamation-triangle me-1"></i>';
    } else if (a.priority >= 2) {
      priorityIcon = '<i class="fas fa-info-circle me-1"></i>';
    }

    div.innerHTML = `
      <div class="d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
          <span class="badge ${badgeClass} me-2">${priorityIcon}${badgeText}</span>
          <span>${a.message}</span>
        </div>
        <div class="text-end">
          <small class="text-muted">${formatTimeAgo(a.created_at)}</small>
          ${window.auth?.user?.role === 'admin' ? `<button class="btn btn-sm btn-outline-danger ms-2" onclick="dismissAlert(${a.alert_id})"><i class="fas fa-times"></i></button>` : ''}
        </div>
      </div>
    `;
    box.appendChild(div);
  });
}

function formatTimeAgo(timestamp) {
  const now = new Date();
  const created = new Date(timestamp);
  const diffMs = now - created;
  const diffMins = Math.floor(diffMs / (1000 * 60));
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffMins < 60) {
    return diffMins <= 1 ? 'Just now' : `${diffMins}m ago`;
  } else if (diffHours < 24) {
    return diffHours === 1 ? '1h ago' : `${diffHours}h ago`;
  } else {
    return diffDays === 1 ? '1d ago' : `${diffDays}d ago`;
  }
}

async function dismissAlert(alertId) {
  try {
    const response = await fetch('/crmfms/api/reports/alerts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'dismiss',
        alert_id: alertId
      })
    });

    const data = await response.json();
    if (data.success) {
      // Refresh the admin dashboard to update alerts
      if (document.getElementById("adminDashboard").style.display !== "none") {
        loadAdminDashboard();
      }
    } else {
      alert('Failed to dismiss alert: ' + data.message);
    }
  } catch (e) {
    console.error('Error dismissing alert:', e);
    alert('Error dismissing alert');
  }
}

function renderAdminSuggestions(items) {
  const box = document.getElementById("adminSuggestionsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No roaming suggestions at this time.</span>';
    return;
  }

  // Sort by created_at descending if available, otherwise take first 5
  let recentItems = items;
  if (items[0] && items[0].created_at) {
    recentItems = items.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
  }
  recentItems = recentItems.slice(0, 5);

  recentItems.forEach(s => {
    const div = document.createElement("div");
    div.className = "mb-2";
    div.innerHTML = `<span class="badge bg-info me-2">${s.building}</span>${s.note}`;
    box.appendChild(div);
  });
}

function renderRecentActivity(items) {
  const box = document.getElementById("recentActivityBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No recent activity.</span>';
    return;
  }
  items.slice(0, 10).forEach(activity => {
    const div = document.createElement("div");
    div.className = "mb-2 small";
    div.innerHTML = `
      <div class="d-flex justify-content-between">
        <span>${activity.action}</span>
        <span class="text-muted">${activity.time_ago}</span>
      </div>
    `;
    box.appendChild(div);
  });
}

function showGracePeriodModal() {
  // Create and show grace period settings modal
  const modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.innerHTML = `
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Set Grace Period</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="gracePeriodForm">
            <div class="mb-3">
              <label for="gracePeriod" class="form-label">Grace Period (minutes)</label>
              <input type="number" class="form-control" id="gracePeriod" min="0" max="60" value="5">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="saveGracePeriod()">Save</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  // Remove modal from DOM after hiding
  modal.addEventListener('hidden.bs.modal', () => {
    document.body.removeChild(modal);
  });
}

async function saveGracePeriod() {
  const gracePeriod = document.getElementById('gracePeriod').value;
  try {
    const response = await fetch('/crmfms/api/admin/admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'set_grace_period',
        grace_period: parseInt(gracePeriod)
      })
    });
    const data = await response.json();
    if (data.success) {
      alert('Grace period updated successfully!');
      bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
    } else {
      alert('Failed to update grace period: ' + data.message);
    }
  } catch (e) {
    console.error('Error saving grace period:', e);
    alert('Error saving grace period');
  }
}

async function loadDeanDashboard() {
  document.getElementById("deanDashboard").style.display = "block";
  try {
    const [dashboard, performance, approvals, analytics] = await Promise.all([
      apiGet("/dean/dean.php?action=dashboard"),
      apiGet("/dean/dean.php?action=faculty_performance"),
      apiGet("/dean/dean.php?action=pending_approvals"),
      apiGet("/dean/dean.php?action=department_analytics"),
    ]);

    // Update KPIs
    setText("deanTotalFaculty", dashboard.total_faculty || 0);
    setText("deanPresentToday", dashboard.present_today || 0);
    setText("deanPendingLeaves", dashboard.pending_leaves || 0);
    setText("deanActiveRooms", dashboard.active_rooms || 0);

    // Render faculty performance issues
    renderFacultyPerformance(performance.items);

    // Render pending approvals
    renderPendingApprovals(approvals.items);

    // Render department analytics
    renderAttendanceChart(analytics.monthly_trend);
    renderRoomUtilization(analytics.room_utilization);

  } catch (e) {
    console.error("Dean dashboard error:", e);
  }
}

function renderFacultyPerformance(items) {
  const box = document.getElementById("facultyPerformanceBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No faculty performance issues detected.</span>';
    return;
  }

  items.forEach(item => {
    const div = document.createElement("div");
    div.className = "mb-2 p-2 border rounded";
    div.innerHTML = `
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <strong>${item.faculty_name}</strong>
          <div class="small text-muted">
            Attendance: ${item.attendance_rate}% | Overtime: ${item.overtime_days} days
          </div>
        </div>
        <span class="badge bg-warning text-dark">${item.attendance_rate < 80 ? 'Low Attendance' : 'High Overtime'}</span>
      </div>
    `;
    box.appendChild(div);
  });
}

function renderPendingApprovals(items) {
  const box = document.getElementById("pendingApprovalsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No pending leave approvals.</span>';
    return;
  }

  items.forEach(item => {
    const div = document.createElement("div");
    div.className = "mb-2 p-2 border rounded";
    div.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <strong>${item.faculty_name}</strong> (${item.role_name})
          <div class="small text-muted">
            ${item.leave_type} - ${item.start_date} to ${item.end_date}
          </div>
          ${item.reason ? `<div class="small text-muted">Reason: ${item.reason}</div>` : ''}
        </div>
        <div>
          <button class="btn btn-sm btn-success me-1" onclick="approveLeave(${item.leave_id}, 'approve')">
            <i class="fas fa-check"></i>
          </button>
          <button class="btn btn-sm btn-danger" onclick="approveLeave(${item.leave_id}, 'reject')">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    `;
    box.appendChild(div);
  });
}

function renderAttendanceChart(data) {
  const ctx = document.getElementById('attendanceChart');
  if (!ctx) return;

  // Simple bar chart using canvas
  const canvas = ctx.getContext('2d');
  canvas.clearRect(0, 0, ctx.width, ctx.height);

  if (!data || data.length === 0) {
    canvas.fillStyle = '#6c757d';
    canvas.font = '14px Arial';
    canvas.fillText('No attendance data available', 20, 100);
    return;
  }

  const barWidth = 40;
  const barSpacing = 20;
  const startX = 50;
  const startY = 180;
  const maxHeight = 150;

  const maxValue = Math.max(...data.map(d => d.unique_attendees));

  data.slice(0, 6).forEach((item, index) => {
    const x = startX + index * (barWidth + barSpacing);
    const height = (item.unique_attendees / maxValue) * maxHeight;

    // Draw bar
    canvas.fillStyle = '#007bff';
    canvas.fillRect(x, startY - height, barWidth, height);

    // Draw label
    canvas.fillStyle = '#000';
    canvas.font = '12px Arial';
    canvas.textAlign = 'center';
    canvas.fillText(item.month, x + barWidth/2, startY + 15);

    // Draw value
    canvas.fillText(item.unique_attendees, x + barWidth/2, startY - height - 5);
  });
}

function renderRoomUtilization(items) {
  const box = document.getElementById("roomUtilizationBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No room utilization data available.</span>';
    return;
  }

  items.forEach(item => {
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    div.innerHTML = `
      <div>
        <strong>${item.room_name}</strong>
        <div class="small text-muted">${item.building}</div>
      </div>
      <span class="badge bg-info">${item.usage_count} uses</span>
    `;
    box.appendChild(div);
  });
}

async function approveLeave(leaveId, decision) {
  try {
    const response = await fetch('/crmfms/api/dean/dean.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'approve_leave',
        leave_id: leaveId,
        decision: decision
      })
    });

    const data = await response.json();
    if (data.success) {
      alert(`Leave request ${decision}d successfully!`);
      loadDeanDashboard(); // Refresh the dashboard
    } else {
      alert('Error: ' + data.message);
    }
  } catch (e) {
    console.error('Error approving leave:', e);
    alert('Error processing leave approval');
  }
}

async function loadSecretaryDashboard() {
  document.getElementById("secretaryDashboard").style.display = "block";
  try {
    const [dashboard, leaveRequests, classroomActivity, departmentCheckins] = await Promise.all([
      apiGet("/secretary/secretary.php?action=dashboard"), // Use secretary dashboard endpoint for basic stats
      apiGet("/leaves/leaves.php?action=pending"), // Faculty leave requests
      apiGet("/attendance/present.php"), // Classroom check-ins
      apiGet("/attendance/present.php"), // Department time-ins (same as classroom for now)
    ]);

    // Update KPIs
    setText("secretaryDeptFaculty", dashboard.total_faculty || 0);
    setText("secretaryPresentToday", dashboard.present_today || 0);
    setText("secretaryPendingLeaves", dashboard.pending_leaves || 0);
    setText("secretaryActiveRooms", dashboard.active_rooms || 0);

    // Render faculty leave requests
    renderSecretaryLeaveRequests(leaveRequests.items);

    // Render classroom check-ins
    renderClassroomCheckins(classroomActivity.items);

    // Render department time-ins
    renderDepartmentTimeIns(departmentCheckins.items);

  } catch (e) {
    console.error("Secretary dashboard error:", e);
  }
}

function renderSecretaryLeaveRequests(items) {
  const box = document.getElementById("facultyLeaveRequestsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No pending leave requests.</span>';
    return;
  }

  // Filter for faculty and program head leave requests only
  const facultyLeaves = items.filter(item =>
    item.role_name === 'faculty' || item.role_name === 'program head'
  );

  if (facultyLeaves.length === 0) {
    box.innerHTML = '<span class="text-muted">No faculty leave requests.</span>';
    return;
  }

  facultyLeaves.forEach(item => {
    const div = document.createElement("div");
    div.className = "mb-2 p-2 border rounded";
    div.innerHTML = `
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <strong>${item.faculty_name || item.user_name}</strong> (${item.role_name})
          <div class="small text-muted">
            ${item.leave_type} - ${item.start_date} to ${item.end_date}
          </div>
          ${item.reason ? `<div class="small text-muted">Reason: ${item.reason}</div>` : ''}
        </div>
        <span class="badge bg-warning text-dark">Pending</span>
      </div>
    `;
    box.appendChild(div);
  });
}

function renderClassroomCheckins(items) {
  const box = document.getElementById("classroomCheckinsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No classroom check-ins today.</span>';
    return;
  }

  // Filter for classroom check-ins only
  const classroomCheckins = items.filter(item => item.location_type === 'room');

  if (classroomCheckins.length === 0) {
    box.innerHTML = '<span class="text-muted">No classroom check-ins today.</span>';
    return;
  }

  classroomCheckins.slice(0, 10).forEach(item => {
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    div.innerHTML = `
      <div>
        <strong>${item.name}</strong>
        <div class="small text-muted">${item.location_label}</div>
      </div>
      <span class="badge bg-success">${item.time_in_human}</span>
    `;
    box.appendChild(div);
  });
}

function renderDepartmentTimeIns(items) {
  const box = document.getElementById("departmentTimeInBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No department check-ins today.</span>';
    return;
  }

  // Filter for department check-ins only
  const deptCheckins = items.filter(item => item.location_type === 'department');

  if (deptCheckins.length === 0) {
    box.innerHTML = '<span class="text-muted">No department check-ins today.</span>';
    return;
  }

  deptCheckins.slice(0, 10).forEach(item => {
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    div.innerHTML = `
      <div>
        <strong>${item.name}</strong>
        <div class="small text-muted">Department Check-in</div>
      </div>
      <span class="badge bg-primary">${item.time_in_human}</span>
    `;
    box.appendChild(div);
  });
}

function renderQRRecords(items) {
  const box = document.getElementById("qrRecordsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No QR code records available.</span>';
    return;
  }

  items.slice(0, 10).forEach(item => {
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    div.innerHTML = `
      <div>
        <strong>${item.name}</strong>
        <div class="small text-muted">${item.type} - ${item.location}</div>
      </div>
      <span class="badge bg-info">${item.created_date || 'Active'}</span>
    `;
    box.appendChild(div);
  });
}

function checkInOut() {
  // Redirect to check-in page for secretary time clock
  window.location.href = '/crmfms/public/modules/checkin/checkin.html';
}

function requestLeave() {
  // Redirect to leave request page
  window.location.href = '/crmfms/public/modules/leaves/leaves.html';
}

async function loadProgramHeadDashboard() {
  document.getElementById("programHeadDashboard").style.display = "block";
  try {
    const [faculty, attendance, requests, classroomActivity, facultyStatus, leaveRequests] = await Promise.all([
      apiGet("/faculties/faculties.php"), // Program faculty
      apiGet("/reports/kpi/index.php"), // Attendance rate
      apiGet("/leaves/leaves.php?action=pending"), // Pending requests
      apiGet("/attendance/present.php"), // Classroom check-ins
      apiGet("/faculties/faculties.php"), // Faculty status (reuse faculty endpoint)
      apiGet("/leaves/leaves.php?action=pending"), // Program leave requests
    ]);

    // Update KPIs
    setText("programFacultyCount", faculty.items?.length || 0);
    setText("programActiveClasses", classes.items?.length || 0);
    setText("programAttendanceRate", `${attendance.class_present || 85}%`);
    setText("programPendingRequests", requests.items?.length || 0);

    // Render classroom check-ins
    renderProgramClassroomCheckins(classroomActivity.items);

    // Render faculty status
    renderProgramFacultyStatus(facultyStatus.items);

    // Render leave requests
    renderProgramLeaveRequests(leaveRequests.items);

    // Render program performance
    renderProgramPerformance(attendance);

  } catch (e) {
    console.error("Program head dashboard error:", e);
  }
}

function renderProgramClassroomCheckins(items) {
  const box = document.getElementById("programClassroomCheckinsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No classroom check-ins today.</span>';
    return;
  }

  // Filter for classroom check-ins only
  const classroomCheckins = items.filter(item => item.location_type === 'room');

  if (classroomCheckins.length === 0) {
    box.innerHTML = '<span class="text-muted">No classroom check-ins today.</span>';
    return;
  }

  classroomCheckins.slice(0, 15).forEach(item => {
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    div.innerHTML = `
      <div>
        <strong>${item.name}</strong>
        <div class="small text-muted">${item.location_label}</div>
      </div>
      <span class="badge bg-success">${item.time_in_human}</span>
    `;
    box.appendChild(div);
  });
}

function renderProgramFacultyStatus(items) {
  const box = document.getElementById("programFacultyStatusBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No faculty data available.</span>';
    return;
  }

  // Filter for faculty and program head roles only
  const programFaculty = items.filter(item =>
    item.roles && (item.roles.includes('faculty') || item.roles.includes('program head'))
  );

  if (programFaculty.length === 0) {
    box.innerHTML = '<span class="text-muted">No program faculty found.</span>';
    return;
  }

  programFaculty.slice(0, 10).forEach(faculty => {
    const statusBadge = faculty.status === 'active' ? 'bg-success' : 'bg-secondary';
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    div.innerHTML = `
      <div>
        <strong>${faculty.first_name} ${faculty.last_name}</strong>
        <div class="small text-muted">${faculty.roles}</div>
      </div>
      <span class="badge ${statusBadge}">${faculty.status}</span>
    `;
    box.appendChild(div);
  });
}

function renderProgramLeaveRequests(items) {
  const box = document.getElementById("programLeaveRequestsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No pending leave requests.</span>';
    return;
  }

  // Filter for program faculty leave requests
  const programLeaves = items.filter(item =>
    item.role_name === 'faculty' || item.role_name === 'program head'
  );

  if (programLeaves.length === 0) {
    box.innerHTML = '<span class="text-muted">No program faculty leave requests.</span>';
    return;
  }

  programLeaves.slice(0, 8).forEach(item => {
    const div = document.createElement("div");
    div.className = "mb-2 p-2 border rounded";
    div.innerHTML = `
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <strong>${item.faculty_name || item.user_name}</strong> (${item.role_name})
          <div class="small text-muted">
            ${item.leave_type} - ${item.start_date} to ${item.end_date}
          </div>
        </div>
        <span class="badge bg-warning text-dark">Pending</span>
      </div>
    `;
    box.appendChild(div);
  });
}

function renderProgramPerformance(data) {
  const box = document.getElementById("programPerformanceBox");
  box.innerHTML = `
    <div class="row text-center">
      <div class="col-6">
        <div class="h5 mb-1">${data.class_present || 0}%</div>
        <div class="small text-muted">Class Attendance</div>
      </div>
      <div class="col-6">
        <div class="h5 mb-1">${data.dept_present || 0}%</div>
        <div class="small text-muted">Department Attendance</div>
      </div>
    </div>
    <hr>
    <div class="small text-muted text-center">
      Last updated: ${new Date().toLocaleTimeString()}
    </div>
  `;
}

function programHeadCheckIn() {
  // Redirect to check-in page for program head time clock
  window.location.href = '/crmfms/public/modules/checkin/checkin.html';
}

function requestProgramLeave() {
  // Redirect to leave request page
  window.location.href = '/crmfms/public/modules/leaves/leaves.html';
}

async function loadFacultyDashboard() {
  document.getElementById("facultyDashboard").style.display = "block";
  try {
    const [attendance, leaves, status, recentCheckins, leaveBalance] = await Promise.all([
      apiGet("/attendance/attendance.php"), // Personal attendance
      apiGet("/leaves/leaves.php"), // Leave requests
      apiGet("/attendance/present.php"), // Current status
      apiGet("/attendance/attendance.php?recent=true"), // Recent check-ins
      apiGet("/leaves/leaves.php?action=balance"), // Leave balance
    ]);

    // Update KPIs
    setText("facultyTodaySchedule", 0); // Schedules functionality removed
    setText("facultyAttendanceRate", `${attendance.rate || 85}%`);
    setText("facultyLeaveBalance", leaveBalance.balance || 10);
    setText("facultyCurrentStatus", status.items?.length > 0 ? "In" : "Out");



    // Render recent check-ins
    renderFacultyRecentCheckins(recentCheckins.items);

    // Render leave requests
    renderFacultyLeaveRequests(leaves.items);

    // Render performance
    renderFacultyPerformance(attendance);

  } catch (e) {
    console.error("Faculty dashboard error:", e);
  }
}



function renderFacultyRecentCheckins(items) {
  const box = document.getElementById("facultyRecentCheckinsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No recent check-ins.</span>';
    return;
  }

  // Show last 5 check-ins
  items.slice(0, 5).forEach(item => {
    const div = document.createElement("div");
    div.className = "d-flex justify-content-between align-items-center mb-2";
    const locationType = item.location_type === 'department' ? 'Department' : 'Classroom';
    div.innerHTML = `
      <div>
        <strong>${locationType}</strong>
        <div class="small text-muted">${item.location_label || item.room_name}</div>
      </div>
      <span class="badge bg-success">${item.time_in_human}</span>
    `;
    box.appendChild(div);
  });
}

function renderFacultyLeaveRequests(items) {
  const box = document.getElementById("facultyLeaveRequestsBox");
  box.innerHTML = "";
  if (!items || items.length === 0) {
    box.innerHTML = '<span class="text-muted">No leave requests found.</span>';
    return;
  }

  // Show user's own leave requests
  const myRequests = items.filter(item => item.user_id === window.auth?.user?.user_id);

  if (myRequests.length === 0) {
    box.innerHTML = '<span class="text-muted">No personal leave requests.</span>';
    return;
  }

  myRequests.slice(0, 5).forEach(item => {
    const statusBadge = item.status === 'approved' ? 'bg-success' :
                       item.status === 'rejected' ? 'bg-danger' : 'bg-warning';
    const div = document.createElement("div");
    div.className = "mb-2 p-2 border rounded";
    div.innerHTML = `
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <strong>${item.leave_type}</strong>
          <div class="small text-muted">
            ${item.start_date} to ${item.end_date}
          </div>
          ${item.comments ? `<div class="small text-muted">Comments: ${item.comments}</div>` : ''}
        </div>
        <span class="badge ${statusBadge}">${item.status}</span>
      </div>
    `;
    box.appendChild(div);
  });
}

function renderFacultyPerformance(data) {
  const box = document.getElementById("facultyPerformanceBox");
  const attendanceRate = data.rate || 85;
  const totalClasses = data.total_classes || 0;
  const attendedClasses = data.attended_classes || 0;

  box.innerHTML = `
    <div class="row text-center">
      <div class="col-4">
        <div class="h5 mb-1">${attendanceRate}%</div>
        <div class="small text-muted">Attendance Rate</div>
      </div>
      <div class="col-4">
        <div class="h5 mb-1">${attendedClasses}/${totalClasses}</div>
        <div class="small text-muted">Classes Attended</div>
      </div>
      <div class="col-4">
        <div class="h5 mb-1">${data.leave_balance || 10}</div>
        <div class="small text-muted">Leave Balance</div>
      </div>
    </div>
    <hr>
    <div class="small text-muted text-center">
      This month • Last updated: ${new Date().toLocaleTimeString()}
    </div>
  `;
}

function facultyTimeIn() {
  // Redirect to check-in page for department time-in
  window.location.href = '/crmfms/public/modules/checkin/checkin.html';
}

function facultyClassroomCheckIn() {
  // Redirect to check-in page for classroom check-in
  window.location.href = '/crmfms/public/modules/checkin/checkin.html';
}

function requestFacultyLeave() {
  // Redirect to leave request page
  window.location.href = '/crmfms/public/modules/leaves/leaves.html';
}

async function loadStaffDashboard() {
  document.getElementById("staffDashboard").style.display = "block";
  try {
    const [present, attendance, tasks, leaves] = await Promise.all([
      apiGet("/attendance/present.php"),
      apiGet("/attendance/attendance.php"),
      apiGet("/monitoring/monitoring.php"), // Pending tasks
      apiGet("/leaves/leaves.php"),
    ]);
    const isCheckedIn = present.items?.some(p => p.user_id === window.auth?.user?.user_id);
    setText("staffCheckInStatus", isCheckedIn ? "Checked In" : "Not Checked In");
    setText("staffWorkingHours", "8h"); // Placeholder
    setText("staffPendingTasks", tasks.items?.length || 0);
    setText("staffLeaveBalance", "10"); // Placeholder
  } catch (e) {
    console.error("Staff dashboard error:", e);
  }
}

// Auto-refresh intervals
let alertRefreshInterval;
let suggestionRefreshInterval;

function startAutoRefresh() {
  // Clear any existing intervals
  stopAutoRefresh();

  // Refresh alerts every 2 minutes
  alertRefreshInterval = setInterval(async () => {
    if (document.getElementById("adminDashboard").style.display !== "none") {
      try {
        const alerts = await apiGet("/reports/alerts.php");
        renderAdminAlerts(alerts.items);
        setText("adminSystemAlerts", alerts.items?.length || 0);
      } catch (e) {
        console.error("Auto-refresh alerts error:", e);
      }
    }
  }, 120000); // 2 minutes

  // Refresh suggestions every 5 minutes
  suggestionRefreshInterval = setInterval(async () => {
    if (document.getElementById("adminDashboard").style.display !== "none") {
      try {
        const suggestions = await apiGet("/monitoring/suggestions.php");
        renderAdminSuggestions(suggestions.items);
      } catch (e) {
        console.error("Auto-refresh suggestions error:", e);
      }
    }
  }, 300000); // 5 minutes
}

function stopAutoRefresh() {
  if (alertRefreshInterval) {
    clearInterval(alertRefreshInterval);
    alertRefreshInterval = null;
  }
  if (suggestionRefreshInterval) {
    clearInterval(suggestionRefreshInterval);
    suggestionRefreshInterval = null;
  }
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  const refreshBtn = document.getElementById("refreshBtn");
  const suggestBtn = document.getElementById("suggestBtn");
  const refreshActivityBtn = document.getElementById("refreshActivityBtn");
  const adminSuggestBtn = document.getElementById("adminSuggestBtn");
  const refreshPerformanceBtn = document.getElementById("refreshPerformanceBtn");
  const refreshApprovalsBtn = document.getElementById("refreshApprovalsBtn");
  const refreshLeaveRequestsBtn = document.getElementById("refreshLeaveRequestsBtn");
  const refreshClassroomBtn = document.getElementById("refreshClassroomBtn");
  const refreshTimeInBtn = document.getElementById("refreshTimeInBtn");
  const refreshProgramClassroomBtn = document.getElementById("refreshProgramClassroomBtn");
  const refreshProgramFacultyBtn = document.getElementById("refreshProgramFacultyBtn");
  const refreshProgramLeavesBtn = document.getElementById("refreshProgramLeavesBtn");
  const refreshFacultyScheduleBtn = document.getElementById("refreshFacultyScheduleBtn");
  const refreshFacultyCheckinsBtn = document.getElementById("refreshFacultyCheckinsBtn");
  const refreshFacultyLeavesBtn = document.getElementById("refreshFacultyLeavesBtn");

  if (refreshBtn) {
    refreshBtn.addEventListener("click", loadAll);
  }
  if (suggestBtn) {
    suggestBtn.addEventListener("click", loadAll);
  }
  if (refreshActivityBtn) {
    refreshActivityBtn.addEventListener("click", () => {
      // Refresh only the admin dashboard activity
      if (document.getElementById("adminDashboard").style.display !== "none") {
        loadAdminDashboard();
      }
    });
  }
  if (adminSuggestBtn) {
    adminSuggestBtn.addEventListener("click", () => {
      // Refresh only admin suggestions
      if (document.getElementById("adminDashboard").style.display !== "none") {
        loadAdminDashboard();
      }
    });
  }
  if (refreshPerformanceBtn) {
    refreshPerformanceBtn.addEventListener("click", () => {
      // Refresh only dean performance data
      if (document.getElementById("deanDashboard").style.display !== "none") {
        loadDeanDashboard();
      }
    });
  }
  if (refreshApprovalsBtn) {
    refreshApprovalsBtn.addEventListener("click", () => {
      // Refresh only dean approvals data
      if (document.getElementById("deanDashboard").style.display !== "none") {
        loadDeanDashboard();
      }
    });
  }
  if (refreshLeaveRequestsBtn) {
    refreshLeaveRequestsBtn.addEventListener("click", () => {
      // Refresh only secretary leave requests
      if (document.getElementById("secretaryDashboard").style.display !== "none") {
        loadSecretaryDashboard();
      }
    });
  }
  if (refreshClassroomBtn) {
    refreshClassroomBtn.addEventListener("click", () => {
      // Refresh only secretary classroom data
      if (document.getElementById("secretaryDashboard").style.display !== "none") {
        loadSecretaryDashboard();
      }
    });
  }
  if (refreshTimeInBtn) {
    refreshTimeInBtn.addEventListener("click", () => {
      // Refresh only secretary time-in data
      if (document.getElementById("secretaryDashboard").style.display !== "none") {
        loadSecretaryDashboard();
      }
    });
  }
  if (refreshProgramClassroomBtn) {
    refreshProgramClassroomBtn.addEventListener("click", () => {
      // Refresh only program head classroom data
      if (document.getElementById("programHeadDashboard").style.display !== "none") {
        loadProgramHeadDashboard();
      }
    });
  }
  if (refreshProgramFacultyBtn) {
    refreshProgramFacultyBtn.addEventListener("click", () => {
      // Refresh only program head faculty data
      if (document.getElementById("programHeadDashboard").style.display !== "none") {
        loadProgramHeadDashboard();
      }
    });
  }
  if (refreshProgramLeavesBtn) {
    refreshProgramLeavesBtn.addEventListener("click", () => {
      // Refresh only program head leave data
      if (document.getElementById("programHeadDashboard").style.display !== "none") {
        loadProgramHeadDashboard();
      }
    });
  }

  if (refreshFacultyCheckinsBtn) {
    refreshFacultyCheckinsBtn.addEventListener("click", () => {
      // Refresh only faculty check-ins data
      if (document.getElementById("facultyDashboard").style.display !== "none") {
        loadFacultyDashboard();
      }
    });
  }
  if (refreshFacultyLeavesBtn) {
    refreshFacultyLeavesBtn.addEventListener("click", () => {
      // Refresh only faculty leave data
      if (document.getElementById("facultyDashboard").style.display !== "none") {
        loadFacultyDashboard();
      }
    });
  }

  // Load dashboard data
  loadAll();

  // Cleanup on page unload
  window.addEventListener('beforeunload', stopAutoRefresh);
});
