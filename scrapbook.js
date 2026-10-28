/**
 * Scrapbook Logic
 * BabyBook / TinyTales
 */

let allScrapbookItems = [];
let currentPage = 1;
const itemsPerPage = 4;

document.addEventListener("DOMContentLoaded", async () => {
  await API.requireAuth();
  const sbDateInput = document.getElementById("sbDate");
  if (sbDateInput) {
    sbDateInput.value = new Date().toISOString().split("T")[0];
  }
  loadScrapbook();
});

async function loadScrapbook() {
  const container = document.getElementById("scrapbookEntriesContainer");

  try {
    const data = await API.fetch("api/scrapbook.php");

    if (data.success) {
      allScrapbookItems = data.items || [];
      renderPage();
    } else {
      container.innerHTML = `<p class="empty">Error loading scrapbook: ${data.error}</p>`;
    }
  } catch (err) {
    console.error("Error fetching scrapbook:", err);
    container.innerHTML = `
      <div style="background:#FFF5F5; border:1px solid #F4B7B7; border-radius:15px; padding:20px; text-align:center; color:#771212;">
        <h4>⚠️ Backend Connection Notice</h4>
        <p style="font-size:13px; opacity:0.9;">${escapeHtml(err.message)}</p>
      </div>
    `;
  }
}

function renderPage() {
  const container = document.getElementById("scrapbookEntriesContainer");
  const totalPages = Math.max(1, Math.ceil(allScrapbookItems.length / itemsPerPage));

  if (currentPage > totalPages) currentPage = totalPages;
  if (currentPage < 1) currentPage = 1;

  document.getElementById("pageIndicator").textContent = `Page ${currentPage} of ${totalPages}`;
  document.getElementById("notebookHeader").textContent = `Notebook — Page ${currentPage}`;

  container.innerHTML = "";

  if (allScrapbookItems.length === 0) {
    container.innerHTML = `<p class="empty">No memories yet. Add your first scrapbook photo and note!</p>`;
    return;
  }

  const startIdx = (currentPage - 1) * itemsPerPage;
  const pageItems = allScrapbookItems.slice(startIdx, startIdx + itemsPerPage);

  pageItems.forEach(item => {
    const card = document.createElement("div");
    card.className = "polaroid-card";

    const photoSrc = item.photo_path || "https://images.unsplash.com/photo-1519689680058-324335c77eba?w=400";

    card.innerHTML = `
      <div class="polaroid-tape"></div>
      <img src="${photoSrc}" alt="${escapeHtml(item.title)}" class="polaroid-img" onerror="this.src='https://via.placeholder.com/300?text=Photo'">
      <div class="polaroid-title">${escapeHtml(item.title)}</div>
      <span class="polaroid-mood">${escapeHtml(item.mood || '😊 Happy')}</span>
      <div class="polaroid-note">${escapeHtml(item.note || '')}</div>
      <div class="polaroid-date">📅 ${item.date || ''}</div>
    `;

    container.appendChild(card);
  });
}

function prevPage() {
  if (currentPage > 1) {
    currentPage--;
    renderPage();
  }
}

function nextPage() {
  const totalPages = Math.max(1, Math.ceil(allScrapbookItems.length / itemsPerPage));
  if (currentPage < totalPages) {
    currentPage++;
    renderPage();
  }
}

async function addScrapbookEntry() {
  const title = document.getElementById("sbTitle").value.trim();
  const date = document.getElementById("sbDate").value;
  const mood = document.getElementById("sbMood").value;
  const note = document.getElementById("sbNote").value.trim();
  const fileInput = document.getElementById("sbImage");
  const addBtn = document.getElementById("addScrapbookBtn");

  if (!title) {
    alert("Please enter a title");
    return;
  }

  if (!date) {
    alert("Please select a date");
    return;
  }

  const formData = new FormData();
  formData.append("title", title);
  formData.append("date", date);
  formData.append("mood", mood);
  formData.append("note", note);

  if (fileInput.files.length > 0) {
    formData.append("image", fileInput.files[0]);
  }

  addBtn.disabled = true;
  addBtn.textContent = "Saving...";

  try {
    const data = await API.fetch("api/scrapbook.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      document.getElementById("sbTitle").value = "";
      document.getElementById("sbNote").value = "";
      fileInput.value = "";
      currentPage = 1;
      loadScrapbook();
    } else {
      alert("Error: " + (data.error || "Could not save scrapbook entry"));
    }
  } catch (err) {
    console.error("Save error:", err);
    alert(err.message || "Failed to save scrapbook entry.");
  } finally {
    addBtn.disabled = false;
    addBtn.textContent = "Add to Scrapbook";
  }
}

async function clearScrapbook() {
  if (!confirm("Are you sure you want to clear all scrapbook entries?")) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "clear");

  try {
    const data = await API.fetch("api/scrapbook.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      currentPage = 1;
      loadScrapbook();
    }
  } catch (err) {
    console.error("Clear error:", err);
    alert(err.message);
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
