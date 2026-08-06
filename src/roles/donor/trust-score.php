<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'donor') {
    header('Location: ../../auth/login.php');
    exit();
}
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));
require_once __DIR__ . '/../../../database/db.php';

function h(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function initials(string $name): string
{
  $words = preg_split('/\s+/', trim($name)) ?: [];
  $initials = '';

  foreach (array_slice(array_filter($words), 0, 2) as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }

  return $initials ?: 'FB';
}

function percentage(int $numerator, int $denominator): int
{
  return $denominator > 0 ? (int) round(($numerator / $denominator) * 100) : 0;
}

$userId = (int) $_SESSION['user']['id'];
$userStatement = mysqli_prepare($dbConn, 'SELECT full_name, trust_score FROM users WHERE user_id = ? AND role = \'donor\' LIMIT 1');

if (!$userStatement) {
  http_response_code(500);
  exit('Unable to load your profile.');
}

mysqli_stmt_bind_param($userStatement, 'i', $userId);
mysqli_stmt_execute($userStatement);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($userStatement));
mysqli_stmt_close($userStatement);

if (!$user) {
  session_unset();
  session_destroy();
  header('Location: ../../auth/login.php');
  exit;
}

$metricsStatement = mysqli_prepare(
  $dbConn,
  "SELECT
        (SELECT COUNT(*) FROM donations WHERE donor_id = ?) AS total_donations,
        (SELECT COUNT(*) FROM donations WHERE donor_id = ? AND status = 'completed') AS completed_donations,
        (SELECT COUNT(*) FROM bookings b JOIN donations d ON d.donation_id = b.donation_id WHERE d.donor_id = ? AND b.status = 'collected') AS collected_bookings,
        (SELECT COUNT(*) FROM reviews r JOIN bookings b ON b.booking_id = r.booking_id JOIN donations d ON d.donation_id = b.donation_id WHERE d.donor_id = ?) AS reviewed_bookings,
        (SELECT COUNT(*) FROM trust_score_log WHERE user_id = ? AND points_change < 0) AS deductions,
        (SELECT COUNT(*) FROM trust_score_log WHERE user_id = ?) AS score_events"
);

$metrics = ['total_donations' => 0, 'completed_donations' => 0, 'collected_bookings' => 0, 'reviewed_bookings' => 0, 'deductions' => 0, 'score_events' => 0];

if ($metricsStatement) {
  mysqli_stmt_bind_param($metricsStatement, 'iiiiii', $userId, $userId, $userId, $userId, $userId, $userId);
  mysqli_stmt_execute($metricsStatement);
  $metrics = mysqli_fetch_assoc(mysqli_stmt_get_result($metricsStatement)) ?: $metrics;
  mysqli_stmt_close($metricsStatement);
}

$historyStatement = mysqli_prepare(
  $dbConn,
  'SELECT description, points_change, created_at FROM trust_score_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
);
$history = [];

if ($historyStatement) {
  mysqli_stmt_bind_param($historyStatement, 'i', $userId);
  mysqli_stmt_execute($historyStatement);
  $history = mysqli_fetch_all(mysqli_stmt_get_result($historyStatement), MYSQLI_ASSOC);
  mysqli_stmt_close($historyStatement);
}

$completionRate = percentage((int) $metrics['completed_donations'], (int) $metrics['total_donations']);
$reviewCoverage = percentage((int) $metrics['reviewed_bookings'], (int) $metrics['collected_bookings']);
$deductionRate = percentage((int) $metrics['deductions'], (int) $metrics['score_events']);
$score = max(0, min(100, (int) $user['trust_score']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trust Score - Donor - FoodBridge</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="trust-score.css">
</head>

<body>
  <div class="noise-bg"></div>
  <header class="dashboard-header">
    <a href="dashboard.php" class="navbar-brand">
      <div class="navbar-logo"><img src="../../assets/images/logo.png" alt="FoodBridge logo"></div>
    </a>
    <div class="nav-overlay" id="navOverlay">
      <nav class="dashboard-nav">
        <a href="dashboard.php" class="dashboard-nav-item">Overview</a><a href="donate.php"
          class="dashboard-nav-item">Donate</a><a href="my-donations.php" class="dashboard-nav-item">My Donations</a><a
          href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a><a href="vouchers.php"
          class="dashboard-nav-item">Vouchers</a><a href="certificates.php"
          class="dashboard-nav-item">Certificates</a><a href="trust-score.php" class="dashboard-nav-item active">Trust
          Score</a><a href="review.php" class="dashboard-nav-item">Review</a>
      </nav>
    </div>
    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile" title="Notifications" style="position: relative;"
        href="notifications.php">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>
        <span
          style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; border-radius: 50%;"></span>
      </a>

      <a href="profile.php" class="profile-avatar">
        <?php if (!empty($userAvatar)): ?>
          <img src="../../<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>"
            style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
          <?= htmlspecialchars($initials) ?>
        <?php endif; ?>
      </a>

      <a href="../../auth/login.php" class="action-btn-circle hide-mobile" title="Log Out">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </a>

      <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle mobile menu">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"
          stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>
    </div>
  </header>
  <div class="dashboard-wrapper">
    <main class="dashboard-content">
      <h1 class="page-heading">Trust Score</h1>
      <p class="page-subheading">View details about your rating level, score deductions, and history of donations.</p>
      <div class="content-body">
        <section class="trust-score-panel" aria-label="Trust score summary">
          <div class="score-ring" style="--score: <?php echo $score; ?>"
            aria-label="Trust score <?php echo $score; ?> out of 100">
            <div class="score-ring-inner"><strong><?php echo $score; ?></strong><span>Score</span></div>
          </div>
          <div class="score-breakdown">
            <div class="score-row"><span class="score-label">Donation Completion Rate</span>
              <div class="score-track">
                <div class="score-fill lime" style="width: <?php echo $completionRate; ?>%;"></div>
              </div><strong><?php echo $completionRate; ?>%</strong>
            </div>
            <div class="score-row"><span class="score-label">Review Coverage</span>
              <div class="score-track">
                <div class="score-fill forest" style="width: <?php echo $reviewCoverage; ?>%;"></div>
              </div><strong><?php echo $reviewCoverage; ?>%</strong>
            </div>
            <div class="score-row"><span class="score-label">Score Deductions</span>
              <div class="score-track">
                <div class="score-fill alert" style="width: <?php echo $deductionRate; ?>%;"></div>
              </div><strong><?php echo $deductionRate; ?>%</strong>
            </div>
          </div>
        </section>
        <section class="trust-history-panel" aria-label="Trust score history">
          <div class="history-header">
            <div><span class="history-eyebrow">Score Activity</span>
              <h2>Trust Score History</h2>
            </div><span class="history-total">Current score: <?php echo $score; ?></span>
          </div>
          <div class="history-list">
            <?php if ($history === []): ?>
              <p class="empty-history">No trust score activity yet.</p>
            <?php else:
              foreach ($history as $event):
                $positive = (int) $event['points_change'] >= 0; ?>
                <article class="history-item <?php echo $positive ? 'positive' : 'negative'; ?>">
                  <div class="history-marker"><?php echo $positive ? '+' : '-'; ?></div>
                  <div class="history-copy">
                    <div class="history-title-row">
                      <h3><?php echo h($event['description']); ?></h3>
                      <strong><?php echo sprintf('%+d marks', (int) $event['points_change']); ?></strong>
                    </div><span><?php echo h(date('j M Y, g:i A', strtotime($event['created_at']))); ?></span>
                  </div>
                </article>
              <?php endforeach; endif; ?>
          </div>
        </section>
      </div>
    </main>
  </div>
  <script src="../../assets/js/header.js"></script>
</body>

</html>