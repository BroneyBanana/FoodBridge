document.addEventListener("DOMContentLoaded", () => {
  const roleInput = document.querySelector("#accountRole");
  const roleTabs = document.querySelectorAll(".role-tab");
  const loginForm = document.querySelector("#loginForm");
  const emailInput = document.querySelector("#email");
  const passwordInput = document.querySelector("#password");
  const togglePassword = document.querySelector("#togglePassword");
  const adminAccess = document.querySelector("#adminAccess");
  const signInButton = loginForm.querySelector(".sign-in-button");
  const formMessage = loginForm.querySelector(".form-message") || document.createElement("p");
  const rememberCheckbox = document.querySelector(".remember-me input");

  formMessage.className = "form-message";
  formMessage.setAttribute("role", "status");
  formMessage.setAttribute("aria-live", "polite");

  if (!formMessage.parentElement) {
    loginForm.insertBefore(formMessage, signInButton);
  }

  const rememberedEmail = localStorage.getItem("foodbridgeRememberedEmail");
  if (rememberedEmail) {
    emailInput.value = rememberedEmail;
    rememberCheckbox.checked = true;
  }

  function setMessage(message, type = "error") {
    formMessage.textContent = message;
    formMessage.classList.toggle("success", type === "success");
  }

  function selectRole(tab, persist = true) {
    roleTabs.forEach((item) => {
      item.classList.remove("active");
      item.setAttribute("aria-selected", "false");
    });

    tab.classList.add("active");
    tab.setAttribute("aria-selected", "true");
    roleInput.value = tab.dataset.role;
    setMessage("");

    if (persist) {
      localStorage.setItem("foodbridgeSelectedRole", tab.dataset.role);
    }
  }

  const savedRole = localStorage.getItem("foodbridgeSelectedRole");
  if (savedRole) {
    const savedTab = document.querySelector(`.role-tab[data-role='${savedRole}']`);
    if (savedTab) {
      selectRole(savedTab, false);
    }
  }

  roleTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      selectRole(tab);
    });
  });

  if (adminAccess) {
    adminAccess.addEventListener("click", (event) => {
      event.preventDefault();
      const adminTab = document.querySelector(".role-tab[data-role='admin']");
      selectRole(adminTab);
      emailInput.focus();
    });
  }

  togglePassword.addEventListener("click", () => {
    const isPasswordVisible = passwordInput.type === "text";

    passwordInput.type = isPasswordVisible ? "password" : "text";
    togglePassword.classList.toggle("showing", !isPasswordVisible);
    togglePassword.setAttribute("aria-label", isPasswordVisible ? "Show password" : "Hide password");
  });

  loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    setMessage("");
    signInButton.disabled = true;
    signInButton.textContent = "Signing In...";

    try {
      const response = await fetch("login.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
        },
        body: new FormData(loginForm),
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        setMessage(data.message || "Unable to sign in. Please try again.");
        return;
      }

      if (rememberCheckbox.checked) {
        localStorage.setItem("foodbridgeRememberedEmail", emailInput.value);
      } else {
        localStorage.removeItem("foodbridgeRememberedEmail");
      }

      const storageKey = data.user.role === "admin" ? "foodbridgeAdminProfile" : "foodbridgeProfile";
      localStorage.setItem(storageKey, JSON.stringify(data.user));
      window.location.href = data.redirect;
    } catch (error) {
      setMessage("Unable to reach the login server. Please run this page through PHP.");
    } finally {
      signInButton.disabled = false;
      signInButton.textContent = "Sign In";
    }
  });

  // Forgot Password modal logic
  const forgotLink = document.querySelector("#forgotPasswordLink");
  const forgotModal = document.querySelector("#forgotPasswordModal");
  const closeForgotModal = document.querySelector("#closeForgotModal");

  const stepEmail = document.querySelector("#forgotStepEmail");
  const stepReset = document.querySelector("#forgotStepReset");

  const forgotForm = document.querySelector("#forgotPasswordForm");
  const forgotEmailInput = document.querySelector("#forgotEmail");
  const forgotMessage = document.querySelector("#forgotMessage");
  const forgotSubmitBtn = document.querySelector("#forgotSubmitBtn");

  const resetForm = document.querySelector("#resetPasswordForm");
  const resetOtpInput = document.querySelector("#resetOtp");
  const resetNewPasswordInput = document.querySelector("#resetNewPassword");
  const resetMessage = document.querySelector("#resetMessage");
  const resetSubmitBtn = document.querySelector("#resetSubmitBtn");

  let pendingResetEmail = "";

  if (forgotLink && forgotModal) {
    forgotLink.addEventListener("click", (event) => {
      event.preventDefault();
      forgotModal.hidden = false;
      stepEmail.hidden = false;
      stepReset.hidden = true;
      forgotEmailInput.value = emailInput.value || "";
      forgotEmailInput.focus();
      forgotMessage.textContent = "";
      forgotMessage.classList.remove("success");
    });

    closeForgotModal.addEventListener("click", () => {
      forgotModal.hidden = true;
    });

    forgotModal.addEventListener("click", (event) => {
      if (event.target === forgotModal) {
        forgotModal.hidden = true;
      }
    });

    forgotForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      forgotMessage.textContent = "";
      forgotSubmitBtn.disabled = true;
      forgotSubmitBtn.textContent = "Sending...";

      try {
        const response = await fetch("forgot-password.php", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: new FormData(forgotForm),
        });
        const data = await response.json();

        if (data.success) {
          pendingResetEmail = forgotEmailInput.value;
          stepEmail.hidden = true;
          stepReset.hidden = false;
          resetMessage.textContent = "";
          resetOtpInput.focus();
        } else {
          forgotMessage.textContent = data.message || "Unable to send code.";
          forgotMessage.classList.remove("success");
        }
      } catch (error) {
        forgotMessage.textContent = "Unable to reach the server. Please try again.";
        forgotMessage.classList.remove("success");
      } finally {
        forgotSubmitBtn.disabled = false;
        forgotSubmitBtn.textContent = "Send Code";
      }
    });

    resetForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      resetMessage.textContent = "";
      resetSubmitBtn.disabled = true;
      resetSubmitBtn.textContent = "Resetting...";

      try {
        const formData = new FormData(resetForm);
        formData.append("email", pendingResetEmail);

        const response = await fetch("reset-password.php", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: formData,
        });
        const data = await response.json();

        resetMessage.textContent = data.message;
        resetMessage.classList.toggle("success", !!data.success);

        if (data.success) {
          setTimeout(() => {
            forgotModal.hidden = true;
          }, 1500);
        }
      } catch (error) {
        resetMessage.textContent = "Unable to reach the server. Please try again.";
        resetMessage.classList.remove("success");
      } finally {
        resetSubmitBtn.disabled = false;
        resetSubmitBtn.textContent = "Reset Password";
      }
    });
  }
});