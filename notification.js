/**
 * Notifications Management Logic
 * TinyTales BabyBook
 */

let allNotifications = [];
let currentFilter = "all";

document.addEventListener("DOMContentLoaded", async () => {
  await API.requireAuth();
  loadNotifications();
});

async function loadNotifications() {
  const list = document.getElementById("notificationList");

  try {
    const data = await API.fetch("api/notifications.php");

    if (data.success) {
      allNotifications = data.notifications || [];
      renderNotifications();
      updateBadge(data.unread_count);
    } else {
      list.innerHTML = `<div style="padding:20px; color:#7b1620;">Failed to load notifications: ${data.error || 'Unknown error'}</div>`;
    }
  } catch (err) {
    console.error("Notifications fetch error:", err);
    list.innerHTML = `
      <div style="background:#FFF5F5; border:1px solid #F4B7B7; border-radius:15px; padding:20px; text-align:center; color:#771212;">
        <h4>⚠️ Backend Notice</h4>
        <p style="font-size:13px; opacity:0.9;">${escapeHtml(err.message)}</p>
      </div>
    `;
  }
}

function renderNotifications() {
  const list = document.getElementById("notificationList");
  list.innerHTML = "";

  const filtered = allNotifications.filter(n => {
    if (currentFilter === "all") return true;
    if (currentFilter === "unread") return !n.is_read;
    return (n.type || "").toLowerCase() === currentFilter.toLowerCase();
  });

  if (filtered.length === 0) {
    list.innerHTML = `
      <div style="padding:40px; text-align:center; color:#888;">
        <p style="font-size:16px;">No notifications in this view 🔕</p>
      </div>
    `;
    return;
  }

  const icons = {
    milestones: "⭐",
    memories: "📷",
    capsule: "💌",
    reminder: "📅",
    tip: "🔔"
  };

  filtered.forEach(n => {
    const div = document.createElement("div");
    const unreadClass = n.is_read ? "" : "unread";
    div.className = `notification ${unreadClass}`.trim();
    div.dataset.type = n.type || "tip";

    const icon = icons[n.type] || "🔔";
    const timeDisplay = n.created_at ? formatTimeAgo(n.created_at) : "Recently";

    div.innerHTML = `
      <div class="icon">${icon}</div>
      <div class="content">
        <strong>${escapeHtml(n.title)}</strong>
        <p>${escapeHtml(n.message)}</p>
        <small>${timeDisplay}</small>
      </div>
      <div class="actions">
        ${!n.is_read ? `<button title="Mark Read" onclick="markRead('${n._id}')">✔</button>` : ''}
        <button title="Delete" onclick="deleteNotification('${n._id}')">🗑</button>
      </div>
    `;

    list.appendChild(div);
  });
}

async function markRead(id) {
  try {
    const formData = new FormData();
    formData.append("action", "mark_read");
    formData.append("id", id);

    const data = await API.fetch("api/notifications.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      const target = allNotifications.find(n => n._id === id);
      if (target) target.is_read = true;
      renderNotifications();
      updateBadge();
    }
  } catch (err) {
    console.error("Mark read error:", err);
  }
}

async function deleteNotification(id) {
  try {
    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);

    const data = await API.fetch("api/notifications.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      allNotifications = allNotifications.filter(n => n._id !== id);
      renderNotifications();
      updateBadge();
    }
  } catch (err) {
    console.error("Delete notification error:", err);
  }
}

async function markAllRead() {
  try {
    const formData = new FormData();
    formData.append("action", "mark_all_read");

    const data = await API.fetch("api/notifications.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      allNotifications.forEach(n => n.is_read = true);
      renderNotifications();
      updateBadge(0);
    }
  } catch (err) {
    console.error("Mark all read error:", err);
  }
}

async function clearAll() {
  if (!confirm("Are you sure you want to clear all notifications?")) return;

  try {
    const formData = new FormData();
    formData.append("action", "clear_all");

    const data = await API.fetch("api/notifications.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      allNotifications = [];
      renderNotifications();
      updateBadge(0);
    }
  } catch (err) {
    console.error("Clear notifications error:", err);
  }
}

function filterNotifications(type, evt) {
  currentFilter = type;
  const allBtns = document.querySelectorAll(".tabs button");
  allBtns.forEach(b => b.classList.remove("active"));
  if (evt && evt.target) {
    evt.target.classList.add("active");
  }
  renderNotifications();
}

function updateBadge(explicitCount) {
  const count = (explicitCount !== undefined)
    ? explicitCount
    : allNotifications.filter(n => !n.is_read).length;

  const badge = document.getElementById("newCount");
  if (badge) {
    badge.textContent = count ? `${count} new` : "";
    badge.style.display = count ? "inline-block" : "none";
  }
}

function formatTimeAgo(dateStr) {
  try {
    const d = new Date(dateStr.replace(/-/g, "/"));
    const now = new Date();
    const diffSec = Math.floor((now - d) / 1000);
    if (diffSec < 60) return "Just now";
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;
    return `${Math.floor(diffSec / 86400)}d ago`;
  } catch (e) {
    return dateStr;
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
