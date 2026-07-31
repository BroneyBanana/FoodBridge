<?php
session_start();
$donor_id = (int) $_SESSION['user_id'];
require_once __DIR__ . "/../../../database/db.php";

// Fetch only this donor's certificates
$certificatesQuery = "
    SELECT *
    FROM certificates
    WHERE donor_id = ?
    ORDER BY certificate_id DESC";

$stmt = $dbConn->prepare($certificatesQuery);
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$certificatesResult = $stmt->get_result();

$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

echo "<!-- DEBUG: donor_id used = $donor_id | rows found = " . $certificatesResult->num_rows . " -->";

// Converts the enum satisfaction rating into a 1-5 numeric score for star display
function satisfactionToRating($rate) {
    switch ($rate) {
        case 'Excellent': return 5.0;
        case 'Good':      return 4.0;
        case 'Average':   return 3.0;
        case 'Poor':      return 2.0;
        default:          return 0.0;
    }
}

function renderStars($rating) {
    $full = round($rating);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $full ? '<span class="star filled">★</span>' : '<span class="star">★</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificates - Donor - FoodBridge</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">

  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">

  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="certificates.css">
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
        <a href="dashboard.php" class="dashboard-nav-item">Overview</a>
        <a href="donate.php" class="dashboard-nav-item">Donate</a>
        <a href="my-donations.php" class="dashboard-nav-item">My Donations</a>
        <a href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="certificates.php" class="dashboard-nav-item active">Certificates</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="review.php" class="dashboard-nav-item">Review</a>
      </nav>
    </div>

    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile notification-btn" title="Notifications" href="notifications.php">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>
        <span class="notification-dot"></span>
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
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>

      <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle mobile menu">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>
    </div>
  </header>

  <!-- Main Content Area -->
  <div class="dashboard-wrapper">
    <main class="dashboard-content certificates-page">

      <section class="certificates-hero">
        <h1 class="page-heading">Certificates of Impact</h1>
        <p class="page-subheading">Download your carbon footprint offset and corporate social responsibility (CSR) certificates.</p>
      </section>

      <section class="certificates-grid" id="certificatesContainer" aria-label="Available Certificates">
        <?php if ($certificatesResult && $certificatesResult->num_rows > 0): ?>
          <?php while ($cert = $certificatesResult->fetch_assoc()):
              $rating = satisfactionToRating($cert['receiver_satisfaction_rate']);
              $startDate = date("M Y", strtotime($cert['period_start']));
              $endDate = date("M Y", strtotime($cert['period_end']));
          ?>
            <article class="certificate-card">
              <div class="cert-badge-wrapper" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="12" cy="8" r="6"/>
                  <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
                </svg>
              </div>

              <h2><?php echo htmlspecialchars($cert['certificate_name']); ?></h2>
              <p class="cert-recipient">Issued by <?php echo htmlspecialchars($cert['issued_by']); ?></p>

              <div class="cert-metrics-row">
                <div class="metric-group">
                  <span class="metric-label">Period</span>
                  <span class="metric-value"><?php echo htmlspecialchars($startDate . " - " . $endDate); ?></span>
                </div>
                <div class="metric-group text-right">
                  <span class="metric-label">Donations</span>
                  <span class="metric-value highlight"><?php echo (int)$cert['food_donated_count']; ?></span>
                </div>
              </div>

              <div class="cert-satisfaction">
                <div class="satisfaction-label">Receiver Satisfaction</div>
                <div class="star-rating" aria-label="Rating: <?php echo $rating; ?> out of 5 stars">
                  <?php echo renderStars($rating); ?>
                </div>
                <div class="score-text"><?php echo number_format($rating, 1); ?> / 5.0</div>
              </div>

              <a class="btn-download" target="_blank" href="generate-certificate.php?id=<?php echo (int)$cert['certificate_id']; ?>">
                Download Certificate
              </a>
            </article>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="no-data">No certificates have been issued to you yet.</p>
        <?php endif; ?>
      </section>

    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="certificates.js"></script>
</body>
</html>