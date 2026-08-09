<?php
// ============================================================
// donation-analytics.php – Full page for Donation Analytics
// ============================================================
require_once '../../../database/db.php';
session_start();

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

// ------------------------------------------------------------
// 1. Total Donations
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "SELECT COUNT(*) AS total FROM donations");
$totalDonations = (int) mysqli_fetch_assoc($res)['total'];

// ------------------------------------------------------------
// 2. Average Quantity
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "SELECT AVG(quantity) AS avg_qty FROM donations");
$avgQty = round((float) mysqli_fetch_assoc($res)['avg_qty'], 2);

// ------------------------------------------------------------
// 3. Food Rescued (sum of all donated quantities)
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT COALESCE(SUM(total_food_donated), 0) AS total_rescued
    FROM users
    WHERE role = 'donor'
");
$foodRescued = (int) mysqli_fetch_assoc($res)['total_rescued'];

// ------------------------------------------------------------
// 4. Donation Categories
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT category, SUM(quantity) AS total_qty
    FROM donations
    GROUP BY category
");
$categoryRows = mysqli_fetch_all($res, MYSQLI_ASSOC);
$categoryLabels = [];
$categoryData = [];
foreach ($categoryRows as $row) {
    $categoryLabels[] = ucfirst(str_replace('_', ' ', $row['category']));
    $categoryData[] = (float) $row['total_qty'];
}
$categoryJson = json_encode(['labels' => $categoryLabels, 'data' => $categoryData]);

// ------------------------------------------------------------
// 5. Monthly Trend (based on expiry_at)
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT DATE_FORMAT(expiry_at, '%Y-%m') AS month,
           SUM(quantity) AS total_qty
    FROM donations
    WHERE expiry_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthRows = mysqli_fetch_all($res, MYSQLI_ASSOC);
$months = [];
$monthlyTotals = [];
foreach ($monthRows as $row) {
    $months[] = $row['month'];
    $monthlyTotals[] = (float) $row['total_qty'];
}
$trendJson = json_encode(['months' => $months, 'totals' => $monthlyTotals]);

// ------------------------------------------------------------
// 6. Active Donations for Heatmap (with lat/lng)
// ------------------------------------------------------------
$res = mysqli_query($dbConn, "
    SELECT d.donation_id, d.food_name, d.category, d.quantity, d.unit,
           d.pickup_address, d.expiry_at,
           u.full_name AS donor_name, u.trust_score, u.latitude, u.longitude,
           (SELECT MIN(timeslot) FROM pickup_slots WHERE donation_id = d.donation_id AND timeslot >= NOW()) AS next_slot
    FROM donations d
    JOIN users u ON d.donor_id = u.user_id
    WHERE d.status = 'active'
      AND d.expiry_at > NOW()
      AND (SELECT COUNT(*) FROM pickup_slots WHERE donation_id = d.donation_id AND timeslot >= NOW()) > 0
");
$activeDonationsList = mysqli_fetch_all($res, MYSQLI_ASSOC);

// filter those with coordinates
$heatmapDonations = array_filter($activeDonationsList, function ($d) {
    return !is_null($d['latitude']) && !is_null($d['longitude']);
});
$heatmapDataJson = json_encode(array_values($heatmapDonations));

// ------------------------------------------------------------
// 7. Export CSV (if requested)
// ------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'donations') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="donations_analytics.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Donation ID', 'Food Name', 'Category', 'Quantity', 'Unit', 'Pickup Address', 'Expiry', 'Status', 'Donor']);
    $q = mysqli_query($dbConn, "
        SELECT d.donation_id, d.food_name, d.category, d.quantity, d.unit,
               d.pickup_address, d.expiry_at, d.status, u.full_name AS donor
        FROM donations d
        JOIN users u ON d.donor_id = u.user_id
    ");
    while ($row = mysqli_fetch_assoc($q)) {
        fputcsv($output, [
            $row['donation_id'],
            $row['food_name'],
            $row['category'],
            $row['quantity'],
            $row['unit'],
            $row['pickup_address'],
            $row['expiry_at'],
            $row['status'],
            $row['donor']
        ]);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Donation Analytics - FoodBridge</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
        rel="stylesheet" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <!-- Leaflet & Heatmap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

    <!-- Global Styles & Header -->
    <link rel="stylesheet" href="../../assets/css/global.css" />
    <link rel="stylesheet" href="../../assets/css/header.css" />
    <!-- Page-specific styles -->
    <link rel="stylesheet" href="donation-analytics.css" />

    <!-- Pass PHP data to JavaScript -->
    <script>
        const categoryData = <?= $categoryJson ?>;
        const trendData = <?= $trendJson ?>;
        const heatmapDonations = <?= $heatmapDataJson ?>;
    </script>
</head>

<body>

    <div class="noise-bg"></div>

    <!-- ===== HEADER (identical to dashboard) ===== -->
    <header class="dashboard-header">
        <a href="dashboard.php" class="navbar-brand">
            <div class="navbar-logo"><img src="../../assets/images/logo.png" alt="Logo" /></div>
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
                <a href="donation-analytics.php" class="dashboard-nav-item active">Analytics</a>
            </nav>
        </div>
        <div class="dashboard-actions">
            <a class="action-btn-circle hide-mobile" title="Notifications" href="notifications.php">
                <img src="../../assets/icons/nav-bell.svg" alt="Notifications" width="20" height="20" />
                <span
                    style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; border-radius: 50%;"></span>
            </a>
            <a href="profile.php" class="profile-avatar">
                <?php if (!empty($userAvatar)): ?>
                    <img src="../../<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>"
                        style="width:100%;height:100%;border-radius:50%;object-fit:cover;" />
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

    <!-- ===== MAIN CONTENT ===== -->
    <div class="dashboard-wrapper">
        <main class="dashboard-content">

            <!-- ============================================================
                 DONATION ANALYTICS SECTION (full page content)
            ============================================================ -->
            <section id="donationAnalytics">

                <!-- Header with export button -->
                <div
                    style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.5rem;">
                    <h1>Donation Analytics</h1>
                    <a href="?export=donations" class="btn btn-accent"
                        style="display:inline-flex;align-items:center;gap:0.5rem;">
                        <img src="../../assets/icons/icon-download.svg" alt="Download" width="18" height="18" />
                        Export Report
                    </a>
                </div>
                <p class="page-subheading">Geographic distribution of active donations and key metrics.</p>

                <!-- Map -->
                <div id="donationMapView" style="margin-top:2rem;">
                    <section class="panel-card">
                        <div class="section-header">
                            <div>
                                <h2>Donation Heatmap</h2>
                                <p class="muted-text">Geographic distribution of active donations (intensity = donation
                                    count)</p>
                            </div>
                        </div>
                        <div id="heatmapContainer"
                            style="height:360px;border-radius:12px;overflow:hidden;background:#eef4ed;"></div>
                    </section>
                </div>

                <!-- Charts Row -->
                <div class="dashboard-grid" style="margin-top:1.5rem;">
                    <section class="panel-card">
                        <div class="section-header">
                            <div>
                                <h2>Food Categories by Amount</h2>
                                <p class="muted-text">Total quantity donated per category</p>
                            </div>
                        </div>
                        <div style="position:relative;height:220px;min-height:220px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </section>

                    <section class="panel-card">
                        <div class="section-header">
                            <div>
                                <h2>Donation Trend (by expiry month)</h2>
                                <p class="muted-text">Total quantity of donations expiring per month</p>
                            </div>
                        </div>
                        <div style="position:relative;height:220px;min-height:220px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </section>
                </div>

                <!-- Summary Cards -->
                <div class="summary-grid" style="margin-top:1.5rem;">
                    <article class="summary-card">
                        <div class="card-header-with-icon">
                            <span class="summary-label">Total Donations</span>
                            <div class="icon-circle bg-lime-light">
                                <img src="../../assets/icons/icon-donations.svg" alt="Donations" width="20" height="20" />
                            </div>
                        </div>
                        <strong><?= $totalDonations ?></strong>
                        <small>All-time donations</small>
                    </article>

                    <article class="summary-card">
                        <div class="card-header-with-icon">
                            <span class="summary-label">Average Quantity</span>
                            <div class="icon-circle bg-terracotta-light">
                                <img src="../../assets/icons/icon-qty.svg" alt="Quantity" width="20" height="20" />
                            </div>
                        </div>
                        <strong><?= $avgQty ?></strong>
                        <small>per donation (in units)</small>
                    </article>

                    <article class="summary-card">
                        <div class="card-header-with-icon">
                            <span class="summary-label">Food Rescued</span>
                            <div class="icon-circle bg-forest-light">
                                <img src="../../assets/icons/icon-rescued.svg" alt="Rescued" width="20" height="20" />
                            </div>
                        </div>
                        <strong><?= $foodRescued ?> items</strong>
                        <small>Lifetime total</small>
                    </article>
                </div>

            </section>

        </main>
    </div>

    <!-- Footer scripts -->
    <script src="../../assets/js/header.js"></script>
    <script src="donation-analytics.js"></script>
</body>

</html>