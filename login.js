/**
 * Login Form Logic
 * BabyBook / TinyTales
 */

document.getElementById("loginForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  const email = document.getElementById("email");
  const password = document.getElementById("password");
  const emailError = document.getElementById("emailError");
  const passwordError = document.getElementById("passwordError");
  const btn = document.getElementById("loginSubmitBtn");

  emailError.textContent = "";
  passwordError.textContent = "";

  let isValid = true;
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (email.value.trim() === "") {
    emailError.textContent = "Email is required.";
    isValid = false;
  } else if (!emailPattern.test(email.value)) {
    emailError.textContent = "Enter a valid email address.";
    isValid = false;
  }

  if (password.value === "") {
    passwordError.textContent = "Password is required.";
    isValid = false;
  } else if (password.value.length < 6) {
    passwordError.textContent = "Password must be at least 6 characters.";
    isValid = false;
  }

  if (!isValid) return;

  btn.disabled = true;
  btn.textContent = "Logging in...";

  const formData = new FormData();
  formData.append("email", email.value.trim());
  formData.append("password", password.value);

  try {
    const res = await fetch("api/auth.php?action=login", {
      method: "POST",
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      window.location.href = "dashboard.html";
    } else {
      passwordError.textContent = data.error || "Invalid credentials.";
    }
  } catch (err) {
    console.error("Login request failed:", err);
    passwordError.textContent = "Could not connect to backend.";
  } finally {
    btn.disabled = false;
    btn.textContent = "Login";
  }
});

function demoLogin() {
  // Quick demo access
  window.location.href = "dashboard.html";
}
