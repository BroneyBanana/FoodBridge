document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#registerForm");
  const steps = [...document.querySelectorAll(".register-step")];
  const dots = [...document.querySelectorAll(".step-dot")];
  const nextButtons = document.querySelectorAll("[data-next]");
  const backButton = document.querySelector("#backButton");
  const roleCards = document.querySelectorAll(".role-card");
  const roleInputs = document.querySelectorAll("input[name='accountRole']");
  const fullNameInput = document.querySelector("#fullName");
  const locationInput = document.querySelector("input[name='profileLocation']");
  const previewName = document.querySelector("#previewName");
  const previewRole = document.querySelector("#previewRole");
  const passwordInput = document.querySelector("#password");
  const confirmPasswordInput = document.querySelector("#confirmPassword");
  const togglePassword = document.querySelector("#togglePassword");
  const submitButton = form.querySelector("button[type='submit']");
  const formMessage = document.querySelector("#registerMessage");

  let currentStep = 0;

  function selectedRole() {
    return document.querySelector("input[name='accountRole']:checked")?.value || "receiver";
  }

  function updateProgress() {
    steps.forEach((step, index) => {
      step.classList.toggle("active", index === currentStep);
    });

    dots.forEach((dot, index) => {
      dot.classList.toggle("active", index === currentStep);
      dot.classList.toggle("complete", index < currentStep);
    });

    backButton.classList.toggle("visible", currentStep > 0);
  }

  function updatePreview() {
    const name = fullNameInput.value.trim() || "Your Name";
    const role = selectedRole();

    previewName.textContent = name;
    previewRole.textContent = role;
  }

  function setMessage(message, type = "error") {
    if (!formMessage) return;

    formMessage.textContent = message;
    formMessage.classList.toggle("success", type === "success");
  }

  function goToStep(stepNumber) {
    currentStep = Math.max(0, Math.min(stepNumber, steps.length - 1));
    updateProgress();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  nextButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (currentStep === 2) {
        const profileFields = steps[2].querySelectorAll("input[required]");
        const isValid = [...profileFields].every((field) => field.reportValidity());

        if (!isValid) return;
      }

      goToStep(currentStep + 1);
    });
  });

  backButton.addEventListener("click", () => {
    goToStep(currentStep - 1);
  });

  roleInputs.forEach((input) => {
    input.addEventListener("change", () => {
      updatePreview();
      goToStep(2);
    });
  });

  roleCards.forEach((card) => {
    card.addEventListener("click", () => {
      const input = card.querySelector("input[name='accountRole']");

      input.checked = true;
      updatePreview();
      goToStep(2);
    });
  });

  fullNameInput.addEventListener("input", updatePreview);
  locationInput.addEventListener("input", updatePreview);

  togglePassword.addEventListener("click", () => {
    const visible = passwordInput.type === "text";

    passwordInput.type = visible ? "password" : "text";
    togglePassword.setAttribute("aria-label", visible ? "Show password" : "Hide password");
  });

  confirmPasswordInput.addEventListener("input", () => {
    const message = confirmPasswordInput.value !== passwordInput.value ? "Passwords do not match." : "";
    confirmPasswordInput.setCustomValidity(message);
  });

  passwordInput.addEventListener("input", () => {
    const message = confirmPasswordInput.value && confirmPasswordInput.value !== passwordInput.value
      ? "Passwords do not match."
      : "";
    confirmPasswordInput.setCustomValidity(message);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    setMessage("");

    const profileFields = steps[2].querySelectorAll("input[required]");
    const isValid = [...profileFields].every((field) => field.reportValidity());

    if (!isValid) return;

    submitButton.disabled = true;
    submitButton.textContent = "Creating...";

    try {
      const response = await fetch("register.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
        },
        body: new FormData(form),
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        setMessage(data.message || "Unable to create your account. Please try again.");
        return;
      }

      const storageKey = data.user.role === "admin" ? "foodbridgeAdminProfile" : "foodbridgeProfile";
      localStorage.setItem(storageKey, JSON.stringify(data.user));
      window.location.href = data.redirect;
    } catch (error) {
      setMessage("Unable to reach the registration server. Please run this page through PHP.");
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = "Continue ->";
    }
  });

  updateProgress();
  updatePreview();
});
