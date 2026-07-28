<?php
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer-master/src/SMTP.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

class FormRenderException extends Exception
{
}

function wantsJson(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    return stripos($accept, 'application/json') !== false
        || stripos($contentType, 'application/json') !== false
        || isset($_SERVER['HTTP_X_REQUESTED_WITH']);
}

function finishRegister(array $payload, int $statusCode = 200): void
{
    if (wantsJson()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!empty($payload['success']) && !empty($payload['redirect'])) {
        header('Location: ' . $payload['redirect']);
        exit;
    }

    $GLOBALS['formError'] = $payload['message'] ?? 'Unable to create your account. Please try again.';
    http_response_code($statusCode);
    throw new FormRenderException();
}

function inputValue(array $data, string $key): string
{
    return trim((string) ($data[$key] ?? ''));
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function expirePreviousOtps(mysqli $dbConn, string $email): void
{
    $stmt = mysqli_prepare($dbConn, 'UPDATE otp_verifications SET status = "expired" WHERE email = ? AND purpose = "registration" AND status = "pending"');
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function sendRegistrationOtpEmail(string $email, string $otpCode): bool
{
    $mail = new PHPMailer(true);

    try {
        $smtpUser = $_ENV['email_server'] ?? '';
        $smtpPort = $_ENV['email_port'] ?? '465';
        $smtpPassword = $_ENV['email_password'] ?? '';

        if ($smtpUser === '' || $smtpPassword === '') {
            error_log('SMTP credentials are missing in .env');
            return false;
        }

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int) $smtpPort;

        $mail->setFrom($smtpUser, 'FoodBridge');
        $mail->addAddress($email);

        $mail->isHTML(false);
        $mail->Subject = 'FoodBridge verification code';
        $mail->Body = "Your verification code is: $otpCode\n\n" .
            "Enter this code on the FoodBridge registration page within 2 minutes.\n\n" .
            "If you did not request this code, please ignore this email.";

        return $mail->send();
    } catch (Exception $e) {
        error_log('OTP email error: ' . $e->getMessage());
        return false;
    }
}

$formError = '';
$posted = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../database/db.php';

    try {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $jsonData = json_decode(file_get_contents('php://input'), true);

            if (is_array($jsonData)) {
                $posted = $jsonData;
            }
        }

        $action = inputValue($posted, 'action');
        $email = inputValue($posted, 'email');

        if ($action === 'sendOtp') {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respondJson(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
            }

            $checkStmt = mysqli_prepare($dbConn, 'SELECT user_id FROM users WHERE email = ? LIMIT 1');
            if (!$checkStmt) {
                respondJson(['success' => false, 'message' => 'Unable to validate email.'], 500);
            }
            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            $result = mysqli_stmt_get_result($checkStmt);
            $existingUser = mysqli_fetch_assoc($result);
            mysqli_stmt_close($checkStmt);

            if ($existingUser) {
                respondJson(['success' => false, 'message' => 'An account with this email already exists.'], 409);
            }

            expirePreviousOtps($dbConn, $email);
            $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', time() + 120);
            $status = 'pending';
            $purpose = 'registration';

            $insertStmt = mysqli_prepare(
                $dbConn,
                'INSERT INTO otp_verifications (email, otp_hash, purpose, expires_at, status)
                 VALUES (?, ?, ?, ?, ?)'
            );

            if (!$insertStmt) {
                respondJson(['success' => false, 'message' => 'Unable to store verification code.'], 500);
            }

            mysqli_stmt_bind_param($insertStmt, 'sssss', $email, $otpHash, $purpose, $expiresAt, $status);
            $created = mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);

            if (!$created) {
                respondJson(['success' => false, 'message' => 'Unable to store verification code.'], 500);
            }

            if (!sendRegistrationOtpEmail($email, $otpCode)) {
                respondJson(['success' => false, 'message' => 'Unable to send verification email. Please make sure your server can send mail.'], 500);
            }

            respondJson(['success' => true, 'message' => 'Verification code sent.']);
        }

        if ($action === 'verifyOtp') {
            $otp = inputValue($posted, 'otp');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $otp === '' || strlen($otp) !== 6) {
                respondJson(['success' => false, 'message' => 'Invalid verification request.'], 400);
            }

            $stmt = mysqli_prepare(
                $dbConn,
                'SELECT otp_id, otp_hash, expires_at
                 FROM otp_verifications
                 WHERE email = ? AND purpose = "registration" AND status = "pending"
                 ORDER BY otp_id DESC
                 LIMIT 1'
            );

            if (!$stmt) {
                respondJson(['success' => false, 'message' => 'Unable to verify code.'], 500);
            }

            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $otpRow = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$otpRow) {
                respondJson(['success' => false, 'message' => 'No pending verification code found. Please resend the code.'], 404);
            }

            if (strtotime($otpRow['expires_at']) < time()) {
                $expireStmt = mysqli_prepare($dbConn, 'UPDATE otp_verifications SET status = "expired" WHERE otp_id = ?');
                if ($expireStmt) {
                    mysqli_stmt_bind_param($expireStmt, 'i', $otpRow['otp_id']);
                    mysqli_stmt_execute($expireStmt);
                    mysqli_stmt_close($expireStmt);
                }
                respondJson(['success' => false, 'message' => 'The verification code has expired. Please resend the code.'], 410);
            }

            if (!password_verify($otp, $otpRow['otp_hash'])) {
                respondJson(['success' => false, 'message' => 'Incorrect verification code. Please try again.'], 401);
            }

            $useStmt = mysqli_prepare($dbConn, 'UPDATE otp_verifications SET status = "used" WHERE otp_id = ?');
            if ($useStmt) {
                mysqli_stmt_bind_param($useStmt, 'i', $otpRow['otp_id']);
                mysqli_stmt_execute($useStmt);
                mysqli_stmt_close($useStmt);
            }

            respondJson(['success' => true, 'message' => 'Verification code accepted.']);
        }

        if ($action === 'register') {
            $role = inputValue($posted, 'accountRole');
            $fullName = inputValue($posted, 'fullName');
            $location = inputValue($posted, 'profileLocation');
            $password = (string) ($posted['password'] ?? '');
            $confirmPassword = (string) ($posted['confirmPassword'] ?? '');

            if (!in_array($role, ['donor', 'receiver'], true)) {
                respondJson(['success' => false, 'message' => 'Please choose a valid account type.'], 400);
            }

            if ($fullName === '' || $email === '' || $location === '' || $password === '') {
                respondJson(['success' => false, 'message' => 'Please complete all required fields.'], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respondJson(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
            }

            if (strlen($password) < 8) {
                respondJson(['success' => false, 'message' => 'Password must be at least 8 characters.'], 400);
            }

            if ($password !== $confirmPassword) {
                respondJson(['success' => false, 'message' => 'Passwords do not match.'], 400);
            }

            $checkStmt = mysqli_prepare($dbConn, 'SELECT user_id FROM users WHERE email = ? LIMIT 1');
            if (!$checkStmt) {
                respondJson(['success' => false, 'message' => 'Unable to prepare registration request.'], 500);
            }

            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            $existingResult = mysqli_stmt_get_result($checkStmt);
            $existingUser = mysqli_fetch_assoc($existingResult);
            mysqli_stmt_close($checkStmt);

            if ($existingUser) {
                respondJson(['success' => false, 'message' => 'An account with this email already exists.'], 409);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $status = 'active';
            $insertStmt = mysqli_prepare(
                $dbConn,
                'INSERT INTO users (role, full_name, email, password_hash, location, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            if (!$insertStmt) {
                respondJson(['success' => false, 'message' => 'Unable to create account.'], 500);
            }

            mysqli_stmt_bind_param($insertStmt, 'ssssss', $role, $fullName, $email, $passwordHash, $location, $status);
            $created = mysqli_stmt_execute($insertStmt);
            $userId = mysqli_insert_id($dbConn);
            mysqli_stmt_close($insertStmt);

            if (!$created) {
                respondJson(['success' => false, 'message' => 'Unable to save account details.'], 500);
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $userId,
                'role' => $role,
                'name' => $fullName,
                'email' => $email,
            ];

            respondJson([
                'success' => true,
                'redirect' => "../roles/$role/profile.html",
                'user' => [
                    'id' => (int) $userId,
                    'role' => $role,
                    'name' => $fullName,
                    'email' => $email,
                    'location' => $location,
                    'trustScore' => 100,
                    'totalFoodDonated' => 0,
                    'status' => $status,
                ],
            ]);
        }

        if (!in_array($role, ['donor', 'receiver'], true)) {
            finishRegister(['success' => false, 'message' => 'Please choose a valid account type.'], 400);
        }

        if ($fullName === '' || $email === '' || $location === '' || $password === '') {
            finishRegister(['success' => false, 'message' => 'Please complete all required fields.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            finishRegister(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
        }

        if (strlen($password) < 8) {
            finishRegister(['success' => false, 'message' => 'Password must be at least 8 characters.'], 400);
        }

        if ($password !== $confirmPassword) {
            finishRegister(['success' => false, 'message' => 'Passwords do not match.'], 400);
        }

        $checkStmt = mysqli_prepare($dbConn, 'SELECT user_id FROM users WHERE email = ? LIMIT 1');

        if (!$checkStmt) {
            finishRegister(['success' => false, 'message' => 'Unable to prepare registration request.'], 500);
        }

        mysqli_stmt_bind_param($checkStmt, 's', $email);
        mysqli_stmt_execute($checkStmt);
        $existingResult = mysqli_stmt_get_result($checkStmt);
        $existingUser = mysqli_fetch_assoc($existingResult);
        mysqli_stmt_close($checkStmt);

        if ($existingUser) {
            finishRegister(['success' => false, 'message' => 'An account with this email already exists.'], 409);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active';
        $insertStmt = mysqli_prepare(
            $dbConn,
            'INSERT INTO users (role, full_name, email, password_hash, location, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (!$insertStmt) {
            finishRegister(['success' => false, 'message' => 'Unable to create account.'], 500);
        }

        mysqli_stmt_bind_param($insertStmt, 'ssssss', $role, $fullName, $email, $passwordHash, $location, $status);
        $created = mysqli_stmt_execute($insertStmt);
        $userId = mysqli_insert_id($dbConn);
        mysqli_stmt_close($insertStmt);

        if (!$created) {
            finishRegister(['success' => false, 'message' => 'Unable to save account details.'], 500);
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $userId,
            'role' => $role,
            'name' => $fullName,
            'email' => $email,
        ];

        finishRegister([
            'success' => true,
            'redirect' => "../roles/$role/profile.html",
            'user' => [
                'id' => (int) $userId,
                'role' => $role,
                'name' => $fullName,
                'email' => $email,
                'location' => $location,
                'trustScore' => 100,
                'totalFoodDonated' => 0,
                'status' => $status,
            ],
        ]);
    } catch (FormRenderException $exception) {
        // Render the form below with the message set by finishRegister().
    }
}

$selectedRole = in_array($posted['accountRole'] ?? '', ['donor', 'receiver'], true) ? $posted['accountRole'] : 'receiver';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - FoodBridge</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Syne:wght@400..800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="register.css">
</head>
<body>
  <div class="noise-bg"></div>

  <main class="register-page">
    <header class="register-topbar">
      <a class="brand-pill" href="../../index.html" aria-label="FoodBridge home">
        <span class="brand-mark">F</span>
        <span>FoodBridge</span>
      </a>

      <div class="step-dots" aria-label="Registration progress">
        <span class="step-dot active" data-dot="0"></span>
        <span class="step-dot" data-dot="1"></span>
        <span class="step-dot" data-dot="2"></span>
        <span class="step-dot" data-dot="3"></span>
      </div>

      <a class="signin-link" href="login.php">Sign in instead</a>
    </header>

    <button class="back-link" type="button" id="backButton">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="m15 18-6-6 6-6"></path>
      </svg>
      Back
    </button>

    <form class="register-flow" id="registerForm" action="register.php" method="post">
      <section class="register-step active intro-step" data-step="0" aria-labelledby="introTitle">
        <div class="hero-logo" aria-hidden="true">
          <span>F</span>
        </div>
        <h1 id="introTitle">Every meal deserves a second chance.</h1>
        <p>FoodBridge connects surplus food from local donors to communities in need. Let's set up your account.</p>

        <button class="primary-action" type="button" data-next>Get Started -></button>
        <a class="quiet-link" href="login.php">Sign in to existing account</a>
      </section>

      <section class="register-step role-step" data-step="1" aria-labelledby="roleTitle">
        <div class="step-heading">
          <h1 id="roleTitle">How will you use FoodBridge?</h1>
          <p>This customises your dashboard, tools, and experience on the platform.</p>
        </div>

        <div class="role-grid">
          <label class="role-card">
            <input type="radio" name="accountRole" value="donor" <?php echo $selectedRole === 'donor' ? 'checked' : ''; ?>>
            <span class="check-badge">&check;</span>
            <span class="role-icon">+</span>
            <strong>Donate Food</strong>
            <span>Share surplus meals, ingredients, or bakery stock from your household or business.</span>
            <span class="tag-row">
              <span>Restaurant</span>
              <span>Household</span>
              <span>Canteen</span>
            </span>
            <span class="role-list">
              <span>List in 60 seconds</span>
              <span>Earn vouchers and badges</span>
              <span>Get tax-exemption certs</span>
            </span>
          </label>

          <label class="role-card">
            <input type="radio" name="accountRole" value="receiver" <?php echo $selectedRole === 'receiver' ? 'checked' : ''; ?>>
            <span class="check-badge">&check;</span>
            <span class="role-icon">&#9633;</span>
            <strong>Find Food</strong>
            <span>Discover food options nearby and book pickup slots that work for you.</span>
            <span class="tag-row">
              <span>Individual</span>
              <span>NGO</span>
              <span>Community</span>
            </span>
            <span class="role-list">
              <span>Browse map and listings</span>
              <span>Book pickups by slot</span>
              <span>Allergy-safe filter</span>
            </span>
          </label>
        </div>
      </section>

      <section class="register-step profile-step" data-step="2" aria-labelledby="profileTitle">
        <div class="profile-preview" aria-hidden="true">
          <div class="avatar-preview">FB</div>
          <strong id="previewName">Your Name</strong>
          <span class="role-pill" id="previewRole">Receiver</span>
          <div class="trust-card">
            <span>100</span>
            <p>Trust Score<br><strong>100/100</strong></p>
          </div>
          <small>This is how others see you</small>
        </div>

        <div class="form-card">
          <h1 id="profileTitle">Create your profile</h1>
          <div class="photo-row">
            <div class="upload-circle">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 16V8"></path>
                <path d="m8 12 4-4 4 4"></path>
                <path d="M20 16.5A4.5 4.5 0 0 1 15.5 21h-7A4.5 4.5 0 0 1 4 16.5"></path>
              </svg>
            </div>
            <div>
              <button class="small-button" type="button">Add Photo</button>
              <span>Optional</span>
            </div>
          </div>

          <label class="field">
            <span>Full name</span>
            <input id="fullName" type="text" name="fullName" placeholder="Your full name" autocomplete="name" value="<?php echo htmlspecialchars($posted['fullName'] ?? '', ENT_QUOTES); ?>" required>
          </label>

          <label class="field">
            <span>Email address</span>
            <input type="email" name="email" placeholder="you@email.com" autocomplete="email" value="<?php echo htmlspecialchars($posted['email'] ?? '', ENT_QUOTES); ?>" required>
          </label>

          <label class="field">
            <span>Location</span>
            <input type="text" name="profileLocation" placeholder="e.g. Subang Jaya, Selangor" autocomplete="street-address" value="<?php echo htmlspecialchars($posted['profileLocation'] ?? '', ENT_QUOTES); ?>" required>
          </label>

          <div class="field-grid">
            <label class="field password-wrap">
              <span>Password</span>
              <input id="password" type="password" name="password" placeholder="Min 8 chars" autocomplete="new-password" minlength="8" required>
              <button class="eye-button" type="button" id="togglePassword" aria-label="Show password">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </label>

            <label class="field">
              <span>Confirm</span>
              <input id="confirmPassword" type="password" name="confirmPassword" placeholder="Re-type" autocomplete="new-password" minlength="8" required>
            </label>
          </div>

          <p class="form-message" id="registerMessage" role="status" aria-live="polite"><?php echo htmlspecialchars($formError, ENT_QUOTES); ?></p>
          <button class="primary-action" type="submit">Continue -></button>
        </div>
      </section>

      <section class="register-step otp-step" data-step="3" aria-labelledby="otpTitle">
        <div class="otp-card">
          <h1 id="otpTitle">Verify your account</h1>
          <p id="otpInfo">Enter the 6-digit code we sent to your email to verify your account.</p>

          <div class="otp-inputs" aria-hidden="false">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-input" aria-label="Digit 1">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-input" aria-label="Digit 2">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-input" aria-label="Digit 3">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-input" aria-label="Digit 4">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-input" aria-label="Digit 5">
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-input" aria-label="Digit 6">
          </div>

          <p class="form-message" id="otpMessage" role="status" aria-live="polite"></p>

          <div class="otp-actions" style="margin-top:18px;">
            <div class="otp-buttons">
              <button type="button" class="primary-action small" id="resendOtp">Resend code</button>
              <button class="primary-action" type="submit">Verify code</button>
            </div>
            <small id="otpTimer" style="color:rgba(28,43,30,0.64);font-size:0.9rem;margin-top:12px;display:block;text-align:center;">Expires in 5:00</small>
          </div>
        </div>
      </section>
    </form>
  </main>

  <script src="register.js"></script>
</body>
</html>
