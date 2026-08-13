<?php
session_start();
require_once __DIR__ . '/../../../database/maintenance_guard.php';
if (!isset($_SESSION['user']['id'])) {
  header('Location: ../../auth/login.php');
  exit;
}
$userId = (int) $_SESSION['user']['id'];

require_once '../../../database/db.php';
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

// Fetch notifications
$query = "
    SELECT 
        n.notification_id, 
        n.title, 
        n.description, 
        n.created_at, 
        u.full_name 
    FROM notifications n
    JOIN users u ON n.user_id = u.user_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
";
$stmt = mysqli_prepare($dbConn, $query);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$dbNotifications = [];
if ($result) {
  $dbNotifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$notificationsJsArray = [];
foreach ($dbNotifications as $row) {
  $time = date('d M Y, H:i', strtotime($row['created_at']));
  $notificationsJsArray[] = [
    'id' => (int) $row['notification_id'],
    'title' => $row['title'],
    'description' => $row['description'],
    'time' => $time,
    'user' => $row['full_name']
  ];
}
$notificationsJson = json_encode($notificationsJsArray);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - Admin - FoodBridge</title>

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
  <link rel="stylesheet" href="notifications.css">
  <script>
    // Inject the notifications data from PHP to JS
    const notificationsList = <?= $notificationsJson ?>;
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

  <!-- Main Content Area -->
  <div class="dashboard-wrapper">
    <main class="dashboard-content admin-notifications">
      <h1 class="page-heading">Notifications</h1>
      <p class="page-subheading">Stay updated on systemic flags, user verification requests, and system alerts.</p>

      <div class="content-body" id="notificationsTarget"></div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="notifications.js"></script>
</body>

</html>