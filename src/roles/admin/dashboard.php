<?php
// ============================================================
// DB CONNECTION
// ============================================================
require_once '../../../database/db.php';
session_start();

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: ../../auth/login.php');
  exit();
}

$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));
// Temporary debug switches — remove once confirmed working
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------------------------------------------------
// 1. Food Rescued — lifetime total from users.total_food_donated,
//    summed across all donors.
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT COALESCE(SUM(total_food_donated), 0) AS total_rescued
    FROM users
    WHERE role = 'donor'
");
$foodRescued = mysqli_fetch_assoc($res)['total_rescued'];

// ------------------------------------------------------------
// 2. Active Donations — count where status = 'active'
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT COUNT(*) AS active_count
    FROM donations
    WHERE status = 'active'
");
$activeDonations = mysqli_fetch_assoc($res)['active_count'];

// Of those active donations, how many have a pickup slot in the next 24h
$res = mysqli_query($dbConn, "
    SELECT COUNT(DISTINCT d.donation_id) AS ready_today
    FROM donations d
    JOIN pickup_slots p ON p.donation_id = d.donation_id
    WHERE d.status = 'active'
      AND p.timeslot BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
");
$readyToday = mysqli_fetch_assoc($res)['ready_today'];

// ------------------------------------------------------------
// 3. Flagged Users
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT COUNT(*) AS flagged_count
    FROM users
    WHERE status = 'warned'
");
$flaggedUsers = mysqli_fetch_assoc($res)['flagged_count'];

// ------------------------------------------------------------
// 4. Today's donation queue
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT d.donation_id, d.food_name, d.category, d.quantity, d.unit,
           d.pickup_address, d.expiry_at, MIN(p.timeslot) AS next_slot
    FROM donations d
    JOIN pickup_slots p ON p.donation_id = d.donation_id
    WHERE d.status = 'active'
      AND p.timeslot >= NOW()
    GROUP BY d.donation_id
    ORDER BY next_slot ASC
    LIMIT 3
");
$queue = mysqli_fetch_all($res, MYSQLI_ASSOC);

// ------------------------------------------------------------
// 5. Impact snapshot
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "SELECT COUNT(*) AS open_reports FROM reports WHERE status = 'active'");
$openReports = mysqli_fetch_assoc($res)['open_reports'];

$res = mysqli_query($dbConn, "
    SELECT COUNT(DISTINCT donor_id) AS active_partners
    FROM donations
    WHERE status = 'active'
");
$activePartners = mysqli_fetch_assoc($res)['active_partners'];

// ------------------------------------------------------------
// 6. Reports by status (all-time, for chart)
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT status, COUNT(*) AS cnt
    FROM reports
    GROUP BY status
");
$reportStatusRows = mysqli_fetch_all($res, MYSQLI_ASSOC);

$reportStatusLabels = [
  'active' => 'Pending',
  'resolved' => 'Resolved',
  'dismissed' => 'Dismissed',
];

// Ensure all three statuses always appear, even if count is 0
$reportStatusData = ['Pending' => 0, 'Resolved' => 0, 'Dismissed' => 0];
foreach ($reportStatusRows as $row) {
  $label = $reportStatusLabels[$row['status']] ?? ucfirst($row['status']);
  $reportStatusData[$label] = (int) $row['cnt'];
}
$reportStatusJson = json_encode($reportStatusData);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - FoodBridge</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">

  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="dashboard.css">
  <script>
    // Inject category data from PHP to JS
    const reportStatusData = <?= $reportStatusJson ?>;
  </script>
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
        <a href="users.php" class="dashboard-nav-item">Users</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="donations.php" class="dashboard-nav-item">Donations</a>
        <a href="trust-rules.php" class="dashboard-nav-item">Trust Rules</a>
        <a href="reports.php" class="dashboard-nav-item">Reports</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
        <a href="donation-analytics.php" class="dashboard-nav-item">Analytics</a>
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
        <span style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; ; border-radius: 50%;"></span>
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
      <h1 class="page-heading">Admin Overview</h1>
      <p class="page-subheading">View system activity, active donations, and platform-wide performance heatmap.</p>

      <div class="content-body admin-dashboard">
        <div class="summary-grid">
          <article class="summary-card">
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
            <small>Lifetime total across all donors</small>
          </article>

          <article class="summary-card">
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
              <?= htmlspecialchars($readyToday) ?> ready in the next 24h
            </small>
          </article>

          <article class="summary-card">
            <div class="card-header-with-icon">
              <span class="summary-label">Warned Users</span>
              <div class="icon-circle bg-forest-light">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--color-forest)" stroke-width="2.5"
                  fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 9v4"></path>
                  <path d="M12 17h.01"></path>
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z">
                  </path>
                </svg>
              </div>
            </div>
            <strong>
              <?= htmlspecialchars($flaggedUsers) ?>
            </strong>
            <small>Accounts need to be aware of</small>
          </article>
        </div>

        <div class="dashboard-grid">
          <section class="panel-card main-panel">
            <div class="section-header">
              <div>
                <h2>Today&rsquo;s donation queue</h2>
                <p class="muted-text">Active donations and their next available pickup window.</p>
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
                  <article class="pickup-card" tabindex="0" role="button">
                    <div class="pickup-info">
                      <span class="pickup-badge <?= $isSoon ? 'badge-ready' : 'badge-match' ?>">
                        <?= $isSoon ? 'Ready soon' : 'Upcoming' ?>
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
                    <a href="donations.php" class="btn btn-sm btn-accent">
                      Go to Donations
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
            <!-- Reports by Status Chart -->
            <section class="panel-card">
              <div class="section-header">
                <div>
                  <h2>Reports by status</h2>
                  <p class="muted-text">All-time breakdown of reports filed on the platform.</p>
                </div>
              </div>
              <div style="position: relative; height: 220px; min-height: 220px; margin-top: 8px;">
                <canvas id="reportStatusChart"></canvas>
              </div>
            </section>

            <!-- Impact snapshot -->
            <section class="panel-card impact-panel">
              <h2>Impact snapshot</h2>
              <ul class="tag-list">
                <li><?= htmlspecialchars($openReports) ?> open report<?= $openReports == 1 ? '' : 's' ?></li>
                <li><?= htmlspecialchars($activePartners) ?> available donor<?= $activePartners == 1 ? '' : 's' ?></li>
                <li><?= htmlspecialchars($readyToday) ?> pickup<?= $readyToday == 1 ? '' : 's' ?> in 24h</li>
              </ul>
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