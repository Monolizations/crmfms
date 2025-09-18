// /modules/leaves/leaves.js
const API = "/crmfms/api/leaves/leaves.php";
const table = document.getElementById("leaveTable");

// Archive controls
let showArchived = false;

// Modal elements
const reviewModal = new bootstrap.Modal(document.getElementById('reviewLeaveModal'));
const reviewForm = document.getElementById('reviewLeaveForm');
const reviewLeaveId = document.getElementById('reviewLeaveId');
const reviewAction = document.getElementById('reviewAction');
const reviewReason = document.getElementById('reviewReason');
const reasonLabel = document.getElementById('reasonLabel');
const decisionText = document.getElementById('decisionText');
const confirmReviewBtn = document.getElementById('confirmReviewBtn');
const confirmBtnText = document.getElementById('confirmBtnText');

async function loadLeaves() {
  console.log('Loading leaves, showArchived:', showArchived);
  try {
    const url = showArchived ? `${API}?archived=true` : API;
    console.log('Fetching from URL:', url);
    const res = await fetch(url, { credentials: "include" });

    // Update header based on view
    const requestsHeader = document.querySelector('#leaveListCard .card-body h6');
    if (requestsHeader) {
      const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');
      const userName = user.name || 'Your';
      const viewType = showArchived ? 'Archived' : 'Active';
      requestsHeader.textContent = `${userName}'s ${viewType} Leave Requests`;
    }

    // Check if response is ok
    if (!res.ok) {
      console.error('Response not ok:', res.status, res.statusText);
      const text = await res.text();
      console.error('Response text:', text);
      table.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading leave requests: ${res.status}</td></tr>`;
      return;
    }

    // Check if response is JSON
    const contentType = res.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await res.text();
      console.error('Non-JSON response:', text);
      table.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Server returned invalid response format</td></tr>`;
      return;
    }

    const data = await res.json();
    console.log('Received data:', data);

    // Check if this is a faculty user response
    if (data.message && data.message.includes('Faculty users cannot view leave request lists')) {
      console.log('Showing faculty interface (old version)');
      // Faculty users - show notice and hide list
      showFacultyInterface();
      return;
    }

    // Check if user can only see their own requests (faculty users)
    if (data.user_can_only_see_own_requests) {
      console.log('Showing faculty leave interface (new version)');
      showFacultyLeaveInterface();
      // Continue to populate the table below
    }
    
    table.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      // Table has 9 columns total
      table.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No leave requests</td></tr>`;
      return;
    }
  // Check if user can only see their own requests (faculty view)
  const isFacultyView = data.user_can_only_see_own_requests || false;

  data.items.forEach(l => {
    const row = document.createElement("tr");
    // Build reason display with approval/rejection reasons if available
    let reasonDisplay = l.reason || '-';
    if (l.status === 'approved' && l.approval_reason) {
      reasonDisplay += `<br><small class="text-success"><strong>Approval Reason:</strong> ${l.approval_reason}</small>`;
    } else if (l.status === 'denied' && l.rejection_reason) {
      reasonDisplay += `<br><small class="text-danger"><strong>Rejection Reason:</strong> ${l.rejection_reason}</small>`;
    }

    // For faculty view, hide user name column and actions column
    if (isFacultyView) {
      row.innerHTML = `
        <td>${l.leave_id}</td>
        <td style="display: none;"></td>
        <td>${l.leave_type || 'Other'}</td>
        <td>${l.start_date}</td>
        <td>${l.end_date}</td>
        <td>${reasonDisplay}</td>
        <td><span class="badge ${l.status === 'approved' ? 'bg-success' : l.status === 'denied' ? 'bg-danger' : 'bg-warning text-dark'}">${l.status}</span></td>
        <td>${l.reviewer || '-'}</td>
        <td style="display: none;"></td>
      `;
    } else {
      row.innerHTML = `
        <td>${l.leave_id}</td>
        <td>${l.user_name}</td>
        <td>${l.leave_type || 'Other'}</td>
        <td>${l.start_date}</td>
        <td>${l.end_date}</td>
        <td>${reasonDisplay}</td>
        <td><span class="badge ${l.status === 'approved' ? 'bg-success' : l.status === 'denied' ? 'bg-danger' : 'bg-warning text-dark'}">${l.status}</span></td>
        <td>${l.reviewer || '-'}</td>
        <td>
          ${l.status === 'pending' ? `
            <button class="btn btn-sm btn-success" onclick="openReviewModal(${l.leave_id},'approved')">Approve</button>
            <button class="btn btn-sm btn-danger" onclick="openReviewModal(${l.leave_id},'denied')">Deny</button>
          ` : ''}
        </td>
      `;
    }
    table.appendChild(row);
  });
  } catch (error) {
    console.error('Error loading leave requests:', error);
    table.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading leave requests: ${error.message}</td></tr>`;
  }
}

document.getElementById("leaveForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const startDate = new Date(document.getElementById("startDate").value);
  const today = new Date();
  today.setHours(0, 0, 0, 0); // Normalize today's date to start of day
  const leaveType = document.getElementById("leaveType").value;

  const diffTime = startDate.getTime() - today.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  // Apply 14-day advance notice rule - EXCEPTION for Sick Leave
  if (diffDays < 14 && leaveType !== 'Sick Leave') {
    alert("Leave requests must be submitted at least two weeks (14 days) in advance (except for sick leave).");
    return;
  }

  // Additional validation for sick leave (can be retroactive up to 7 days)
  if (leaveType === 'Sick Leave') {
    const maxSickLeaveRetroactive = new Date();
    maxSickLeaveRetroactive.setDate(today.getDate() - 7); // 7 days ago
    maxSickLeaveRetroactive.setHours(0, 0, 0, 0);
    
    if (startDate < maxSickLeaveRetroactive) {
      alert("Sick leave cannot be requested more than 7 days in the past.");
      return;
    }
  }

  const endDate = new Date(document.getElementById("endDate").value);

  if (endDate < startDate) {
    alert("End date cannot be before start date.");
    return;
  }

  const body = {
    action: "create",
    start_date: document.getElementById("startDate").value,
    end_date: document.getElementById("endDate").value,
    leave_type: document.getElementById("leaveType").value,
    reason: document.getElementById("reason").value
  };
  const res = await fetch(API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(body)
  });
  
  // Check if response is ok
  if (!res.ok) {
    console.error('Response not ok:', res.status, res.statusText);
    const text = await res.text();
    console.error('Response text:', text);
    alert('Server error: ' + res.status);
    return;
  }
  
  // Check if response is JSON
  const contentType = res.headers.get('content-type');
  if (!contentType || !contentType.includes('application/json')) {
    const text = await res.text();
    console.error('Non-JSON response:', text);
    alert('Server returned non-JSON response');
    return;
  }
  
  const data = await res.json();
  alert(data.message || (data.success ? "Submitted" : "Error"));
  if (data.success) {
    const leaveRequestModal = bootstrap.Modal.getInstance(document.getElementById('leaveRequestModal'));
    if (leaveRequestModal) {
      leaveRequestModal.hide();
    }
    // Clear form fields
    document.getElementById("startDate").value = "";
    document.getElementById("endDate").value = "";
    document.getElementById("leaveType").value = "";
    document.getElementById("reason").value = "";
  }
  loadLeaves();
});

// Open the review modal with the specified action
function openReviewModal(leaveId, action) {
  reviewLeaveId.value = leaveId;
  reviewAction.value = 'review';
  reviewReason.value = '';
  
  // Update modal content based on action
  if (action === 'approved') {
    document.getElementById('reviewLeaveModalLabel').textContent = 'Approve Leave Request';
    reasonLabel.textContent = 'Approval Reason';
    decisionText.textContent = 'You are about to APPROVE this leave request.';
    confirmReviewBtn.className = 'btn btn-success';
    confirmBtnText.textContent = 'Approve';
    reviewReason.placeholder = 'Provide a reason for approval (optional)...';
  } else if (action === 'denied') {
    document.getElementById('reviewLeaveModalLabel').textContent = 'Reject Leave Request';
    reasonLabel.textContent = 'Rejection Reason';
    decisionText.textContent = 'You are about to REJECT this leave request.';
    confirmReviewBtn.className = 'btn btn-danger';
    confirmBtnText.textContent = 'Reject';
    reviewReason.placeholder = 'Please provide a reason for rejection (recommended)...';
  }
  
  // Store the action for later use
  confirmReviewBtn.dataset.action = action;
  
  reviewModal.show();
}

// Handle the confirmation button click
confirmReviewBtn.addEventListener('click', async () => {
  const action = confirmReviewBtn.dataset.action;
  const reason = reviewReason.value.trim();
  
  try {
    const res = await fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ 
        action: "review", 
        leave_id: reviewLeaveId.value, 
        status: action,
        reason: reason
      })
    });
    
    const data = await res.json();
    
    if (data.success) {
      reviewModal.hide();
      loadLeaves();
      alert(data.message || 'Leave request updated successfully');
    } else {
      alert('Error: ' + (data.message || 'Failed to update leave request'));
    }
  } catch (error) {
    console.error('Error updating leave request:', error);
    alert('Error: Failed to update leave request');
  }
});

// Archive functionality
document.getElementById('showActiveBtn').addEventListener('click', () => {
  showArchived = false;
  updateViewButtons();
  loadLeaves();
});

document.getElementById('showArchivedBtn').addEventListener('click', () => {
  showArchived = true;
  updateViewButtons();
  loadLeaves();
});

document.getElementById('archiveOldBtn').addEventListener('click', async () => {
  if (confirm('Are you sure you want to archive old leave requests? This will move all non-pending requests older than 30 days to the archive.')) {
    try {
      const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'archive_old' })
      });
      const data = await res.json();
      alert(data.message);
      if (data.success) {
        loadLeaves();
      }
    } catch (error) {
      console.error('Error archiving old requests:', error);
      alert('Error archiving old requests');
    }
  }
});

function updateViewButtons() {
  const activeBtn = document.getElementById('showActiveBtn');
  const archivedBtn = document.getElementById('showArchivedBtn');
  
  if (showArchived) {
    activeBtn.classList.remove('active');
    activeBtn.classList.add('btn-outline-primary');
    archivedBtn.classList.add('active');
    archivedBtn.classList.remove('btn-outline-secondary');
  } else {
    activeBtn.classList.add('active');
    activeBtn.classList.remove('btn-outline-primary');
    archivedBtn.classList.remove('active');
    archivedBtn.classList.add('btn-outline-secondary');
  }
}

// Check if user can archive (admin/dean roles)
function checkArchivePermissions() {
  const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');
  const canArchive = user.roles && (user.roles.includes('admin') || user.roles.includes('dean'));
  const archiveBtn = document.getElementById('archiveOldBtn');
  if (archiveBtn) {
    archiveBtn.style.display = canArchive ? 'inline-block' : 'none';
  }
}

// Legacy function for backward compatibility (if needed)
async function reviewLeave(id, status) {
  // This function is now replaced by openReviewModal
  openReviewModal(id, status);
}

// Function to show faculty-specific interface (old version - no longer used)
function showFacultyInterface() {
  // Hide view toggle buttons for faculty
  const viewToggleButtons = document.getElementById('viewToggleButtons');
  if (viewToggleButtons) {
    viewToggleButtons.style.display = 'none';
  }

  // Show faculty notice
  const facultyNotice = document.getElementById('facultyNotice');
  if (facultyNotice) {
    facultyNotice.classList.remove('d-none');
  }

  // Hide leave list card for faculty
  const leaveListCard = document.getElementById('leaveListCard');
  if (leaveListCard) {
    leaveListCard.style.display = 'none';
  }

  // Don't load leaves for faculty users since they can't see the list
  return;
}

// Function to show user leave interface (for faculty, program head, and staff - shows their own requests)
function showFacultyLeaveInterface() {
  // Show view toggle buttons for faculty/program head/staff to view archived requests
  const viewToggleButtons = document.getElementById('viewToggleButtons');
  if (viewToggleButtons) {
    viewToggleButtons.style.display = 'block';
  }

  // Update notice to reflect that they can see their own requests
  const facultyNotice = document.getElementById('facultyNotice');
  if (facultyNotice) {
    const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');
    const userName = user.name || 'User';
     const userRole = user.roles && user.roles.includes('program head') ? 'Program Head' :
                     user.roles && user.roles.includes('staff') ? 'Staff' : 'Faculty';
    facultyNotice.innerHTML = `
      <i class="fas fa-info-circle me-2"></i>
      <strong>${userName}'s Leave Requests:</strong> As a ${userRole}, you can view your active and archived leave requests using the toggle buttons above, and submit new applications below. Only administrators and supervisors can view and manage other users' leave requests.
    `;
    facultyNotice.classList.remove('d-none');
  }

  // Show leave balance card for faculty/program head
  const leaveBalanceCard = document.getElementById('leaveBalanceCard');
  if (leaveBalanceCard) {
    leaveBalanceCard.classList.remove('d-none');
    loadLeaveBalance();
  }

  // Show leave list card (but with limited functionality)
  const leaveListCard = document.getElementById('leaveListCard');
  if (leaveListCard) {
    leaveListCard.style.display = 'block';
  }

  // Update table headers for user view
  const requestsHeader = document.querySelector('#leaveListCard .card-body h6');
  if (requestsHeader) {
    const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');
    const userName = user.name || 'Your';
    requestsHeader.textContent = `${userName}'s Leave Requests`;
  }

  // Hide user and actions columns for user view
  const userColumn = document.getElementById('userColumn');
  const actionsColumn = document.getElementById('actionsColumn');

  if (userColumn) {
    userColumn.style.display = 'none';
  }
  if (actionsColumn) {
    actionsColumn.style.display = 'none';
  }
}

// Function to load leave balance for faculty and program head users
async function loadLeaveBalance() {
  try {
    const response = await fetch(`${API}?action=balance`, { credentials: 'include' });
    const data = await response.json();

    if (data.balance !== undefined) {
      const remainingBalance = document.getElementById('remainingBalance');
      const usedDays = document.getElementById('usedDays');

      if (remainingBalance) {
        remainingBalance.textContent = data.balance;
      }
      if (usedDays) {
        usedDays.textContent = 10 - data.balance; // Assuming 10 days total annual leave
      }
    }
  } catch (error) {
    console.error('Error loading leave balance:', error);
    // Hide balance card if there's an error
    const leaveBalanceCard = document.getElementById('leaveBalanceCard');
    if (leaveBalanceCard) {
      leaveBalanceCard.classList.add('d-none');
    }
  }
}

// Function to show admin/supervisor interface
function showAdminInterface() {
  // Show view toggle buttons for admins/supervisors
  const viewToggleButtons = document.getElementById('viewToggleButtons');
  if (viewToggleButtons) {
    viewToggleButtons.style.display = 'block';
  }

  // Hide faculty notice
  const facultyNotice = document.getElementById('facultyNotice');
  if (facultyNotice) {
    facultyNotice.classList.add('d-none');
  }

  // Hide leave balance card for admin/supervisor users
  const leaveBalanceCard = document.getElementById('leaveBalanceCard');
  if (leaveBalanceCard) {
    leaveBalanceCard.classList.add('d-none');
  }

  // Show leave list card for admins/supervisors
  const leaveListCard = document.getElementById('leaveListCard');
  if (leaveListCard) {
    leaveListCard.style.display = 'block';
  }
}

// Check user role and show appropriate interface
function checkUserRoleAndShowInterface() {
  const user = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');
  const userRoles = user.roles || [];

  if (userRoles.includes('faculty') || userRoles.includes('program head') || userRoles.includes('staff')) {
    // Faculty, Program Head, and Staff users can now see their own requests, so we return true to load leaves
    // The interface will be adjusted in showFacultyLeaveInterface() when data is loaded
    return true;
  } else {
    showAdminInterface();
    return true;
  }
}

// Initial load
const shouldLoadLeaves = checkUserRoleAndShowInterface();
checkArchivePermissions();
if (shouldLoadLeaves) {
  loadLeaves();
}
