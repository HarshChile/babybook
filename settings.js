/**
 * Settings Logic
 * BabyBook / TinyTales
 */

document.addEventListener("DOMContentLoaded", () => {
  loadSettings();
});

async function loadSettings() {
  try {
    const res = await fetch("api/settings.php");
    const data = await res.json();

    if (data.success && data.settings) {
      const s = data.settings;
      if (document.getElementById("babyNameInput")) {
        document.getElementById("babyNameInput").value = s.baby_name || "";
      }
      if (document.getElementById("parentEmailInput")) {
        document.getElementById("parentEmailInput").value = s.parent_email || "";
      }
      if (document.getElementById("emailToggle")) {
        document.getElementById("emailToggle").checked = !!s.email_alerts;
      }
      if (document.getElementById("milestoneToggle")) {
        document.getElementById("milestoneToggle").checked = !!s.milestone_reminders;
      }
      if (document.getElementById("privacyToggle")) {
        document.getElementById("privacyToggle").checked = !!s.private_memories;
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
  const emailAlerts = document.getElementById("emailToggle").checked;
  const milestoneReminders = document.getElementById("milestoneToggle").checked;
  const privateMemories = document.getElementById("privacyToggle").checked;

  const payload = {
    baby_name: babyName,
    parent_email: parentEmail,
    email_alerts: emailAlerts,
    milestone_reminders: milestoneReminders,
    private_memories: privateMemories
  };

  if (btn) {
    btn.disabled = true;
    btn.textContent = "Saving...";
  }

  try {
    const res = await fetch("api/settings.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.success) {
      alert("Settings saved successfully! ⚙️");
    } else {
      alert("Error saving settings: " + (data.error || "Unknown error"));
    }
  } catch (err) {
    console.error("Save settings error:", err);
    alert("Failed to save settings to server.");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = "Save Changes";
    }
  }
}
