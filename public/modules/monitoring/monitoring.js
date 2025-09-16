// /modules/monitoring/monitoring.js
const API = "/crmfms/api/monitoring/monitoring.php";

async function loadBuildingCheckins() {
  const container = document.getElementById("buildingCards");

  try {
    // Show loading state
    if (container) {
      container.innerHTML = '<div class="col-12 text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading building check-in data...</div>';
    }

    const res = await fetch(API + "?action=building_checkins", { credentials: "include" });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const data = await res.json();

    if (container && data.buildings) {
      container.innerHTML = "";

      if (data.buildings.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted"><i class="fas fa-info-circle me-2"></i>No buildings found</div>';
        return;
      }

      data.buildings.forEach(building => {
        const col = document.createElement("div");
        col.className = "col-md-4 mb-3";

        // Status-based styling
        let statusClass = '';
        let statusIcon = '';
        switch(building.status) {
          case 'low':
            statusClass = 'border-warning';
            statusIcon = '<i class="fas fa-exclamation-triangle text-warning me-2"></i>';
            break;
          case 'high':
            statusClass = 'border-success';
            statusIcon = '<i class="fas fa-check-circle text-success me-2"></i>';
            break;
          default: // normal
            statusClass = 'border-info';
            statusIcon = '<i class="fas fa-info-circle text-info me-2"></i>';
        }

        col.innerHTML = `
          <div class="card h-100 ${statusClass}">
            <div class="card-body text-center">
              <h5 class="card-title">${building.name}</h5>
              <h2 class="text-primary">${building.checkins_today}</h2>
              <p class="card-text text-muted">Check-ins today</p>
              <div class="mt-2">
                ${statusIcon}
                <small class="text-capitalize">${building.status} activity</small>
              </div>
            </div>
          </div>
        `;
        container.appendChild(col);
      });
    }
  } catch (error) {
    console.error("Error loading building check-ins:", error);

    // Show error state
    if (container) {
      container.innerHTML = `
        <div class="col-12">
          <div class="alert alert-danger text-center">
            <i class="fas fa-exclamation-triangle me-2"></i>Failed to load building data.
            <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadBuildingCheckins()">Retry</button>
          </div>
        </div>
      `;
    }
  }
}

async function loadSuggestions() {
  const list = document.getElementById("suggestList");

  try {
    // Show loading state
    if (list) {
      list.innerHTML = '<li class="list-group-item text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading suggestions...</li>';
    }

    const res = await fetch(API + "?action=suggestions", { credentials: "include" });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const data = await res.json();

    if (list) {
      list.innerHTML = "";

      if (!data.items || data.items.length === 0) {
        list.innerHTML = '<li class="list-group-item text-muted text-center"><i class="fas fa-info-circle me-2"></i>No suggestions available</li>';
        return;
      }

      data.items.forEach(s => {
        const li = document.createElement("li");
        li.className = `list-group-item d-flex justify-content-between align-items-start`;

        // Add priority-based styling
        let priorityClass = '';
        let priorityIcon = '';
        switch(s.priority) {
          case 'high':
            priorityClass = 'border-danger';
            priorityIcon = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>';
            break;
          case 'medium':
            priorityClass = 'border-warning';
            priorityIcon = '<i class="fas fa-info-circle text-warning me-2"></i>';
            break;
          case 'low':
            priorityClass = 'border-info';
            priorityIcon = '<i class="fas fa-lightbulb text-info me-2"></i>';
            break;
        }

        li.className += ` ${priorityClass}`;

        // Add type-based icon
        let typeIcon = '';
        switch(s.type) {
          case 'monitoring_gap':
            typeIcon = '<i class="fas fa-search me-2"></i>';
            break;
          case 'system_alert':
            typeIcon = '<i class="fas fa-bell me-2"></i>';
            break;
          case 'peak_activity':
            typeIcon = '<i class="fas fa-chart-line me-2"></i>';
            break;
          case 'room_activity':
            typeIcon = '<i class="fas fa-door-open me-2"></i>';
            break;
          case 'leave_requests':
            typeIcon = '<i class="fas fa-calendar-times me-2"></i>';
            break;
          case 'error':
            typeIcon = '<i class="fas fa-exclamation-circle text-danger me-2"></i>';
            break;
          default:
            typeIcon = '<i class="fas fa-lightbulb me-2"></i>';
        }

        li.innerHTML = `
          <div class="ms-2 me-auto">
            <div class="fw-bold">${typeIcon}${s.building || 'Unknown Building'}</div>
            ${s.note || 'No additional notes'}
          </div>
          <span class="badge bg-${s.priority === 'high' ? 'danger' : s.priority === 'medium' ? 'warning' : 'secondary'} rounded-pill">
            ${s.priority || 'normal'}
          </span>
        `;
        list.appendChild(li);
      });
    }
  } catch (error) {
    console.error("Error loading suggestions:", error);

    // Show error state
    if (list) {
      list.innerHTML = `
        <li class="list-group-item list-group-item-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>Failed to load suggestions.
          <button class="btn btn-sm btn-link p-0 ms-2" onclick="loadSuggestions()">Retry</button>
        </li>
      `;
    }
  }
}

// Event listeners
document.getElementById("refreshDashboard")?.addEventListener("click", () => {
  loadBuildingCheckins();
  loadSuggestions();
});

document.getElementById("refreshSuggestions")?.addEventListener("click", loadSuggestions);

// Initialize dashboard on page load
document.addEventListener("DOMContentLoaded", () => {
  loadBuildingCheckins();
  loadSuggestions();
});

