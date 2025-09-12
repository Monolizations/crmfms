// /modules/floors/floors.js
const API_URL = "/crmfms/api/floors/floors.php";
const BUILDINGS_API_URL = "/crmfms/api/buildings/buildings.php";
const floorTable = document.getElementById("floorTable");

const addFloorModal = new bootstrap.Modal(document.getElementById('addFloorModal'));
const addFloorForm = document.getElementById("addFloorForm");
const addBuildingSelect = document.getElementById("addBuildingId");

const editFloorModal = new bootstrap.Modal(document.getElementById('editFloorModal'));
const editFloorForm = document.getElementById("editFloorForm");
const editBuildingSelect = document.getElementById("editBuildingId");

let allBuildings = []; // To store all available buildings

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
    }
  } catch (error) {
    console.error("Failed to load buildings:", error);
  }
}

async function loadFloors() {
  try {
    const res = await fetch(API_URL, { credentials: "include" });
    const data = await res.json();
    floorTable.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      floorTable.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No floors found</td></tr>`;
      return;
    }

    data.items.forEach(floor => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${floor.floor_id}</td>
        <td>${floor.building_name}</td>
        <td>${floor.floor_number}</td>
        <td>${floor.name || '-'}</td>
        <td>${floor.description || '-'}</td>
        <td>
          <button class="btn btn-sm btn-primary" onclick="editFloor(${floor.floor_id}, ${floor.building_id}, ${floor.floor_number}, '${floor.name || ''}', '${floor.description || ''}')">Edit</button>
          <button class="btn btn-sm btn-danger ms-1" onclick="deleteFloor(${floor.floor_id})">Delete</button>
        </td>
      `;
      floorTable.appendChild(row);
    });
  } catch (error) {
    console.error("Failed to load floors:", error);
  }
}

addFloorForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "create",
    building_id: document.getElementById("addBuildingId").value,
    floor_number: document.getElementById("addFloorNumber").value,
    name: document.getElementById("addFloorName").value,
    description: document.getElementById("addFloorDescription").value
  };
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(body)
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Floor created successfully" : "Error creating floor"));
    if (data.success) {
      addFloorForm.reset();
      addFloorModal.hide();
      loadFloors();
    }
  } catch (error) {
    console.error("Failed to create floor:", error);
    alert("An error occurred while creating the floor.");
  }
});

function editFloor(id, buildingId, floorNumber, name, description) {
  document.getElementById("editFloorId").value = id;
  document.getElementById("editBuildingId").value = buildingId;
  document.getElementById("editFloorNumber").value = floorNumber;
  document.getElementById("editFloorName").value = name;
  document.getElementById("editFloorDescription").value = description;
  editFloorModal.show();
}

editFloorForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "update",
    floor_id: document.getElementById("editFloorId").value,
    building_id: document.getElementById("editBuildingId").value,
    floor_number: document.getElementById("editFloorNumber").value,
    name: document.getElementById("editFloorName").value,
    description: document.getElementById("editFloorDescription").value
  };
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(body)
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Floor updated successfully" : "Error updating floor"));
    if (data.success) {
      editFloorModal.hide();
      loadFloors();
    }
  } catch (error) {
    console.error("Failed to update floor:", error);
    alert("An error occurred while updating the floor.");
  }
});

async function deleteFloor(id) {
  if (!confirm("Are you sure you want to delete this floor? This will also delete all associated rooms.")) {
    return;
  }
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "delete", floor_id: id })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Floor deleted successfully" : "Error deleting floor"));
    if (data.success) {
      loadFloors();
    }
  } catch (error) {
    console.error("Failed to delete floor:", error);
    alert("An error occurred while deleting the floor.");
  }
}

// Initial load
fetchBuildings();
loadFloors();
