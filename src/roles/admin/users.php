<?php
// Prevent browser caching
// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
// header("Cache-Control: post-check=0, pre-check=0", false);
// header("Pragma: no-cache");

require_once '../../../database/db.php';

// Handle AJAX POST requests for CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);

  if (isset($input['action']) && isset($input['user_id'])) {
    $action = $input['action'];
    $userId = (int) $input['user_id'];

    if ($action === 'warn') {
      $query = "UPDATE users SET status = 'warned' WHERE user_id = ?";
      $stmt = mysqli_prepare($dbConn, $query);
      mysqli_stmt_bind_param($stmt, "i", $userId);
      $success = mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      echo json_encode(['success' => $success && $affected >= 0, 'error' => mysqli_error($dbConn), 'affected' => $affected]);
      exit;
    } elseif ($action === 'ban') {
      $query = "UPDATE users SET status = 'banned' WHERE user_id = ?";
      $stmt = mysqli_prepare($dbConn, $query);
      mysqli_stmt_bind_param($stmt, "i", $userId);
      $success = mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      echo json_encode(['success' => $success && $affected >= 0, 'error' => mysqli_error($dbConn), 'affected' => $affected]);
      exit;
    } elseif ($action === 'restore') {
      $query = "UPDATE users SET status = 'active' WHERE user_id = ?";
      $stmt = mysqli_prepare($dbConn, $query);
      mysqli_stmt_bind_param($stmt, "i", $userId);
      $success = mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      echo json_encode(['success' => $success && $affected >= 0, 'error' => mysqli_error($dbConn), 'affected' => $affected]);
      exit;
    } elseif ($action === 'adjust_score') {
      $newScore = (int) $input['score'];
      $reason = $input['reason'] ?? 'Manual adjustment';

      // Get old score
      $q1 = "SELECT trust_score FROM users WHERE user_id = ?";
      $s1 = mysqli_prepare($dbConn, $q1);
      mysqli_stmt_bind_param($s1, "i", $userId);
      mysqli_stmt_execute($s1);
      $res1 = mysqli_stmt_get_result($s1);
      $userRow = mysqli_fetch_assoc($res1);

      if ($userRow) {
        $oldScore = $userRow['trust_score'];
        $diff = $newScore - $oldScore;

        // Update score
        $q2 = "UPDATE users SET trust_score = ? WHERE user_id = ?";
        $s2 = mysqli_prepare($dbConn, $q2);
        mysqli_stmt_bind_param($s2, "ii", $newScore, $userId);
        $success = mysqli_stmt_execute($s2);
        $affected = mysqli_stmt_affected_rows($s2);

        // Insert log
        if ($diff !== 0) {
          $q3 = "INSERT INTO trust_score_log (user_id, description, points_change) VALUES (?, ?, ?)";
          $s3 = mysqli_prepare($dbConn, $q3);
          mysqli_stmt_bind_param($s3, "isi", $userId, $reason, $diff);
          mysqli_stmt_execute($s3);
        }

        echo json_encode(['success' => $success, 'error' => mysqli_error($dbConn), 'affected' => $affected]);
        exit;
      } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
      }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
  }
  echo json_encode(['success' => false, 'error' => 'Missing action or user_id']);
  exit;
}

$query = "SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC";
$result = mysqli_query($dbConn, $query);
$users = [];
if ($result) {
  $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management - Admin - FoodBridge</title>

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
  <link rel="stylesheet" href="users.css">
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
        <a href="users.php" class="dashboard-nav-item active">Users</a>
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
          style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; background-color: #ff4757; border-radius: 50%;"></span>
      </a>

      <a href="profile.php" class="profile-avatar">DO</a>

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
      <h1 class="page-heading">User Management</h1>
      <p class="page-subheading">View, verify, and manage donors, receivers, and trust scores.</p>

      <div class="content-body">
        <div class="table-container">
          <!-- Top bar: filters and search -->
          <div class="table-top-bar">
            <div class="filter-group">
              <button class="filter-btn active">All</button>
              <button class="filter-btn">Donor</button>
              <button class="filter-btn">Receiver</button>
            </div>
            <div class="search-box">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" class="search-icon">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input type="text" placeholder="Search users...">
            </div>
          </div>

          <!-- Table -->
          <div class="table-responsive">
            <table class="users-table">
              <thead>
                <tr>
                  <th>USER</th>
                  <th>ROLE</th>
                  <th>TRUST SCORE</th>
                  <th>STATUS</th>
                  <th>ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                <tr data-role="<?= strtolower($u['role']) ?>">
                  <?php foreach ($users as $u): ?>
                    <?php
                    $scoreClass = 'score-high';
                    if ($u['trust_score'] < 50)
                      $scoreClass = 'score-low';
                    else if ($u['trust_score'] < 80)
                      $scoreClass = 'score-medium';

                    $statusClass = 'badge-active';
                    if ($u['status'] === 'warned')
                      $statusClass = 'badge-warn';
                    else if ($u['status'] === 'suspended' || $u['status'] === 'banned')
                      $statusClass = 'badge-ban';

                    $avatar = strtoupper(substr($u['full_name'], 0, 2));
                    $safeName = htmlspecialchars($u['full_name'], ENT_QUOTES);
                    ?>
                  <tr>
                    <td>
                      <div class="user-cell">
                        <div class="user-avatar"><?= $avatar ?></div>
                        <span class="user-name"><?= htmlspecialchars($u['full_name']) ?></span>
                      </div>
                    </td>
                    <td><span class="badge badge-<?= strtolower($u['role']) ?>"><?= strtoupper($u['role']) ?></span></td>
                    <td><span class="trust-score <?= $scoreClass ?>"><?= $u['trust_score'] ?></span></td>
                    <td class="status-cell"><span class="badge <?= $statusClass ?>"><?= strtoupper($u['status']) ?></span>
                    </td>
                    <td>
                      <div class="action-buttons">
                        <button class="btn-action ok"
                          onclick="openScoreModal(this, '<?= $safeName ?>', <?= $u['trust_score'] ?>, '<?= htmlspecialchars($u['role']) ?>', <?= $u['user_id'] ?>)">Adjust
                          score</button>
                        <button class="btn-action warn" onclick="warnUser(this, <?= $u['user_id'] ?>)" <?= ($u['status'] !== 'active') ? 'style="display:none;"' : '' ?>>Warn</button>
                        <button class="btn-action ban" onclick="banUser(this, <?= $u['user_id'] ?>)" <?= ($u['status'] !== 'active') ? 'style="display:none;"' : '' ?>>Ban</button>
                        <button class="btn-action ok restore" onclick="restoreUser(this, <?= $u['user_id'] ?>)"
                          <?= ($u['status'] === 'active') ? 'style="display:none;"' : '' ?>>Restore</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($users) === 0): ?>
                  <tr>
                    <td colspan="5" style="text-align:center; padding: 20px; color: var(--color-text-muted);">No users
                      found in database.</td>
                  </tr>
                <?php endif; ?>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Score Modal -->
      <div class="modal-wrap" id="score-modal-wrap">
        <div class="modal">
          <div class="mtitle" id="sm-title">Adjust trust score</div>
          <div class="msub">Manually add or deduct points. This should follow a reviewed report decision.</div>
          <div class="score-adj">
            <button class="sadj-btn" onclick="adjScore(-5)">−</button>
            <div class="sadj-score" id="sm-score">92</div>
            <button class="sadj-btn" onclick="adjScore(5)">+</button>
          </div>
          <div style="font-size:11px;color:var(--color-text-muted);text-align:center;margin-bottom:10px">Adjust in steps
            of 5. Each change requires a reason.</div>
          <div class="fgroup">
            <div class="flabel">Reason for adjustment</div>
            <textarea id="sm-note" rows="3"
              placeholder="e.g. Deducting 10pts — report #12 upheld (spoiled food, second offence)"></textarea>
          </div>
          <div class="mfoot">
            <button class="btn-outline" onclick="closeScoreModal()">Cancel</button>
            <button class="btn-primary" onclick="saveScoreAdj()">Save</button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="users.js"></script>
</body>

</html>