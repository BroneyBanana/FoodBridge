<?php
declare(strict_types=1);

session_start();

if (($_SESSION['user']['role'] ?? '') !== 'donor') {
  header('Location: ../../auth/login.php');
  exit;
}

require_once __DIR__ . '/../../../database/db.php';
$userAvatar = $_SESSION['user']['avatarImage'] ?? '';
$userName = $_SESSION['user']['name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 2));

function h(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function initials(string $name): string
{
  $parts = preg_split('/\s+/', trim($name)) ?: [];
  $result = '';
  foreach (array_slice(array_filter($parts), 0, 2) as $part) {
    $result .= strtoupper(substr($part, 0, 1));
  }
  return $result ?: 'FB';
}

function relativeDate(string $date): string
{
  $seconds = max(0, time() - strtotime($date));
  if ($seconds < 3600)
    return max(1, (int) floor($seconds / 60)) . ' minutes ago';
  if ($seconds < 86400)
    return (int) floor($seconds / 3600) . ' hours ago';
  if ($seconds < 604800)
    return (int) floor($seconds / 86400) . ' days ago';
  return date('j M Y', strtotime($date));
}

function imagePath(?string $path): ?string
{
  if (!$path)
    return null;
  if (preg_match('#^https?://#i', $path))
    return $path;
  return '../../' . $path;
}

$donorId = (int) $_SESSION['user']['id'];
$donorStatement = mysqli_prepare($dbConn, 'SELECT full_name FROM users WHERE user_id = ? AND role = \'donor\' LIMIT 1');
mysqli_stmt_bind_param($donorStatement, 'i', $donorId);
mysqli_stmt_execute($donorStatement);
$donor = mysqli_fetch_assoc(mysqli_stmt_get_result($donorStatement));
mysqli_stmt_close($donorStatement);

if (!$donor) {
  session_unset();
  session_destroy();
  header('Location: ../../auth/login.php');
  exit;
}

$reviewsStatement = mysqli_prepare(
  $dbConn,
  'SELECT r.rating, r.comment, r.review_image_url, r.created_at, u.full_name AS receiver_name, u.profile_url, d.food_name
     FROM reviews r
     INNER JOIN bookings b ON b.booking_id = r.booking_id
     INNER JOIN donations d ON d.donation_id = b.donation_id
     INNER JOIN users u ON u.user_id = b.receiver_id
     WHERE d.donor_id = ?
     ORDER BY r.created_at DESC'
);
mysqli_stmt_bind_param($reviewsStatement, 'i', $donorId);
mysqli_stmt_execute($reviewsStatement);
$reviews = mysqli_fetch_all(mysqli_stmt_get_result($reviewsStatement), MYSQLI_ASSOC);
mysqli_stmt_close($reviewsStatement);

$reviewCount = count($reviews);
$ratingTotal = array_sum(array_map(static fn(array $review): int => (int) $review['rating'], $reviews));
$averageRating = $reviewCount ? number_format($ratingTotal / $reviewCount, 1) : '0.0';
$positiveCount = count(array_filter($reviews, static fn(array $review): bool => (int) $review['rating'] >= 4));
$positiveRate = $reviewCount ? (int) round(($positiveCount / $reviewCount) * 100) : 0;
$monthStart = strtotime(date('Y-m-01 00:00:00'));
$thisMonth = count(array_filter($reviews, static fn(array $review): bool => strtotime($review['created_at']) >= $monthStart));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviews - Donor - FoodBridge</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="review.css">
</head>

<body>
  <div class="noise-bg"></div>
  <header class="dashboard-header">
    <a href="dashboard.php" class="navbar-brand">
      <div class="navbar-logo"><img src="../../assets/images/logo.png" alt="FoodBridge logo"></div>
    </a>
    <div class="nav-overlay" id="navOverlay">
      <nav class="dashboard-nav"><a href="dashboard.php" class="dashboard-nav-item">Overview</a><a href="donate.php"
          class="dashboard-nav-item">Donate</a><a href="my-donations.php" class="dashboard-nav-item">My Donations</a><a
          href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a><a href="vouchers.php"
          class="dashboard-nav-item">Vouchers</a><a href="certificates.php"
          class="dashboard-nav-item">Certificates</a><a href="trust-score.php" class="dashboard-nav-item">Trust
          Score</a><a href="review.php" class="dashboard-nav-item active">Review</a></nav>
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
      <h1 class="page-heading">Reviews</h1>
      <p class="page-subheading">See how recipients rated your recent donations, pickup experience, food quality, and
        overall reliability.</p>
      <div class="content-body">
        <section class="reviews-shell" aria-label="Donor reviews">
          <div class="reviews-summary">
            <div class="summary-copy"><span class="eyebrow">Community Feedback</span>
              <h2>Reviews from food receivers</h2>
              <p>Feedback is shown for donations that recipients have collected and reviewed.</p>
            </div>
            <div class="rating-card" aria-label="Average rating <?php echo $averageRating; ?> out of 5">
              <strong><?php echo $averageRating; ?></strong>
              <div class="stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <span><?php echo $reviewCount; ?> total review<?php echo $reviewCount === 1 ? '' : 's'; ?></span>
            </div>
          </div>
          <div class="review-stats">
            <div class="stat-tile"><strong><?php echo $positiveRate; ?>%</strong><span>Positive feedback</span></div>
            <div class="stat-tile"><strong><?php echo $thisMonth; ?></strong><span>Reviews this month</span></div>
          </div>
          <div class="reviews-toolbar">
            <h2>All Reviews</h2><span>Newest first</span>
          </div>
          <div class="reviews-grid">
            <?php if ($reviews === []): ?>
              <p class="empty-reviews">No recipient reviews have been received yet.</p>
            <?php else:
              foreach ($reviews as $review):
                $profile = imagePath($review['profile_url']);
                $photo = imagePath($review['review_image_url']);
                $stars = str_repeat('&#9733;', max(0, min(5, (int) $review['rating']))); ?>
                <article class="review-card">
                  <div class="review-body">
                    <div class="review-header">
                      <?php if ($profile): ?><img class="reviewer-avatar" src="<?php echo h($profile); ?>"
                          alt="<?php echo h($review['receiver_name']); ?>">
                      <?php else: ?>
                        <div class="reviewer-avatar reviewer-initials" aria-hidden="true">
                          <?php echo h(initials($review['receiver_name'])); ?>
                        </div><?php endif; ?>
                      <div>
                        <h3><?php echo h($review['receiver_name']); ?></h3><span>Receiver &middot;
                          <?php echo h(relativeDate($review['created_at'])); ?></span>
                      </div>
                    </div>
                    <div class="stars" aria-label="<?php echo (int) $review['rating']; ?> out of 5 stars">
                      <?php echo $stars; ?>
                    </div>
                    <?php if ($review['comment']): ?>
                      <p><?php echo nl2br(h($review['comment'])); ?></p><?php endif; ?>
                    <small class="review-donation">Donation: <?php echo h($review['food_name']); ?></small>
                    <?php if ($photo): ?><img class="review-photo" src="<?php echo h($photo); ?>"
                        alt="Review photo for <?php echo h($review['food_name']); ?>"><?php endif; ?>
                  </div>
                </article>
              <?php endforeach; endif; ?>
          </div>
        </section>
      </div>
    </main>
  </div>
  <script src="../../assets/js/header.js"></script>
</body>

</html>