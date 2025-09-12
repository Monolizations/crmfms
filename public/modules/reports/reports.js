// /modules/reports/reports.js
const API = "/api/reports/reports.php";
let currentData = [];

document.getElementById("filterForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    action: "generate",
    start_date: document.getElementById("startDate").value,
    end_date: document.getElementById("endDate").value,
    type: document.getElementById("reportType").value
  };
  const res = await fetch(API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(body)
  });
  const data = await res.json();
  renderReport(body.type, data.items || []);
});

document.getElementById("exportBtn").addEventListener("click", () => {
  if (currentData.length === 0) {
    alert("No data to export");
    return;
  }
  const csv = [
    Object.keys(currentData[0]).join(","),
    ...currentData.map(row => Object.values(row).map(v => `"${v}"`).join(","))
  ].join("\n");
  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "report.csv";
  a.click();
  URL.revokeObjectURL(url);
});

function renderReport(type, items) {
  const head = document.getElementById("reportHead");
  const table = document.getElementById("reportTable");
  currentData = items;
  head.innerHTML = "";
  table.innerHTML = "";

  if (items.length === 0) {
    table.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No records</td></tr>`;
    return;
  }

  const cols = Object.keys(items[0]);
  head.innerHTML = `<tr>${cols.map(c => `<th>${c}</th>`).join("")}</tr>`;
  items.forEach(row => {
    const tr = document.createElement("tr");
    tr.innerHTML = cols.map(c => `<td>${row[c]}</td>`).join("");
    table.appendChild(tr);
  });
}
