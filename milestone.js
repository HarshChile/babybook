/**
 * Milestone Management
 * BabyBook / TinyTales
 */

document.addEventListener("DOMContentLoaded", async () => {
    await API.requireAuth();
    const dateInput = document.getElementById("date");
    if (dateInput) {
        dateInput.value = new Date().toISOString().split("T")[0];
    }
    loadMilestones();
});

function openForm() {
    document.getElementById("modal").style.display = "flex";
}

function closeForm() {
    document.getElementById("modal").style.display = "none";
}

async function loadMilestones() {
    const container = document.getElementById("milestones");

    try {
        const data = await API.fetch("api/milestones.php");

        if (data.success) {
            container.innerHTML = "";

            if (!data.milestones || data.milestones.length === 0) {
                container.innerHTML = `
                    <div style="background:white; border-radius:15px; padding:30px; margin-top:25px; text-align:center; color:#880D1E;">
                        <p>No milestones recorded yet. Click <strong>+ Add Milestone</strong> to record your baby's first achievement!</p>
                    </div>
                `;
                return;
            }

            const icons = {
                social: "❤",
                physical: "★",
                language: "☺"
            };

            data.milestones.forEach(m => {
                const cat = (m.category || "social").toLowerCase();
                const iconChar = icons[cat] || "★";

                const card = document.createElement("div");
                card.className = "card";

                card.innerHTML = `
                    <div class="card-left">
                        <div class="icon">${iconChar}</div>
                        <div>
                            <strong>${escapeHtml(m.title)}</strong><br>
                            <span class="tag ${cat}">${escapeHtml(cat)}</span>
                        </div>
                    </div>
                    <div class="card-right">
                        <div class="date">📅 ${m.date || ''}</div>
                        <button class="delete-milestone-btn" title="Delete" onclick="deleteMilestone('${m._id}')">🗑️</button>
                    </div>
                `;

                container.appendChild(card);
            });
        }
    } catch (err) {
        console.error("Error loading milestones:", err);
        container.innerHTML = `
            <div style="background:#FFF5F5; border:1px solid #F4B7B7; border-radius:15px; padding:24px; margin-top:25px; text-align:center; color:#771212;">
                <h4>⚠️ Backend Connection Notice</h4>
                <p style="font-size:14px; opacity:0.9;">${escapeHtml(err.message)}</p>
            </div>
        `;
    }
}

async function saveMilestone() {
    const title = document.getElementById("title").value.trim();
    const category = document.getElementById("category").value;
    const date = document.getElementById("date").value;

    if (!title || !category || !date) {
        alert("Please fill all fields");
        return;
    }

    const formData = new FormData();
    formData.append("title", title);
    formData.append("category", category);
    formData.append("date", date);

    try {
        const data = await API.fetch("api/milestones.php", {
            method: "POST",
            body: formData
        });

        if (data.success) {
            document.getElementById("title").value = "";
            document.getElementById("category").value = "";
            closeForm();
            loadMilestones();
        } else {
            alert("Error: " + (data.error || "Could not save milestone"));
        }
    } catch (err) {
        console.error("Error saving milestone:", err);
        alert(err.message || "Failed to connect to backend server.");
    }
}

async function deleteMilestone(id) {
    if (!confirm("Are you sure you want to delete this milestone?")) {
        return;
    }

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);

    try {
        const data = await API.fetch("api/milestones.php", {
            method: "POST",
            body: formData
        });

        if (data.success) {
            loadMilestones();
        } else {
            alert("Error: " + (data.error || "Could not delete milestone"));
        }
    } catch (err) {
        console.error("Delete error:", err);
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
