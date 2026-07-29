document.addEventListener("DOMContentLoaded", () => {
  // ---- Load config from PHP ----
  const profile = window.receiverProfileConfig || {};

  // Fallback defaults if config is missing
  if (!profile.name) {
    console.warn("Receiver profile config not found – check PHP injection.");
  }

  // ---- DOM references ----
  const avatarContainer = document.getElementById("profileAvatarContainer");
  const avatarImg = document.getElementById("profileAvatarImg");
  const avatarInput = document.getElementById("avatarFileInput");
  const initialsEl = document.getElementById("profileInitials");
  const sidebarName = document.getElementById("sidebarReceiverName");
  const metaMemberSince = document.getElementById("metaMemberSince");
  const statPickups = document.getElementById("statPickups");
  const statOnTime = document.getElementById("statOnTime");

  const credForm = document.getElementById("credentialsForm");
  const receiverName = document.getElementById("receiverName");
  const receiverEmail = document.getElementById("receiverEmail");
  const receiverLocation = document.getElementById("receiverLocation");

  const togglePasswordBtn = document.getElementById("togglePasswordFormBtn");
  const chevron = document.getElementById("chevronIcon");
  const passwordContainer = document.getElementById("passwordFieldsContainer");
  const currentPass = document.getElementById("currentPassword");
  const newPass = document.getElementById("newPassword");
  const confirmPass = document.getElementById("confirmPassword");

  const prefNearby = document.getElementById("prefAlertNearby");
  const prefWeekly = document.getElementById("prefWeeklyDigest");
  const prefDetails = document.getElementById("prefDetailsVisible");
  const savePrefsBtn = document.getElementById("savePreferencesBtn");

  const toastContainer = document.getElementById("toastContainer");

  // ---- Toast system ----
  function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    const icons = {
      success: `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`,
      error: `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`,
      info: `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`
    };
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <div class="toast-content">${message}</div>
      <button type="button" class="toast-close" aria-label="Close message">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    `;
    toastContainer.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("show"));
    const timer = setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 400);
    }, 4000);
    toast.querySelector(".toast-close").addEventListener("click", () => {
      clearTimeout(timer);
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 400);
    });
  }

  // ---- Update UI from profile data ----
  function updateUI() {
    sidebarName.textContent = profile.name || "Receiver";
    metaMemberSince.textContent = profile.memberSince || "N/A";
    statPickups.textContent = profile.pickups ?? 0;
    statOnTime.textContent = (profile.onTimeRate ?? 100) + "%";

    // Avatar
    if (profile.avatarImage) {
      avatarImg.src = "../../" + profile.avatarImage;
      avatarImg.classList.remove("hidden");
      initialsEl.classList.add("hidden");
    } else {
      avatarImg.classList.add("hidden");
      initialsEl.classList.remove("hidden");
      initialsEl.textContent = profile.initials || "FB";
    }

    // Form fields
    receiverName.value = profile.name || "";
    receiverEmail.value = profile.email || "";
    receiverLocation.value = profile.location || "";

    // Preferences (if stored)
    if (profile.preferences) {
      prefNearby.checked = !!profile.preferences.alertNearby;
      prefWeekly.checked = !!profile.preferences.weeklyDigest;
      prefDetails.checked = !!profile.preferences.detailsVisible;
    }
  }

  // ---- Toggle password fields ----
  togglePasswordBtn.addEventListener("click", () => {
    const hidden = passwordContainer.classList.toggle("hidden");
    chevron.classList.toggle("rotated", !hidden);
    if (hidden) {
      currentPass.value = "";
      newPass.value = "";
      confirmPass.value = "";
    }
  });

  // ---- Password visibility toggles ----
  document.querySelectorAll(".password-toggle-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const target = document.getElementById(btn.dataset.toggleTarget);
      if (!target) return;
      const isPass = target.type === "password";
      target.type = isPass ? "text" : "password";
      btn.innerHTML = isPass
        ? `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`
        : `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
    });
  });

  // ---- Submit: Account credentials ----
  credForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = receiverName.value.trim();
    const location = receiverLocation.value.trim();
    if (!name || !location) {
      showToast("Name and location are required.", "error");
      return;
    }

    const cur = currentPass.value;
    const newPw = newPass.value;
    const conf = confirmPass.value;

    if (cur || newPw || conf) {
      if (!cur) return showToast("Current password is required.", "error");
      if (!newPw) return showToast("New password is required.", "error");
      if (newPw.length < 8) return showToast("New password must be at least 8 characters.", "error");
      if (newPw !== conf) return showToast("Passwords do not match.", "error");
    }

    const formData = new FormData();
    formData.append("action", "update_profile");
    formData.append("name", name);
    formData.append("location", location);
    if (newPw) {
      formData.append("currentPassword", cur);
      formData.append("newPassword", newPw);
    }

    try {
      const res = await fetch("profile.php", { method: "POST", body: formData });
      const data = await res.json();
      if (data.success) {
        profile.name = data.name;
        profile.location = location;
        // Update session name for header
        localStorage.setItem("foodbridgeReceiverProfile", JSON.stringify(profile));
        updateUI();
        // Clear password fields and collapse
        currentPass.value = "";
        newPass.value = "";
        confirmPass.value = "";
        passwordContainer.classList.add("hidden");
        chevron.classList.remove("rotated");
        showToast(data.message, "success");
      } else {
        showToast(data.message, "error");
      }
    } catch (err) {
      showToast("Server error. Please try again.", "error");
    }
  });

  // ---- Submit: Preferences ----
  savePrefsBtn.addEventListener("click", async () => {
    const prefs = {
      alertNearby: prefNearby.checked,
      weeklyDigest: prefWeekly.checked,
      detailsVisible: prefDetails.checked
    };

    const formData = new FormData();
    formData.append("action", "save_preferences");
    formData.append("preferences", JSON.stringify(prefs));

    try {
      const res = await fetch("profile.php", { method: "POST", body: formData });
      const data = await res.json();
      if (data.success) {
        profile.preferences = prefs;
        localStorage.setItem("foodbridgeReceiverProfile", JSON.stringify(profile));
        showToast(data.message, "success");
      } else {
        showToast(data.message, "error");
      }
    } catch (err) {
      showToast("Server error. Please try again.", "error");
    }
  });

  // ---- Avatar upload ----
  avatarContainer.addEventListener("click", () => avatarInput.click());

  avatarInput.addEventListener("change", async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith("image/")) return showToast("Please select an image.", "error");
    if (file.size > 2 * 1024 * 1024) return showToast("Image must be under 2MB.", "error");

    const formData = new FormData();
    formData.append("action", "upload_avatar");
    formData.append("avatar", file);

    try {
      const res = await fetch("profile.php", { method: "POST", body: formData });
      const data = await res.json();
      if (data.success) {
        profile.avatarImage = data.avatarUrl;
        updateUI();
        window.location.reload();

        if (typeof window.updateHeaderAvatar === "function") window.updateHeaderAvatar();
        showToast("Profile picture updated.", "success");
      } else {
        showToast(data.message, "error");
      }
    } catch (err) {
      showToast("Server error during upload.", "error");
    }
    avatarInput.value = "";
  });

  // ---- Initial render ----
  updateUI();

  // ---- Optional: store profile in localStorage for header.js ----
  localStorage.setItem("foodbridgeReceiverProfile", JSON.stringify(profile));
});