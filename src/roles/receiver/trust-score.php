<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'receiver') {
    header('Location: ../../auth/login.php');
    exit();
}
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

require_once __DIR__ . '/../../../database/db.php';
function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $value = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $part)
        $value .= strtoupper(substr($part, 0, 1));
    return $value ?: 'FB';
}
function percent(int $a, int $b): int
{
    return $b ? (int) round(($a / $b) * 100) : 0;
}
$receiverId = (int) $_SESSION['user']['id'];
$userStmt = mysqli_prepare($dbConn, 'SELECT full_name, trust_score FROM users WHERE user_id = ? AND role = \'receiver\' LIMIT 1');
mysqli_stmt_bind_param($userStmt, 'i', $receiverId);
mysqli_stmt_execute($userStmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: ../../auth/login.php');
    exit;
}
$metricStmt = mysqli_prepare($dbConn, "SELECT SUM(status = 'collected') AS collected, SUM(status = 'missed') AS missed, SUM(status = 'cancelled') AS cancelled, SUM(status IN ('collected','missed','cancelled')) AS completed_outcomes, (SELECT COUNT(*) FROM reviews r INNER JOIN bookings b ON b.booking_id = r.booking_id WHERE b.receiver_id = ?) AS reviews FROM bookings WHERE receiver_id = ?");
mysqli_stmt_bind_param($metricStmt, 'ii', $receiverId, $receiverId);
mysqli_stmt_execute($metricStmt);
$metrics = mysqli_fetch_assoc(mysqli_stmt_get_result($metricStmt)) ?: [];
mysqli_stmt_close($metricStmt);
$historyStmt = mysqli_prepare($dbConn, 'SELECT description, points_change, created_at FROM trust_score_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
mysqli_stmt_bind_param($historyStmt, 'i', $receiverId);
mysqli_stmt_execute($historyStmt);
$history = mysqli_fetch_all(mysqli_stmt_get_result($historyStmt), MYSQLI_ASSOC);
mysqli_stmt_close($historyStmt);
$collected = (int) ($metrics['collected'] ?? 0);
$outcomes = (int) ($metrics['completed_outcomes'] ?? 0);
$missedOrCancelled = (int) ($metrics['missed'] ?? 0) + (int) ($metrics['cancelled'] ?? 0);
$pickupRate = percent($collected, $outcomes);
$reviewRate = percent((int) ($metrics['reviews'] ?? 0), $collected);
$violationRate = percent($missedOrCancelled, $outcomes);
$score = max(0, min(100, (int) $user['trust_score']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust Score - Receiver - FoodBridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/header.css">
    <link rel="stylesheet" href="trust-score.css">
</head>

<body>
    <div class="noise-bg"></div>
    <header class="dashboard-header"><a href="dashboard.php" class="navbar-brand">
            <div class="navbar-logo"><img src="../../assets/images/logo.png" alt="FoodBridge logo"></div>
        </a>
        <div class="nav-overlay" id="navOverlay">
            <nav class="dashboard-nav"><a href="dashboard.php" class="dashboard-nav-item">Overview</a><a
                    href="browse-donations.php" class="dashboard-nav-item">Browse Food</a><a href="bookings.php"
                    class="dashboard-nav-item">My Bookings</a><a href="trust-score.php"
                    class="dashboard-nav-item active">Trust Score</a><a href="history.php"
                    class="dashboard-nav-item">History</a></nav>
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
    <div class="dashboard-wrapper">
        <main class="dashboard-content">
            <h1 class="page-heading">Trust Score</h1>
            <p class="page-subheading">View details about your rating level, score deductions, and history of
                collections.</p>
            <div class="content-body">
                <section class="trust-score-panel" aria-label="Trust score summary">
                    <div class="score-ring" style="--score: <?php echo $score; ?>"
                        aria-label="Trust score <?php echo $score; ?> out of 100">
                        <div class="score-ring-inner"><strong><?php echo $score; ?></strong><span>Score</span></div>
                    </div>
                    <div class="score-breakdown">
                        <div class="score-row"><span class="score-label">Collection Completion Rate</span>
                            <div class="score-track">
                                <div class="score-fill lime" style="width: <?php echo $pickupRate; ?>%"></div>
                            </div><strong><?php echo $pickupRate; ?>%</strong>
                        </div>
                        <div class="score-row"><span class="score-label">Reviews Submitted</span>
                            <div class="score-track">
                                <div class="score-fill forest" style="width: <?php echo $reviewRate; ?>%"></div>
                            </div><strong><?php echo $reviewRate; ?>%</strong>
                        </div>
                        <div class="score-row"><span class="score-label">Missed / Cancelled</span>
                            <div class="score-track">
                                <div class="score-fill alert" style="width: <?php echo $violationRate; ?>%"></div>
                            </div><strong><?php echo $violationRate; ?>%</strong>
                        </div>
                    </div>
                </section>
                <section class="trust-history-panel" aria-label="Trust score history">
                    <div class="history-header">
                        <div><span class="history-eyebrow">Score Activity</span>
                            <h2>Trust Score History</h2>
                        </div><span class="history-total">Current score: <?php echo $score; ?></span>
                    </div>
                    <div class="history-list"><?php if (!$history): ?>
                            <p class="empty-history">No trust score activity yet.</p>
                        <?php else:
                        foreach ($history as $event):
                            $positive = (int) $event['points_change'] >= 0; ?>
                                <article class="history-item <?php echo $positive ? 'positive' : 'negative'; ?>">
                                    <div class="history-marker"><?php echo $positive ? '+' : '-'; ?></div>
                                    <div class="history-copy">
                                        <div class="history-title-row">
                                            <h3><?php echo h($event['description']); ?></h3>
                                            <strong><?php echo sprintf('%+d marks', (int) $event['points_change']); ?></strong>
                                        </div>
                                        <span><?php echo h(date('j M Y, g:i A', strtotime($event['created_at']))); ?></span>
                                    </div>
                                </article><?php endforeach; endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="../../assets/js/header.js"></script>
</body>

</html>