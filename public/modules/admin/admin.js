// /modules/admin/admin.js
const ADMIN_API = "/crmfms/api/admin/admin.php";
const USERS_API_URL = "/crmfms/api/admin/admin.php"; // Use admin API for user management
const ROLES_API_URL = "/crmfms/api/roles/roles.php";

const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
const editUserForm = document.getElementById("editUserForm");
const editRolesCheckboxes = document.getElementById("editRolesCheckboxes");

let allRoles = []; // To store all available roles with their IDs
let currentUserPage = 1;
let currentAuditPage = 1;

async function fetchRoles() {
  try {
    const res = await fetch(ROLES_API_URL, { credentials: "include" });
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    const data = await res.json();
    if (data.success && data.roles) {
      allRoles = data.roles; // Store all roles
      
      // Populate Edit User roles checkboxes
      editRolesCheckboxes.innerHTML = '';
      allRoles.forEach(role => {
        const div = document.createElement("div");
        div.className = "form-check form-check-inline";
        div.innerHTML = `
          <input class="form-check-input" type="checkbox" id="editRole_${role.role_id}" value="${role.role_id}">
          <label class="form-check-label" for="editRole_${role.role_id}">${role.role_name}</label>
        `;
        editRolesCheckboxes.appendChild(div);
      });
    }
  } catch (error) {
    console.error("Failed to load roles:", error);
  }
}

async function loadUsers(page = 1) {
  currentUserPage = page;
  try {
    const res = await fetch(USERS_API_URL + `?action=users&page=${page}`, { credentials: "include" });
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    const data = await res.json();
    if (data.success === false) throw new Error(data.message);
    const table = document.getElementById("userTable");
    table.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      table.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No users found</td></tr>`;
      document.getElementById("userPagination").innerHTML = "";
      return;
    }

    data.items.forEach(u => {
      const row = document.createElement("tr");
      const roles = u.roles ? u.roles.split(',').map(role => `<span class="badge bg-info me-1">${role}</span>`).join('') : '';
      row.innerHTML = `
        <td>${u.user_id}</td>
        <td>${u.employee_id}</td>
        <td>${u.first_name} ${u.last_name}</td>
        <td>${u.email}</td>
        <td>${roles}</td>
        <td><span class="badge ${u.status === 'active' ? 'bg-success' : 'bg-secondary'}">${u.status}</span></td>
        <td>
          <button class="btn btn-sm btn-warning" onclick="toggleUserStatus(${u.user_id})">Toggle Active</button>
          <button class="btn btn-sm btn-primary ms-1" onclick="editUser(${u.user_id}, '${u.employee_id}', '${u.first_name}', '${u.last_name}', '${u.email}', '${u.roles}')">Edit</button>
        </td>
      `;
      table.appendChild(row);
    });

    renderPagination('userPagination', data.total, data.per_page, currentUserPage, loadUsers);
  } catch (error) {
    console.error('Error loading users:', error);
    const table = document.getElementById("userTable");
    table.innerHTML = `<tr><td colspan="7" class="text-center text-muted">Error loading users: ${error.message}</td></tr>`;
    document.getElementById("userPagination").innerHTML = "";
  }
}

async function loadSettings() {
  try {
    const res = await fetch(ADMIN_API + "?action=settings", { credentials: "include" });
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    const data = await res.json();
    if (data.success && data.settings) {
      document.getElementById("gracePeriod").value = data.settings.grace_period || 5;
      document.getElementById("overtimeThreshold").value = data.settings.overtime_threshold || 8;
    }
  } catch (error) {
    console.error("Failed to load settings:", error);
  }
}

async function loadAuditFilters() {
  try {
    // Load users
    const userRes = await fetch(ADMIN_API + "?action=audit_users", { credentials: "include" });
    if (!userRes.ok) throw new Error(`HTTP ${userRes.status}: ${userRes.statusText}`);
    const userData = await userRes.json();
    const userSelect = document.getElementById("auditUserFilter");
    userData.users.forEach(u => {
      const option = document.createElement("option");
      option.value = u.user_id;
      option.textContent = u.name;
      userSelect.appendChild(option);
    });

    // Load actions
    const actionRes = await fetch(ADMIN_API + "?action=audit_actions", { credentials: "include" });
    if (!actionRes.ok) throw new Error(`HTTP ${actionRes.status}: ${actionRes.statusText}`);
    const actionData = await actionRes.json();
    const actionSelect = document.getElementById("auditActionFilter");
    actionData.actions.forEach(act => {
      const option = document.createElement("option");
      option.value = act;
      option.textContent = act;
      actionSelect.appendChild(option);
    });
  } catch (error) {
    console.error('Error loading audit filters:', error);
  }
}

function formatAuditDetails(details, action, userName) {
  if (!details) return '-';

  // Special formatting for check-in/check-out actions
  if (action.includes('CHECK_IN') || action.includes('CHECK_OUT')) {
    try {
      const parsed = JSON.parse(details);
      if (parsed && parsed.location) {
        // Extract last name from user name
        const nameParts = userName.split(' ');
        const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : userName;

        // Get location
        const location = parsed.location;

        // Determine action type
        const isCheckIn = action.includes('CHECK_IN');
        const actionText = isCheckIn ? 'checked in' : 'checked out';

        // For classroom actions, format with room code
        if (action === 'CLASSROOM_CHECK_IN') {
          const roomCode = parsed.room_code || '';
          return `${lastName} ${actionText} in classroom ${location}${roomCode ? ' (' + roomCode + ')' : ''}`;
        } else {
          // For department/manual actions, use simpler format
          return `${lastName} ${actionText} at ${location}`;
        }
      }
    } catch (e) {
      // If JSON parsing fails, continue with normal formatting
    }
  }

  try {
    const parsed = JSON.parse(details);
    if (typeof parsed === 'object' && parsed !== null) {
      const formatted = [];
      for (const [key, value] of Object.entries(parsed)) {
        const displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        formatted.push(`${displayKey}: ${value}`);
      }
      return formatted.join('<br>');
    }
  } catch (e) {
    // Not JSON, return as is
  }

  return details;
}

function renderPagination(containerId, total, perPage, currentPage, loadFunction) {
  const container = document.getElementById(containerId);
  const totalPages = Math.ceil(total / perPage);

  if (totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  let html = '<nav><ul class="pagination pagination-sm justify-content-center">';

  // Previous button
  if (currentPage > 1) {
    html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); ${loadFunction.name}(${currentPage - 1})">Previous</a></li>`;
  } else {
    html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
  }

  // Page numbers
  const startPage = Math.max(1, currentPage - 2);
  const endPage = Math.min(totalPages, currentPage + 2);

  if (startPage > 1) {
    html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); ${loadFunction.name}(1)">1</a></li>`;
    if (startPage > 2) {
      html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    if (i === currentPage) {
      html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
    } else {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); ${loadFunction.name}(${i})">${i}</a></li>`;
    }
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); ${loadFunction.name}(${totalPages})">${totalPages}</a></li>`;
  }

  // Next button
  if (currentPage < totalPages) {
    html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); ${loadFunction.name}(${currentPage + 1})">Next</a></li>`;
  } else {
    html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
  }

  html += '</ul></nav>';
  container.innerHTML = html;
}

async function loadAudits(page = 1) {
  currentAuditPage = page;
  try {
    const params = new URLSearchParams();
    params.append('action', 'audit');
    params.append('page', page);

    const startDate = document.getElementById("auditStartDate").value;
    const endDate = document.getElementById("auditEndDate").value;
    const userId = document.getElementById("auditUserFilter").value;
    const actionFilter = document.getElementById("auditActionFilter").value;

    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (userId) params.append('user_id', userId);
    if (actionFilter) params.append('action_filter', actionFilter);

    const res = await fetch(ADMIN_API + "?" + params.toString(), { credentials: "include" });
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    const data = await res.json();
    if (data.success === false) throw new Error(data.message);
    const table = document.getElementById("auditTable");
    table.innerHTML = "";
    data.items.forEach(a => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${a.audit_id}</td>
        <td>${a.user_name}</td>
        <td>${a.action}</td>
        <td>${formatAuditDetails(a.details, a.action, a.user_name)}</td>
        <td>${a.ip_address || '-'}</td>
        <td>${new Date(a.created_at).toLocaleString()}</td>
      `;
      table.appendChild(row);
    });

    renderPagination('auditPagination', data.total, data.per_page, currentAuditPage, loadAudits);
  } catch (error) {
    console.error('Error loading audits:', error);
    const table = document.getElementById("auditTable");
    table.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Error loading audits: ${error.message}</td></tr>`;
    document.getElementById("auditPagination").innerHTML = "";
  }
}

async function loadRecentActivity() {
  try {
    const res = await fetch(ADMIN_API + "?action=recent_activity", { credentials: "include" });
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    const data = await res.json();

    const activityList = document.getElementById("recentActivityList");
    const emptyState = document.getElementById("recentActivityEmpty");

    activityList.innerHTML = "";

    if (!data.items || data.items.length === 0) {
      emptyState.classList.remove('d-none');
      return;
    }

    emptyState.classList.add('d-none');

    data.items.forEach(activity => {
      const activityItem = document.createElement("div");
      activityItem.className = "list-group-item px-0 py-3";

      // Format the activity details
      let activityText = formatActivityText(activity);

      activityItem.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <div class="fw-medium text-dark mb-1">${activityText}</div>
            <small class="text-muted">${activity.time_ago}</small>
          </div>
          <div class="ms-3">
            <span class="badge bg-primary">${activity.action.replace(/_/g, ' ')}</span>
          </div>
        </div>
      `;

      activityList.appendChild(activityItem);
    });
  } catch (error) {
    console.error('Error loading recent activity:', error);
    const activityList = document.getElementById("recentActivityList");
    const emptyState = document.getElementById("recentActivityEmpty");
    activityList.innerHTML = `<div class="list-group-item text-center text-muted">Error loading activity: ${error.message}</div>`;
    emptyState.classList.add('d-none');
  }
}

function formatActivityText(activity) {
  // Use the same formatting logic as audit details for consistency
  if (activity.action.includes('CHECK_IN') || activity.action.includes('CHECK_OUT')) {
    try {
      const parsed = JSON.parse(activity.details);
      if (parsed && parsed.location) {
        // Extract last name from user name (activity.details contains user_name as fallback)
        const userName = activity.details || '';
        const nameParts = userName.split(' ');
        const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : userName;

        // Get location
        const location = parsed.location;

        // Determine action type
        const isCheckIn = activity.action.includes('CHECK_IN');
        const actionText = isCheckIn ? 'checked in' : 'checked out';

        // For classroom actions, format with room code
        if (activity.action === 'CLASSROOM_CHECK_IN') {
          const roomCode = parsed.room_code || '';
          return `${lastName} ${actionText} in classroom ${location}${roomCode ? ' (' + roomCode + ')' : ''}`;
        } else {
          // For department/manual actions, use simpler format
          return `${lastName} ${actionText} at ${location}`;
        }
      }
    } catch (e) {
      // If JSON parsing fails, use the details as is
    }
  }

  // Default formatting
  return activity.details || activity.action.replace(/_/g, ' ');
}

document.getElementById("settingsForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "saveSettings",
    grace_period: document.getElementById("gracePeriod").value,
    overtime_threshold: document.getElementById("overtimeThreshold").value
  };
  const res = await fetch(ADMIN_API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(body)
  });
  const data = await res.json();
  alert(data.message || (data.success ? "Saved" : "Error"));
});

async function toggleUserStatus(id) {
  const res = await fetch(ADMIN_API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify({ action: "toggle", user_id: id })
  });
  const data = await res.json();
  alert(data.message);
  loadUsers(currentUserPage);
}

function editUser(userId, employeeId, firstName, lastName, email, userRolesString) {
  document.getElementById("editUserId").value = userId;
  document.getElementById("editEmployeeId").value = employeeId;
  document.getElementById("editFirstName").value = firstName;
  document.getElementById("editLastName").value = lastName;
  document.getElementById("editEmail").value = email;
  document.getElementById("editPassword").value = ''; // Clear password field

  // Deselect all checkboxes first
  editRolesCheckboxes.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.checked = false;
  });

  // Select current roles in the edit modal
  const userRoles = userRolesString ? userRolesString.split(',') : [];
  userRoles.forEach(roleName => {
    const role = allRoles.find(r => r.role_name === roleName);
    if (role) {
      const checkbox = document.getElementById(`editRole_${role.role_id}`);
      if (checkbox) {
        checkbox.checked = true;
      }
    }
  });

  editUserModal.show();
}

editUserForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const selectedRoles = Array.from(editRolesCheckboxes.querySelectorAll('input[type="checkbox"]:checked')).map(checkbox => checkbox.value);
  const body = {
    action: "update",
    user_id: document.getElementById("editUserId").value,
    employee_id: document.getElementById("editEmployeeId").value,
    first_name: document.getElementById("editFirstName").value,
    last_name: document.getElementById("editLastName").value,
    email: document.getElementById("editEmail").value,
    password: document.getElementById("editPassword").value || null, // Send null if empty
    roles: selectedRoles
  };

  try {
    const res = await fetch(ADMIN_API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(body)
    });
    const data = await res.json();
    alert(data.message || (data.success ? "User updated successfully" : "Error updating user"));
    if (data.success) {
      editUserModal.hide();
      loadUsers(currentUserPage);
    }
  } catch (error) {
    console.error("Failed to update user:", error);
    alert("An error occurred while updating the user.");
  }
});

// Auto-refresh for audit logs
let auditRefreshInterval;

document.getElementById("autoRefreshAudit").addEventListener("change", (e) => {
  if (e.target.checked) {
    auditRefreshInterval = setInterval(loadAudits, 30000); // 30 seconds
  } else {
    clearInterval(auditRefreshInterval);
  }
});

// Initial load
fetchRoles();
loadUsers(1);
loadSettings();
loadAudits(1);
loadAuditFilters();
loadRecentActivity();

// Recent activity refresh
document.getElementById("refreshActivityBtn").addEventListener("click", loadRecentActivity);

// Audit filters and buttons
document.getElementById("auditStartDate").addEventListener("change", () => loadAudits(1));
document.getElementById("auditEndDate").addEventListener("change", () => loadAudits(1));
document.getElementById("auditUserFilter").addEventListener("change", () => loadAudits(1));
document.getElementById("auditActionFilter").addEventListener("change", () => loadAudits(1));

document.getElementById("refreshAuditBtn").addEventListener("click", () => loadAudits(currentAuditPage));

document.getElementById("exportAuditBtn").addEventListener("click", async () => {
  const params = new URLSearchParams();
  params.append('action', 'audit');
  // Remove page limit for export
  params.append('limit', 'all');

  const startDate = document.getElementById("auditStartDate").value;
  const endDate = document.getElementById("auditEndDate").value;
  const userId = document.getElementById("auditUserFilter").value;
  const actionFilter = document.getElementById("auditActionFilter").value;

  if (startDate) params.append('start_date', startDate);
  if (endDate) params.append('end_date', endDate);
  if (userId) params.append('user_id', userId);
  if (actionFilter) params.append('action_filter', actionFilter);

  const res = await fetch(ADMIN_API + "?" + params.toString(), { credentials: "include" });
  const data = await res.json();

  // Create CSV
  const headers = ['ID', 'User', 'Action', 'Details', 'IP Address', 'Time'];
  const rows = data.items.map(a => [
    a.audit_id,
    a.user_name,
    a.action,
    formatAuditDetails(a.details, a.action, a.user_name) || '',
    a.ip_address || '',
    new Date(a.created_at).toLocaleString()
  ]);
  const csvContent = [headers, ...rows].map(row => row.map(field => `"${field.replace(/"/g, '""')}"`).join(',')).join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'audit_trail.csv';
  a.click();
  window.URL.revokeObjectURL(url);
});