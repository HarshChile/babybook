/**
 * Scrapbook Calendar Logic
 * BabyBook / TinyTales
 */

let currentDate = new Date();
let eventsCache = {};
let selectedDateStr = "";

const monthNames = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

document.addEventListener("DOMContentLoaded", () => {
  const calDateInput = document.getElementById("calDate");
  const todayStr = new Date().toISOString().split("T")[0];
  if (calDateInput) {
    calDateInput.value = todayStr;
    selectedDateStr = todayStr;
  }
  loadAndRenderCalendar();
});

function prevMonth() {
  currentDate.setMonth(currentDate.getMonth() - 1);
  loadAndRenderCalendar();
}

function nextMonth() {
  currentDate.setMonth(currentDate.getMonth() + 1);
  loadAndRenderCalendar();
}

async function loadAndRenderCalendar() {
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth() + 1;

  document.getElementById("currentMonthYear").textContent = `${monthNames[month - 1]} ${year}`;

  try {
    const res = await fetch(`api/calendar.php?year=${year}&month=${month}`);
    const data = await res.json();
    if (data.success) {
      eventsCache = data.events || {};
    }
  } catch (err) {
    console.error("Error fetching calendar events:", err);
    eventsCache = {};
  }

  buildCalendarGrid(year, month - 1);
  if (selectedDateStr) {
    showDayEvents(selectedDateStr);
  }
}

function buildCalendarGrid(year, month) {
  const tbody = document.getElementById("calendarBody");
  tbody.innerHTML = "";

  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const today = new Date();

  let day = 1;
  let finished = false;

  for (let r = 0; r < 6; r++) {
    const tr = document.createElement("tr");

    for (let c = 0; c < 7; c++) {
      const td = document.createElement("td");

      if ((r === 0 && c < firstDay) || day > daysInMonth) {
        td.innerHTML = "";
      } else {
        const curDay = day;
        const mm = String(month + 1).padStart(2, "0");
        const dd = String(curDay).padStart(2, "0");
        const dateKey = `${year}-${mm}-${dd}`;

        const isToday = (today.getFullYear() === year && today.getMonth() === month && today.getDate() === curDay);
        if (isToday) td.classList.add("today");

        if (selectedDateStr === dateKey) {
          td.classList.add("active-day");
        }

        let eventsHtml = "";
        const dayEvents = eventsCache[dateKey] || [];
        if (dayEvents.length > 0) {
          const memoriesCount = dayEvents.filter(e => e.type === "memory").length;
          const milestonesCount = dayEvents.filter(e => e.type === "milestone").length;

          if (memoriesCount > 0) {
            eventsHtml += `<span class="cal-event-dot">📸 ${memoriesCount}</span>`;
          }
          if (milestonesCount > 0) {
            eventsHtml += `<span class="cal-event-dot cal-milestone-dot">⭐ ${milestonesCount}</span>`;
          }
        }

        td.innerHTML = `
          <div class="day-num">${curDay}</div>
          <div class="day-events">${eventsHtml}</div>
        `;

        td.onclick = () => selectDay(dateKey, td);
        day++;
      }

      tr.appendChild(td);
      if (day > daysInMonth && c === 6) {
        finished = true;
      }
    }

    tbody.appendChild(tr);
    if (day > daysInMonth) break;
  }
}

function selectDay(dateStr, tdElem) {
  selectedDateStr = dateStr;
  document.querySelectorAll("td").forEach(t => t.classList.remove("active-day"));
  if (tdElem) tdElem.classList.add("active-day");

  const calDateInput = document.getElementById("calDate");
  if (calDateInput) calDateInput.value = dateStr;

  showDayEvents(dateStr);
}

function showDayEvents(dateStr) {
  const header = document.getElementById("previewDayHeader");
  const list = document.getElementById("previewDayList");

  header.textContent = `Memories on ${dateStr}:`;
  list.innerHTML = "";

  const events = eventsCache[dateStr] || [];

  if (events.length === 0) {
    list.innerHTML = `<p style="font-size:12px; color:#888;">No memories on this date yet.</p>`;
    return;
  }

  events.forEach(e => {
    const item = document.createElement("div");
    item.className = "day-preview-item";
    const icon = e.type === "milestone" ? "⭐ Milestone: " : "📸 ";
    item.innerHTML = `
      <strong>${icon}${escapeHtml(e.title)}</strong>
      ${e.description ? `<br><small style="color:#666;">${escapeHtml(e.description)}</small>` : ''}
    `;
    list.appendChild(item);
  });
}

async function saveCalendarMemory() {
  const title = document.getElementById("calTitle").value.trim();
  const date = document.getElementById("calDate").value;
  const desc = document.getElementById("calText").value.trim();
  const fileInput = document.getElementById("calImage");
  const saveBtn = document.getElementById("saveMemoryBtn");

  if (!title) {
    alert("Please enter a memory title");
    return;
  }

  if (!date) {
    alert("Please select a date");
    return;
  }

  const formData = new FormData();
  formData.append("title", title);
  formData.append("date", date);
  formData.append("desc", desc);
  if (fileInput.files.length > 0) {
    formData.append("image", fileInput.files[0]);
  }

  saveBtn.disabled = true;
  saveBtn.textContent = "Saving...";

  try {
    const res = await fetch("api/calendar.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      document.getElementById("calTitle").value = "";
      document.getElementById("calText").value = "";
      fileInput.value = "";
      loadAndRenderCalendar();
      alert("Memory saved to calendar! 📅");
    } else {
      alert("Error saving memory: " + (data.error || "Unknown error"));
    }
  } catch (err) {
    console.error("Calendar save error:", err);
    alert("Failed to save memory to server.");
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = "Save Memory";
  }
}

function escapeHtml(text) {
  if (!text) return "";
  return text.replace(/[&<>"']/g, function(m) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[m];
  });
}
