// /modules/monitoring/monitoring.js
const API = "/api/monitoring/monitoring.php";

async function loadSuggestions() {
  const res = await fetch(API + "?action=suggestions", { credentials: "include" });
  const data = await res.json();
  const list = document.getElementById("suggestList");
  list.innerHTML = "";
  if (!data.items || data.items.length === 0) {
    list.innerHTML = `<li class="list-group-item text-muted">No suggestions</li>`;
    return;
  }
  data.items.forEach(s => {
    const li = document.createElement("li");
    li.className = "list-group-item";
    li.innerHTML = `<strong>Building ${s.building_id}</strong> - ${s.note}`;
    list.appendChild(li);
  });
}

async function loadRounds() {
  const res = await fetch(API + "?action=list", { credentials: "include" });
  const data = await res.json();
  const table = document.getElementById("roundTable");
  table.innerHTML = "";
  if (!data.items || data.items.length === 0) {
    table.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No monitoring rounds</td></tr>`;
    return;
  }
  data.items.forEach(r => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${r.round_id}</td>
      <td>${r.building_id}</td>
      <td>${r.notes || '-'}</td>
      <td>${r.round_time}</td>
    `;
    table.appendChild(row);
  });
}

document.getElementById("roundForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "create",
    building_id: document.getElementById("buildingId").value,
    notes: document.getElementById("roundNotes").value
  };
  const res = await fetch(API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(body)
  });
  const data = await res.json();
  alert(data.message || (data.success ? "Saved" : "Error"));
  loadRounds();
});

document.getElementById("refreshSuggestions").addEventListener("click", loadSuggestions);

loadSuggestions();
loadRounds();
