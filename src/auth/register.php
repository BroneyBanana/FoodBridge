<?php
session_start();

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

        $role = inputValue($posted, 'accountRole');
        $fullName = inputValue($posted, 'fullName');
        $email = inputValue($posted, 'email');
        $location = inputValue($posted, 'profileLocation');
        $password = (string) ($posted['password'] ?? '');
        $confirmPassword = (string) ($posted['confirmPassword'] ?? '');

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
    </form>
  </main>

  <script src="register.js"></script>
</body>
</html>
