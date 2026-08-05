document.addEventListener("DOMContentLoaded", () => {
  const stepEmail = document.querySelector("#forgotStepEmail");
  const stepOtp = document.querySelector("#forgotStepOtp");
  const stepPassword = document.querySelector("#forgotStepPassword");

  // Step 1 Elements
  const forgotForm = document.querySelector("#forgotPasswordForm");
  const forgotEmailInput = document.querySelector("#forgotEmail");
  const forgotMessage = document.querySelector("#forgotMessage");
  const forgotSubmitBtn = document.querySelector("#forgotSubmitBtn");

  // Step 2 Elements
  const verifyOtpForm = document.querySelector("#verifyOtpForm");
  const otpBoxes = document.querySelectorAll(".otp-box");
  const otpMessage = document.querySelector("#otpMessage");
  const verifyCodeBtn = document.querySelector("#verifyCodeBtn");
  const resendCodeBtn = document.querySelector("#resendCodeBtn");
  const timerDisplay = document.querySelector("#timerDisplay");

  // Step 3 Elements
  const resetForm = document.querySelector("#resetPasswordForm");
  const newPasswordInput = document.querySelector("#resetNewPassword");
  const confirmPasswordInput = document.querySelector("#resetConfirmPassword");
  const resetMessage = document.querySelector("#resetMessage");
  const resetSubmitBtn = document.querySelector("#resetSubmitBtn");

  let userEmail = "";
  let otpVerified = false;  // track whether OTP was successfully verified
  let timerInterval = null;

  // -------------------------------------------------------------
  // Countdown Timer Logic
  // -------------------------------------------------------------
  function startTimer(durationInSeconds) {
    clearInterval(timerInterval);
    let timeLeft = durationInSeconds;

    if (timerDisplay) {
      timerDisplay.style.color = "#667085";
    }

    function updateTimer() {
      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      const formattedSeconds = seconds < 10 ? `0${seconds}` : seconds;

      if (timerDisplay) {
        timerDisplay.textContent = `Expires in ${minutes}:${formattedSeconds}`;
      }

      if (timeLeft <= 0) {
        clearInterval(timerInterval);
        if (timerDisplay) {
          timerDisplay.textContent = "Code expired. Please request a new one.";
          timerDisplay.style.color = "#d93025";
        }
      }
      timeLeft--;
    }

    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
  }

  // -------------------------------------------------------------
  // STEP 1: Send Email
  // -------------------------------------------------------------
  if (forgotForm) {
    if (forgotEmailInput) forgotEmailInput.focus();

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
          userEmail = forgotEmailInput.value;
          otpVerified = false; // reset verification flag

          // Switch view to Step 2
          stepEmail.hidden = true;
          stepOtp.hidden = false;

          // Focus first OTP box
          if (otpBoxes[0]) otpBoxes[0].focus();

          // Start the timer
          startTimer(90);
        } else {
          forgotMessage.textContent = data.message || "Unable to send code.";
        }
      } catch (error) {
        forgotMessage.textContent = "Unable to reach the server. Please try again.";
      } finally {
        forgotSubmitBtn.disabled = false;
        forgotSubmitBtn.textContent = "Send Code";
      }
    });
  }

  // -------------------------------------------------------------
  // STEP 2: OTP Input Navigation & Verification
  // -------------------------------------------------------------
  otpBoxes.forEach((box, index) => {
    box.addEventListener("input", (e) => {
      const val = e.target.value;
      if (val.length === 1 && index < otpBoxes.length - 1) {
        otpBoxes[index + 1].focus();
      }
    });

    box.addEventListener("keydown", (e) => {
      if (e.key === "Backspace" && !box.value && index > 0) {
        otpBoxes[index - 1].focus();
      }
    });

    box.addEventListener("paste", (e) => {
      e.preventDefault();
      const pastedData = (e.clipboardData || window.clipboardData).getData("text").trim();
      if (/^\d{6}$/.test(pastedData)) {
        pastedData.split("").forEach((char, i) => {
          if (otpBoxes[i]) otpBoxes[i].value = char;
        });
        otpBoxes[5].focus();
      }
    });
  });

  if (verifyOtpForm) {
    verifyOtpForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      otpMessage.textContent = "";

      const otpCode = Array.from(otpBoxes).map((box) => box.value).join("");
      if (otpCode.length !== 6) {
        otpMessage.textContent = "Please enter all 6 digits.";
        return;
      }

      verifyCodeBtn.disabled = true;
      verifyCodeBtn.textContent = "Verifying...";

      try {
        const formData = new FormData();
        formData.append("email", userEmail);
        formData.append("otp", otpCode);

        const response = await fetch("verify-otp.php", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: formData,
        });
        const data = await response.json();

        if (data.success) {
          clearInterval(timerInterval);
          otpVerified = true;  // mark as verified

          // Switch view to Step 3
          stepOtp.hidden = true;
          stepPassword.hidden = false;
          if (newPasswordInput) newPasswordInput.focus();
        } else {
          otpMessage.textContent = data.message || "Invalid or expired code.";
        }
      } catch (error) {
        otpMessage.textContent = "Verification failed. Please try again.";
      } finally {
        verifyCodeBtn.disabled = false;
        verifyCodeBtn.textContent = "Verify code";
      }
    });

    if (resendCodeBtn) {
      resendCodeBtn.addEventListener("click", async () => {
        otpMessage.textContent = "";
        resendCodeBtn.disabled = true;
        resendCodeBtn.textContent = "Sending...";

        try {
          const formData = new FormData();
          formData.append("email", userEmail);

          const response = await fetch("forgot-password.php", {
            method: "POST",
            headers: { Accept: "application/json" },
            body: formData,
          });
          const data = await response.json();

          if (data.success) {
            otpMessage.style.color = "#54795e";
            otpMessage.textContent = "A new verification code has been sent.";
            otpVerified = false;  // new code invalidates previous verification
            startTimer(90);
          } else {
            otpMessage.style.color = "#d93025";
            otpMessage.textContent = data.message || "Failed to resend code.";
          }
        } catch (error) {
          otpMessage.style.color = "#d93025";
          otpMessage.textContent = "Server error. Try again later.";
        } finally {
          resendCodeBtn.disabled = false;
          resendCodeBtn.textContent = "Resend code";
        }
      });
    }
  }

  // -------------------------------------------------------------
  // STEP 3: Set New Password & Confirm Password
  // -------------------------------------------------------------
  if (resetForm) {
    resetForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      resetMessage.textContent = "";

      // Ensure OTP was verified before allowing reset (extra safety)
      if (!otpVerified) {
        resetMessage.textContent = "You must verify your code first. Please go back.";
        return;
      }

      const newPassword = newPasswordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      if (newPassword.length < 8) {
        resetMessage.textContent = "Password must be at least 8 characters.";
        return;
      }

      if (newPassword !== confirmPassword) {
        resetMessage.textContent = "Passwords do not match. Please try again.";
        return;
      }

      resetSubmitBtn.disabled = true;
      resetSubmitBtn.textContent = "Resetting...";

      try {
        const formData = new FormData();
        formData.append("email", userEmail);
        formData.append("password", newPassword);
        formData.append("confirm_password", confirmPassword);

        const response = await fetch("reset-password.php", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: formData,
        });
        const data = await response.json();

        resetMessage.textContent = data.message;

        if (data.success) {
          // Optionally revoke the verified flag
          otpVerified = false;
          setTimeout(() => {
            window.location.href = "login.php";
          }, 1500);
        }
      } catch (error) {
        resetMessage.textContent = "Unable to reach the server. Please try again.";
      } finally {
        resetSubmitBtn.disabled = false;
        resetSubmitBtn.textContent = "Reset Password";
      }
    });
  }
});