/**
 * Time Capsule Logic
 * BabyBook / TinyTales
 */

document.addEventListener("DOMContentLoaded", () => {
  const saveBtn = document.getElementById("saveCapsuleBtn");
  if (saveBtn) {
    saveBtn.addEventListener("click", addCapsule);
  }

  // Default unlock date 5 years from now
  const unlockInput = document.getElementById("unlockDate");
  if (unlockInput) {
    const future = new Date();
    future.setFullYear(future.getFullYear() + 5);
    unlockInput.value = future.toISOString().split("T")[0];
  }

  loadCapsules();
});

async function loadCapsules() {
  const box = document.getElementById("capsuleBox");

  try {
    const res = await fetch("api/capsules.php");
    const data = await res.json();

    if (data.success) {
      renderCapsules(data.capsules || []);
    } else {
      box.innerHTML = `<p>Error loading capsules: ${data.error}</p>`;
    }
  } catch (err) {
    console.error("Error fetching capsules:", err);
    box.innerHTML = "<p>Unable to connect to server.</p>";
  }
}

function renderCapsules(capsules) {
  const box = document.getElementById("capsuleBox");
  box.innerHTML = "";

  if (capsules.length === 0) {
    box.innerHTML = "<p>No capsules yet. Leave a surprise for the future!</p>";
    return;
  }

  capsules.forEach(c => {
    const div = document.createElement("div");

    if (c.is_locked) {
      div.className = "capsule locked";
      div.innerHTML = `
        <button class="delete-capsule-btn" onclick="deleteCapsule('${c._id}')" title="Delete">🗑️</button>
        <h4>${escapeHtml(c.title)}</h4>
        <p class="lock">🔒 Locked until ${c.unlock_date}</p>
        <small style="color:#888;">Content will be revealed on this special day!</small>
      `;
    } else {
      let mediaHTML = "";
      if (c.media_path) {
        const type = c.media_type || "";
        if (type.startsWith("video") || c.media_path.endsWith(".mp4") || c.media_path.endsWith(".mov")) {
          mediaHTML = `<video controls src="${c.media_path}"></video>`;
        } else if (type.startsWith("audio") || c.media_path.endsWith(".mp3") || c.media_path.endsWith(".wav")) {
          mediaHTML = `<audio controls src="${c.media_path}"></audio>`;
        } else {
          mediaHTML = `<img src="${c.media_path}" alt="Capsule Memory">`;
        }
      }

      div.className = "capsule";
      div.innerHTML = `
        <button class="delete-capsule-btn" onclick="deleteCapsule('${c._id}')" title="Delete">🗑️</button>
        <h4>${escapeHtml(c.title)}</h4>
        <p>${escapeHtml(c.message)}</p>
        ${mediaHTML}
        <p class="unlock">🔓 Unlocked on ${c.unlock_date}!</p>
      `;
    }

    box.appendChild(div);
  });
}

async function addCapsule() {
  const title = document.getElementById("title").value.trim();
  const message = document.getElementById("message").value.trim();
  const unlockDate = document.getElementById("unlockDate").value;
  const mediaInput = document.getElementById("media");
  const saveBtn = document.getElementById("saveCapsuleBtn");

  if (!title || !message || !unlockDate) {
    alert("Please fill title, message, and unlock date");
    return;
  }

  const formData = new FormData();
  formData.append("title", title);
  formData.append("message", message);
  formData.append("unlockDate", unlockDate);

  if (mediaInput.files.length > 0) {
    formData.append("media", mediaInput.files[0]);
  }

  saveBtn.disabled = true;
  saveBtn.textContent = "Locking in capsule...";

  try {
    const res = await fetch("api/capsules.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      document.getElementById("title").value = "";
      document.getElementById("message").value = "";
      mediaInput.value = "";
      loadCapsules();
      alert("Time capsule locked away safely! 💌");
    } else {
      alert("Error: " + (data.error || "Could not save capsule"));
    }
  } catch (err) {
    console.error("Save error:", err);
    alert("Failed to save capsule to server.");
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = "Lock in Capsule 🔒";
  }
}

async function deleteCapsule(id) {
  if (!confirm("Are you sure you want to delete this capsule?")) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("id", id);

  try {
    const res = await fetch("api/capsules.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      loadCapsules();
    }
  } catch (err) {
    console.error("Delete error:", err);
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
