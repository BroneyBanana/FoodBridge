<?php
require_once '../../../database/db.php';

// Handle AJAX POST requests for CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && isset($input['report_id'])) {
        $reportId = (int)$input['report_id'];
        
        if ($input['action'] === 'resolve') {
            $deduction = (int)($input['deduction'] ?? 0);
            $rawNote = $input['note'] ?? '';
            $note = $deduction > 0 ? $rawNote . " (-" . $deduction . ")" : $rawNote;
            
            // 1. Update report status and note
            $q1 = "UPDATE reports SET status = 'resolved', admin_message = ? WHERE report_id = ?";
            $s1 = mysqli_prepare($dbConn, $q1);
            mysqli_stmt_bind_param($s1, "si", $note, $reportId);
            mysqli_stmt_execute($s1);
            
            // 2. If there's a deduction, get the user being reported and reduce their score
            if ($deduction > 0) {
                // Find who the report is against (the donor)
                $q2 = "SELECT d.donor_id FROM reports r JOIN bookings b ON r.booking_id = b.booking_id JOIN donations d ON b.donation_id = d.donation_id WHERE r.report_id = ?";
                $s2 = mysqli_prepare($dbConn, $q2);
                mysqli_stmt_bind_param($s2, "i", $reportId);
                mysqli_stmt_execute($s2);
                $res2 = mysqli_stmt_get_result($s2);
                if ($row = mysqli_fetch_assoc($res2)) {
                    $donorId = $row['donor_id'];
                    // Update trust score
                    $q3 = "UPDATE users SET trust_score = GREATEST(0, trust_score - ?) WHERE user_id = ?";
                    $s3 = mysqli_prepare($dbConn, $q3);
                    mysqli_stmt_bind_param($s3, "ii", $deduction, $donorId);
                    mysqli_stmt_execute($s3);
                    
                    // Insert log
                    $q4 = "INSERT INTO trust_score_log (user_id, description, points_change) VALUES (?, ?, ?)";
                    $s4 = mysqli_prepare($dbConn, $q4);
                    $desc = "Report resolved: " . $note;
                    $negDeduction = -$deduction;
                    mysqli_stmt_bind_param($s4, "isi", $donorId, $desc, $negDeduction);
                    mysqli_stmt_execute($s4);
                }
            }
            echo json_encode(['success' => true]);
            exit;
        } elseif ($input['action'] === 'dismiss') {
            $q1 = "UPDATE reports SET status = 'dismissed' WHERE report_id = ?";
            $s1 = mysqli_prepare($dbConn, $q1);
            mysqli_stmt_bind_param($s1, "i", $reportId);
            mysqli_stmt_execute($s1);
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

$query = "
    SELECT 
        r.report_id, 
        r.issue_type, 
        r.comment, 
        r.evidence_image_url,
        r.admin_message,
        r.status, 
        r.created_at,
        rec.full_name AS receiver_name,
        don.full_name AS donor_name
    FROM reports r
    JOIN bookings b ON r.booking_id = b.booking_id
    JOIN users rec ON b.receiver_id = rec.user_id
    JOIN donations d ON b.donation_id = d.donation_id
    JOIN users don ON d.donor_id = don.user_id
    ORDER BY r.created_at DESC
";
$result = mysqli_query($dbConn, $query);
$dbReports = [];
if ($result) {
    $dbReports = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$reportsJsArray = [];
foreach ($dbReports as $row) {
    // Simple time formatting
    $time = date('d M Y, H:i', strtotime($row['created_at']));
    $issueType = ucfirst(str_replace('_', ' ', $row['issue_type']));
    
    $reportsJsArray[] = [
        'id' => (int)$row['report_id'],
        'from' => $row['receiver_name'],
        'fromType' => 'receiver',
        'against' => $row['donor_name'],
        'againstType' => 'donor',
        'issue' => $issueType,
        'time' => $time,
        'body' => $row['comment'] ? $row['comment'] : 'No comment provided.',
        'status' => $row['status'] == 'active' ? 'pending' : $row['status'],
        'deduction' => null,
        'note' => $row['admin_message'] ? $row['admin_message'] : '',
        'evidence' => $row['evidence_image_url']
    ];
}
$reportsJson = json_encode($reportsJsArray);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - Admin - FoodBridge</title>

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
  <link rel="stylesheet" href="reports.css">
  <script>
    // Inject the reports data from PHP to JS
    const reports = <?= $reportsJson ?>;
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
        <a href="users.php" class="dashboard-nav-item">Users</a>
        <a href="vouchers.html" class="dashboard-nav-item">Vouchers</a>
        <a href="donations.html" class="dashboard-nav-item">Donations</a>
        <a href="trust-rules.html" class="dashboard-nav-item">Trust Rules</a>
        <a href="reports.php" class="dashboard-nav-item active">Reports</a>
        <a href="certificates.html" class="dashboard-nav-item">Certificates</a>
      </nav>
    </div>

    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile" title="Notifications" style="position: relative;"
        href="notifications.html">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>
        <span
          style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; background-color: #ff4757; border-radius: 50%;"></span>
      </a>

      <a href="profile.html" class="profile-avatar">DO</a>

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
      <h1 class="page-heading">Reports</h1>
      <p class="page-subheading">Review all incoming reports. Mark as legitimate to see a suggested deduction — then
        confirm with a reason note. Dismiss if unfounded.</p>

      <div class="content-body">
        <div class="metrics">
          <div class="metric">
            <div class="mlabel">Pending</div>
            <div class="mval a" id="cnt-pending">0</div>
          </div>
          <div class="metric">
            <div class="mlabel">Resolved</div>
            <div class="mval g" id="cnt-resolved">0</div>
          </div>
          <div class="metric">
            <div class="mlabel">Dismissed</div>
            <div class="mval" id="cnt-dismissed">0</div>
          </div>
        </div>

        <div id="reports-list"></div>
      </div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="reports.js"></script>
</body>

</html>
