<?php
session_start();
require_once '../../../database/db.php';

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'donor') {
  header('Location: ../../auth/login.php');
  exit;
}

$userId = $_SESSION['user']['id'];
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';

// --- Helper: get initials ---
function getInitials($fullName)
{
  $parts = array_filter(explode(' ', trim($fullName)));
  $initials = '';
  if (count($parts) >= 1)
    $initials .= strtoupper(substr(array_shift($parts), 0, 1));
  if (count($parts) >= 1)
    $initials .= strtoupper(substr(array_shift($parts), 0, 1));
  return $initials ?: 'FB';
}

// --- Handle POST AJAX requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // 1. Update profile (name, location, password)
  if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';

    if (empty($name) || empty($location)) {
      echo json_encode(['success' => false, 'message' => 'Name and location cannot be empty.']);
      exit;
    }

    // Fetch current password hash
    $stmt = mysqli_prepare($dbConn, "SELECT password_hash FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dbUser = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $updatePassword = false;
    if (!empty($newPassword)) {
      if (empty($currentPassword) || !password_verify($currentPassword, $dbUser['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
        exit;
      }
      $updatePassword = true;
      $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    if ($updatePassword) {
      $stmt = mysqli_prepare($dbConn, "UPDATE users SET full_name = ?, location = ?, password_hash = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "sssi", $name, $location, $newHash, $userId);
    } else {
      $stmt = mysqli_prepare($dbConn, "UPDATE users SET full_name = ?, location = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "ssi", $name, $location, $userId);
    }

    if (mysqli_stmt_execute($stmt)) {
      $_SESSION['user']['name'] = $name;
      echo json_encode(['success' => true, 'message' => 'Profile updated successfully!', 'name' => $name]);
    } else {
      echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
    }
    mysqli_stmt_close($stmt);
    exit;
  }

  // 2. Upload avatar
  if ($action === 'upload_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
      $uploadDir = __DIR__ . '/../../../src/uploads/profiles/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }

      if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image size must be less than 2MB.']);
        exit;
      }

      $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
      if (strpos($fileType, 'image/') !== 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid image file.']);
        exit;
      }

      $fileName = 'avatar_' . $userId . '_' . time() . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
      $destination = $uploadDir . $fileName;

      if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
        $avatarUrl = 'uploads/profiles/' . $fileName;
        $stmt = mysqli_prepare($dbConn, "UPDATE users SET profile_url = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $avatarUrl, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['user']['avatarImage'] = $avatarUrl;
        echo json_encode(['success' => true, 'avatarUrl' => $avatarUrl]);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
      }
    } else {
      echo json_encode(['success' => false, 'message' => 'No valid file uploaded.']);
    }
    exit;
  }
  echo json_encode(['success' => false, 'message' => 'Invalid action.']);
  exit;
}

// --- Load receiver data for display ---
$stmt = mysqli_prepare($dbConn, "SELECT full_name, email, profile_url, location, trust_score, created_at FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
  header('Location: ../../auth/login.php');
  exit;
}

$memberSince = date('F Y', strtotime($user['created_at']));
$profileUrl = $user['profile_url'] ?? '';
$initials = getInitials($user['full_name']);

// --- Compute receiver stats ---
// Total pickups (bookings)
$stmt = mysqli_prepare($dbConn, "SELECT COUNT(*) AS total_donations FROM donations WHERE donor_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$pickupCount = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total_donations'] ?? 0;
mysqli_stmt_close($stmt);

// On-time rate: percentage of 'collected' bookings out of total
$stmt = mysqli_prepare($dbConn, "
    SELECT 
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN b.status = 'collected' THEN 1 ELSE 0 END) AS completed_bookings
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    WHERE d.donor_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$totalBookings = $bookingStats['total_bookings'] ?? 0;
$completedBookings = $bookingStats['completed_bookings'] ?? 0;
$onTimeRate = $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100) : 100;

// --- Prepare JSON config for JavaScript ---
$initData = json_encode([
  'name' => $user['full_name'],
  'email' => $user['email'],
  'location' => $user['location'] ?? '',
  'avatarImage' => $profileUrl,
  'memberSince' => $memberSince,
  'trustScore' => $user['trust_score'],
  'pickups' => (int) $pickupCount,
  'onTimeRate' => $onTimeRate,
  'initials' => $initials
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donor Profile - FoodBridge</title>

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
  <link rel="stylesheet" href="profile.css">
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
        <a href="my-donations.php" class="dashboard-nav-item active">My Donations</a>
        <a href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="review.php" class="dashboard-nav-item">Review</a>
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
        <?php if (!empty($profileUrl)): ?>
          <img src="../../<?= htmlspecialchars($profileUrl) ?>" alt="<?= htmlspecialchars($user['full_name']) ?>"
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
      <h1 class="page-heading">Donor Profile</h1>
      <p class="page-subheading">Update your profile credentials, address location, and notification preferences.</p>

      <!-- Main content body for receiver configuration -->
      <div class="content-body">
        <div class="profile-grid">
          <!-- Left Panel: Sidebar Info & Quick Summary -->
          <aside class="profile-sidebar" aria-labelledby="sidebarReceiverName">
            <div class="profile-card">
              <div class="profile-avatar-large" id="profileAvatarContainer"
                style="cursor: pointer; position: relative; overflow: hidden;" title="Click to change profile picture">
                <span id="profileInitials"><?= htmlspecialchars($initials) ?></span>
                <img id="profileAvatarImg" class="<?= empty($profileUrl) ? 'hidden' : '' ?>"
                  src="../../<?= htmlspecialchars($profileUrl) ?>" alt="Profile Picture"
                  style="width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;" />
                <div class="avatar-overlay"
                  style="position: absolute; inset: 0; background: rgba(0,0,0,0.5); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; opacity: 0; transition: opacity 0.2s;">
                  Change
                </div>
              </div>
              <input type="file" id="avatarFileInput" accept="image/*" style="display: none;" />
              <h2 id="sidebarReceiverName" class="profile-name"><?= htmlspecialchars($user['full_name']) ?></h2>
              <span class="profile-role">Donor</span>

              <div class="profile-meta-info">
                <div class="meta-item">
                  <span class="meta-label">Member Since</span>
                  <span class="meta-value" id="metaMemberSince"><?= htmlspecialchars($memberSince) ?></span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Trust Score</span>
                  <span class="meta-value"
                    style="color: #22c55e; font-weight: 700;"><?= $user['trust_score'] ?>/100</span>
                </div>
              </div>
            </div>

            <!-- Stats grid in sidebar -->
            <div class="profile-card stats-sidebar-card">
              <div class="activity-grid">
                <div>
                  <strong id="statPickups"><?= $pickupCount ?></strong>
                  <span>Donations</span>
                </div>
                <div>
                  <strong id="statOnTime"><?= $onTimeRate ?>%</strong>
                  <span>Reliability</span>
                </div>
              </div>
            </div>
          </aside>

          <!-- Right Panel: Configurations & Actions -->
          <div class="profile-main-content">
            <!-- Form 1: Account Credentials & Details -->
            <section class="profile-section-card" aria-labelledby="credentialsTitle">
              <h3 id="credentialsTitle" class="section-title">Account Credentials</h3>
              <form id="credentialsForm" class="profile-form">
                <div class="form-group-row">
                  <div class="form-group">
                    <label for="receiverName">Full Name</label>
                    <input type="text" id="receiverName" required placeholder="Enter full name"
                      value="<?= htmlspecialchars($user['full_name']) ?>" />
                  </div>
                  <div class="form-group">
                    <label for="receiverEmail">Email Address</label>
                    <input type="email" id="receiverEmail" disabled placeholder="Enter email address"
                      value="<?= htmlspecialchars($user['email']) ?>" />
                  </div>
                </div>

                <div class="form-group">
                  <label for="receiverLocation">Location / Address</label>
                  <input type="text" id="receiverLocation" required placeholder="Subang Jaya, Selangor"
                    value="<?= htmlspecialchars($user['location'] ?? '') ?>" />
                </div>

                <div class="divider"></div>

                <div class="change-password-toggle-wrapper">
                  <button type="button" class="btn-text-toggle" id="togglePasswordFormBtn">
                    <span>Change Account Password</span>
                    <svg class="chevron-icon" id="chevronIcon" viewBox="0 0 24 24" width="18" height="18" fill="none"
                      stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                  </button>
                </div>

                <div class="password-fields hidden" id="passwordFieldsContainer">
                  <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <div class="input-password-wrapper">
                      <input type="password" id="currentPassword" placeholder="••••••••" />
                      <button type="button" class="password-toggle-btn" data-toggle-target="currentPassword"
                        aria-label="Toggle password visibility">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2"
                          fill="none" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div class="form-group-row">
                    <div class="form-group">
                      <label for="newPassword">New Password</label>
                      <div class="input-password-wrapper">
                        <input type="password" id="newPassword" placeholder="••••••••" />
                        <button type="button" class="password-toggle-btn" data-toggle-target="newPassword"
                          aria-label="Toggle password visibility">
                          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2"
                            fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                        </button>
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="confirmPassword">Confirm New Password</label>
                      <div class="input-password-wrapper">
                        <input type="password" id="confirmPassword" placeholder="••••••••" />
                        <button type="button" class="password-toggle-btn" data-toggle-target="confirmPassword"
                          aria-label="Toggle password visibility">
                          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2"
                            fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-actions">
                  <button type="submit" class="btn btn-primary">Save Account Details</button>
                </div>
              </form>
            </section>
          </div>
        </div>
      </div>

      <!-- Toast Notification Container -->
      <div id="toastContainer" class="toast-container" aria-live="polite"></div>
    </main>
  </div>

  <!-- Pass data to JavaScript -->
  <script>
    window.receiverProfileConfig = <?= $initData ?>;
  </script>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="profile.js"></script>
</body>

</html>