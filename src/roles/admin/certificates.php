<?php
require_once __DIR__ . "/../../../database/db.php";
session_start();
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));
$message = "";
$messageType = "";

$isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1') || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['action']) && $_GET['action'] === 'calculate_metrics') {
    header('Content-Type: application/json');

    $donor_id     = filter_input(INPUT_GET, 'donor_id', FILTER_VALIDATE_INT);
    $period_start = !empty($_GET['period_start']) ? trim($_GET['period_start']) : null;
    $period_end   = !empty($_GET['period_end'])   ? trim($_GET['period_end'])   : null;

    if (!$donor_id || !$period_start || !$period_end) {
        echo json_encode([
            "success" => false,
            "message" => "Donor ID and date range are required."
        ]);
        exit;
    }

    // Sum quantity of completed donations whose expiry_at falls in the period
    $sql = "SELECT COALESCE(SUM(quantity), 0) AS total_donated
            FROM donations
            WHERE donor_id = ?
              AND status = 'completed'
              AND DATE(expiry_at) BETWEEN ? AND ?";

    $stmt = $dbConn->prepare($sql);

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Prepare failed: " . $dbConn->error
        ]);
        exit;
    }

    $stmt->bind_param("iss", $donor_id, $period_start, $period_end);
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    $totalDonated = (float)($data['total_donated'] ?? 0);
    $count = round($totalDonated);

    // New grade rules based on quantity
    if ($count >= 46) {
        $satisfaction = 'Excellent';
    } elseif ($count >= 26) {
        $satisfaction = 'Good';
    } elseif ($count >= 11) {
        $satisfaction = 'Average';
    } else {
        $satisfaction = 'Poor';   // 0 - 10
    }

    echo json_encode([
        "success"                    => true,
        "food_donated_count"         => $count,
        "receiver_satisfaction_rate" => $satisfaction
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'create_certificate') {
    $donor_id = filter_input(INPUT_POST, 'donor_id', FILTER_VALIDATE_INT);
    $certificate_name = isset($_POST['certificate_name']) ? trim(htmlspecialchars($_POST['certificate_name'], ENT_QUOTES, 'UTF-8')) : '';
    $issued_by = !empty($_POST['issued_by']) ? trim(htmlspecialchars($_POST['issued_by'], ENT_QUOTES, 'UTF-8')) : 'FoodBridge Admin';

    $period_start = !empty($_POST['period_start']) ? $_POST['period_start'] . " 00:00:00" : null;
    $period_end   = !empty($_POST['period_end'])   ? $_POST['period_end'] . " 23:59:59" : null;

    $food_donated_count = filter_input(INPUT_POST, 'food_donated_count', FILTER_VALIDATE_INT) ?: 0;
    $receiver_satisfaction_rate = $_POST['receiver_satisfaction_rate'] ?? 'Good';

    if ($donor_id && !empty($certificate_name) && !empty($period_start) && !empty($period_end)) {
        $sql = "INSERT INTO certificates (donor_id, certificate_name, issued_by, period_start, period_end, food_donated_count, receiver_satisfaction_rate) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $dbConn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("issssis", $donor_id, $certificate_name, $issued_by, $period_start, $period_end, $food_donated_count, $receiver_satisfaction_rate);

            if ($stmt->execute()) {
                $newId = $dbConn->insert_id;

                if ($isAjax) {
                    $donorName = 'Unknown';
                    $dStmt = $dbConn->prepare("SELECT full_name FROM users WHERE user_id = ?");
                    $dStmt->bind_param("i", $donor_id);
                    $dStmt->execute();
                    $dRes = $dStmt->get_result();
                    if ($dRow = $dRes->fetch_assoc()) {
                        $donorName = !empty($dRow['full_name']) ? $dRow['full_name'] : 'Unknown';
                    }
                    $dStmt->close();

                    header('Content-Type: application/json');
                    echo json_encode([
                        "success" => true,
                        "certificate" => [
                            "certificate_id" => $newId,
                            "certificate_name" => $certificate_name,
                            "donor_name" => $donorName,
                            "issued_by" => $issued_by,
                            "period_start" => $period_start,
                            "period_end" => $period_end,
                            "food_donated_count" => $food_donated_count,
                            "receiver_satisfaction_rate" => $receiver_satisfaction_rate
                        ]
                    ]);
                    exit;
                }

                $message = "Certificate created successfully!";
                $messageType = "success";
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(["success" => false, "message" => "Error saving certificate: " . $stmt->error]);
                    exit;
                }
                $message = "Error saving certificate: " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $dbConn->error]);
                exit;
            }
            $message = "Database query preparation failed: " . $dbConn->error;
            $messageType = "error";
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
            exit;
        }
        $message = "Please fill in all required fields.";
        $messageType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_certificate') {
    header('Content-Type: application/json');

    $certificate_id = filter_input(INPUT_POST, 'certificate_id', FILTER_VALIDATE_INT);
    if (!$certificate_id) {
        echo json_encode(["success" => false, "message" => "Invalid certificate ID."]);
        exit;
    }

    $delStmt = $dbConn->prepare("DELETE FROM certificates WHERE certificate_id = ?");
    $delStmt->bind_param("i", $certificate_id);

    if ($delStmt->execute()) {
        echo json_encode(["success" => true, "certificate_id" => $certificate_id]);
    } else {
        echo json_encode(["success" => false, "message" => "Error deleting certificate: " . $delStmt->error]);
    }
    $delStmt->close();
    exit;
}


$donorsQuery = "
    SELECT 
        user_id AS id, 
        NULLIF(full_name, '') AS name,
        total_food_donated AS donations
    FROM users 
    WHERE role = 'donor' 
    ORDER BY name ASC";

$donorsResult = $dbConn->query($donorsQuery);


$certificatesQuery = "
    SELECT c.*, 
           COALESCE(NULLIF(u.full_name, ''), 'Unknown') AS donor_name 
    FROM certificates c
    LEFT JOIN users u ON c.donor_id = u.user_id
    ORDER BY c.certificate_id DESC";

$certificatesResult = $dbConn->query($certificatesQuery);

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
    <title>Admin Dashboard - FoodBridge</title>

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
                <a href="users.php" class="dashboard-nav-item">Users</a>
                <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
                <a href="donations.php" class="dashboard-nav-item">Donations</a>
                <a href="trust-rules.php" class="dashboard-nav-item">Trust Rules</a>
                <a href="reports.php" class="dashboard-nav-item">Reports</a>
                <a href="certificates.php" class="dashboard-nav-item active">Certificates</a>
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

            <a href="profile.php" class="profile-avatar">
                <?php if (!empty($userAvatar)): ?>
                    <img src="../../<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>"
                        style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
            </a>

            <a href="../../auth/login.php" class="action-btn-circle hide-mobile" title="Log Out">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
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

            <?php if (!empty($message)): ?>
                <div class="alert-message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="certificates-hero">
                <div class="hero-header-flex">
                    <div>
                        <h1 class="page-heading">Certificates of Impact</h1>
                        <p class="page-subheading">Manage, create, and revoke corporate social responsibility (CSR) certificates for donors.</p>
                    </div>
                    <button class="btn-create-trigger" id="openModalBtn" type="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Create Certificate
                    </button>
                </div>
            </section>

            <!-- Certificates Display Grid -->
            <section class="certificates-grid" id="certificatesContainer" aria-label="Available Certificates">
                <?php if ($certificatesResult && $certificatesResult->num_rows > 0): ?>
                    <?php while ($cert = $certificatesResult->fetch_assoc()):
                        $rating = satisfactionToRating($cert['receiver_satisfaction_rate']);
                        $startDate = date("M Y", strtotime($cert['period_start']));
                        $endDate = date("M Y", strtotime($cert['period_end']));
                    ?>
                        <article class="certificate-card" data-cert-id="<?php echo (int)$cert['certificate_id']; ?>">
                            <div class="cert-badge-wrapper" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <circle cx="12" cy="8" r="6"/>
                                    <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
                                </svg>
                            </div>

                            <h2><?php echo htmlspecialchars($cert['certificate_name']); ?></h2>
                            <p class="cert-recipient"><?php echo htmlspecialchars($cert['donor_name'] ?? 'Unknown'); ?></p>

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

                            <button class="btn-revoke" type="button" onclick="revokeCertificate(<?php echo (int)$cert['certificate_id']; ?>)">Revoke Certificate</button>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-data">No certificates generated yet.</p>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <!-- Create Certificate Modal Form -->
    <div class="modal-overlay" id="certModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Create New Certificate</h2>
                <button class="modal-close-btn" id="closeModalBtn" type="button">&times;</button>
            </div>

            <form id="createCertificateForm" method="POST" action="certificates.php">
                <input type="hidden" name="action" value="create_certificate">

                <div class="form-group">
                    <label for="certDonorSelect">Select Recipient Donor</label>
                    <select id="certDonorSelect" name="donor_id" required>
                        <option value="" disabled selected>-- Choose a Registered Donor --</option>
                        <?php
                        if ($donorsResult && $donorsResult->num_rows > 0) {
                            while ($donor = $donorsResult->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($donor['id']) . '" data-donations="' . htmlspecialchars($donor['donations'] ?? 0) . '">' . htmlspecialchars($donor['name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="certTitle">Certificate Title</label>
                    <input type="text" id="certTitle" name="certificate_name" placeholder="e.g., Food Hero Q1 2026" required />
                </div>

                <div class="form-group">
                    <label for="issuedBy">Issued By</label>
                    <input type="text" id="issuedBy" name="issued_by" value="FoodBridge Admin" required />
                </div>

                <div class="form-row-split">
                    <div class="form-group">
                        <label for="periodStart">Start Date</label>
                        <input type="date" id="periodStart" name="period_start" required />
                    </div>
                    <div class="form-group">
                        <label for="periodEnd">End Date</label>
                        <input type="date" id="periodEnd" name="period_end" required />
                    </div>
                </div>

                <div class="form-row-split">
                    <div class="form-group">
                        <label for="certDonations">Food Donated Count</label>
                        <input type="number" id="certDonations" name="food_donated_count" placeholder="0" required />
                    </div>
                    <div class="form-group">
                        <label for="certRating">Satisfaction Rate</label>
                        <select id="certRating" name="receiver_satisfaction_rate">
                            <option value="Excellent">Excellent</option>
                            <option value="Good" selected>Good</option>
                            <option value="Average">Average</option>
                            <option value="Poor">Poor</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-submit-action">Generate & Create</button>
            </form>
        </div>
    </div>

    <!-- Page Specific Logic -->
    <script src="../../assets/js/header.js"></script>
    <script src="certificates.js"></script>

</body>

</html>