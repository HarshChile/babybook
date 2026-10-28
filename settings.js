/**
 * Settings Logic
 * TinyTales BabyBook
 */

document.addEventListener("DOMContentLoaded", async () => {
  await API.requireAuth();
  loadSettings();
});

async function loadSettings() {
  try {
    const data = await API.fetch("api/settings.php");

    if (data.success && data.settings) {
      const s = data.settings;
      if (document.getElementById("babyNameInput")) {
        document.getElementById("babyNameInput").value = s.baby_name || "";
      }
      if (document.getElementById("parentEmailInput")) {
        document.getElementById("parentEmailInput").value = s.parent_email || "";
      }
    }
  } catch (err) {
    console.error("Error loading settings:", err);
  }
}

async function saveSettings() {
  const btn = document.getElementById("saveSettingsBtn");
  const babyName = document.getElementById("babyNameInput").value.trim();
  const parentEmail = document.getElementById("parentEmailInput").value.trim();

  const payload = {
    baby_name: babyName,
    parent_email: parentEmail
  };

  if (btn) {
    btn.disabled = true;
    btn.textContent = "Saving...";
  }

  try {
    const data = await API.fetch("api/settings.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    if (data.success) {
      alert("Settings saved successfully! ⚙️");
    } else {
      alert("Error saving settings: " + (data.error || "Unknown error"));
    }
  } catch (err) {
    console.error("Save settings error:", err);
    alert(err.message || "Failed to save settings to server.");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = "Save Changes";
    }
  }
}

/**
 * Handle password change for authenticated user
 */
async function changePassword() {
  const currentPassword = document.getElementById("currentPassword").value;
  const newPassword = document.getElementById("newPassword").value;
  const confirmNewPassword = document.getElementById("confirmNewPassword").value;
  const btn = document.getElementById("changePwdBtn");
  const msgBox = document.getElementById("pwdMsg");

  msgBox.style.display = "none";

  if (newPassword.length < 6) {
    msgBox.style.display = "block";
    msgBox.style.background = "#FFF0F2";
    msgBox.style.color = "#8b0f1f";
    msgBox.textContent = "New password must be at least 6 characters.";
    return;
  }

  if (newPassword !== confirmNewPassword) {
    msgBox.style.display = "block";
    msgBox.style.background = "#FFF0F2";
    msgBox.style.color = "#8b0f1f";
    msgBox.textContent = "New password and confirmation do not match.";
    return;
  }

  btn.disabled = true;
  btn.textContent = "Updating...";

  const formData = new FormData();
  formData.append("current_password", currentPassword);
  formData.append("new_password", newPassword);
  formData.append("confirm_password", confirmNewPassword);

  try {
    const data = await API.fetch("api/auth.php?action=change_password", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      msgBox.style.display = "block";
      msgBox.style.background = "#EBFBF2";
      msgBox.style.color = "#1D7544";
      msgBox.textContent = "🎉 Password updated successfully!";
      document.getElementById("changePasswordForm").reset();
    } else {
      msgBox.style.display = "block";
      msgBox.style.background = "#FFF0F2";
      msgBox.style.color = "#8b0f1f";
      msgBox.textContent = data.error || "Failed to change password.";
    }
  } catch (err) {
    console.error("Change password error:", err);
    msgBox.style.display = "block";
    msgBox.style.background = "#FFF0F2";
    msgBox.style.color = "#8b0f1f";
    msgBox.textContent = err.message || "Failed to connect to server.";
  } finally {
    btn.disabled = false;
    btn.textContent = "🔑 Update Password";
  }
}
