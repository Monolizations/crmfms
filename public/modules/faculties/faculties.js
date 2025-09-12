// /modules/faculties/faculties.js
const API_URL = "/crmfms/api/faculties/faculties.php";
const ROLES_API_URL = "/crmfms/api/roles/roles.php";
const table = document.getElementById("facultyTable");

let addUserModal, editUserModal;
let addUserForm, addRolesCheckboxes, editUserForm, editRolesCheckboxes;

let allRoles = []; // To store all available roles with their IDs

document.addEventListener('DOMContentLoaded', () => {
  // Initialize modals and form elements after DOM is loaded
  addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
  addUserForm = document.getElementById("addUserForm");
  addRolesCheckboxes = document.getElementById("addRolesCheckboxes");

  editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
  editUserForm = document.getElementById("editUserForm");
  editRolesCheckboxes = document.getElementById("editRolesCheckboxes");

  // Event delegation for dynamically added buttons
  table.addEventListener('click', (e) => {
    const target = e.target;
    if (target.classList.contains('toggle-user-status-btn')) {
      const userId = target.dataset.userId;
      toggleUserStatus(userId);
    } else if (target.classList.contains('edit-user-btn')) {
      const userId = target.dataset.userId;
      const employeeId = target.dataset.employeeId;
      const firstName = target.dataset.firstName;
      const lastName = target.dataset.lastName;
      const email = target.dataset.email;
      const roles = target.dataset.roles;
      editUser(userId, employeeId, firstName, lastName, email, roles);
    }
  });

  addUserForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const selectedRoles = Array.from(addRolesCheckboxes.querySelectorAll('input[type="checkbox"]:checked')).map(checkbox => checkbox.value);
    const body = {
      action: "create",
      employee_id: document.getElementById("addEmployeeId").value,
      first_name: document.getElementById("addFirstName").value,
      last_name: document.getElementById("addLastName").value,
      email: document.getElementById("addEmail").value,
      password: document.getElementById("addPassword").value,
      roles: selectedRoles
    };
    try {
      const res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(body)
      });
      const data = await res.json();
      alert(data.message || (data.success ? "User created successfully" : "Error creating user"));
      if (data.success) {
        addUserForm.reset();
        addUserModal.hide();
        loadUsers();
      }
    } catch (error) {
      console.error("Failed to create user:", error);
      alert("An error occurred while creating the user.");
    }
  });

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
      const res = await fetch(API_URL, {
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
});

async function fetchRoles() {
  try {
    const res = await fetch(ROLES_API_URL, { credentials: "include" });
    const data = await res.json();
    if (data.success && data.roles) {
      allRoles = data.roles; // Store all roles
      
      const currentUser = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user'));
      const isSecretary = currentUser && currentUser.roles.includes('secretary');

      // Populate Add User roles checkboxes
      addRolesCheckboxes.innerHTML = '';
      allRoles.forEach(role => {
        // If secretary, only show 'program head' and 'faculty'
        if (isSecretary && !['program head', 'faculty'].includes(role.role_name)) {
          return; 
        }
        const div = document.createElement("div");
        div.className = "form-check form-check-inline";
        div.innerHTML = `
          <input class="form-check-input" type="checkbox" id="addRole_${role.role_id}" value="${role.role_id}">
          <label class="form-check-label" for="addRole_${role.role_id}">${role.role_name}</label>
        `;
        addRolesCheckboxes.appendChild(div);
      });

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
  try {
    const res = await fetch(API_URL, { credentials: "include" });
    const data = await res.json();
    
    table.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      table.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No users found</td></tr>`;
      return;
    }

    const currentUser = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user'));
    const canEdit = currentUser && (currentUser.roles.includes('admin') || currentUser.roles.includes('dean'));

    data.items.forEach(user => {
      const row = document.createElement("tr");
      const roles = user.roles ? user.roles.split(',').map(role => `<span class="badge bg-info me-1">${role}</span>`).join('') : '';
      row.innerHTML = `
        <td>${user.user_id}</td>
        <td>${user.employee_id}</td>
        <td>${user.first_name} ${user.last_name}</td>
        <td>${user.email}</td>
        <td><span class="badge ${user.status === 'active' ? 'bg-success' : 'bg-secondary'}">${user.status}</span></td>
        <td>${roles}</td>
        <td>
          <button class="btn btn-sm btn-warning toggle-user-status-btn" data-user-id="${user.user_id}">Toggle Active</button>
          ${canEdit ? `<button class="btn btn-sm btn-primary edit-user-btn ms-1" 
            data-user-id="${user.user_id}" 
            data-employee-id="${user.employee_id}" 
            data-first-name="${user.first_name}" 
            data-last-name="${user.last_name}" 
            data-email="${user.email}" 
            data-roles="${user.roles}"
          >Edit</button>` : ''}
        </td>
      `;
      table.appendChild(row);
    });
  } catch (error) {
    console.error("Failed to load users:", error);
  }
}

async function toggleUserStatus(id) {
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "toggle", user_id: id })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "User status updated" : "Error updating status"));
    if (data.success) {
      loadUsers();
    }
  } catch (error) {
    console.error("Failed to toggle user status:", error);
    alert("An error occurred while updating the user status.");
  }
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


// Initial load
fetchRoles();
loadUsers();
