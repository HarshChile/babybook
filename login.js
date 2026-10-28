/**
 * Login Form Logic
 * BabyBook / TinyTales
 */

document.addEventListener("DOMContentLoaded", () => {
  // If already authenticated, redirect to dashboard
  API.requireAuth();
});

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
    const data = await API.fetch("api/auth.php?action=login", {
      method: "POST",
      body: formData
    });

    if (data.success) {
      window.location.href = "dashboard.html";
    } else {
      passwordError.textContent = data.error || "Invalid credentials.";
    }
  } catch (err) {
    console.error("Login request failed:", err);
    passwordError.textContent = err.message || "Login failed. Please try again.";
  } finally {
    btn.disabled = false;
    btn.textContent = "Login";
  }
});

/**
 * Authenticate securely using the demo parent account
 */
async function demoLogin() {
  document.getElementById("email").value = "demo@tinytales.app";
  document.getElementById("password").value = "password123";
  const form = document.getElementById("loginForm");
  if (form) {
    form.dispatchEvent(new Event("submit"));
  }
}
