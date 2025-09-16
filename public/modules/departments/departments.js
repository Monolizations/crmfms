// /modules/departments/departments.js
const API_URL = "/crmfms/api/departments/departments.php";
const deptQrTable = document.getElementById("deptQrTable");

let qrViewerModal;
let qrCodeImage;
let downloadQrBtn;

document.addEventListener('DOMContentLoaded', () => {
  // Initialize Bootstrap Modals
  qrViewerModal = new bootstrap.Modal(document.getElementById('qrViewerModal'));
  qrCodeImage = document.getElementById('qrCodeImage');
  downloadQrBtn = document.getElementById('downloadQrBtn');

  // Add event listeners
  document.getElementById('addDeptQrBtn').addEventListener('click', createDepartmentQr);

  // Event delegation for dynamically added buttons
  deptQrTable.addEventListener('click', (e) => {
    const target = e.target;
    if (target.classList.contains('view-qr-btn')) {
      const qrCodeUrl = target.dataset.qrCodeUrl;
      const qrValue = target.dataset.qrValue;
      viewQrCode(qrCodeUrl, qrValue);
    } else if (target.classList.contains('delete-qr-btn')) {
      const codeId = target.dataset.codeId;
      deleteDepartmentQr(codeId);
    }
  });

  // Initial load
  loadDepartmentQrs();
});

async function loadDepartmentQrs() {
  try {
    const res = await fetch(API_URL, { credentials: "include" });
    const data = await res.json();
    deptQrTable.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      deptQrTable.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No department QR codes found</td></tr>`;
      return;
    }

    data.items.forEach(qr => {
      const isPermanent = qr.code_value.startsWith('QR-DEPT-SECRETARY-');
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${qr.code_id}</td>
        <td>${qr.name} ${isPermanent ? '<span class="badge bg-success ms-1">Permanent</span>' : ''}</td>
        <td><code>${qr.code_value}</code></td>
        <td>${qr.qr_code_url ? `<img src="${qr.qr_code_url}" alt="QR Code" style="width: 50px; height: 50px;">` : 'None'}</td>
        <td>${new Date(qr.created_at).toLocaleDateString()}</td>
        <td>
          ${qr.qr_code_url ? `<button class="btn btn-sm btn-info view-qr-btn me-1" data-qr-code-url="${qr.qr_code_url}" data-qr-value="${qr.code_value}">View QR</button>` : ''}
          ${!isPermanent ? `<button class="btn btn-sm btn-danger delete-qr-btn" data-code-id="${qr.code_id}">Delete</button>` : '<span class="text-muted">Permanent</span>'}
        </td>
      `;
      deptQrTable.appendChild(row);
    });
  } catch (error) {
    console.error("Failed to load department QR codes:", error);
  }
}

async function createDepartmentQr() {
  if (!confirm("Create a new department QR code for check-in purposes?")) {
    return;
  }

  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "create", name: "Department Check-in" })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Department QR code created successfully" : "Error creating QR code"));
    if (data.success) {
      loadDepartmentQrs();
      // Auto-show the QR code
      if (data.qr_code_url) {
        setTimeout(() => {
          viewQrCode(data.qr_code_url, "New Department QR");
        }, 500);
      }
    }
  } catch (error) {
    console.error("Failed to create department QR code:", error);
    alert("An error occurred while creating the QR code.");
  }
}

async function deleteDepartmentQr(codeId) {
  // Check if this is a permanent QR code by looking at the button's data attribute
  const deleteBtn = document.querySelector(`[data-code-id="${codeId}"].delete-qr-btn`);
  if (!deleteBtn) {
    alert("QR code not found.");
    return;
  }

  if (!confirm("Are you sure you want to delete this department QR code? This action cannot be undone.")) {
    return;
  }

  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "delete", code_id: codeId })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Department QR code deleted successfully" : "Error deleting QR code"));
    if (data.success) {
      loadDepartmentQrs();
    }
  } catch (error) {
    console.error("Failed to delete department QR code:", error);
    alert("An error occurred while deleting the QR code.");
  }
}

function viewQrCode(qrCodeUrl, identifier) {
  qrCodeImage.src = qrCodeUrl;
  downloadQrBtn.href = qrCodeUrl;
  downloadQrBtn.download = `Department_QR_${identifier}.png`;
  qrViewerModal.show();
}