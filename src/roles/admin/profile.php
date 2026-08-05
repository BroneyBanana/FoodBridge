<?php
session_start();
require_once '../../../database/db.php';

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$userId = $_SESSION['user']['id'];

// --- Helper: compute initials ---
function getInitials($fullName) {
    $parts = array_filter(explode(' ', trim($fullName)));
    $initials = '';
    if (count($parts) >= 1) $initials .= strtoupper(substr(array_shift($parts), 0, 1));
    if (count($parts) >= 1) $initials .= strtoupper(substr(array_shift($parts), 0, 1));
    return $initials ?: 'AD';
}

// --- Handle POST AJAX requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update profile (name, password) – no location for admin
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
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
            $stmt = mysqli_prepare($dbConn, "UPDATE users SET full_name = ?, password_hash = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $name, $newHash, $userId);
        } else {
            $stmt = mysqli_prepare($dbConn, "UPDATE users SET full_name = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $name, $userId);
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

    // 3. Update maintenance mode
    if ($action === 'update_maintenance') {
        $isMaintenance = filter_var($_POST['maintenance'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $mode = $isMaintenance ? 'on' : 'off';

        mysqli_query($dbConn, "DELETE FROM platform_settings");
        $stmt = mysqli_prepare($dbConn, "INSERT INTO platform_settings (maintenance_mode) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "s", $mode);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Maintenance mode updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update maintenance mode.']);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// --- Load admin data ---
$stmt = mysqli_prepare($dbConn, "SELECT full_name, email, profile_url, created_at FROM users WHERE user_id = ?");
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

// --- Maintenance status ---
$stmt = mysqli_prepare($dbConn, "SELECT maintenance_mode FROM platform_settings LIMIT 1");
mysqli_stmt_execute($stmt);
$settingsResult = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$maintenanceMode = ($settingsResult['maintenance_mode'] ?? 'off') === 'on';

// --- JSON config for JavaScript ---
$initData = json_encode([
    'name' => $user['full_name'],
    'email' => $user['email'],
    'avatarImage' => $profileUrl,
    'memberSince' => $memberSince,
    'systemMaintenance' => $maintenanceMode,
    'initials' => $initials
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Profile - FoodBridge</title>

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
        <a href="users.php" class="dashboard-nav-item">Users</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="donations.php" class="dashboard-nav-item">Donations</a>
        <a href="trust-rules.php" class="dashboard-nav-item">Trust Rules</a>
        <a href="reports.php" class="dashboard-nav-item">Reports</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
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
      <h1 class="page-heading">Admin Profile</h1>
      <p class="page-subheading">Manage your credentials and system maintenance settings.</p>

      <!-- Main content body for admin configuration -->
      <div class="content-body">
        <div class="profile-grid">
          <!-- Left Panel: Sidebar Info & Quick Summary -->
          <aside class="profile-sidebar" aria-labelledby="sidebarAdminName">
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
              <h2 id="sidebarAdminName" class="profile-name"><?= htmlspecialchars($user['full_name']) ?></h2>
              <span class="profile-role">Super Admin</span>

              <div class="profile-meta-info">
                <div class="meta-item">
                  <span class="meta-label">Member Since</span>
                  <span class="meta-value" id="metaMemberSince"><?= htmlspecialchars($memberSince) ?></span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Access Level</span>
                  <span class="meta-value" style="color: #22c55e; font-weight: 700;">Full Access</span>
                </div>
              </div>
            </div>
          </aside>

          <!-- Right Panel: Configurations & Actions -->
          <div class="profile-main-content">
            <!-- Form 1: Account Credentials -->
            <section class="profile-section-card" aria-labelledby="credentialsTitle">
              <h3 id="credentialsTitle" class="section-title">Account Credentials</h3>
              <form id="credentialsForm" class="profile-form">
                <div class="form-group-row">
                  <div class="form-group">
                    <label for="adminName">Full Name</label>
                    <input type="text" id="adminName" required placeholder="Enter full name"
                      value="<?= htmlspecialchars($user['full_name']) ?>" />
                  </div>
                  <div class="form-group">
                    <label for="adminEmail">Email Address</label>
                    <input type="email" id="adminEmail" disabled placeholder="Enter email address"
                      value="<?= htmlspecialchars($user['email']) ?>" />
                  </div>
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

            <!-- Form 2: System Maintenance Settings -->
            <section class="profile-section-card" aria-labelledby="maintenanceTitle">
              <h3 id="maintenanceTitle" class="section-title">System Maintenance Settings</h3>
              <p class="section-desc">Control global availability and platform access permissions.</p>

              <div class="maintenance-box">
                <div class="maintenance-status-row">
                  <span class="status-label">Platform Status:</span>
                  <span class="status-indicator-badge <?= $maintenanceMode ? 'maintenance' : 'online' ?>"
                    id="platformStatusBadge"><?= $maintenanceMode ? 'Under Maintenance' : 'Active & Online' ?></span>
                </div>

                <div class="permission-item maintenance-item">
                  <div class="permission-info">
                    <strong>Enable Maintenance Mode</strong>
                    <span>Temporarily disable access to all receiver and admin dashboards. Admins will retain
                      access.</span>
                  </div>
                  <label class="switch-container">
                    <input type="checkbox" id="systemMaintenanceToggle" <?= $maintenanceMode ? 'checked' : '' ?> />
                    <span class="slider"></span>
                  </label>
                </div>
              </div>

              <div class="form-actions">
                <button type="button" id="saveMaintenanceBtn" class="btn btn-primary">Apply Operations</button>
              </div>
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
    window.adminProfileConfig = <?= $initData ?>;
  </script>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="profile.js"></script>
</body>

</html>