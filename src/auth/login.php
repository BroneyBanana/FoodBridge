<?php
session_start();

class FormRenderException extends Exception
{
}

function isJsonRequest(): bool
{
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

  return stripos($accept, 'application/json') !== false
    || stripos($contentType, 'application/json') !== false
    || isset($_SERVER['HTTP_X_REQUESTED_WITH']);
}

function respond(array $payload, int $statusCode = 200): void
{
  if (isJsonRequest()) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
  }

  if (!empty($payload['success']) && !empty($payload['redirect'])) {
    header('Location: ' . $payload['redirect']);
    exit;
  }

  $GLOBALS['formError'] = $payload['message'] ?? 'Unable to sign in. Please try again.';
  http_response_code($statusCode);
  throw new FormRenderException();
}

function cleanInput(?string $value): string
{
  return trim((string) $value);
}

$formError = '';
$selectedRole = $_POST['role'] ?? 'donor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once __DIR__ . '/../../database/db.php';

  try {
    $requestData = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
      $jsonData = json_decode(file_get_contents('php://input'), true);

      if (is_array($jsonData)) {
        $requestData = $jsonData;
      }
    }

    $email = cleanInput($requestData['email'] ?? '');
    $password = (string) ($requestData['password'] ?? '');
    $selectedRole = cleanInput($requestData['role'] ?? 'donor');

    if ($email === '' || $password === '') {
      respond(['success' => false, 'message' => 'Please enter your email and password.'], 400);
    }

    $stmt = mysqli_prepare(
      $dbConn,
      'SELECT user_id, role, full_name, email, profile_url, password_hash, location, trust_score, total_food_donated, status
           FROM users
           WHERE email = ?
           LIMIT 1'
    );

    if (!$stmt) {
      respond(['success' => false, 'message' => 'Unable to prepare login request.'], 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $passwordMatches = false;

    if ($user) {
      $storedPassword = $user['password_hash'];
      $passwordMatches = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);
    }

    if (!$user || !$passwordMatches) {
      respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    if ($selectedRole !== '' && $user['role'] !== $selectedRole) {
      respond(['success' => false, 'message' => 'Please choose the correct account type for this email.'], 403);
    }

    if (in_array($user['status'], ['suspended', 'banned'], true)) {
      respond(['success' => false, 'message' => 'This account is not allowed to sign in.'], 403);
    }

    $passwordInfo = password_get_info($user['password_hash']);

    if ($passwordInfo['algo'] === 0 && hash_equals($user['password_hash'], $password)) {
      $newHash = password_hash($password, PASSWORD_DEFAULT);
      $updateStmt = mysqli_prepare($dbConn, 'UPDATE users SET password_hash = ? WHERE user_id = ?');

      if ($updateStmt) {
        $userId = (int) $user['user_id'];
        mysqli_stmt_bind_param($updateStmt, 'si', $newHash, $userId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
      }
    }

    session_regenerate_id(true);

    // Build absolute avatar URL from the actual DB value
    $baseUrl = '/FoodBridge/'; // adjust if your project is at a different path
    $avatarUrl = !empty($user['profile_url']) ? $user['profile_url'] : '';

    $_SESSION['user'] = [
      'id' => (int) $user['user_id'],
      'role' => $user['role'],
      'name' => $user['full_name'],
      'email' => $user['email'],
      'avatarImage' => $avatarUrl,
    ];

    $redirects = [
      'admin' => '../roles/admin/dashboard.php',
      'donor' => '../roles/donor/dashboard.php',
      'receiver' => '../roles/receiver/dashboard.php',
    ];

    respond([
      'success' => true,
      'redirect' => $redirects[$user['role']] ?? '../../index.php',
      'user' => [
        'id' => (int) $user['user_id'],
        'role' => $user['role'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'location' => $user['location'],
        'trustScore' => (int) $user['trust_score'],
        'totalFoodDonated' => (int) $user['total_food_donated'],
        'status' => $user['status'],
        'avatarImage' => $user['profile_url'] ?? '',
      ],
    ]);
  } catch (FormRenderException $exception) {
    // Render the form below with the message set by respond().
  }
}

$selectedRole = in_array($selectedRole, ['donor', 'receiver', 'admin'], true) ? $selectedRole : 'donor';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In - FoodBridge</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="login.css">
</head>

<body>
  <div class="noise-bg"></div>

  <main class="login-page">
    <a class="back-button" href="../../index.php" aria-label="Back to home">
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

    <section class="login-panel" aria-labelledby="login-title">
      <div class="admin-access">
        <a href="#admin" id="adminAccess">Admin Access</a>
        <span>Use your database account</span>
      </div>

      <div class="login-card">
        <div class="login-header">
          <p class="eyebrow">FoodBridge Login</p>
          <h2 id="login-title">Welcome Back</h2>
          <p>Sign in to continue your food rescue journey.</p>
        </div>

        <div class="role-tabs" role="tablist" aria-label="Choose account type">
          <button class="role-tab <?php echo $selectedRole === 'donor' ? 'active' : ''; ?>" type="button" role="tab"
            aria-selected="<?php echo $selectedRole === 'donor' ? 'true' : 'false'; ?>" data-role="donor">Donor</button>
          <button class="role-tab <?php echo $selectedRole === 'receiver' ? 'active' : ''; ?>" type="button" role="tab"
            aria-selected="<?php echo $selectedRole === 'receiver' ? 'true' : 'false'; ?>"
            data-role="receiver">Receiver</button>
          <button class="role-tab <?php echo $selectedRole === 'admin' ? 'active' : ''; ?>" type="button" role="tab"
            aria-selected="<?php echo $selectedRole === 'admin' ? 'true' : 'false'; ?>" data-role="admin">Admin</button>
        </div>

        <form class="login-form" action="login.php" method="post" id="loginForm">
          <input type="hidden" name="role" id="accountRole"
            value="<?php echo htmlspecialchars($selectedRole, ENT_QUOTES); ?>">

          <label class="form-field" for="email">
            <span>Email</span>
            <input id="email" name="email" type="email" autocomplete="email"
              value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required>
          </label>

          <label class="form-field password-field" for="password">
            <span>Password</span>
            <div class="password-control">
              <input id="password" name="password" type="password" autocomplete="current-password" required>
              <button class="icon-button" type="button" id="togglePassword" aria-label="Show password">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m3 3 18 18"></path>
                  <path d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6"></path>
                  <path d="M9.9 4.2A9.8 9.8 0 0 1 12 4c6.5 0 10 8 10 8a17.8 17.8 0 0 1-3.1 4.3"></path>
                  <path d="M6.6 6.6C3.6 8.6 2 12 2 12s3.5 8 10 8a9.7 9.7 0 0 0 4.1-.9"></path>
                </svg>
              </button>
            </div>
          </label>

          <div class="form-row">
            <label class="remember-me">
              <input type="checkbox" name="remember">
              <span>Remember me</span>
            </label>
            <a href="forgot.php" id="forgotPasswordLink">Forgot password?</a>
          </div>

          <p class="form-message" role="status" aria-live="polite">
            <?php echo htmlspecialchars($formError, ENT_QUOTES); ?>
          </p>
          <button class="sign-in-button" type="submit">Sign In</button>
        </form>

        <p class="signup-copy">Don't have an account? <a href="register.php">Sign Up</a></p>
      </div>
      <!-- Forgot Password Modal -->
      <div class="modal-overlay" id="forgotPasswordModal" hidden>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="forgotTitle">
          <button class="modal-close" type="button" id="closeForgotModal" aria-label="Close">&times;</button>

          <!-- Step 1: request code -->
          <div id="forgotStepEmail">
            <h3 id="forgotTitle">Reset your password</h3>
            <p>Enter your email and we'll send you a verification code.</p>

            <form id="forgotPasswordForm">
              <label class="form-field" for="forgotEmail">
                <span>Email</span>
                <input id="forgotEmail" name="email" type="email" required>
              </label>

              <p class="form-message" id="forgotMessage" role="status" aria-live="polite"></p>

              <button class="sign-in-button" type="submit" id="forgotSubmitBtn">Send Code</button>
            </form>
          </div>

          <!-- Step 2: enter code + new password -->
          <div id="forgotStepReset" hidden>
            <h3>Enter your code</h3>
            <p>Check your email for the 6-digit code, then set a new password.</p>

            <form id="resetPasswordForm">
              <label class="form-field" for="resetOtp">
                <span>Verification code</span>
                <input id="resetOtp" name="otp" type="text" inputmode="numeric" maxlength="6" required>
              </label>

              <label class="form-field" for="resetNewPassword">
                <span>New password</span>
                <input id="resetNewPassword" name="new_password" type="password" minlength="8" required>
              </label>

              <p class="form-message" id="resetMessage" role="status" aria-live="polite"></p>

              <button class="sign-in-button" type="submit" id="resetSubmitBtn">Reset Password</button>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="login.js"></script>
</body>

</html>