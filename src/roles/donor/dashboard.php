<?php
require_once '../../../database/db.php'; 
session_start();
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

if (!isset($_SESSION['user'])) {
  header("Location: ../../auth/login.php");
  exit;
}
$userId = $_SESSION['user']['id'];

// 1. Total Rescued and Trust Score
$stmt = mysqli_prepare($dbConn, "SELECT total_food_donated, trust_score FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$foodRescued = $userRow['total_food_donated'];
$trustScore = $userRow['trust_score'];

// 2. Active Donations
$stmt = mysqli_prepare($dbConn, "SELECT COUNT(*) AS active_count FROM donations WHERE donor_id = ? AND status = 'active'");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$activeDonations = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['active_count'];

$stmt = mysqli_prepare($dbConn, "
    SELECT COUNT(*) AS ready_today
    FROM donations d
    JOIN pickup_slots p ON p.donation_id = d.donation_id
    WHERE d.donor_id = ? AND d.status = 'active'
      AND p.timeslot BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$readyToday = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['ready_today'];

// 3. Queue (Upcoming donations)
$stmt = mysqli_prepare($dbConn, "
    SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.pickup_address, MIN(p.timeslot) as next_slot
    FROM donations d
    JOIN pickup_slots p ON p.donation_id = d.donation_id
    WHERE d.donor_id = ? AND d.status = 'active'
    GROUP BY d.donation_id
    ORDER BY next_slot ASC
    LIMIT 3
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$queue = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// 4. Impact metrics
$stmt = mysqli_prepare($dbConn, "
    SELECT COUNT(DISTINCT b.receiver_id) AS active_partners
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    WHERE d.donor_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$activePartners = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['active_partners'];

$trustBadge = 'Good';
if ($trustScore >= 90)
  $trustBadge = 'Excellent';
elseif ($trustScore < 50)
  $trustBadge = 'Warning';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donor Dashboard - FoodBridge</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">

  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">

  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="dashboard.css">
</head>

<body>
  <div class="noise-bg"></div>
  <header class="dashboard-header">
    <a href="dashboard.php" class="navbar-brand">
      <div class="navbar-logo">
        <img src="../../assets/images/logo.png" alt="Logo" />
      </div>
    </a>

    <div class="nav-overlay" id="navOverlay">
      <nav class="dashboard-nav">
        <a href="dashboard.php" class="dashboard-nav-item active">Overview</a>
        <a href="donate.php" class="dashboard-nav-item">Donate</a>
        <a href="my-donations.php" class="dashboard-nav-item">My Donations</a>
        <a href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="review.php" class="dashboard-nav-item">Review</a>
      </nav>
    </div>

    <div class="dashboard-actions">
            <a class="action-btn-circle hide-mobile" title="Notifications" style="position: relative;" href="notifications.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                </svg>
                <span style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; border-radius: 50%;"></span>
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

  <!-- Main Content Area -->
  <div class="dashboard-wrapper">
    <main class="dashboard-content">
      <h1 class="page-heading">Donor Overview</h1>
      <p class="page-subheading">Track your impact, claim food rescue points, and check your active donations status.
      </p>

      <div class="content-body donor-dashboard">
        <div class="summary-grid">
          <article class="summary-card stat-card-rescued">
            <div class="card-header-with-icon">
              <span class="summary-label">Food Rescued</span>
              <div class="icon-circle bg-lime-light">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--color-forest)" stroke-width="2"
                  fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M7 7h10"></path>
                  <path d="M7 12h10"></path>
                  <path d="M7 17h6"></path>
                  <path d="M5 7c0 8 2.5 10 7 10s7-2 7-10"></path>
                </svg>
              </div>
            </div>
            <strong>
              <?= htmlspecialchars($foodRescued) ?> items
            </strong>
            <small>Lifetime total rescued</small>
          </article>

          <article class="summary-card stat-card-donations">
            <div class="card-header-with-icon">
              <span class="summary-label">Active Donations</span>
              <div class="icon-circle bg-terracotta-light">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--color-terracotta)" stroke-width="2"
                  fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
            </div>
            <strong>
              <?= htmlspecialchars($activeDonations) ?>
            </strong>
            <small>
              <?= htmlspecialchars($readyToday) ?> ready for pickup today
            </small>
          </article>

          <article class="summary-card stat-card-trust">
            <div class="card-header-with-icon">
              <span class="summary-label">Trust Score</span>
              <div class="icon-circle bg-forest-light">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--color-forest)" stroke-width="2.5"
                  fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
              </div>
            </div>
            <strong>
              <?= htmlspecialchars($trustScore) ?>%
            </strong>
            <small>
              <?= $trustBadge ?> donor performance
            </small>
          </article>
        </div>

        <div class="dashboard-grid">
          <section class="panel-card main-panel">
            <div class="section-header">
              <div>
                <h2>Today’s donation queue</h2>
                <p class="muted-text">Your upcoming food releases, pickup windows, and priority rescue matches.</p>
              </div>
              <span class="date-pill" id="todayDate"></span>
            </div>

            <div class="pickup-list">
              <?php if (empty($queue)): ?>
                <p class="muted-text">No active donations with upcoming pickup slots right now.</p>
              <?php else: ?>
                <?php foreach ($queue as $item): ?>
                  <?php
                  $slotTime = strtotime($item['next_slot']);
                  $hoursUntil = ($slotTime - time()) / 3600;
                  $isSoon = $hoursUntil <= 2;
                  ?>
                  <article class="pickup-card" data-href="my-donations.php?id=<?= (int) $item['donation_id'] ?>"
                    tabindex="0" role="button">
                    <div class="pickup-info">
                      <span class="pickup-badge <?= $isSoon ? 'badge-ready' : 'badge-match' ?>">
                        <?php if ($isSoon): ?>
                          <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"
                            stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                          </svg>
                          Ready soon
                        <?php else: ?>
                          <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"
                            stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
                            <polygon
                              points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2">
                            </polygon>
                          </svg>
                          Upcoming
                        <?php endif; ?>
                      </span>
                      <h3>
                        <?= htmlspecialchars($item['food_name']) ?>
                      </h3>
                      <p class="pickup-desc">
                        <span class="meta-item">
                          <?= htmlspecialchars($item['quantity']) ?>
                          <?= htmlspecialchars($item['unit']) ?>
                        </span>
                        <span class="meta-separator">•</span>
                        <span class="meta-item">
                          <?= htmlspecialchars($item['pickup_address']) ?>
                        </span>
                        <span class="meta-separator">•</span>
                        <span class="meta-item">pickup
                          <?= date('g:i A', $slotTime) ?>
                        </span>
                      </p>
                    </div>
                    <a href="my-donations.php" class="btn btn-sm <?= $isSoon ? 'btn-primary' : 'btn-accent' ?>">
                      Go to My donation
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px;">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                      </svg>
                    </a>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>

          <aside class="dashboard-side">
            <section class="panel-card impact-panel">
              <h2>Impact snapshot</h2>
              <ul class="tag-list">
                <li>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20"></path>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"></path>
                  </svg>
                  <?= htmlspecialchars($foodRescued) ?> items rescued
                </li>
                <li>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 8l-2 4h4l-2 4"></path>
                  </svg>
                  <?= htmlspecialchars($activePartners) ?> total receiver served
                </li>
                <li>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12h18"></path>
                    <path d="M12 3v18"></path>
                  </svg>
                  <?= htmlspecialchars($readyToday) ?> pickups today
                </li>
              </ul>
            </section>

            <section class="panel-card trust-panel">
              <div class="panel-header-with-badge">
                <h2>Trust snapshot</h2>
                <span class="trust-badge">
                  <?= $trustBadge ?>
                </span>
              </div>
              <div class="trust-meter-wrapper">
                <div class="trust-meter">
                  <span class="trust-fill" style="width: <?= htmlspecialchars($trustScore) ?>%;"></span>
                </div>
                <span class="trust-percentage">
                  <?= htmlspecialchars($trustScore) ?>%
                </span>
              </div>
              <p class="muted-text">Your efforts are feeding families and reducing waste. Keep up the great work.</p>
            </section>

            <section class="panel-card actions-panel">
              <h2>Quick actions</h2>
              <div class="quick-actions">
                <a href="donate.html" class="action-link-item">
                  <div class="link-left-content">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round" class="action-icon">
                      <path d="M12 5v14"></path>
                      <path d="M5 12h14"></path>
                    </svg>
                    <span>Donate new food</span>
                  </div>
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round" class="chevron-icon">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </a>
                <a href="my-donations.html" class="action-link-item">
                  <div class="link-left-content">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round" class="action-icon">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                      <line x1="16" y1="2" x2="16" y2="6"></line>
                      <line x1="8" y1="2" x2="8" y2="6"></line>
                      <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Manage donations</span>
                  </div>
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round" class="chevron-icon">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </a>
                <a href="leaderboard.html" class="action-link-item">
                  <div class="link-left-content">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round" class="action-icon">
                      <path d="M12 2l2.5 6.5L21 11l-6.5 2.5L12 20l-2.5-6.5L3 11l6.5-2.5L12 2z"></path>
                    </svg>
                    <span>View leaderboard</span>
                  </div>
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round" class="chevron-icon">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </a>
              </div>
            </section>
          </aside>
        </div>
      </div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="dashboard.js"></script>
</body>

</html>