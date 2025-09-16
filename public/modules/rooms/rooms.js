// /modules/rooms/rooms.js
const API_URL = "/crmfms/api/rooms/rooms.php";
const BUILDINGS_API_URL = "/crmfms/api/buildings/buildings.php";
const FLOORS_API_URL = "/crmfms/api/floors/floors.php";

// Department QR Code data
const DEPARTMENT_QR_DATA = '{"type":"department","department_id":1,"department_name":"Main Department","purpose":"Time In/Time Out","status":"active","created":"2025-09-13 07:53:49"}';
const roomTable = document.getElementById("roomTable");

let addRoomModal;
let addRoomForm;
let addBuildingSelect;
let addFloorSelect;

let editRoomModal;
let editRoomForm;
let editBuildingSelect;
let editFloorSelect;

let qrViewerModal;
let qrCodeImage;
let downloadQrBtn;

let allBuildings = [];
let allFloors = [];

document.addEventListener('DOMContentLoaded', () => {
  // Initialize Bootstrap Modals and get form elements after DOM is loaded
  addRoomModal = new bootstrap.Modal(document.getElementById('addRoomModal'));
  addRoomForm = document.getElementById("addRoomForm");
  addBuildingSelect = document.getElementById("addBuildingId");
  addFloorSelect = document.getElementById("addFloorId");

  editRoomModal = new bootstrap.Modal(document.getElementById('editRoomModal'));
  editRoomForm = document.getElementById("editRoomForm");
  editBuildingSelect = document.getElementById("editBuildingId");
  editFloorSelect = document.getElementById("editFloorId");

  qrViewerModal = new bootstrap.Modal(document.getElementById('qrViewerModal'));
  qrCodeImage = document.getElementById('qrCodeImage');
  downloadQrBtn = document.getElementById('downloadQrBtn');

  // Add event listeners after elements are initialized
  addBuildingSelect.addEventListener('change', (e) => fetchFloors(e.target.value, 'add'));
  editBuildingSelect.addEventListener('change', (e) => fetchFloors(e.target.value, 'edit'));

  addRoomForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const body = {
      action: "create",
      floor_id: document.getElementById("addFloorId").value,
      room_code: document.getElementById("addRoomCode").value,
      name: document.getElementById("addRoomName").value
    };
    try {
      const res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(body)
      });
      const data = await res.json();
      alert(data.message || (data.success ? "Room created successfully" : "Error creating room"));
      if (data.success) {
        addRoomForm.reset();
        addRoomModal.hide();
        loadRooms();
        // Show QR code preview only (no auto-download)
        if (data.qr_code_url) {
          viewQrCode(data.qr_code_url, document.getElementById("addRoomCode").value);
        }
      }
    } catch (error) {
      console.error("Failed to create room:", error);
      alert("An error occurred while creating the room.");
    }
  });

  editRoomForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const body = {
      action: "update",
      room_id: document.getElementById("editRoomId").value,
      floor_id: document.getElementById("editFloorId").value,
      room_code: document.getElementById("editRoomCode").value,
      name: document.getElementById("editRoomName").value
    };
    try {
      const res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(body)
      });
      const data = await res.json();
      alert(data.message || (data.success ? "Room updated successfully" : "Error updating room"));
      if (data.success) {
        editRoomModal.hide();
        loadRooms();
      }
    } catch (error) {
      console.error("Failed to update room:", error);
      alert("An error occurred while updating the room.");
    }
  });

  // Event delegation for dynamically added buttons
  roomTable.addEventListener('click', (e) => {
    const target = e.target;
    if (target.classList.contains('edit-room-btn')) {
      const roomId = target.dataset.roomId;
      const floorId = target.dataset.floorId;
      const roomCode = target.dataset.roomCode;
      const roomName = target.dataset.roomName;
      editRoom(roomId, floorId, roomCode, roomName);
    } else if (target.classList.contains('delete-room-btn')) {
      const roomId = target.dataset.roomId;
      deleteRoom(roomId);
    } else if (target.classList.contains('toggle-status-btn')) {
      const roomId = target.dataset.roomId;
      toggleRoomStatus(roomId);
    } else if (target.classList.contains('view-qr-btn')) {
      const qrCodeUrl = target.dataset.qrCodeUrl;
      const roomCode = target.dataset.roomCode;
      viewQrCode(qrCodeUrl, roomCode);
    }
  });

  // Check user permissions and show/hide UI elements
  const canModify = window.auth && (window.auth.isAdmin() || window.auth.isDean() || window.auth.isSecretary());
  const addRoomBtn = document.getElementById('addRoomBtn');
  if (addRoomBtn) {
    addRoomBtn.style.display = canModify ? 'inline-block' : 'none';
  }

  // Initial load
  fetchBuildings();
  loadRooms();
});

async function fetchBuildings() {
  try {
    const res = await fetch(BUILDINGS_API_URL, { credentials: "include" });
    const data = await res.json();
    if (data.items) {
      allBuildings = data.items;
      addBuildingSelect.innerHTML = '';
      editBuildingSelect.innerHTML = '';
      allBuildings.forEach(building => {
        const option = document.createElement("option");
        option.value = building.building_id;
        option.textContent = building.name;
        addBuildingSelect.appendChild(option);

        const editOption = document.createElement("option");
        editOption.value = building.building_id;
        editOption.textContent = building.name;
        editBuildingSelect.appendChild(editOption);
      });
      // Trigger floor fetch for the first building by default
      if (allBuildings.length > 0) {
        fetchFloors(addBuildingSelect.value, 'add');
        fetchFloors(editBuildingSelect.value, 'edit');
      }
    }
  } catch (error) {
    console.error("Failed to load buildings:", error);
  }
}

async function fetchFloors(buildingId, type) {
  try {
    const res = await fetch(`${FLOORS_API_URL}?building_id=${buildingId}`, { credentials: "include" });
    const data = await res.json();
    let targetSelect = type === 'add' ? addFloorSelect : editFloorSelect;
    targetSelect.innerHTML = '';
    if (data.items) {
      allFloors = data.items; // Store all floors for the selected building
      data.items.forEach(floor => {
        const option = document.createElement("option");
        option.value = floor.floor_id;
        option.textContent = `${floor.floor_number} - ${floor.name || ''}`.trim();
        targetSelect.appendChild(option);
      });
    }
  } catch (error) {
    console.error("Failed to load floors:", error);
  }
}

async function loadRooms() {
  try {
    const res = await fetch(API_URL, { credentials: "include" });
    const data = await res.json();
    roomTable.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      roomTable.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No rooms found</td></tr>`;
      return;
    }

    data.items.forEach(room => {
      // Check user permissions for action buttons
      const canModify = window.auth && (window.auth.isAdmin() || window.auth.isDean() || window.auth.isSecretary());
      const actionsHtml = canModify ? `
        <button class="btn btn-sm btn-primary edit-room-btn"
          data-room-id="${room.room_id}"
          data-floor-id="${room.floor_id}"
          data-room-code="${room.room_code}"
          data-room-name="${room.name}"
        >Edit</button>
        <button class="btn btn-sm btn-danger delete-room-btn ms-1" data-room-id="${room.room_id}">Delete</button>
        <button class="btn btn-sm btn-warning toggle-status-btn ms-1" data-room-id="${room.room_id}">Toggle Active</button>
        ${room.qr_code_url ? `<button class="btn btn-sm btn-info view-qr-btn ms-1" data-qr-code-url="${room.qr_code_url}" data-room-code="${room.room_code}">View QR</button>` : ''}
      ` : `
        ${room.qr_code_url ? `<button class="btn btn-sm btn-info view-qr-btn" data-qr-code-url="${room.qr_code_url}" data-room-code="${room.room_code}">View QR</button>` : '<span class="text-muted">No QR</span>'}
      `;

      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${room.room_id}</td>
        <td>${room.building_name}</td>
        <td>${room.floor_number} ${room.floor_name ? `(${room.floor_name})` : ''}</td>
        <td>${room.room_code}</td>
        <td>${room.name}</td>
        <td>${room.qr_code_url ? `<img src="${room.qr_code_url}" alt="QR Code" style="width: 50px; height: 50px;">` : 'None'}</td>
        <td><span class="badge ${room.status === 'active' ? 'bg-success' : 'bg-secondary'}">${room.status}</span></td>
        <td>${actionsHtml}</td>
      `;
      roomTable.appendChild(row);
    });
  } catch (error) {
    console.error("Failed to load rooms:", error);
  }
}

async function deleteRoom(id) {
  if (!confirm("Are you sure you want to delete this room?")) {
    return;
  }
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "delete", room_id: id })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Room deleted successfully" : "Error deleting room"));
    if (data.success) {
      loadRooms();
    }
  } catch (error) {
    console.error("Failed to delete room:", error);
    alert("An error occurred while deleting the room.");
  }
}

async function toggleRoomStatus(id) {
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "toggle", room_id: id })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Room status updated" : "Error updating status"));
    if (data.success) {
      loadRooms();
    }
  } catch (error) {
    console.error("Failed to toggle room status:", error);
    alert("An error occurred while updating the room status.");
  }
}

function editRoom(roomId, floorId, roomCode, name) {
  document.getElementById("editRoomId").value = roomId;
  document.getElementById("editRoomCode").value = roomCode;
  document.getElementById("editRoomName").value = name;

  // Select the correct building and floor
  const currentFloor = allFloors.find(f => f.floor_id == floorId); // Use == for type coercion
  if (currentFloor) {
    editBuildingSelect.value = currentFloor.building_id;
    fetchFloors(currentFloor.building_id, 'edit').then(() => {
      editFloorSelect.value = floorId;
    });
  }
  editRoomModal.show();
}

function viewQrCode(qrCodeUrl, identifier) {
  qrCodeImage.src = qrCodeUrl;
  downloadQrBtn.href = qrCodeUrl;
  downloadQrBtn.download = `QR_Code_${identifier}.png`;
  qrViewerModal.show();
}

// Department QR Code functions
function viewDepartmentQR() {
  const qrCodeUrl = `/crmfms/api/qr/generate.php?data=${encodeURIComponent(DEPARTMENT_QR_DATA)}&size=400`;
  qrCodeImage.src = qrCodeUrl;
  downloadQrBtn.href = qrCodeUrl;
  downloadQrBtn.download = 'Department_QR_Code.png';
  qrViewerModal.show();
}

function downloadDepartmentQR() {
  const qrCodeUrl = `/crmfms/api/qr/generate.php?data=${encodeURIComponent(DEPARTMENT_QR_DATA)}&size=400`;
  const link = document.createElement('a');
  link.href = qrCodeUrl;
  link.download = 'Department_QR_Code.png';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}