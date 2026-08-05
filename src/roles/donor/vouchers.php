<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth_path = __DIR__ . '/../../../auth.php';
if (file_exists($auth_path)) {
    include($auth_path);
}

// Direct MySQL fallback
if (!isset($db_connect) || $db_connect === null) {
    $db_host = "localhost";
    $db_user = "root";
    $db_pass = ""; 
    $db_name = "foodbridge";

    $db_connect = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($db_connect->connect_error) {
        die("Database connection failed: " . $db_connect->connect_error);
    }
}

// ===== FIXED: use the correct session key =====
if (!isset($_SESSION['user']['id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$donor_id   = (int) $_SESSION['user']['id'];          // ← correct key
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName   = $_SESSION['user']['name'] ?? 'User';
// ==============================================

$vouchers_list = [];
$redeemed_voucher_ids = [];
$total_donations = 0;

try {
    // 1. Fetch total food donated by this donor directly from users table
    $donations_sql = "SELECT COALESCE(total_food_donated, 0) AS total_donated 
                      FROM users 
                      WHERE user_id = ? AND role = 'donor'";
    $stmt_don = $db_connect->prepare($donations_sql);
    if ($stmt_don) {
        $stmt_don->bind_param("i", $donor_id);
        $stmt_don->execute();
        $res_don = $stmt_don->get_result()->fetch_assoc();
        $total_donations = (int)($res_don['total_donated'] ?? 0);
        $stmt_don->close();
    }
    $userAvatar = $_SESSION['user']['avatarImage'] ?? '';
    $userName = $_SESSION['user']['name'] ?? 'User';
    $initials = strtoupper(substr($userName, 0, 2));

    // 2. Fetch list of voucher IDs already redeemed by this donor
    $redemptions_sql = "SELECT voucher_id FROM voucher_redemptions WHERE donor_id = ?";
    $stmt_red = $db_connect->prepare($redemptions_sql);
    if ($stmt_red) {
        $stmt_red->bind_param("i", $donor_id);
        $stmt_red->execute();
        $res_red = $stmt_red->get_result();
        while ($row = $res_red->fetch_assoc()) {
            $redeemed_voucher_ids[] = (int)$row['voucher_id'];
        }
        $stmt_red->close();
    }

    // 3. Fetch all available vouchers from database
    $vouchers_sql = "SELECT voucher_id, brand_name, reward_title, voucher_code, required_donations, expiration_date FROM vouchers";
    $vouchers_result = $db_connect->query($vouchers_sql);
    
    if ($vouchers_result) {
        while ($row = $vouchers_result->fetch_assoc()) {
            $vouchers_list[] = $row;
        }          
    } else {
        throw new Exception("Failed to retrieve vouchers data.");
    }

} catch (Exception $e) {
    error_log("Error retrieving voucher data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vouchers - Donor - FoodBridge</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">
  
  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  
  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="vouchers.css">
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
        <a href="vouchers.php" class="dashboard-nav-item active">Vouchers</a>
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

      <a href="profile.php" class="profile-avatar"><?php echo isset($_SESSION['user_initials']) ? htmlspecialchars($_SESSION['user_initials']) : 'DO'; ?></a>
      
      <a href="../../auth/logout.php" class="action-btn-circle hide-mobile" title="Log Out">
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
    <main class="dashboard-content vouchers-page">
      
      <section class="vouchers-hero">
        <h1 class="page-heading">Rewards & Vouchers</h1>
        <p class="page-subheading">Stay updated on your claimed bookings, active rescue windows, and trust ratings alerts.</p>
      </section>
      
      <!-- Available Vouchers Grid -->
      <section class="vouchers-grid" id="vouchersGrid" aria-label="Available partner rewards">
        <?php foreach($vouchers_list as $row){ 
            $v_id = (int)$row['voucher_id'];
            $req_donations = (int)$row['required_donations'];
            
            $is_redeemed = in_array($v_id, $redeemed_voucher_ids);
            $is_locked = $total_donations < $req_donations;
            $remaining_packs = $req_donations - $total_donations;

            // Brand Initials (e.g. Jaya Grocer -> JA)
            $words = explode(' ', trim($row['brand_name']));
            $initials = (count($words) >= 2) 
              ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
              : strtoupper(substr($row['brand_name'], 0, 2));

            $modifier_class = '';
            if ($is_redeemed) $modifier_class .= ' is-redeemed';
            if ($is_locked && !$is_redeemed) $modifier_class .= ' is-locked';
        ?>
          <article class="voucher-card <?php echo $modifier_class; ?>" 
                   data-id="<?php echo htmlspecialchars($row['voucher_id']); ?>" 
                   data-title="<?php echo htmlspecialchars($row['reward_title']); ?>"
                   data-code="<?php echo htmlspecialchars($row['voucher_code']); ?>">
            
            <?php if ($is_redeemed): ?>
              <div class="redeemed-overlay-badge">REDEEMED</div>
            <?php endif; ?>

            <?php if ($is_locked && !$is_redeemed): ?>
              <div class="locked-overlay-badge">
                Donate <?php echo $remaining_packs; ?> more pax of food to unlock
              </div>
            <?php endif; ?>

            <div class="voucher-card-header">
              <div class="voucher-avatar"><?php echo htmlspecialchars($initials); ?></div>
              <div class="ticket-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                  <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                </svg>
              </div>
            </div>

            <div class="voucher-details">
              <span class="partner-brand"><?php echo htmlspecialchars($row['brand_name']); ?></span>
              <h2 class="voucher-reward-title"><?php echo htmlspecialchars($row['reward_title']); ?></h2>
              <span class="expiry-stamp">VALID UNTIL <?php echo date("d/m/Y", strtotime($row['expiration_date'])); ?></span>
              <span class="voucher-note">Redeemable at your nearest <?php echo htmlspecialchars($row['brand_name']); ?> store.</span>
            </div>

            <button 
              class="btn-copy-code redeem-btn" 
              type="button" 
              <?php echo ($is_locked || $is_redeemed) ? 'disabled' : ''; ?>
            >
              <svg class="copy-svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
              </svg>
              <span><?php echo $is_locked ? 'Locked' : ($is_redeemed ? 'Redeemed' : 'Redeem'); ?></span>
            </button>

          </article>
        <?php } ?>
      </section>

    </main>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmationRedeemModal" class="modal">
    <div class="modal-content">
      <h2>Confirm Redemption</h2>
      <p>Are you sure you want to redeem <strong id="confirmationVoucherTitle"></strong>?</p>
      <div class="modal-actions" style="margin-top:20px; display:flex; gap:10px;">
        <button onclick="redeemVoucher()" class="btn-primary">Yes, Redeem</button>
        <button onclick="closeConfirmationModal()" class="btn-secondary">Cancel</button>
      </div>
    </div>
  </div>

<!-- Success Modal with Code Display -->
<div id="successRedeemModal" class="modal">
  <div class="modal-content">
    <h2>Success!</h2>
    <p>You have successfully redeemed <strong id="successVoucherTitle"></strong>.</p>
    
    <!-- Code Container Box -->
    <div style="margin: 20px 0; padding: 16px; background: #f4f5f3; border-radius: 12px; border: 1.5px dashed #1c2b1e;">
      <span style="display: block; font-size: 0.8rem; color: #6b7280; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Your Voucher Code</span>
      <span id="displayVoucherCode" style="font-family: monospace; font-size: 1.5rem; font-weight: 800; color: #1c2b1e; letter-spacing: 2px;">---</span>
    </div>

    <div class="modal-actions" style="display: flex; gap: 10px; justify-content: center;">
      <button onclick="copyVoucherCode()" class="btn-secondary" id="copyCodeBtn">Copy Code</button>
      <button onclick="closeSuccessModal()" class="btn-primary">Close</button>
    </div>
  </div>
</div>

  <!-- Error Modal -->
  <div id="errorRedeemModal" class="modal">
    <div class="modal-content">
      <h2>Redemption Failed</h2>
      <p>Could not redeem <strong id="errorVoucherTitle"></strong>. Please try again.</p>
      <button onclick="closeErrorModal()" class="btn-secondary" style="margin-top:20px;">Close</button>
    </div>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="vouchers.js"></script>
</body>
</html>