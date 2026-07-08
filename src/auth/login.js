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

  formMessage.className = "form-message";
  formMessage.setAttribute("role", "status");
  formMessage.setAttribute("aria-live", "polite");

  if (!formMessage.parentElement) {
    loginForm.insertBefore(formMessage, signInButton);
  }

  function setMessage(message, type = "error") {
    formMessage.textContent = message;
    formMessage.classList.toggle("success", type === "success");
  }

  function selectRole(tab) {
    roleTabs.forEach((item) => {
      item.classList.remove("active");
      item.setAttribute("aria-selected", "false");
    });

    tab.classList.add("active");
    tab.setAttribute("aria-selected", "true");
    roleInput.value = tab.dataset.role;
    setMessage("");
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
});
