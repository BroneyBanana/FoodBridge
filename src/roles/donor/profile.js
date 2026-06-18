document.addEventListener("DOMContentLoaded", () => {
  const STORAGE_KEY = "foodbridgeProfile";

  // Default Donor State
  const defaultProfile = {
    name: "Sunrise Bakery",
    email: "donor@food.com",
    role: "donor",
    location: "Subang Jaya, Selangor",
    memberSince: "June 2026",
    password: "password123", // Default mock password
    trustScore: 100,
    donations: 8,
    reliability: "100%",
    preferences: {
      alertExpiry: true,
      halalDefault: false,
      contactVisible: true
    }
  };

  // 1. Load Profile from LocalStorage
  let profile = JSON.parse(localStorage.getItem(STORAGE_KEY));
  
  // If no profile found or the role is not donor (e.g. registered a different role but landed here)
  if (!profile || profile.role !== "donor") {
    profile = defaultProfile;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
  }

  // Ensure stats values exist in profile
  if (profile.trustScore === undefined) profile.trustScore = 100;
  if (profile.donations === undefined) profile.donations = 8;
  if (profile.reliability === undefined) profile.reliability = "100%";
  if (!profile.memberSince) profile.memberSince = "June 2026";
  if (!profile.password) profile.password = "password123";
  if (!profile.preferences) {
    profile.preferences = {
      alertExpiry: true,
      halalDefault: false,
      contactVisible: true
    };
  }

  // DOM Elements
  const profileAvatarContainer = document.getElementById("profileAvatarContainer");
  const profileAvatarImg = document.getElementById("profileAvatarImg");
  const avatarFileInput = document.getElementById("avatarFileInput");
  const profileInitials = document.getElementById("profileInitials");
  const sidebarDonorName = document.getElementById("sidebarDonorName");
  const metaMemberSince = document.getElementById("metaMemberSince");
  const statDonations = document.getElementById("statDonations");
  const statReliability = document.getElementById("statReliability");

  const credentialsForm = document.getElementById("credentialsForm");
  const donorNameInput = document.getElementById("donorName");
  const donorEmailInput = document.getElementById("donorEmail");
  const donorLocationInput = document.getElementById("donorLocation");

  const togglePasswordFormBtn = document.getElementById("togglePasswordFormBtn");
  const chevronIcon = document.getElementById("chevronIcon");
  const passwordFieldsContainer = document.getElementById("passwordFieldsContainer");
  const currentPasswordInput = document.getElementById("currentPassword");
  const newPasswordInput = document.getElementById("newPassword");
  const confirmPasswordInput = document.getElementById("confirmPassword");

  const prefAlertExpiry = document.getElementById("prefAlertExpiry");
  const prefHalalDefault = document.getElementById("prefHalalDefault");
  const prefContactVisible = document.getElementById("prefContactVisible");
  const savePreferencesBtn = document.getElementById("savePreferencesBtn");

  const toastContainer = document.getElementById("toastContainer");

  // Toast Functionality
  function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    
    // Choose icons
    let iconSvg = '';
    if (type === "success") {
      iconSvg = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
    } else if (type === "error") {
      iconSvg = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
    } else {
      iconSvg = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
    }

    toast.innerHTML = `
      <span class="toast-icon">${iconSvg}</span>
      <div class="toast-content">${message}</div>
      <button type="button" class="toast-close" aria-label="Close message">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    `;
    
    toastContainer.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
      toast.classList.add("show");
    }, 10);
    
    // Auto dismiss after 4 seconds
    const dismissTimeout = setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 400);
    }, 4000);
    
    // Close button
    toast.querySelector(".toast-close").addEventListener("click", () => {
      clearTimeout(dismissTimeout);
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 400);
    });
  }

  // Get Initials from Name
  function getInitials(name) {
    return name
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0])
      .join("")
      .toUpperCase() || "FB";
  }

  // Update UI components dynamically
  function updateUI() {
    const initials = getInitials(profile.name);
    
    // Update sidebar & initials
    sidebarDonorName.textContent = profile.name;
    metaMemberSince.textContent = profile.memberSince;
    
    if (profile.avatarImage) {
      profileAvatarImg.src = profile.avatarImage;
      profileAvatarImg.classList.remove("hidden");
      profileInitials.classList.add("hidden");
    } else {
      profileAvatarImg.classList.add("hidden");
      profileInitials.classList.remove("hidden");
      profileInitials.textContent = initials;
    }
    statDonations.textContent = profile.donations;
    statReliability.textContent = profile.reliability;

    // Update form fields
    donorNameInput.value = profile.name;
    donorEmailInput.value = profile.email;
    donorLocationInput.value = profile.location;

    // Preferences checkboxes
    prefAlertExpiry.checked = !!profile.preferences.alertExpiry;
    prefHalalDefault.checked = !!profile.preferences.halalDefault;
    prefContactVisible.checked = !!profile.preferences.contactVisible;

    // Trigger header sync
    if (typeof window.updateHeaderAvatar === 'function') {
      window.updateHeaderAvatar();
    }
  }

  // Toggle Password fields collapse accordion
  togglePasswordFormBtn.addEventListener("click", () => {
    const isHidden = passwordFieldsContainer.classList.contains("hidden");
    if (isHidden) {
      passwordFieldsContainer.classList.remove("hidden");
      chevronIcon.classList.add("rotated");
    } else {
      passwordFieldsContainer.classList.add("hidden");
      chevronIcon.classList.remove("rotated");
      // Clear values when hiding
      currentPasswordInput.value = "";
      newPasswordInput.value = "";
      confirmPasswordInput.value = "";
    }
  });

  // Password Visibility Toggle buttons
  document.querySelectorAll(".password-toggle-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.getAttribute("data-toggle-target");
      const targetInput = document.getElementById(targetId);
      if (targetInput) {
        const isPassword = targetInput.type === "password";
        targetInput.type = isPassword ? "text" : "password";
        btn.classList.toggle("showing", isPassword);
        
        // Update SVG icon representation
        if (isPassword) {
          btn.innerHTML = `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
        } else {
          btn.innerHTML = `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        }
      }
    });
  });

  // Form 1 Submit: Account Credentials
  credentialsForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const newName = donorNameInput.value.trim();
    const newEmail = donorEmailInput.value.trim();
    const newLocation = donorLocationInput.value.trim();
    
    // If they typed something in currentPassword or newPassword, handle password change logic
    const currentPass = currentPasswordInput.value;
    const newPass = newPasswordInput.value;
    const confirmPass = confirmPasswordInput.value;

    let passwordChanged = false;

    if (currentPass || newPass || confirmPass) {
      if (!currentPass) {
        showToast("Please enter your current password to make changes.", "error");
        return;
      }
      if (currentPass !== profile.password) {
        showToast("Incorrect current password.", "error");
        return;
      }
      if (!newPass) {
        showToast("Please specify a new password.", "error");
        return;
      }
      if (newPass.length < 6) {
        showToast("New password must be at least 6 characters.", "error");
        return;
      }
      if (newPass !== confirmPass) {
        showToast("Passwords do not match.", "error");
        return;
      }
      
      // Update password
      profile.password = newPass;
      passwordChanged = true;
      
      // Reset inputs & hide
      currentPasswordInput.value = "";
      newPasswordInput.value = "";
      confirmPasswordInput.value = "";
      passwordFieldsContainer.classList.add("hidden");
      chevronIcon.classList.remove("rotated");
    }

    // Save Name, Email, Location
    profile.name = newName;
    profile.email = newEmail;
    profile.location = newLocation;
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
    updateUI();
    
    if (passwordChanged) {
      showToast("Profile details and password updated!", "success");
    } else {
      showToast("Profile details updated successfully!", "success");
    }
  });

  // Form 2 Submit: Preferences
  savePreferencesBtn.addEventListener("click", () => {
    profile.preferences.alertExpiry = prefAlertExpiry.checked;
    profile.preferences.halalDefault = prefHalalDefault.checked;
    profile.preferences.contactVisible = prefContactVisible.checked;

    localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
    updateUI();
    showToast("Donor preferences saved successfully!", "success");
  });

  // Click avatar to change photo
  profileAvatarContainer.addEventListener("click", () => {
    avatarFileInput.click();
  });

  // Handle file change
  avatarFileInput.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (file) {
      if (!file.type.startsWith("image/")) {
        showToast("Please select a valid image file.", "error");
        return;
      }
      if (file.size > 2 * 1024 * 1024) { // limit 2MB
        showToast("Image size must be less than 2MB.", "error");
        return;
      }

      const reader = new FileReader();
      reader.onload = (event) => {
        profile.avatarImage = event.target.result;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
        updateUI();
        showToast("Profile picture updated successfully!", "success");
      };
      reader.readAsDataURL(file);
    }
  });

  // Initial load
  updateUI();
});
