<?php
declare(strict_types=1);

session_start();

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
  header('Location: ../../auth/login.php');
  exit;
}

require_once __DIR__ . '/../../../database/db.php';

function escapeHtml(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function initials(string $name): string
{
  $parts = preg_split('/\s+/', trim($name)) ?: [];
  $letters = '';

  foreach (array_slice(array_filter($parts), 0, 2) as $part) {
    $letters .= strtoupper(substr($part, 0, 1));
  }

  return $letters ?: 'FB';
}

mysqli_query(
  $dbConn,
  'CREATE TABLE IF NOT EXISTS trust_rule_settings (
        setting_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        suspension_threshold TINYINT UNSIGNED NOT NULL DEFAULT 30,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CHECK (setting_id = 1),
        CHECK (suspension_threshold BETWEEN 0 AND 100)
    )'
);
mysqli_query($dbConn, 'INSERT IGNORE INTO trust_rule_settings (setting_id, suspension_threshold) VALUES (1, 30)');

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrfToken = (string) ($_POST['csrf_token'] ?? '');
  $submittedThreshold = filter_input(INPUT_POST, 'suspension_threshold', FILTER_VALIDATE_INT);

  if (!hash_equals($_SESSION['trust_rules_csrf'] ?? '', $csrfToken)) {
    $message = 'Your session has expired. Please try saving again.';
    $messageType = 'error';
  } elseif ($submittedThreshold === false || $submittedThreshold === null || $submittedThreshold < 0 || $submittedThreshold > 100) {
    $message = 'The suspension threshold must be a whole number from 0 to 100.';
    $messageType = 'error';
  } else {
    mysqli_begin_transaction($dbConn);
    $settingStatement = mysqli_prepare($dbConn, 'UPDATE trust_rule_settings SET suspension_threshold = ? WHERE setting_id = 1');
    $banStatement = mysqli_prepare(
      $dbConn,
      "UPDATE users SET status = 'banned' WHERE role IN ('donor', 'receiver') AND trust_score < ? AND status <> 'banned'"
    );

    $saved = false;
    $bannedCount = 0;
    if ($settingStatement && $banStatement) {
      mysqli_stmt_bind_param($settingStatement, 'i', $submittedThreshold);
      $settingsSaved = mysqli_stmt_execute($settingStatement);
      mysqli_stmt_bind_param($banStatement, 'i', $submittedThreshold);
      $usersUpdated = mysqli_stmt_execute($banStatement);
      $bannedCount = mysqli_stmt_affected_rows($banStatement);
      $saved = $settingsSaved && $usersUpdated;
    }
    if ($settingStatement)
      mysqli_stmt_close($settingStatement);
    if ($banStatement)
      mysqli_stmt_close($banStatement);

    if ($saved && mysqli_commit($dbConn)) {
      $_SESSION['trust_rules_notice'] = sprintf('Threshold saved. %d account%s below it %s banned.', $bannedCount, $bannedCount === 1 ? '' : 's', $bannedCount === 1 ? 'was' : 'were');
      header('Location: trust-rules.php');
      exit;
    }

    mysqli_rollback($dbConn);

    $message = 'Unable to save the threshold. Please try again.';
    $messageType = 'error';
  }
}

if (isset($_SESSION['trust_rules_notice'])) {
  $message = (string) $_SESSION['trust_rules_notice'];
  $messageType = 'success';
  unset($_SESSION['trust_rules_notice']);
}

$_SESSION['trust_rules_csrf'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['trust_rules_csrf'];

$settingResult = mysqli_query($dbConn, 'SELECT suspension_threshold FROM trust_rule_settings WHERE setting_id = 1');
$setting = $settingResult ? mysqli_fetch_assoc($settingResult) : null;
$threshold = (int) ($setting['suspension_threshold'] ?? 30);

$atRiskStatement = mysqli_prepare(
  $dbConn,
  "SELECT full_name, role, trust_score, status
     FROM users
     WHERE role IN ('donor', 'receiver') AND trust_score < ?
     ORDER BY trust_score ASC, full_name ASC"
);
$usersAtRisk = [];

if ($atRiskStatement) {
  mysqli_stmt_bind_param($atRiskStatement, 'i', $threshold);
  mysqli_stmt_execute($atRiskStatement);
  $result = mysqli_stmt_get_result($atRiskStatement);
  $usersAtRisk = mysqli_fetch_all($result, MYSQLI_ASSOC);
  mysqli_stmt_close($atRiskStatement);
}

$adminName = (string) ($_SESSION['user']['name'] ?? 'Admin');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trust Rules - Admin - FoodBridge</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="trust-rules.css">
</head>

<body>
  <div class="noise-bg"></div>
  <header class="dashboard-header">
    <a href="dashboard.php" class="navbar-brand">
      <div class="navbar-logo"><img src="../../assets/images/logo.png" alt="FoodBridge logo"></div>
    </a>
    <div class="nav-overlay" id="navOverlay">
      <nav class="dashboard-nav">
        <a href="dashboard.php" class="dashboard-nav-item">Overview</a>
        <a href="users.php" class="dashboard-nav-item">Users</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="donations.php" class="dashboard-nav-item">Donations</a>
        <a href="trust-rules.php" class="dashboard-nav-item active">Trust Rules</a>
        <a href="reports.php" class="dashboard-nav-item">Reports</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
      </nav>
    </div>
    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile" title="Notifications" href="notifications.php">&#128276;</a>
      <a href="profile.php" class="profile-avatar"><?php echo escapeHtml(initials($adminName)); ?></a>
      <a href="../../auth/login.php" class="action-btn-circle hide-mobile" title="Log Out">&#10132;</a>
      <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle mobile menu">&#9776;</button>
    </div>
  </header>

  <div class="dashboard-wrapper">
    <main class="dashboard-content">
      <h1 class="page-heading">Trust Rules Settings</h1>
      <p class="page-subheading">Configure automatic score deductions, verification thresholds, and platform moderation
        rules.</p>
      <div class="content-body">
        <div class="container">
          <section class="trust-box" aria-labelledby="rules-title">
            <div class="title">
              <h2 id="rules-title">Automated Trust Rules</h2>
            </div>
            <p>Set the trust-score threshold below which an account is automatically banned.</p>
            <?php if ($message !== ''): ?>
              <p class="form-notice <?php echo escapeHtml($messageType); ?>" role="status">
                <?php echo escapeHtml($message); ?>
              </p>
            <?php endif; ?>
            <form action="trust-rules.php" method="post">
              <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">
              <div class="slider-wrapper">
                <output class="value-box" id="sliderValue" for="slider"><?php echo $threshold; ?></output>
                <input type="range" min="0" max="100" value="<?php echo $threshold; ?>" id="slider"
                  name="suspension_threshold">
                <div class="range-labels"><span>0</span><span>100</span></div>
              </div>
              <button class="save-btn" type="submit">Save Threshold</button>
            </form>
          </section>

          <section class="risk-section" aria-labelledby="risk-title">
            <h2 id="risk-title">Users At Risk <span>(<?php echo count($usersAtRisk); ?>)</span></h2>
            <div class="risk-list">
              <?php if ($usersAtRisk === []): ?>
                <p class="empty-risk">No users currently below this threshold.</p>
              <?php else:
                foreach ($usersAtRisk as $user): ?>
                  <article class="risk-card">
                    <div class="risk-user">
                      <div class="risk-avatar"><?php echo escapeHtml(initials($user['full_name'])); ?></div>
                      <div>
                        <h3><?php echo escapeHtml($user['full_name']); ?></h3>
                        <p><?php echo escapeHtml(ucfirst($user['role'])); ?> &middot;
                          <?php echo escapeHtml(ucfirst(str_replace('_', ' ', $user['status']))); ?>
                        </p>
                      </div>
                    </div>
                    <div class="risk-meta"><span
                        class="score <?php echo (int) $user['trust_score'] <= 20 ? 'danger' : 'warning'; ?>"><?php echo (int) $user['trust_score']; ?></span>
                    </div>
                  </article>
                <?php endforeach; endif; ?>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>
  <script src="../../assets/js/header.js"></script>
  <script>const slider = document.getElementById('slider'), value = document.getElementById('sliderValue'); function syncSliderValue() { const thumbRadius = 11, trackWidth = slider.clientWidth - thumbRadius * 2, position = thumbRadius + (trackWidth * Number(slider.value) / 100); value.value = slider.value; value.textContent = slider.value; value.style.left = position + 'px' } slider.addEventListener('input', syncSliderValue); window.addEventListener('resize', syncSliderValue); syncSliderValue();</script>
</body>

</html>