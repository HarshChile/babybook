/**
 * Memories Timeline & Upload Management
 * BabyBook / TinyTales
 */

let currentMemories = [];
let currentSelectedMemory = null;
let showFavoritesOnly = false;

document.addEventListener("DOMContentLoaded", async () => {
  await API.requireAuth();
  // Set default date input to today
  const dateInput = document.getElementById("date");
  if (dateInput) {
    dateInput.value = new Date().toISOString().split("T")[0];
  }
  loadMemories();
});

function openModal() {
  document.getElementById("addModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("addModal").style.display = "none";
}

function toggleFavoritesOnly() {
  showFavoritesOnly = !showFavoritesOnly;
  const btn = document.getElementById("favFilterBtn");
  if (showFavoritesOnly) {
    btn.style.background = "#d45a6a";
    btn.style.color = "#fff";
  } else {
    btn.style.background = "#fff";
    btn.style.color = "inherit";
  }
  loadMemories();
}

async function loadMemories() {
  const container = document.getElementById("memories");
  const sort = document.getElementById("sortOrder") ? document.getElementById("sortOrder").value : "desc";
  const category = document.getElementById("filterCategory") ? document.getElementById("filterCategory").value : "all";

  let url = `api/memories.php?sort=${encodeURIComponent(sort)}&category=${encodeURIComponent(category)}`;
  if (showFavoritesOnly) {
    url += "&favorite=1";
  }

  try {
    const data = await API.fetch(url);

    if (data.success) {
      currentMemories = data.memories || [];
      renderTimeline(currentMemories);
    } else {
      container.innerHTML = `<p style="color:#7b1620;">Failed to load memories: ${data.error || 'Unknown error'}</p>`;
    }
  } catch (err) {
    console.error("Error fetching memories:", err);
    container.innerHTML = `
      <div style="grid-column: 1 / -1; background:#FFF5F5; border: 1px solid #F4B7B7; border-radius:16px; padding:24px; text-align:center; color:#771212;">
        <h4 style="margin-bottom:6px;">⚠️ Backend Connection Notice</h4>
        <p style="font-size:14px; opacity:0.9;">${escapeHtml(err.message)}</p>
      </div>
    `;
  }
}

function renderTimeline(memories) {
  const container = document.getElementById("memories");
  container.innerHTML = "";

  if (memories.length === 0) {
    container.innerHTML = `
      <div style="grid-column: 1 / -1; background:#fff; border-radius:18px; padding:40px; text-align:center; color:#7b1620;">
        <h3 style="margin-bottom:8px;">No memories found 🧸</h3>
        <p>Start recording your baby's journey by uploading your first photo memory.</p>
        <button class="add-btn-primary" style="margin-top:16px;" onclick="openModal()">+ Add Memory Now</button>
      </div>
    `;
    return;
  }

  memories.forEach(m => {
    const card = document.createElement("div");
    card.className = "memory-card";
    card.onclick = () => openPreview(m);

    const photoSrc = m.photo_url || m.photo_path || "https://via.placeholder.com/600x400?text=Baby+Photo";
    const tag = m.category || "Milestone";
    const favBadge = m.is_favorite ? '<span class="card-fav">❤️</span>' : '';

    card.innerHTML = `
      <div class="card-img-wrap">
        <img src="${photoSrc}" alt="${escapeHtml(m.title)}" onerror="this.src='https://via.placeholder.com/600x400?text=Photo'">
        <span class="card-tag">${escapeHtml(tag)}</span>
        ${favBadge}
      </div>
      <h3>${escapeHtml(m.title)}</h3>
      <p>${escapeHtml(m.description || '')}</p>
      <div class="date">📅 ${m.date || ''}</div>
    `;

    container.appendChild(card);
  });
}

async function addMemory() {
  const title = document.getElementById("title").value.trim();
  const desc = document.getElementById("desc").value.trim();
  const date = document.getElementById("date").value;
  const category = document.getElementById("category").value;
  const imgInput = document.getElementById("image");
  const saveBtn = document.getElementById("saveMemoryBtn");

  if (!title) {
    alert("Please enter a memory title");
    return;
  }

  if (!date) {
    alert("Please select a date for this memory");
    return;
  }

  if (!imgInput.files || imgInput.files.length === 0) {
    alert("Please select a photo to upload");
    return;
  }

  const formData = new FormData();
  formData.append("title", title);
  formData.append("desc", desc);
  formData.append("date", date);
  formData.append("category", category);
  formData.append("image", imgInput.files[0]);

  saveBtn.disabled = true;
  saveBtn.textContent = "Uploading photo...";

  try {
    const data = await API.fetch("api/memories.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      // Clear fields
      document.getElementById("title").value = "";
      document.getElementById("desc").value = "";
      imgInput.value = "";
      closeModal();
      loadMemories();
    } else {
      alert("Error saving memory: " + (data.error || "Unknown error"));
    }
  } catch (err) {
    console.error("Upload error:", err);
    alert(err.message || "Failed to upload photo to server.");
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = "Save to Timeline";
  }
}

function openPreview(memory) {
  currentSelectedMemory = memory;

  const photoSrc = memory.photo_url || memory.photo_path || "https://via.placeholder.com/600x400?text=Baby+Photo";
  document.getElementById("pImg").src = photoSrc;
  document.getElementById("pTitle").innerText = memory.title;
  document.getElementById("pDesc").innerText = memory.description || "No description provided.";
  document.getElementById("pDate").innerText = "📅 " + (memory.date || "Unknown date");
  document.getElementById("pTag").innerText = memory.category || "Milestone";

  const favBtn = document.getElementById("pFavBtn");
  if (memory.is_favorite) {
    favBtn.textContent = "❤️ Favorited";
    favBtn.style.background = "#FFD1D8";
  } else {
    favBtn.textContent = "🤍 Add Favorite";
    favBtn.style.background = "#FFF0F2";
  }

  document.getElementById("preview").style.display = "flex";
}

function closePreview() {
  document.getElementById("preview").style.display = "none";
  currentSelectedMemory = null;
}

async function toggleFavoriteCurrent() {
  if (!currentSelectedMemory || !currentSelectedMemory._id) return;

  const formData = new FormData();
  formData.append("action", "toggle_favorite");
  formData.append("id", currentSelectedMemory._id);

  try {
    const data = await API.fetch("api/memories.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      currentSelectedMemory.is_favorite = data.is_favorite;
      const favBtn = document.getElementById("pFavBtn");
      if (data.is_favorite) {
        favBtn.textContent = "❤️ Favorited";
        favBtn.style.background = "#FFD1D8";
      } else {
        favBtn.textContent = "🤍 Add Favorite";
        favBtn.style.background = "#FFF0F2";
      }
      loadMemories();
    }
  } catch (err) {
    console.error("Error toggling favorite:", err);
    alert(err.message);
  }
}

async function deleteCurrentMemory() {
  if (!currentSelectedMemory || !currentSelectedMemory._id) return;

  if (!confirm(`Are you sure you want to delete "${currentSelectedMemory.title}"?`)) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("id", currentSelectedMemory._id);

  try {
    const data = await API.fetch("api/memories.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      closePreview();
      loadMemories();
    } else {
      alert("Delete failed: " + (data.error || "Unknown error"));
    }
  } catch (err) {
    console.error("Delete error:", err);
    alert(err.message || "Could not delete memory.");
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
