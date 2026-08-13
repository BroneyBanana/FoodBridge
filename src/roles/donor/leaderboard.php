<?php
require_once '../../../database/db.php'; 
session_start();
require_once __DIR__ . '/../../../database/maintenance_guard.php';

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'donor') {
    header('Location: ../../auth/login.php');
    exit();
}

$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

// Query now includes profile_url
$query = "SELECT user_id, full_name, trust_score, total_food_donated, profile_url 
          FROM users 
          WHERE role = 'donor' 
          ORDER BY total_food_donated DESC, trust_score DESC 
          LIMIT 20";
$result = mysqli_query($dbConn, $query);
$donors = [];
if ($result) {
  $donors = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getInitials($name)
{
  $words = explode(' ', trim($name));
  $initials = '';
  foreach ($words as $w) {
    if (!empty($w)) {
      $initials .= strtoupper(substr($w, 0, 1));
    }
    if (strlen($initials) >= 2)
      break;
  }
  return $initials ?: 'FB';
}

function getFirstName($name)
{
  $words = explode(' ', trim($name));
  return htmlspecialchars($words[0]);
}

function getAvatarHtml($row)
{
  if (!empty($row['profile_url'])) {
    // Normalize slashes and remove any leading slash
    $path = ltrim(str_replace('\\', '/', $row['profile_url']), '/');
    // Build URL relative to the root
    $url = '../../' . $path;
    return "<img src=\"$url\" alt=\"Profile\" class=\"avatar-img\">";
  } else {
    $initials = getInitials($row['full_name']);
    return "<span class=\"avatar-initials\">$initials</span>";
  }
}

$first = $donors[0] ?? null;
$second = $donors[1] ?? null;
$third = $donors[2] ?? null;

$rest = array_slice($donors, 3);
$leaderboardRows = [];
$rank = 4;
foreach ($rest as $row) {
  // Prepare the full URL for JavaScript as well
  $profileUrl = '';
  if (!empty($row['profile_url'])) {
    $path = ltrim(str_replace('\\', '/', $row['profile_url']), '/');
    $profileUrl = '../../' . $path;
  }

  $leaderboardRows[] = [
    'rank' => $rank++,
    'initials' => getInitials($row['full_name']),
    'name' => htmlspecialchars($row['full_name']),
    'trust' => (int) $row['trust_score'],
    'TotalFoodDonated' => (int) $row['total_food_donated'],
    'profile_url' => $profileUrl   // now a full URL
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
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">

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
        <a href="leaderboard.php" class="dashboard-nav-item active">Leaderboard</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="review.php" class="dashboard-nav-item">Review</a>
      </nav>
    </div>

    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile" title="Notifications" style="position: relative;"
        href="notifications.php">
        <img src="../../assets/icons/nav-bell.svg" alt="Notifications" width="20" height="20" />
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
        <img src="../../assets/icons/nav-logout.svg" alt="Log Out" width="20" height="20" />
      </a>

      <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle mobile menu">
        <img src="../../assets/icons/nav-menu.svg" alt="Menu" width="24" height="24" />
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
              <img src="../../assets/icons/icon-medal.svg" alt="Silver Medal" width="24" height="24" />
            </div>
            <div class="donor-avatar second-avatar">
              <?= getAvatarHtml($second) ?>
            </div>
            <h2>
              <?= getFirstName($second['full_name']) ?>
            </h2>
            <div class="podium-card podium-silver">
              <div class="points-wrapper">
                <strong>
                  <?= $second['total_food_donated'] ?>
                </strong>
                <span>Total Food Donated</span>
              </div>
              <em>2</em>
            </div>
          </article>
        <?php endif; ?>

        <?php if ($first): ?>
          <article class="podium-player first-place">
            <div class="award-icon trophy" aria-hidden="true">
              <img src="../../assets/icons/icon-trophy.svg" alt="Gold Trophy" width="28" height="28" />
            </div>
            <div class="donor-avatar main-avatar">
              <?= getAvatarHtml($first) ?>
            </div>
            <h2>
              <?= getFirstName($first['full_name']) ?>
            </h2>
            <div class="podium-card podium-gold">
              <div class="points-wrapper">
                <strong>
                  <?= $first['total_food_donated'] ?>
                </strong>
                <span>Total Food Donated</span>
              </div>
              <em>1</em>
            </div>
          </article>
        <?php endif; ?>

        <?php if ($third): ?>
          <article class="podium-player third-place">
            <div class="award-icon bronze" aria-hidden="true">
              <img src="../../assets/icons/icon-medal.svg" alt="Bronze Medal" width="24" height="24" />
            </div>
            <div class="donor-avatar third-avatar">
              <?= getAvatarHtml($first) ?>
            </div>
            <h2>
              <?= getFirstName($third['full_name']) ?>
            </h2>
            <div class="podium-card podium-bronze">
              <div class="points-wrapper">
                <strong>
                  <?= $third['total_food_donated'] ?>
                </strong>
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