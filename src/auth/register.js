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
  const profileSubmit = document.querySelector(".profile-step button[type='submit']");
  const otpSubmit = document.querySelector(".otp-step button[type='submit']");
  const otpInputs = [...document.querySelectorAll(".otp-input")];
  const otpMessage = document.querySelector("#otpMessage");
  const resendOtpButton = document.querySelector("#resendOtp");
  const formMessage = document.querySelector("#registerMessage");

  let currentStep = 0;
  let generatedOtp = "";

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

  function setMessage(message, type = "error", target = "register") {
    const targetElement = target === "otp" ? otpMessage : formMessage;
    if (!targetElement) return;

    targetElement.textContent = message;
    targetElement.classList.toggle("success", type === "success");
  }

  function generateOtp() {
    generatedOtp = Array.from({ length: 6 }, () => Math.floor(Math.random() * 10)).join("");
    console.log("OTP code:", generatedOtp);
  }

  function clearOtp() {
    otpInputs.forEach((input) => {
      input.value = "";
    });
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

        generateOtp();
        setMessage("A verification code has been sent.", "success", "otp");
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

  otpInputs.forEach((input, index) => {
    input.addEventListener("input", () => {
      input.value = input.value.replace(/\D/g, "");
      if (input.value && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
    });

    input.addEventListener("keydown", (event) => {
      if (event.key === "Backspace" && !input.value && index > 0) {
        otpInputs[index - 1].focus();
      }
    });
  });

  resendOtpButton.addEventListener("click", () => {
    generateOtp();
    setMessage("A new code has been sent.", "success", "otp");
    clearOtp();
    otpInputs[0]?.focus();
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
    setMessage("", "error", currentStep === 3 ? "otp" : "register");

    if (currentStep === 2) {
      const profileFields = steps[2].querySelectorAll("input[required]");
      const isValid = [...profileFields].every((field) => field.reportValidity());

      if (!isValid) return;

      generateOtp();
      setMessage("A verification code has been sent.", "success", "otp");
      goToStep(3);
      return;
    }

    if (currentStep === 3) {
      const otpCode = otpInputs.map((input) => input.value).join("");
      if (otpCode.length !== 6) {
        setMessage("Please enter the full 6-digit code.", "error", "otp");
        return;
      }

      if (otpCode !== generatedOtp) {
        setMessage("Incorrect code. Please try again.", "error", "otp");
        return;
      }

      submitButton.disabled = true;
      otpSubmit.disabled = true;
      otpSubmit.textContent = "Verifying...";

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
          setMessage(data.message || "Unable to create your account. Please try again.", "error", "otp");
          return;
        }

        const storageKey = data.user.role === "admin" ? "foodbridgeAdminProfile" : "foodbridgeProfile";
        localStorage.setItem(storageKey, JSON.stringify(data.user));
        window.location.href = data.redirect;
      } catch (error) {
        setMessage("Unable to reach the registration server. Please run this page through PHP.", "error", "otp");
      } finally {
        submitButton.disabled = false;
        otpSubmit.disabled = false;
        otpSubmit.textContent = "Verify code";
      }
    }
  });

  updateProgress();
  updatePreview();
  generateOtp();
});
