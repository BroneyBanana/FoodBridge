<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - FoodBridge</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="login.css">

<style>
    /* Ensure hidden steps stay hidden */
    [hidden] {
      display: none !important;
    }

    /* Step container layout */
    #forgotStepEmail,
    #forgotStepOtp,
    #forgotStepPassword {
      width: 100%;
      display: flex;
      flex-direction: column;
    }

    /* 6-box OTP input styling */
    .otp-group {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 24px 0 16px 0;
    }
    .otp-box {
      width: 48px;
      height: 54px;
      text-align: center;
      font-size: 20px;
      font-weight: 700;
      border: 1px solid #d0d5dd;
      border-radius: 12px;
      background: #ffffff;
      outline: none;
      transition: all 0.2s ease;
    }
    .otp-box:focus {
      border-color: #54795e;
      box-shadow: 0 0 0 3px rgba(84, 121, 94, 0.2);
    }

    /* Step 2 action button layout */
    .login-card .step-actions {
      display: flex !important;
      gap: 12px !important;
      margin-top: 16px !important;
      width: 100% !important;
    }

    /* Exact medium sage green matching your screenshot */
    .login-card .sign-in-button,
    .login-card .secondary-btn,
    .login-card .primary-btn {
      padding: 14px 20px !important;
      border-radius: 12px !important;
      border: none !important;
      background-color: #54795e !important; /* Sage green tone */
      color: #ffffff !important;
      font-family: inherit !important;
      font-size: 15px !important;
      font-weight: 600 !important;
      cursor: pointer !important;
      transition: background-color 0.2s ease !important;
    }

    .login-card .step-actions .secondary-btn,
    .login-card .step-actions .primary-btn {
      flex: 1 !important;
    }

    .login-card .sign-in-button:hover,
    .login-card .secondary-btn:hover,
    .login-card .primary-btn:hover {
      background-color: #43624c !important;
    }

    .login-card .secondary-btn:disabled,
    .login-card .primary-btn:disabled,
    .login-card .sign-in-button:disabled {
      opacity: 0.6 !important;
      cursor: not-allowed !important;
    }

    .info-status {
      text-align: center;
      color: #54795e;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 8px;
    }

    .timer-text {
      text-align: center;
      color: #667085;
      font-size: 14px;
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="noise-bg"></div>

  <main class="login-page">
    <a class="back-button" href="login.php" aria-label="Back to login">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M19 12H5"></path>
        <path d="m12 19-7-7 7-7"></path>
      </svg>
    </a>

    <section class="login-visual" aria-label="FoodBridge community">
      <img src="../assets/images/impact3.jpg" alt="FoodBridge volunteers packing donated food">
      <div class="visual-shade"></div>
      <div class="brand-lockup">
        <img src="../assets/images/logo.png" alt="" aria-hidden="true">
        <span>FoodBridge</span>
      </div>
      <h1>Share what you can.<br>Take what you need.</h1>
    </section>

    <section class="login-panel">
      <div class="login-card">
        
        <!-- STEP 1: Enter Email -->
        <div id="forgotStepEmail">
          <div class="login-header">
            <p class="eyebrow">Account Recovery</p>
            <h2>Reset your password</h2>
            <p>Enter your email and we'll send you a verification code.</p>
          </div>

          <form id="forgotPasswordForm" class="login-form">
            <label class="form-field" for="forgotEmail">
              <span>Email</span>
              <input id="forgotEmail" name="email" type="email" required>
            </label>

            <p class="form-message" id="forgotMessage" role="status" aria-live="polite"></p>
            <button class="sign-in-button" type="submit" id="forgotSubmitBtn">Send Code</button>
          </form>
          <p class="signup-copy">Remembered your password? <a href="login.php">Sign In</a></p>
        </div>

        <!-- STEP 2: Verify Code (Matching your screenshot style) -->
        <div id="forgotStepOtp" hidden>
          <div class="login-header" style="text-align: center;">
            <h2 style="font-size: 28px;">Verify your account</h2>
            <p>Enter the 6-digit code we sent to your email to verify your account.</p>
          </div>

          <form id="verifyOtpForm" class="login-form">
            <div class="otp-group">
              <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
              <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
              <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
              <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
              <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
              <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
            </div>

            <p class="info-status">A verification code has been sent.</p>
            <p class="form-message" id="otpMessage" role="status" aria-live="polite"></p>

            <div class="step-actions">
              <button class="secondary-btn" type="button" id="resendCodeBtn">Resend code</button>
              <button class="primary-btn" type="submit" id="verifyCodeBtn">Verify code</button>
            </div>

            <p class="timer-text" id="timerDisplay">Expires in 1:30</p>
          </form>
        </div>

        <!-- STEP 3: Set New Password + Confirm Password -->
        <div id="forgotStepPassword" hidden>
          <div class="login-header">
            <p class="eyebrow">New Password</p>
            <h2>Set new password</h2>
            <p>Please create a strong password for your account.</p>
          </div>

          <form id="resetPasswordForm" class="login-form">
            <label class="form-field" for="resetNewPassword">
              <span>New password</span>
              <input id="resetNewPassword" name="new_password" type="password" minlength="8" required placeholder="Min. 8 characters">
            </label>

            <label class="form-field" for="resetConfirmPassword">
              <span>Confirm password</span>
              <input id="resetConfirmPassword" name="confirm_password" type="password" minlength="8" required placeholder="Re-enter password">
            </label>

            <p class="form-message" id="resetMessage" role="status" aria-live="polite"></p>
            <button class="sign-in-button" type="submit" id="resetSubmitBtn">Reset Password</button>
          </form>
        </div>

      </div>
    </section>
  </main>

  <script src="forgot.js"></script>
</body>
</html>