<?php
session_start();
require_once '../../../database/db.php';
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

if (!isset($_SESSION['user'])) {
  header("Location: ../../auth/login.php");
  exit;
}
$userId = $_SESSION['user']['id'];

// 1. Food Received and Trust Score
$stmt = mysqli_prepare($dbConn, "SELECT trust_score FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$trustScore = $userRow['trust_score'];

// Total collected
$stmt = mysqli_prepare($dbConn, "
    SELECT COALESCE(SUM(d.quantity), 0) AS total_collected
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    WHERE b.receiver_id = ? AND b.status = 'collected'
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$foodReceived = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total_collected'];

// 2. Active Bookings
$stmt = mysqli_prepare($dbConn, "SELECT COUNT(*) AS active_count FROM bookings WHERE receiver_id = ? AND status = 'reserved'");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$activeBookings = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['active_count'];

// 3. Booking Queue
$stmt = mysqli_prepare($dbConn, "
    SELECT b.booking_id, d.food_name, d.quantity, d.unit, d.pickup_address, p.timeslot
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    JOIN pickup_slots p ON b.pickup_slot_id = p.pickup_slot_id
    WHERE b.receiver_id = ? AND b.status = 'reserved'
    ORDER BY p.timeslot ASC
    LIMIT 3
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$queue = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// 4. Impact
$stmt = mysqli_prepare($dbConn, "
    SELECT COUNT(DISTINCT d.donor_id) AS active_donors
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    WHERE b.receiver_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$donorsSupported = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['active_donors'];

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
  <title>Receiver Dashboard - FoodBridge</title>
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
        <a href="browse-donations.php" class="dashboard-nav-item">Browse Food</a>
        <a href="bookings.php" class="dashboard-nav-item">My Bookings</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="history.php" class="dashboard-nav-item">History</a>
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
      <h1 class="page-heading">Receiver Overview</h1>
      <p class="page-subheading">Track your food collections, manage upcoming pickups, and find new donations.</p>

      <div class="content-body receiver-dashboard">
        <div class="summary-grid">
          <article class="summary-card stat-card-rescued">
            <div class="card-header-with-icon">
              <span class="summary-label">Food Received</span>
              <div class="icon-circle bg-forest-light">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--color-forest)" stroke-width="2"
                  fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="9" cy="21" r="1"></circle>
                  <circle cx="20" cy="21" r="1"></circle>
                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
              </div>
            </div>
            <strong><?= htmlspecialchars($foodReceived) ?> items</strong>
            <small>Lifetime total collected</small>
          </article>

          <article class="summary-card stat-card-active">
            <div class="card-header-with-icon">
              <span class="summary-label">Active Bookings</span>
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
            <strong><?= htmlspecialchars($activeBookings) ?></strong>
            <small>Pending pickups</small>
          </article>

          <article class="summary-card stat-card-trust">
            <div class="card-header-with-icon">
              <span class="summary-label">Trust Score</span>
              <div class="icon-circle bg-lime-light">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="#7cb018" stroke-width="2.5" fill="none"
                  stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
              </div>
            </div>
            <strong><?= htmlspecialchars($trustScore) ?>%</strong>
            <small><?= $trustBadge ?> receiver performance</small>
          </article>
        </div>

        <div class="dashboard-grid">
          <section class="panel-card main-panel">
            <div class="section-header">
              <div>
                <h2>My upcoming pickups</h2>
                <p class="muted-text">Your scheduled collections and priority food grabs.</p>
              </div>
              <span class="date-pill" id="todayDate"></span>
            </div>

            <div class="pickup-list">
              <?php if (empty($queue)): ?>
                <p class="muted-text">You have no upcoming pickups right now. Browse donations to find food.</p>
              <?php else: ?>
                <?php foreach ($queue as $item): ?>
                  <?php
                  $slotTime = strtotime($item['timeslot']);
                  $hoursUntil = ($slotTime - time()) / 3600;
                  $isSoon = $hoursUntil <= 2;
                  ?>
                  <article class="pickup-card" data-href="bookings.php" tabindex="0" role="button">
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
                      <h3><?= htmlspecialchars($item['food_name']) ?></h3>
                      <p class="pickup-desc">
                        <span class="meta-item"><?= htmlspecialchars($item['quantity']) ?>
                          <?= htmlspecialchars($item['unit']) ?></span>
                        <span class="meta-separator">•</span>
                        <span class="meta-item"><?= htmlspecialchars($item['pickup_address']) ?></span>
                        <span class="meta-separator">•</span>
                        <span class="meta-item">pickup <?= date('g:i A', $slotTime) ?></span>
                      </p>
                    </div>
                    <a href="bookings.php" class="btn btn-sm <?= $isSoon ? 'btn-primary' : 'btn-accent' ?>">
                      View Details
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
            <section class="panel-card trust-panel">
              <div class="panel-header-with-badge">
                <h2>Impact snapshot</h2>
                <span class="trust-badge"><?= $trustBadge ?></span>
              </div>
              <div class="trust-meter-wrapper">
                <div class="trust-meter">
                  <span class="trust-fill" style="width: <?= htmlspecialchars($trustScore) ?>%;"></span>
                </div>
                <span class="trust-percentage"><?= htmlspecialchars($trustScore) ?>%</span>
              </div>
              <p class="muted-text">You have supported <?= htmlspecialchars($donorsSupported) ?> unique
                donor<?= $donorsSupported == 1 ? '' : 's' ?> so far.</p>
            </section>
            <section class="panel-card actions-panel">
              <h2>Quick actions</h2>
              <div class="quick-actions">
                <a href="browse-donations.php" class="action-link-item">
                  <div class="link-left-content">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round" class="action-icon">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                      <circle cx="9" cy="7" r="4"></circle>
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Browse Food</span>
                  </div>
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round" class="chevron-icon">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </a>
                <a href="history.php" class="action-link-item">
                  <div class="link-left-content">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round" class="action-icon">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                      <line x1="16" y1="2" x2="16" y2="6"></line>
                      <line x1="8" y1="2" x2="8" y2="6"></line>
                      <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Review donations</span>
                  </div>
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"
                    stroke-linecap="round" stroke-linejoin="round" class="chevron-icon">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </a>
                <a href="trust-score.php" class="action-link-item">
                  <div class="link-left-content">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round" class="action-icon">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="16" y1="13" x2="8" y2="13"></line>
                      <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    <span>View Trust Score</span>
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
  <script src="receiver-dashboard.js"></script>
</body>

</html>