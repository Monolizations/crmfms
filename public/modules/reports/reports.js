// /modules/reports/reports.js
const API = "/crmfms/api/reports/reports.php";
let currentData = [];

// Set default dates on page load
document.addEventListener("DOMContentLoaded", async () => {
  // Set start date to 7 days ago
  const startDate = new Date();
  startDate.setDate(startDate.getDate() - 7);
  document.getElementById("startDate").value = startDate.toISOString().split('T')[0];

  // Set end date to latest attendance record date
  try {
    const latestDate = await getLatestAttendanceDate();
    document.getElementById("endDate").value = latestDate;
  } catch (error) {
    // Fallback to today
    document.getElementById("endDate").value = new Date().toISOString().split('T')[0];
  }
});

document.getElementById("filterForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    start_date: document.getElementById("startDate").value,
    end_date: document.getElementById("endDate").value,
    type: document.getElementById("reportType").value
  };

  try {
    const res = await fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(body)
    });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const data = await res.json();

    // Handle different response formats
    if (data.items) {
      renderReport(body.type, data.items);
    } else if (data.analytics && data.items) {
      // Special handling for leave analytics
      renderLeaveAnalytics(data);
    } else if (data.summary) {
      // Handle summary-only reports
      renderSummaryReport(body.type, data);
    } else {
      renderReport(body.type, []);
    }
  } catch (error) {
    console.error('Report generation failed:', error);
    renderError('Failed to generate report: ' + error.message);
  }
});

// Get latest attendance date for default end date
async function getLatestAttendanceDate() {
  try {
    const response = await fetch('/crmfms/api/reports/reports.php?action=latest_date', {
      method: 'GET',
      credentials: 'include'
    });
    if (response.ok) {
      const data = await response.json();
      return data.latest_date || new Date().toISOString().split('T')[0];
    }
  } catch (error) {
    console.error('Failed to get latest date:', error);
  }
  return new Date().toISOString().split('T')[0];
}

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
  const title = document.getElementById("reportTitle");

  currentData = items;
  head.innerHTML = "";
  table.innerHTML = "";

  // Update report title
  title.textContent = getReportTitle(type);

  if (!items || items.length === 0) {
    table.innerHTML = `<tr><td colspan="10" class="text-center text-muted">No records found for the selected criteria</td></tr>`;
    return;
  }

  const cols = Object.keys(items[0]);
  head.innerHTML = `<tr>${cols.map(c => `<th>${c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</th>`).join("")}</tr>`;

  items.forEach(row => {
    const tr = document.createElement("tr");
    tr.innerHTML = cols.map(c => `<td>${row[c] || 'N/A'}</td>`).join("");
    table.appendChild(tr);
  });
}

function renderLeaveAnalytics(data) {
  const head = document.getElementById("reportHead");
  const table = document.getElementById("reportTable");
  const title = document.getElementById("reportTitle");

  currentData = data.items || [];
  title.textContent = "Leave Analytics Dashboard";

  // Show summary first
  if (data.analytics) {
    const summaryHtml = `
      <div class="alert alert-info">
        <h6>Summary Statistics:</h6>
        <ul class="mb-0">
          <li>Total Leaves: ${data.analytics.total_leaves}</li>
          <li>Approval Rate: ${data.analytics.status_distribution?.approved?.percentage || 'N/A'}</li>
          <li>Average Approval Time: ${data.analytics.avg_approval_time} hours</li>
        </ul>
      </div>
    `;
    document.getElementById("reportTable").parentElement.insertAdjacentHTML('beforebegin', summaryHtml);
  }

  head.innerHTML = "";
  table.innerHTML = "";

  if (!data.items || data.items.length === 0) {
    table.innerHTML = `<tr><td colspan="10" class="text-center text-muted">No leave records found</td></tr>`;
    return;
  }

  const cols = Object.keys(data.items[0]);
  head.innerHTML = `<tr>${cols.map(c => `<th>${c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</th>`).join("")}</tr>`;

  data.items.forEach(row => {
    const tr = document.createElement("tr");
    tr.innerHTML = cols.map(c => `<td>${row[c] || 'N/A'}</td>`).join("");
    table.appendChild(tr);
  });
}

function renderSummaryReport(type, data) {
  const head = document.getElementById("reportHead");
  const table = document.getElementById("reportTable");
  const title = document.getElementById("reportTitle");

  title.textContent = getReportTitle(type);

  // Create summary display
  let summaryHtml = `<div class="alert alert-success"><h6>Report Summary:</h6><ul class="mb-0">`;

  if (data.summary) {
    Object.entries(data.summary).forEach(([key, value]) => {
      summaryHtml += `<li>${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}: ${value}</li>`;
    });
  }
  summaryHtml += '</ul></div>';

  // Clear any existing summary
  const existingSummary = document.querySelector('.alert-success');
  if (existingSummary) existingSummary.remove();

  document.getElementById("reportTable").parentElement.insertAdjacentHTML('beforebegin', summaryHtml);

  head.innerHTML = "";
  table.innerHTML = "";

  // Show detailed data if available
  const detailData = data.daily_performance || data.buildings || data.rooms || [];
  if (detailData.length > 0) {
    currentData = detailData;
    const cols = Object.keys(detailData[0]);
    head.innerHTML = `<tr>${cols.map(c => `<th>${c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</th>`).join("")}</tr>`;

    detailData.forEach(row => {
      const tr = document.createElement("tr");
      tr.innerHTML = cols.map(c => `<td>${row[c] || 'N/A'}</td>`).join("");
      table.appendChild(tr);
    });
  } else {
    table.innerHTML = `<tr><td colspan="10" class="text-center text-muted">Summary report generated successfully</td></tr>`;
    currentData = [];
  }
}

function renderError(message) {
  const table = document.getElementById("reportTable");
  table.innerHTML = `<tr><td colspan="10" class="text-center text-danger">${message}</td></tr>`;
  currentData = [];
}

function getReportTitle(type) {
  const titles = {
    'attendance': 'Attendance Records',
    'leaves': 'Leave Requests',
    'delinquents': 'Attendance Delinquents',
    'room_utilization': 'Room Utilization Report',
    'faculty_attendance': 'Faculty Attendance Summary',
    'building_occupancy': 'Building Occupancy Analytics',

    'leave_analytics': 'Leave Analytics Dashboard',
    'system_performance': 'System Performance Report',
    'department_performance': 'Department Performance Report',
    'time_analytics': 'Time-based Analytics Report',
    'alert_incident': 'Alert and Incident Report',
    'resource_allocation': 'Resource Allocation Report'
  };
  return titles[type] || 'Report Results';
}
