<?php
require_once '../../../database/db.php';

$query = "SELECT user_id, full_name, trust_score, total_food_donated FROM users WHERE role = 'donor' ORDER BY total_food_donated DESC, trust_score DESC LIMIT 20";
$result = mysqli_query($dbConn, $query);
$donors = [];
if ($result) {
    $donors = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= strtoupper(substr($w, 0, 1));
        }
        if (strlen($initials) >= 2) break;
    }
    return $initials ? $initials : 'FB';
}

function getFirstName($name) {
    $words = explode(' ', trim($name));
    return htmlspecialchars($words[0]);
}

$first = $donors[0] ?? null;
$second = $donors[1] ?? null;
$third = $donors[2] ?? null;

$rest = array_slice($donors, 3);
$leaderboardRows = [];
$rank = 4;
foreach ($rest as $row) {
    $leaderboardRows[] = [
        'rank' => $rank++,
        'initials' => getInitials($row['full_name']),
        'name' => htmlspecialchars($row['full_name']),
        'trust' => (int)$row['trust_score'],
        'TotalFoodDonated' => (int)$row['total_food_donated']
    ];
}
$leaderboardJson = json_encode($leaderboardRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leaderboard - Donor - FoodBridge</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">
  
  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  
  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="leaderboard.css">
  <script>
    // Inject rest of leaderboard rows via PHP
    const leaderboardRows = <?= $leaderboardJson ?>;
  </script>
</head>
<body>
  <div class="noise-bg"></div>
  <header class="dashboard-header">
    <a href="dashboard.html" class="navbar-brand">
      <div class="navbar-logo">
        <img src="../../assets/images/logo.png" alt="Logo" />
      </div>
    </a>
    
    <div class="nav-overlay" id="navOverlay">
      <nav class="dashboard-nav">
        <a href="dashboard.html" class="dashboard-nav-item">Overview</a>
        <a href="donate.html" class="dashboard-nav-item">Donate</a>
        <a href="my-donations.html" class="dashboard-nav-item">My Donations</a>
        <a href="leaderboard.php" class="dashboard-nav-item active">Leaderboard</a>
        <a href="vouchers.html" class="dashboard-nav-item">Vouchers</a>
        <a href="certificates.html" class="dashboard-nav-item">Certificates</a>
        <a href="trust-score.html" class="dashboard-nav-item">Trust Score</a>
        <a href="review.html" class="dashboard-nav-item">Review</a>
      </nav>
    </div>
    
    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile" title="Notifications" style="position: relative;" href="notifications.php">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>
        <span style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; background-color: #ff4757; border-radius: 50%;"></span>
      </a>

      <a href="profile.html" class="profile-avatar">DO</a>
      
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
    <main class="dashboard-content leaderboard-page">
      
      <section class="leaderboard-hero">
        <h1 class="page-heading">Global Ranks</h1>
        <p class="page-subheading">The most generous contributors in our network.</p>
      </section>

      <section class="podium-section" aria-label="Top donor leaderboard">
        
        <?php if ($second): ?>
        <article class="podium-player second-place">
          <div class="award-icon silver" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
          </div>
          <div class="donor-avatar"><?= getInitials($second['full_name']) ?></div>
          <h2><?= getFirstName($second['full_name']) ?></h2>
          <div class="podium-card podium-silver">
            <div class="points-wrapper">
              <strong><?= $second['total_food_donated'] ?></strong>
              <span>Total Food Donated</span>
            </div>
            <em>2</em>
          </div>
        </article>
        <?php endif; ?>

        <?php if ($first): ?>
        <article class="podium-player first-place">
          <div class="award-icon trophy" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H4v2h16v-2h-5c-.55 0-1-.45-1-1v-2.34M12 2a7 7 0 0 0-7 7c0 2.52 2 4.47 4.47 5.34h5.06C17 13.47 19 11.52 19 9a7 7 0 0 0-7-7z"/></svg>
          </div>
          <div class="donor-avatar main-avatar"><?= getInitials($first['full_name']) ?></div>
          <h2><?= getFirstName($first['full_name']) ?></h2>
          <div class="podium-card podium-gold">
            <div class="points-wrapper">
              <strong><?= $first['total_food_donated'] ?></strong>
              <span>Total Food Donated</span>
            </div>
            <em>1</em>
          </div>
        </article>
        <?php endif; ?>

        <?php if ($third): ?>
        <article class="podium-player third-place">
          <div class="award-icon bronze" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
          </div>
          <div class="donor-avatar"><?= getInitials($third['full_name']) ?></div>
          <h2><?= getFirstName($third['full_name']) ?></h2>
          <div class="podium-card podium-bronze">
            <div class="points-wrapper">
              <strong><?= $third['total_food_donated'] ?></strong>
              <span>Total Food Donated</span>
            </div>
            <em>3</em>
          </div>
        </article>
        <?php endif; ?>
      </section>

      <section class="rank-list" aria-label="Other donor ranks" id="rankList"></section>

    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="leaderboard.js"></script>
</body>
</html>
