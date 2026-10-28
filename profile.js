/**
 * Baby Profile Management
 * TinyTales BabyBook
 */

let activeBabyId = null;
let ownedBabies = [];

document.addEventListener("DOMContentLoaded", async () => {
  await API.requireAuth();
  loadProfile();
});

async function loadProfile() {
  try {
    const data = await API.fetch("api/profile.php");

    if (data.success) {
      ownedBabies = data.babies || [];
      const p = data.profile || data.active_baby;

      if (p) {
        activeBabyId = p._id;
        document.getElementById("babyName").value = p.baby_name || "";
        document.getElementById("birthDate").value = p.birth_date || "";
        document.getElementById("parentNames").value = p.parent_names || "";
        document.getElementById("bio").value = p.bio || "";

        if (p.avatar_path) {
          setAvatarImage(p.avatar_path);
        } else {
          clearAvatarImage();
        }
      }

      updateBabySelector();
    }
  } catch (err) {
    console.error("Error loading profile:", err);
  }
}

function updateBabySelector() {
  const sel = document.getElementById("babySelector");
  if (!sel) return;

  sel.innerHTML = "";
  if (ownedBabies.length === 0) {
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "No child profiles";
    sel.appendChild(opt);
    return;
  }

  ownedBabies.forEach(b => {
    const opt = document.createElement("option");
    opt.value = b._id;
    opt.textContent = b.baby_name || "Child";
    if (b._id === activeBabyId) {
      opt.selected = true;
    }
    sel.appendChild(opt);
  });
}

async function switchBaby(babyId) {
  if (!babyId || babyId === activeBabyId) return;

  try {
    const formData = new FormData();
    formData.append("action", "select");
    formData.append("id", babyId);

    const data = await API.fetch("api/profile.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      loadProfile();
    } else {
      alert("Error switching baby: " + (data.error || "Access denied"));
    }
  } catch (err) {
    console.error("Switch baby error:", err);
    alert(err.message || "Failed to switch child profile");
  }
}

async function promptNewBaby() {
  const babyName = prompt("Enter new baby's name:");
  if (!babyName || !babyName.trim()) return;

  const birthDate = prompt("Enter birth date (YYYY-MM-DD):", new Date().toISOString().split("T")[0]);

  const formData = new FormData();
  formData.append("action", "create");
  formData.append("babyName", babyName.trim());
  formData.append("birthDate", birthDate || "");

  try {
    const data = await API.fetch("api/profile.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      alert(`🎉 Profile created for ${babyName.trim()}!`);
      loadProfile();
    } else {
      alert("Error creating baby profile: " + (data.error || "Failed"));
    }
  } catch (err) {
    console.error("Create baby error:", err);
    alert(err.message);
  }
}

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      setAvatarImage(e.target.result);
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function setAvatarImage(src) {
  const display = document.getElementById("avatarDisplay");
  const emoji = document.getElementById("avatarEmoji");
  if (emoji) emoji.style.display = "none";

  let img = display.querySelector("img");
  if (!img) {
    img = document.createElement("img");
    display.insertBefore(img, display.firstChild);
  }
  img.src = src;
}

function clearAvatarImage() {
  const display = document.getElementById("avatarDisplay");
  const emoji = document.getElementById("avatarEmoji");
  if (emoji) emoji.style.display = "block";
  const img = display.querySelector("img");
  if (img) img.remove();
}

async function saveProfile() {
  const babyName = document.getElementById("babyName").value.trim();
  const birthDate = document.getElementById("birthDate").value;
  const parentNames = document.getElementById("parentNames").value.trim();
  const bio = document.getElementById("bio").value.trim();
  const avatarFile = document.getElementById("avatarFile");
  const btn = document.getElementById("saveProfileBtn");

  if (!babyName) {
    alert("Please enter baby's name");
    return;
  }

  const formData = new FormData();
  if (activeBabyId) {
    formData.append("id", activeBabyId);
  }
  formData.append("babyName", babyName);
  formData.append("birthDate", birthDate);
  formData.append("parentNames", parentNames);
  formData.append("bio", bio);

  if (avatarFile.files && avatarFile.files.length > 0) {
    formData.append("avatar", avatarFile.files[0]);
  }

  btn.disabled = true;
  btn.textContent = "Saving...";

  try {
    const data = await API.fetch("api/profile.php", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      alert("Baby profile saved successfully! 👶🎉");
      loadProfile();
    } else {
      alert("Error: " + (data.error || "Could not save profile"));
    }
  } catch (err) {
    console.error("Profile save error:", err);
    alert(err.message || "Failed to save profile to server.");
  } finally {
    btn.disabled = false;
    btn.textContent = "💾 Save Baby Profile";
  }
}
