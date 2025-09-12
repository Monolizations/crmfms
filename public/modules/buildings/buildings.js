// /modules/buildings/buildings.js
const API_URL = "/crmfms/api/buildings/buildings.php";
const buildingTable = document.getElementById("buildingTable");

const addBuildingModal = new bootstrap.Modal(document.getElementById('addBuildingModal'));
const addBuildingForm = document.getElementById("addBuildingForm");

const editBuildingModal = new bootstrap.Modal(document.getElementById('editBuildingModal'));
const editBuildingForm = document.getElementById("editBuildingForm");

async function loadBuildings() {
  try {
    const res = await fetch(API_URL, { credentials: "include" });
    const data = await res.json();
    buildingTable.innerHTML = "";
    if (!data.items || data.items.length === 0) {
      buildingTable.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No buildings found</td></tr>`;
      return;
    }

    data.items.forEach(building => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${building.building_id}</td>
        <td>${building.name}</td>
        <td>${building.description || '-'}</td>
        <td>
          <button class="btn btn-sm btn-primary" onclick="editBuilding(${building.building_id}, '${building.name}', '${building.description || ''}')">Edit</button>
          <button class="btn btn-sm btn-danger ms-1" onclick="deleteBuilding(${building.building_id})">Delete</button>
        </td>
      `;
      buildingTable.appendChild(row);
    });
  } catch (error) {
    console.error("Failed to load buildings:", error);
  }
}

addBuildingForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "create",
    name: document.getElementById("addBuildingName").value,
    description: document.getElementById("addBuildingDescription").value
  };
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(body)
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Building created successfully" : "Error creating building"));
    if (data.success) {
      addBuildingForm.reset();
      addBuildingModal.hide();
      loadBuildings();
    }
  } catch (error) {
    console.error("Failed to create building:", error);
    alert("An error occurred while creating the building.");
  }
});

function editBuilding(id, name, description) {
  document.getElementById("editBuildingId").value = id;
  document.getElementById("editBuildingName").value = name;
  document.getElementById("editBuildingDescription").value = description;
  editBuildingModal.show();
}

editBuildingForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "update",
    building_id: document.getElementById("editBuildingId").value,
    name: document.getElementById("editBuildingName").value,
    description: document.getElementById("editBuildingDescription").value
  };
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(body)
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Building updated successfully" : "Error updating building"));
    if (data.success) {
      editBuildingModal.hide();
      loadBuildings();
    }
  } catch (error) {
    console.error("Failed to update building:", error);
    alert("An error occurred while updating the building.");
  }
});

async function deleteBuilding(id) {
  if (!confirm("Are you sure you want to delete this building? This will also delete all associated floors and rooms.")) {
    return;
  }
  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ action: "delete", building_id: id })
    });
    const data = await res.json();
    alert(data.message || (data.success ? "Building deleted successfully" : "Error deleting building"));
    if (data.success) {
      loadBuildings();
    }
  } catch (error) {
    console.error("Failed to delete building:", error);
    alert("An error occurred while deleting the building.");
  }
}

loadBuildings();
