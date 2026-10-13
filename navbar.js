document.addEventListener("DOMContentLoaded", () => {
  const currentPage = window.location.pathname.split("/").pop();
  const items = document.querySelectorAll(".nav-item");

  items.forEach(item => {
    const link = item.querySelector("a");
    if (!link) return;

    const href = link.getAttribute("href");

    // Active page
    if (href === currentPage) {
      item.classList.add("active");
    }

    // Optional: soft highlight (like Scrapbook in your screenshot)
    if (href === "scrapbook.html" && currentPage !== "scrapbook.html") {
      item.classList.add("soft");
    }
  });
});