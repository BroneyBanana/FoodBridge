<?php
session_start();
require_once __DIR__ . '/../../../database/maintenance_guard.php';
require_once __DIR__ . "/../../../database/db.php";

// Correct way based on your login system
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'donor') {
    header('Location: ../../auth/login.php');
    exit();
}

$donor_id = (int) $_SESSION['user']['id'];   // ← THIS is the correct key
$cert_id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($cert_id <= 0) {
    die("Invalid Certificate ID.");
}

// Fetch certificate + donor full_name
$query = "
    SELECT c.*, 
           COALESCE(NULLIF(u.full_name, ''), 'Valued Donor') AS donor_name
    FROM certificates c
    LEFT JOIN users u ON c.donor_id = u.user_id
    WHERE c.certificate_id = ? AND c.donor_id = ?
    LIMIT 1";

$stmt = $dbConn->prepare($query);
$stmt->bind_param("ii", $cert_id, $donor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Certificate not found or access denied.");
}

$cert = $result->fetch_assoc();

function satisfactionToRating($rate) {
    switch ($rate) {
        case 'Excellent': return 5.0;
        case 'Good':      return 4.0;
        case 'Average':   return 3.0;
        case 'Poor':      return 2.0;
        default:          return 0.0;
    }
}

$rating    = satisfactionToRating($cert['receiver_satisfaction_rate']);
$startDate = date("M Y", strtotime($cert['period_start']));
$endDate   = date("M Y", strtotime($cert['period_end']));
$donorName = $cert['donor_name'];   // now comes from the JOIN
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Certificate - <?php echo htmlspecialchars($cert['certificate_name']); ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="generate-certificate.css">
</head>
<body>

  <div class="no-print no-print-bar">
    <span>Certificate Preview</span>
    <button class="btn-print" onclick="window.print()">Save as PDF / Print</button>
  </div>

  <div class="cert-container">
    <div class="logo-title">FoodBridge Impact Program</div>
    <h1 class="cert-title"><?php echo htmlspecialchars($cert['certificate_name']); ?></h1>
    <div class="subtitle">Official Recognition of Environmental & Community Contribution</div>

    <div class="present-text">This certificate is proudly presented to</div>
    <div class="donor-name"><?php echo htmlspecialchars($donorName); ?></div>

    <p class="cert-body">
      In recognition of your outstanding dedication to reducing food waste and supporting local communities. Through your donations via the <strong>FoodBridge Platform</strong>, you have directly contributed to sustainable food redistribution and carbon offset goals.
    </p>

    <table class="metrics-grid">
      <tr>
        <td>
          <span class="metric-value"><?php echo (int)$cert['food_donated_count']; ?></span>
          <span class="metric-label">Donations Completed</span>
        </td>
        <td>
          <span class="metric-value"><?php echo htmlspecialchars($startDate . " - " . $endDate); ?></span>
          <span class="metric-label">Contribution Period</span>
        </td>
        <td>
          <span class="metric-value"><?php echo number_format($rating, 1); ?> / 5.0 ★</span>
          <span class="metric-label">Receiver Satisfaction</span>
        </td>
      </tr>
    </table>

    <table class="footer-table">
      <tr>
        <td class="footer-cell">
          <div class="sig-signature">JhYzz</div>
          <div class="sig-line"></div>
          <div class="sig-name"><?php echo htmlspecialchars($cert['issued_by']); ?></div>
          <div class="sig-title">Issuing Authority</div>
        </td>
        <td class="seal-cell">
          <div class="badge">★</div>
        </td>
        <td class="footer-cell">
          <div class="sig-signature">IzW</div>
          <div class="sig-line"></div>
          <div class="sig-name">FoodBridge Board</div>
          <div class="sig-title">Verification Committee</div>
        </td>
      </tr>
    </table>

    <div class="cert-id">Certificate ID: CERT-<?php echo sprintf("%05d", $cert['certificate_id']); ?></div>
  </div>

  <script>
    // Automatically trigger browser print/PDF dialog upon load
    window.onload = function() {
      window.print();
    };
  </script>
</body>
</html>