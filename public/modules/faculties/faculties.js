// /modules/faculties/faculties.js
const API_URL = "/crmfms/api/faculties/faculties.php";
const ROLES_API_URL = "/crmfms/api/roles/roles.php";
const table = document.getElementById("facultyTable");

let addUserModal, editUserModal;
let addUserForm, addRolesCheckboxes, editUserForm, editRolesCheckboxes;

let allRoles = []; // To store all available roles with their IDs
let currentPage = 1;
let totalPages = 1;
let currentDean = null;
let deanCandidatesPage = 1;
let deanCandidatesTotalPages = 1;
let deanSearchQuery = '';

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
         loadUsers(currentPage);
         loadDeanInfo();
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
        loadUsers(currentPage);
        loadDeanInfo();
      }
    } catch (error) {
      console.error("Failed to update user:", error);
      alert("An error occurred while updating the user.");
    }
  });

  // Add event listeners for dean search
  const deanSearchBtn = document.getElementById('deanSearchBtn');
  const deanClearSearchBtn = document.getElementById('deanClearSearchBtn');
  const deanSearchInput = document.getElementById('deanSearchInput');

  if (deanSearchBtn) {
    deanSearchBtn.addEventListener('click', performDeanSearch);
  }

  if (deanClearSearchBtn) {
    deanClearSearchBtn.addEventListener('click', clearDeanSearch);
  }

  if (deanSearchInput) {
    deanSearchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        performDeanSearch();
      }
    });
  }

  // Load dean candidates when modal is shown
  const changeDeanModal = document.getElementById('changeDeanModal');
  if (changeDeanModal) {
    changeDeanModal.addEventListener('show.bs.modal', () => {
      deanCandidatesPage = 1;
      deanSearchQuery = '';
      if (deanSearchInput) deanSearchInput.value = '';
      loadDeanCandidates(1, '');
    });
  }

  // Initial load
  fetchRoles();
  loadUsers();
  loadDeanInfo();
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

async function loadUsers(page = 1) {
  try {
    const res = await fetch(`${API_URL}?page=${page}`, { credentials: "include" });
    const data = await res.json();

    currentPage = data.page || 1;
    totalPages = data.total_pages || 1;

    table.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      table.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No users found</td></tr>`;
      updatePaginationControls();
      return;
    }

    const currentUser = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user'));
    const canEdit = currentUser && (currentUser.roles.includes('admin') || currentUser.roles.includes('dean'));

    data.items.forEach(user => {
      const row = document.createElement("tr");
      const isDean = user.roles && user.roles.includes('dean');
      const roles = user.roles ? user.roles.split(',').map(role => {
        if (role === 'dean') {
          return `<span class="badge bg-warning text-dark me-1"><i class="fas fa-crown me-1"></i>${role}</span>`;
        }
        return `<span class="badge bg-info me-1">${role}</span>`;
      }).join('') : '';

      // Add special styling for dean row
      if (isDean) {
        row.className = 'table-warning';
      }

      row.innerHTML = `
        <td>${user.user_id}</td>
        <td>${user.employee_id}</td>
        <td>
          ${user.first_name} ${user.last_name}
          ${isDean ? '<i class="fas fa-crown text-warning ms-2" title="Current Dean"></i>' : ''}
        </td>
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

    updatePaginationControls();
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
      loadUsers(currentPage);
      loadDeanInfo();
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

async function loadDeanInfo() {
  try {
    const currentUser = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user'));
    const isAdmin = currentUser && currentUser.roles.includes('admin');

    if (!isAdmin) {
      document.getElementById('deanManagementSection').style.display = 'none';
      return;
    }

    document.getElementById('deanManagementSection').style.display = 'block';

    // Load current dean and candidates
    const res = await fetch(`${API_URL}?action=current_dean&page=1&search=`, { credentials: "include" });
    const data = await res.json();

    currentDean = data.dean;
    const deanDisplay = document.getElementById('currentDeanDisplay');

    if (data.dean) {
      deanDisplay.textContent = `${data.dean.first_name} ${data.dean.last_name} (${data.dean.employee_id})`;
      deanDisplay.className = 'ms-2 text-success fw-bold';
    } else {
      deanDisplay.textContent = 'No dean assigned';
      deanDisplay.className = 'ms-2 text-warning';
    }

    // Load dean candidates for the modal (will be called when modal opens)
  } catch (error) {
    console.error("Failed to load dean info:", error);
  }
}

async function loadDeanCandidates(page = 1, search = '') {
  try {
    const url = `${API_URL}?action=current_dean&page=${page}&search=${encodeURIComponent(search)}`;
    const res = await fetch(url, { credentials: "include" });
    const data = await res.json();

    // Update pagination state
    deanCandidatesPage = data.page || 1;
    deanCandidatesTotalPages = data.total_pages || 1;
    deanSearchQuery = data.search || '';

    const candidatesTable = document.getElementById('deanCandidatesTable');
    candidatesTable.innerHTML = '';

    if (!data.candidates || data.candidates.length === 0) {
      candidatesTable.innerHTML = `<tr><td colspan="5" class="text-center text-muted">
        ${search ? 'No users found matching your search.' : 'No users available'}
      </td></tr>`;
      updateDeanPaginationControls();
      return;
    }

    data.candidates.forEach(user => {
      const isCurrentDean = currentDean && currentDean.user_id == user.user_id;
      const roles = user.roles ? user.roles.split(',').map(role => `<span class="badge bg-info me-1">${role}</span>`).join('') : '';

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${user.user_id}</td>
        <td>
          ${user.first_name} ${user.last_name}
          ${isCurrentDean ? '<span class="badge bg-success ms-2">Current Dean</span>' : ''}
        </td>
        <td>${user.employee_id}</td>
        <td>${roles}</td>
        <td>
          ${isCurrentDean ?
            '<button class="btn btn-sm btn-success" disabled><i class="fas fa-check"></i> Current</button>' :
            `<button class="btn btn-sm btn-warning" onclick="setAsDean(${user.user_id}, '${user.first_name} ${user.last_name}')">
              <i class="fas fa-crown"></i> Make Dean
             </button>`
          }
        </td>
      `;
      candidatesTable.appendChild(row);
    });

    updateDeanPaginationControls();
  } catch (error) {
    console.error("Failed to load dean candidates:", error);
  }
}

async function setAsDean(userId, userName) {
  if (!confirm(`Are you sure you want to make ${userName} the new dean? This will remove the dean role from the current dean.`)) {
    return;
  }

  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "set_dean", user_id: userId })
    });
    const data = await res.json();

    if (data.success) {
      alert(`Successfully changed dean to ${userName}`);
      bootstrap.Modal.getInstance(document.getElementById('changeDeanModal')).hide();
      loadDeanInfo();
      loadUsers(currentPage);
    } else {
      alert('Failed to change dean: ' + data.message);
    }
  } catch (error) {
    console.error("Failed to set dean:", error);
    alert("An error occurred while changing the dean.");
  }
}

function updatePaginationControls() {
  const paginationContainer = document.getElementById('paginationControls');
  if (!paginationContainer) return;

  let paginationHTML = '';

  if (totalPages > 1) {
    paginationHTML = `
      <nav aria-label="User pagination">
        <ul class="pagination justify-content-center">
          <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1})" aria-label="Previous">
              <span aria-hidden="true">&laquo;</span>
            </a>
          </li>`;

    // Calculate page range to show (max 5 pages)
    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

    // Adjust start page if we're near the end
    if (endPage - startPage + 1 < maxPagesToShow) {
      startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
      paginationHTML += `
        <li class="page-item ${i === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
        </li>`;
    }

    paginationHTML += `
          <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1})" aria-label="Next">
              <span aria-hidden="true">&raquo;</span>
            </a>
          </li>
        </ul>
      </nav>
      <div class="text-center text-muted mt-2">
        Page ${currentPage} of ${totalPages} (${totalPages * 20} total entries)
      </div>`;
  }

  paginationContainer.innerHTML = paginationHTML;
}

function updateDeanPaginationControls() {
  const paginationContainer = document.getElementById('deanPaginationControls');
  if (!paginationContainer) return;

  let paginationHTML = '';

  if (deanCandidatesTotalPages > 1) {
    paginationHTML = `
      <nav aria-label="Dean candidates pagination">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item ${deanCandidatesPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changeDeanPage(${deanCandidatesPage - 1})" aria-label="Previous">
              <span aria-hidden="true">&laquo;</span>
            </a>
          </li>`;

    // Calculate page range to show (max 3 pages for modal)
    const maxPagesToShow = 3;
    let startPage = Math.max(1, deanCandidatesPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(deanCandidatesTotalPages, startPage + maxPagesToShow - 1);

    // Adjust start page if we're near the end
    if (endPage - startPage + 1 < maxPagesToShow) {
      startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
      paginationHTML += `
        <li class="page-item ${i === deanCandidatesPage ? 'active' : ''}">
          <a class="page-link" href="#" onclick="changeDeanPage(${i})">${i}</a>
        </li>`;
    }

    paginationHTML += `
          <li class="page-item ${deanCandidatesPage === deanCandidatesTotalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changeDeanPage(${deanCandidatesPage + 1})" aria-label="Next">
              <span aria-hidden="true">&raquo;</span>
            </a>
          </li>
        </ul>
      </nav>
      <div class="text-center text-muted mt-2 small">
        Page ${deanCandidatesPage} of ${deanCandidatesTotalPages}
      </div>`;
  }

  paginationContainer.innerHTML = paginationHTML;
}

function changePage(page) {
  if (page >= 1 && page <= totalPages) {
    loadUsers(page);
  }
}

function changeDeanPage(page) {
  if (page >= 1 && page <= deanCandidatesTotalPages) {
    loadDeanCandidates(page, deanSearchQuery);
  }
}

function performDeanSearch() {
  const searchInput = document.getElementById('deanSearchInput');
  deanSearchQuery = searchInput.value.trim();
  deanCandidatesPage = 1; // Reset to first page when searching
  loadDeanCandidates(1, deanSearchQuery);
}

function clearDeanSearch() {
  document.getElementById('deanSearchInput').value = '';
  deanSearchQuery = '';
  deanCandidatesPage = 1;
  loadDeanCandidates(1, '');
}

// Initial load
fetchRoles();
loadUsers();
