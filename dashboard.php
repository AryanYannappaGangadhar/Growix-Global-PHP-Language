<?php header("Content-type: application/javascript"); ?>
document.addEventListener("DOMContentLoaded", () => {
    loadProfile();
    loadAttendance();

    // Load Gallery
    loadGallery();

    // Gallery Upload
    const galleryInput = document.getElementById("gallery-upload");
    if (galleryInput) {
        galleryInput.addEventListener("change", async (e) => {
            const files = Array.from(e.target.files);
            if (files.length === 0) return;

            // Convert all to Base64
            const promises = files.map(file => {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = evt => resolve(evt.target.result);
                    reader.onerror = err => reject(err);
                    reader.readAsDataURL(file);
                });
            });

            try {
                const base64Images = await Promise.all(promises);
                await apiRequest("/user/upload-photos", "POST", { photos: base64Images });
                alert("Photos uploaded successfully!");
                loadGallery(); // Refresh gallery
            } catch (err) {
                alert(err.message || "Failed to upload photos");
            }
        });
    }

    // Logout
    const logoutBtn = document.getElementById("logout-btn");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", async () => {
            try {
                await apiRequest("/auth/logout", "POST");
                localStorage.removeItem("auth_token"); // Clear local token
                window.location.href = "login.php";
            } catch (err) {
                console.error("Logout failed", err);
                localStorage.removeItem("auth_token"); // Clear anyway
                window.location.href = "login.php";
            }
        });
    }

    // Mark Attendance
    const markBtn = document.getElementById("mark-attendance-btn");
    if (markBtn) {
        markBtn.addEventListener("click", async () => {
            try {
                const res = await apiRequest("/attendance/mark", "POST");
                alert(res.message || "Attendance marked successfully!");
                loadAttendance(); // Refresh table
            } catch (err) {
                alert(err.message || "Failed to mark attendance");
            }
        });
    }

    // Edit Profile Modal Toggles
    const editBtn = document.getElementById("edit-profile-btn");
    const modal = document.getElementById("profile-modal");
    const cancelBtn = document.getElementById("cancel-profile-btn");
    const profileForm = document.getElementById("profile-form");

    if (editBtn && modal && cancelBtn) {
        editBtn.addEventListener("click", () => {
            modal.classList.remove("hidden");
            // Pre-fill form
            document.getElementById("profile-fullname-input").value = document.getElementById("profile-name").textContent;
        });

        cancelBtn.addEventListener("click", () => {
            modal.classList.add("hidden");
        });
        
        // File Input Change
        const fileInput = document.getElementById("profile-photo-file");
        const base64Input = document.getElementById("profile-photo-base64");

        if (fileInput) {
            fileInput.addEventListener("change", (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        base64Input.value = evt.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Save Profile
        if (profileForm) {
            profileForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const fullName = document.getElementById("profile-fullname-input").value;
                const photoBase64 = base64Input ? base64Input.value : null;
                
                try {
                    await apiRequest("/user/update-profile", "POST", { fullName, photoBase64 });
                    alert("Profile updated successfully!"); 
                    modal.classList.add("hidden");
                    loadProfile();
                } catch (err) {
                    alert(err.message);
                }
            });
        }
    }
});

async function loadProfile() {
    try {
        const user = await apiRequest("/user/me");
        
        document.getElementById("profile-name").textContent = user.fullName;
        document.getElementById("profile-email").textContent = user.email;
        document.getElementById("profile-username").textContent = `@${user.username}`;

        // Add first name to welcome
        const welcomeEl = document.getElementById("welcome-name");
        if (welcomeEl) welcomeEl.textContent = user.fullName.split(' ')[0];
        
        const photoEl = document.getElementById("profile-photo");
        if (user.photoUrl || user.photoBase64) {
            photoEl.src = user.photoUrl || user.photoBase64;
        } else {
            // Placeholder
            photoEl.src = "https://ui-avatars.com/api/?name=" + encodeURIComponent(user.fullName) + "&background=random";
        }

        // Update Date
        const dateEl = document.getElementById("current-date");
        if (dateEl) {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('en-US', options);
        }

    } catch (err) {
        console.error("Failed to load profile", err);
        // If 401, redirect to login
        if (err.message.includes("Unauthorized") || err.message.includes("login")) {
            window.location.href = "login.php";
        }
    }
}

async function loadGallery() {
    try {
        const photos = await apiRequest("/user/get-photos");
        const grid = document.getElementById("gallery-grid");
        if (!grid) return;

        grid.innerHTML = "";
        
        if (photos.length === 0) {
            grid.innerHTML = "<p class='muted'>No photos uploaded yet.</p>";
            return;
        }

        photos.forEach(photo => {
            const div = document.createElement("div");
            div.className = "gallery-item";
            div.innerHTML = `<img src="${photo.photo_base64}" class="gallery-img" alt="Gallery Photo">`;
            grid.appendChild(div);
        });

    } catch (err) {
        console.error("Failed to load gallery", err);
    }
}

async function loadAttendance() {
    try {
        const data = await apiRequest("/attendance/me");
        const records = Array.isArray(data) ? data : (data.records || []);
        
        const tbody = document.getElementById("attendance-table-body");
        tbody.innerHTML = "";

        records.forEach(record => {
            const tr = document.createElement("tr");
            
            // Format time to 12-hour AM/PM
            const formatTime = (timeStr) => {
                if (!timeStr) return "-";
                // Assuming timeStr is like "2023-10-27 14:30:00" or "14:30:00"
                const date = new Date(timeStr.includes("T") || timeStr.includes(" ") ? timeStr : `2000-01-01 ${timeStr}`);
                return date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
            };

            // Format date to Indian format (DD/MM/YYYY)
            const formatDate = (dateStr) => {
                if (!dateStr) return "-";
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' });
            };

            tr.innerHTML = `
                <td>${formatDate(record.date)}</td>
                <td>${formatTime(record.checkInAt)}</td>
                <td>${formatTime(record.checkOutAt)}</td>
                <td><span class="status-badge status-present">${record.status}</span></td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error("Failed to load attendance", err);
    }
}
