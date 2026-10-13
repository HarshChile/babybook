/**
 * Baby Profile Management
 * BabyBook / TinyTales
 */

document.addEventListener("DOMContentLoaded", () => {
  loadProfile();
});

async function loadProfile() {
  try {
    const res = await fetch("api/profile.php");
    const data = await res.json();

    if (data.success && data.profile) {
      const p = data.profile;
      document.getElementById("babyName").value = p.baby_name || "";
      document.getElementById("birthDate").value = p.birth_date || "";
      document.getElementById("parentNames").value = p.parent_names || "";
      document.getElementById("bio").value = p.bio || "";

      if (p.avatar_path) {
        setAvatarImage(p.avatar_path);
      }
    }
  } catch (err) {
    console.error("Error loading profile:", err);
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
    const res = await fetch("api/profile.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      alert("Baby profile saved successfully! 👶🎉");
      window.location.href = "dashboard.html";
    } else {
      alert("Error: " + (data.error || "Could not save profile"));
    }
  } catch (err) {
    console.error("Profile save error:", err);
    alert("Failed to save profile to server.");
  } finally {
    btn.disabled = false;
    btn.textContent = "💾 Save Profile";
  }
}
