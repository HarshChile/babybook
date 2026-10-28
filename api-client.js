/**
 * TinyTales Unified API Client & Authentication Controller
 * Automatically manages session authentication, credentials, and error handling
 */
const API = {
  currentUser: null,
  activeBaby: null,

  /**
   * Determine backend base URL based on how the page was loaded
   */
  getBaseUrl() {
    if (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) {
      return "http://localhost:8000/";
    }
    return "";
  },

  /**
   * Resolve relative endpoint to full API URL
   */
  url(endpoint) {
    const clean = endpoint.replace(/^\/+/, "");
    return this.getBaseUrl() + clean;
  },

  /**
   * Safe fetch with automatic JSON parsing and session credential inclusion
   */
  async fetch(endpoint, options = {}) {
    const fullUrl = this.url(endpoint);

    // Ensure session cookies are sent across origins/ports
    options.credentials = options.credentials || "include";

    try {
      const res = await window.fetch(fullUrl, options);
      const text = await res.text();

      // Check if static server returned unparsed PHP
      if (text.trim().startsWith("<?php") || text.includes("<?php\n") || text.includes("<?php\r\n")) {
        throw new Error(
          "PHP server is not executing PHP files! Please make sure 'php -S localhost:8000' is running and open http://localhost:8000."
        );
      }

      let data;
      try {
        data = JSON.parse(text);
      } catch (jsonErr) {
        console.error("Non-JSON response from server:", text);
        throw new Error(
          `Server returned unexpected response (Status ${res.status}): ${text.substring(0, 100)}`
        );
      }

      // Handle 401 Unauthorized across protected endpoints
      if (res.status === 401) {
        this.currentUser = null;
        this.activeBaby = null;
        const currentPath = window.location.pathname.split("/").pop() || "home.html";
        const publicPages = ["home.html", "home2.html", "login.html", "register.html", "forgot.html", "reset.html", "learnmore.html", ""];
        if (!publicPages.includes(currentPath)) {
          console.warn("Unauthenticated session detected. Redirecting to login.html...");
          window.location.href = "login.html";
          return { success: false, error: "Unauthorized. Redirecting to login..." };
        }
      }

      return data;
    } catch (err) {
      if (err.name === "TypeError" && err.message.includes("Failed to fetch")) {
        throw new Error(
          "Cannot connect to backend server. Please make sure 'php -S localhost:8000' is running."
        );
      }
      throw err;
    }
  },

  /**
   * Check authentication state on page load
   * Redirects unauthenticated users to login for protected pages
   */
  async requireAuth() {
    const currentPath = window.location.pathname.split("/").pop() || "home.html";
    const publicPages = ["home.html", "home2.html", "login.html", "register.html", "forgot.html", "reset.html", "learnmore.html", ""];

    try {
      const data = await this.fetch("api/auth.php?action=me");
      if (data && data.success && data.loggedIn) {
        this.currentUser = data.user;
        this.activeBaby = data.active_baby;

        // If currently on login or register, redirect to dashboard
        if (currentPath === "login.html" || currentPath === "register.html") {
          window.location.href = "dashboard.html";
        }
        return data.user;
      } else {
        this.currentUser = null;
        this.activeBaby = null;
        if (!publicPages.includes(currentPath)) {
          window.location.href = "login.html";
          return null;
        }
      }
    } catch (err) {
      console.warn("Auth check failed:", err.message);
      if (!publicPages.includes(currentPath)) {
        window.location.href = "login.html";
      }
    }
    return null;
  },

  /**
   * Log out of current session and redirect to home
   */
  async logout() {
    try {
      await this.fetch("api/auth.php?action=logout", { method: "POST" });
    } catch (e) {
      console.error("Logout request failed:", e);
    } finally {
      this.currentUser = null;
      this.activeBaby = null;
      // Invalidate any cached browser state
      if (window.sessionStorage) window.sessionStorage.clear();
      window.location.href = "home.html";
    }
  }
};

// Auto-wire logout buttons across all pages
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".logout-link").forEach(el => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      API.logout();
    });
  });
});
