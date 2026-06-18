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

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const profileFields = steps[2].querySelectorAll("input[required]");
    const isValid = [...profileFields].every((field) => field.reportValidity());

    if (!isValid) return;

    const profile = {
      name: fullNameInput.value.trim(),
      email: form.elements.email.value.trim(),
      location: locationInput.value.trim(),
      role: selectedRole(),
    };

    localStorage.setItem("foodbridgeProfile", JSON.stringify(profile));
    window.location.href = `../roles/${profile.role}/profile.html`;
  });

  updateProgress();
  updatePreview();
});
