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
  const submitButton = profileSubmit;
  const otpInputs = [...document.querySelectorAll(".otp-input")];
  const otpMessage = document.querySelector("#otpMessage");
  const resendOtpButton = document.querySelector("#resendOtp");
  const otpTimer = document.querySelector("#otpTimer");
  const emailInput = document.querySelector("input[name='email']");
  const formMessage = document.querySelector("#registerMessage");

  let currentStep = 0;
  let otpExpiry = 0;
  let otpTimerInterval = null;
  let resendAvailableAt = 0;
  let resendInterval = null;

  const OTP_LIFETIME_MS = 2 * 60 * 1000; // 2 minutes
  const RESEND_COOLDOWN_MS = 30 * 1000; // 30 seconds

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

  async function sendOtpRequest() {
    if (!emailInput) {
      throw new Error('Cannot send OTP without an email address.');
    }

    const email = emailInput.value.trim();
    if (!email) {
      throw new Error('Please enter a valid email address.');
    }

    const data = new FormData();
    data.append('action', 'sendOtp');
    data.append('email', email);

    const response = await fetch('register.php', {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: data,
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Unable to send verification code.');
    }

    otpExpiry = Date.now() + OTP_LIFETIME_MS;
    startOtpTimer();
    startResendCooldown();
  }

  function startOtpTimer() {
    if (!otpTimer) return;
    clearInterval(otpTimerInterval);
    function update() {
      const remaining = Math.max(0, otpExpiry - Date.now());
      const seconds = Math.ceil(remaining / 1000);
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      otpTimer.textContent = remaining > 0 ? `Expires in ${mins}:${secs.toString().padStart(2, '0')}` : 'Code expired';

      if (remaining <= 0) {
        clearInterval(otpTimerInterval);
        setMessage('The verification code has expired. Request a new code.', 'error', 'otp');
      }
    }
    update();
    otpTimerInterval = setInterval(update, 1000);
  }

  function startResendCooldown() {
    if (!resendOtpButton) return;
    resendAvailableAt = Date.now() + RESEND_COOLDOWN_MS;
    resendOtpButton.disabled = true;
    clearTimeout(resendInterval);
    // Keep button label unchanged; re-enable after cooldown
    resendInterval = setTimeout(() => {
      resendOtpButton.disabled = false;
    }, RESEND_COOLDOWN_MS);
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

        sendOtpRequest()
          .then(() => {
            setMessage("A verification code has been sent.", "success", "otp");
            goToStep(currentStep + 1);
          })
          .catch((error) => {
            setMessage(error.message, "error", "register");
          });

        return;
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

  resendOtpButton.addEventListener("click", async () => {
    if (Date.now() < resendAvailableAt) return;

    try {
      await sendOtpRequest();
      setMessage("A new code has been sent.", "success", "otp");
      clearOtp();
      otpInputs[0]?.focus();
    } catch (error) {
      setMessage(error.message, "error", "otp");
    }
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

      try {
        await sendOtpRequest();
        setMessage("A verification code has been sent.", "success", "otp");
        goToStep(3);
      } catch (error) {
        setMessage(error.message, "error", "register");
      }

      return;
    }

    if (currentStep === 3) {
      const otpCode = otpInputs.map((input) => input.value).join("");
      if (otpCode.length !== 6) {
        setMessage("Please enter the full 6-digit code.", "error", "otp");
        return;
      }

      if (!otpExpiry || Date.now() > otpExpiry) {
        setMessage('The verification code has expired. Please request a new code.', 'error', 'otp');
        return;
      }

      submitButton.disabled = true;
      otpSubmit.disabled = true;
      otpSubmit.textContent = "Verifying...";

      try {
        const verifyForm = new FormData();
        verifyForm.append('action', 'verifyOtp');
        verifyForm.append('email', emailInput.value.trim());
        verifyForm.append('otp', otpCode);

        const verifyResponse = await fetch('register.php', {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: verifyForm,
        });

        const verifyData = await verifyResponse.json();
        if (!verifyResponse.ok || !verifyData.success) {
          setMessage(verifyData.message || 'Unable to verify your code.', 'error', 'otp');
          return;
        }

        const registerFormData = new FormData(form);
        registerFormData.append('action', 'register');

        const response = await fetch('register.php', {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: registerFormData,
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
          setMessage(data.message || 'Unable to create your account. Please try again.', 'error', 'otp');
          return;
        }

        const storageKey = data.user.role === 'admin' ? 'foodbridgeAdminProfile' : 'foodbridgeProfile';
        localStorage.setItem(storageKey, JSON.stringify(data.user));
        window.location.href = data.redirect;
      } catch (error) {
        setMessage('Unable to reach the registration server. Please run this page through PHP.', 'error', 'otp');
      } finally {
        submitButton.disabled = false;
        otpSubmit.disabled = false;
        otpSubmit.textContent = 'Verify code';
      }
    }
  });

  updateProgress();
  updatePreview();
});
