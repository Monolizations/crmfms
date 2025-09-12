// /modules/schedules/schedules.js
const API = "/crmfms/api/schedules/schedules.php";
const table = document.getElementById("scheduleTable");

async function loadFaculties() {
  try {
    const res = await fetch("/crmfms/api/faculties/faculties.php", { credentials: "include" });
    if (!res.ok) {
      console.error("Faculties API error:", res.status, res.statusText);
      if (res.status === 401 || res.status === 403) {
        const facultySelect = document.getElementById("facultyId");
        facultySelect.innerHTML = '<option value="">No permission to view faculty</option>';
      }
      return;
    }
    const data = await res.json();
    const facultySelect = document.getElementById("facultyId");
    facultySelect.innerHTML = '<option value="">Select Faculty</option>';

    if (data.items && data.items.length > 0) {
      data.items.forEach(faculty => {
        const option = document.createElement("option");
        option.value = faculty.user_id;
        option.textContent = `${faculty.last_name}, ${faculty.first_name}`;
        facultySelect.appendChild(option);
      });
    } else {
      facultySelect.innerHTML = '<option value="">No faculty members found</option>';
    }
  } catch (error) {
    console.error("Error loading faculties:", error);
  }
}

async function loadRooms() {
  try {
    const res = await fetch("/crmfms/api/rooms/rooms.php", { credentials: "include" });
    if (!res.ok) {
      console.error("Rooms API error:", res.status, res.statusText);
      if (res.status === 401 || res.status === 403) {
        const roomSelect = document.getElementById("roomId");
        roomSelect.innerHTML = '<option value="">No permission to view rooms</option>';
      }
      return;
    }
    const data = await res.json();
    const roomSelect = document.getElementById("roomId");
    roomSelect.innerHTML = '<option value="">Select Room</option>';

    if (data.items && data.items.length > 0) {
      data.items.forEach(room => {
        const option = document.createElement("option");
        option.value = room.room_id;
        option.textContent = `${room.name}, ${room.room_code}`;
        roomSelect.appendChild(option);
      });
    } else {
      roomSelect.innerHTML = '<option value="">No rooms found</option>';
    }
  } catch (error) {
    console.error("Error loading rooms:", error);
  }
}

async function loadSchedules() {
  const res = await fetch(API, { credentials: "include" });
  const data = await res.json();
  table.innerHTML = "";
  if (!data.items || data.items.length === 0) {
    table.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No schedules</td></tr>`;
    return;
  }
   data.items.forEach(s => {
     const row = document.createElement("tr");
     row.innerHTML = `
       <td>${s.schedule_id}</td>
       <td>${s.last_name}, ${s.first_name}</td>
       <td>${s.room_name}, ${s.room_code}</td>
       <td>${s.day_of_week}</td>
       <td>${s.start_time}</td>
       <td>${s.end_time}</td>
       <td>
         <span class="badge ${s.status === 'active' ? 'bg-success' : 'bg-secondary'}">${s.status}</span>
       </td>
       <td>
         <button class="btn btn-sm btn-warning" onclick="toggleSchedule(${s.schedule_id})">Toggle Active</button>
       </td>
     `;
     table.appendChild(row);
   });
}

document.getElementById("scheduleForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "create",
    faculty_id: document.getElementById("facultyId").value,
    room_id: document.getElementById("roomId").value,
    day_of_week: document.getElementById("dayOfWeek").value,
    start_time: document.getElementById("startTime").value,
    end_time: document.getElementById("endTime").value
  };
  const res = await fetch(API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(body)
  });
  const data = await res.json();
  alert(data.message || (data.success ? "Added" : "Error"));
  loadSchedules();
});

async function toggleSchedule(id) {
  const res = await fetch(API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify({ action: "toggle", schedule_id: id })
  });
  const data = await res.json();
  alert(data.message || (data.success ? "Updated" : "Error"));
  loadSchedules();
}

// Initialize page
async function initPage() {
  // Check if user is authenticated before loading data
  if (!window.auth || !window.auth.isAuthenticated) {
    window.location.href = '/crmfms/public/modules/auth/login.html';
    return;
  }

  await Promise.all([loadFaculties(), loadRooms()]);
  loadSchedules();
}

initPage();
