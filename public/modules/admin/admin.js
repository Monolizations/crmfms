// /modules/admin/admin.js
const ADMIN_API = "/crmfms/api/admin/admin.php";
const USERS_API_URL = "/crmfms/api/admin/admin.php"; // Use admin API for user management
const ROLES_API_URL = "/crmfms/api/roles/roles.php";

const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
const editUserForm = document.getElementById("editUserForm");
const editRolesCheckboxes = document.getElementById("editRolesCheckboxes");

let allRoles = []; // To store all available roles with their IDs

async function fetchRoles() {
  try {
    const res = await fetch(ROLES_API_URL, { credentials: "include" });
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

async function loadUsers() {
  const res = await fetch(USERS_API_URL + "?action=users", { credentials: "include" }); // Fetch from admin API
  const data = await res.json();
  const table = document.getElementById("userTable");
  table.innerHTML = "";
  if (!data.items || data.items.length === 0) {
    table.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No users found</td></tr>`;
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
}

async function loadSettings() {
  const res = await fetch(ADMIN_API + "?action=settings", { credentials: "include" });
  const data = await res.json();
  document.getElementById("gracePeriod").value = data.grace_period;
  document.getElementById("overtimeThreshold").value = data.overtime_threshold;
}

async function loadAudits() {
  const res = await fetch(ADMIN_API + "?action=audit", { credentials: "include" });
  const data = await res.json();
  const table = document.getElementById("auditTable");
  table.innerHTML = "";
  data.items.forEach(a => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${a.audit_id}</td>
      <td>${a.user_name}</td>
      <td>${a.action}</td>
      <td>${a.description || '-'}</td>
      <td>${a.created_at}</td>
    `;
    table.appendChild(row);
  });
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
  loadUsers();
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
      loadUsers();
    }
  } catch (error) {
    console.error("Failed to update user:", error);
    alert("An error occurred while updating the user.");
  }
});

// Initial load
fetchRoles();
loadUsers();
loadSettings();
loadAudits();