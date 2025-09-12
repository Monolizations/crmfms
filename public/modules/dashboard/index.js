// /modules/dashboard/index.js
const API_BASE = "/crmfms/api";

async function apiGet(path) {
  const r = await fetch(`${API_BASE}${path}`, { credentials: "include" });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  return r.json();
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

    // Show role-specific dashboard
    if (roles.includes("admin")) {
      await loadAdminDashboard();
    } else if (roles.includes("dean") || roles.includes("secretary")) {
      await loadDeanDashboard();
    } else if (roles.includes("program head")) {
      await loadProgramHeadDashboard();
    } else if (roles.includes("faculty")) {
      await loadFacultyDashboard();
    } else if (roles.includes("staff")) {
      await loadStaffDashboard();
    } else {
      // Default to dean dashboard for unknown roles
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
    // Load admin-specific data
    const [users, sessions, alerts, approvals] = await Promise.all([
      apiGet("/admin/admin.php?action=stats"), // Assuming this exists or create
      apiGet("/attendance/present.php"),
      apiGet("/reports/alerts.php"),
      apiGet("/leaves/leaves.php?action=pending"),
    ]);
    setText("adminTotalUsers", users.total || 0);
    setText("adminActiveSessions", sessions.items?.length || 0);
    setText("adminSystemAlerts", alerts.items?.length || 0);
    setText("adminPendingApprovals", approvals.items?.length || 0);
  } catch (e) {
    console.error("Admin dashboard error:", e);
  }
}

async function loadDeanDashboard() {
  document.getElementById("deanDashboard").style.display = "block";
  try {
    const [kpis, present, alerts, sugg] = await Promise.all([
      apiGet("/reports/kpi/index.php"),
      apiGet("/attendance/present.php"),
      apiGet("/reports/alerts.php"),
      apiGet("/monitoring/suggestions.php"),
    ]);
    setText("kpiDeptPresent", kpis.dept_present || 0);
    setText("kpiClassPresent", kpis.class_present || 0);
    setText("kpiMissed", kpis.missed_timeouts || 0);
    setText("kpiLeaves", kpis.pending_leaves || 0);
    renderPresent(present.items);
    renderAlerts(alerts.items);
    renderSuggestions(sugg.items);
  } catch (e) {
    console.error("Dean dashboard error:", e);
  }
}

async function loadProgramHeadDashboard() {
  document.getElementById("programHeadDashboard").style.display = "block";
  try {
    // Load program-specific data
    const [faculty, classes, attendance, requests] = await Promise.all([
      apiGet("/faculties/faculties.php"), // Program faculty
      apiGet("/schedules/schedules.php"), // Active classes
      apiGet("/reports/kpi/index.php"), // Attendance rate
      apiGet("/leaves/leaves.php?action=pending"), // Pending requests
    ]);
    setText("programFacultyCount", faculty.items?.length || 0);
    setText("programActiveClasses", classes.items?.length || 0);
    setText("programAttendanceRate", `${attendance.class_present || 0}%`);
    setText("programPendingRequests", requests.items?.length || 0);
  } catch (e) {
    console.error("Program head dashboard error:", e);
  }
}

async function loadFacultyDashboard() {
  document.getElementById("facultyDashboard").style.display = "block";
  try {
    const [schedule, attendance, leaves, status] = await Promise.all([
      apiGet("/schedules/schedules.php"), // Today's schedule
      apiGet("/attendance/attendance.php"), // Personal attendance
      apiGet("/leaves/leaves.php"), // Pending leaves
      apiGet("/attendance/present.php"), // Current status
    ]);
    const todaySchedules = schedule.items?.filter(s => s.day_of_week === new Date().toLocaleDateString('en', {weekday: 'short'})) || [];
    setText("facultyTodaySchedule", todaySchedules.length);
    setText("facultyAttendanceRate", "85%"); // Placeholder
    setText("facultyPendingLeaves", leaves.items?.length || 0);
    setText("facultyCurrentStatus", status.items?.length > 0 ? "In" : "Out");

    // Render schedule
    const scheduleBox = document.getElementById("facultyScheduleBox");
    scheduleBox.innerHTML = "";
    if (todaySchedules.length === 0) {
      scheduleBox.innerHTML = '<span class="text-muted">No classes today.</span>';
    } else {
      todaySchedules.forEach(s => {
        const div = document.createElement("div");
        div.className = "mb-2";
        div.innerHTML = `<strong>${s.room_name}</strong> - ${s.start_time} to ${s.end_time}`;
        scheduleBox.appendChild(div);
      });
    }
  } catch (e) {
    console.error("Faculty dashboard error:", e);
  }
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

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  const refreshBtn = document.getElementById("refreshBtn");
  const suggestBtn = document.getElementById("suggestBtn");
  
  if (refreshBtn) {
    refreshBtn.addEventListener("click", loadAll);
  }
  if (suggestBtn) {
    suggestBtn.addEventListener("click", loadAll);
  }
  
  // Load dashboard data
  loadAll();
});
