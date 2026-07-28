<?php
declare(strict_types=1);

session_start();
if (($_SESSION['user']['role'] ?? '') !== 'receiver') { header('Location: ../../auth/login.php'); exit; }
require_once __DIR__ . '/../../../database/db.php';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function initials(string $name): string { $words = preg_split('/\s+/', trim($name)) ?: []; $out = ''; foreach (array_slice(array_filter($words), 0, 2) as $word) $out .= strtoupper(substr($word, 0, 1)); return $out ?: 'FB'; }
function imagePath(?string $path): ?string { if (!$path) return null; return preg_match('#^https?://#i', $path) ? $path : '../../' . ltrim($path, '/'); }
function uploadImage(string $field, string $directory): ?string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($_FILES[$field]['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Upload an image no larger than 5 MB.');
    $imageInfo = @getimagesize($_FILES[$field]['tmp_name']);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$imageInfo || !isset($extensions[$imageInfo[2]])) throw new RuntimeException('Please upload a JPG, PNG, GIF, or WebP image.');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Unable to prepare the upload directory.');
    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$imageInfo[2]];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $directory . DIRECTORY_SEPARATOR . $fileName)) throw new RuntimeException('Unable to save the uploaded image.');
    return 'uploads/' . basename($directory) . '/' . $fileName;
}

$receiverId = (int) $_SESSION['user']['id'];
$userStatement = mysqli_prepare($dbConn, 'SELECT full_name FROM users WHERE user_id = ? AND role = \'receiver\' LIMIT 1');
mysqli_stmt_bind_param($userStatement, 'i', $receiverId); mysqli_stmt_execute($userStatement);
$receiver = mysqli_fetch_assoc(mysqli_stmt_get_result($userStatement)); mysqli_stmt_close($userStatement);
if (!$receiver) { session_unset(); session_destroy(); header('Location: ../../auth/login.php'); exit; }

$_SESSION['history_csrf'] ??= bin2hex(random_bytes(32));
$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['history_csrf'], (string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Your session has expired. Please try again.');
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        if (!$bookingId || !in_array($action, ['review', 'report'], true)) throw new RuntimeException('Invalid history item.');
        $ownershipStatement = mysqli_prepare($dbConn, "SELECT booking_id FROM bookings WHERE booking_id = ? AND receiver_id = ? AND status = 'collected' LIMIT 1");
        mysqli_stmt_bind_param($ownershipStatement, 'ii', $bookingId, $receiverId); mysqli_stmt_execute($ownershipStatement);
        $ownedBooking = mysqli_fetch_assoc(mysqli_stmt_get_result($ownershipStatement)); mysqli_stmt_close($ownershipStatement);
        if (!$ownedBooking) throw new RuntimeException('You can only act on your own collected bookings.');

        if ($action === 'review') {
            $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
            $comment = trim((string) ($_POST['comment'] ?? ''));
            if (!$rating || $rating < 1 || $rating > 5 || $comment === '') throw new RuntimeException('Choose a rating and enter a review comment.');
            $image = uploadImage('review_image', __DIR__ . '/../../uploads/reviews');
            $reviewStatement = mysqli_prepare($dbConn, 'INSERT INTO reviews (booking_id, rating, comment, review_image_url) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($reviewStatement, 'iiss', $bookingId, $rating, $comment, $image);
            if (!mysqli_stmt_execute($reviewStatement)) throw new RuntimeException('A review has already been submitted for this collection.');
            mysqli_stmt_close($reviewStatement); $message = 'Your review has been submitted.';
        } else {
            $issueType = trim((string) ($_POST['issue_type'] ?? ''));
            $comment = trim((string) ($_POST['comment'] ?? ''));
            if ($issueType === '' || $comment === '') throw new RuntimeException('Select an issue type and describe the problem.');
            $image = uploadImage('evidence_image', __DIR__ . '/../../uploads/reports');
            $reportStatement = mysqli_prepare($dbConn, 'INSERT INTO reports (booking_id, issue_type, comment, evidence_image_url) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($reportStatement, 'isss', $bookingId, $issueType, $comment, $image);
            if (!mysqli_stmt_execute($reportStatement)) throw new RuntimeException('Unable to submit the report.');
            mysqli_stmt_close($reportStatement); $message = 'Your report has been submitted for review.';
        }
        $_SESSION['history_notice'] = $message; header('Location: history.php'); exit;
    } catch (RuntimeException $exception) { $message = $exception->getMessage(); $messageType = 'error'; }
}
if (isset($_SESSION['history_notice'])) { $message = (string) $_SESSION['history_notice']; $messageType = 'success'; unset($_SESSION['history_notice']); }

$historyStatement = mysqli_prepare($dbConn, "SELECT b.booking_id, b.booking_time, d.food_name, d.image_url, u.full_name AS donor_name, r.rating, r.comment AS review_comment, r.review_image_url, r.created_at AS review_created_at, COUNT(rep.report_id) AS report_count
    FROM bookings b INNER JOIN donations d ON d.donation_id = b.donation_id INNER JOIN users u ON u.user_id = d.donor_id LEFT JOIN reviews r ON r.booking_id = b.booking_id LEFT JOIN reports rep ON rep.booking_id = b.booking_id
    WHERE b.receiver_id = ? AND b.status = 'collected' GROUP BY b.booking_id, b.booking_time, d.food_name, d.image_url, u.full_name, r.rating, r.comment, r.review_image_url, r.created_at ORDER BY b.booking_time DESC");
mysqli_stmt_bind_param($historyStatement, 'i', $receiverId); mysqli_stmt_execute($historyStatement);
$history = mysqli_fetch_all(mysqli_stmt_get_result($historyStatement), MYSQLI_ASSOC); mysqli_stmt_close($historyStatement);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>History - Receiver - FoodBridge</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet"><link rel="stylesheet" href="../../assets/css/global.css"><link rel="stylesheet" href="../../assets/css/header.css"><link rel="stylesheet" href="history.css"></head><body>
<div class="noise-bg"></div><header class="dashboard-header"><a href="dashboard.php" class="navbar-brand"><div class="navbar-logo"><img src="../../assets/images/logo.png" alt="FoodBridge logo"></div></a><div class="nav-overlay" id="navOverlay"><nav class="dashboard-nav"><a href="dashboard.php" class="dashboard-nav-item">Overview</a><a href="browse-donations.php" class="dashboard-nav-item">Browse Food</a><a href="bookings.php" class="dashboard-nav-item">My Bookings</a><a href="trust-score.php" class="dashboard-nav-item">Trust Score</a><a href="history.php" class="dashboard-nav-item active">History</a></nav></div><div class="dashboard-actions"><a class="action-btn-circle hide-mobile" title="Notifications" href="notifications.php">&#128276;</a><a href="profile.php" class="profile-avatar"><?php echo h(initials($receiver['full_name'])); ?></a><a href="../../auth/login.php" class="action-btn-circle hide-mobile" title="Log Out">&#10132;</a><button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle mobile menu">&#9776;</button></div></header>
<div class="dashboard-wrapper"><main class="dashboard-content"><h1 class="page-heading">Collection History</h1><p class="page-subheading">Review completed food collections and report any issue to the admin team.</p>
<?php if ($message): ?><p class="history-notice <?php echo h($messageType); ?>" role="status"><?php echo h($message); ?></p><?php endif; ?>
<section class="history-panel" aria-label="Collection history"><div class="history-list"><?php if (!$history): ?><p class="empty-history">You have no completed collections yet.</p><?php else: foreach ($history as $item): ?><article class="history-card"><div class="history-info"><h3><?php echo h($item['food_name']); ?></h3><p class="history-meta"><?php echo h($item['donor_name']); ?> &bull; <?php echo h(date('j F Y', strtotime($item['booking_time']))); ?></p></div><div class="card-actions">
<?php if ($item['rating'] !== null): ?><button class="text-action-btn review" type="button" data-view-review data-rating="<?php echo (int) $item['rating']; ?>" data-comment="<?php echo h($item['review_comment'] ?? ''); ?>" data-image="<?php echo h(imagePath($item['review_image_url']) ?? ''); ?>">View Review</button><?php else: ?><button class="text-action-btn review" type="button" data-open="review" data-booking="<?php echo (int) $item['booking_id']; ?>" data-food="<?php echo h($item['food_name']); ?>">Do Review</button><?php endif; ?>
<button class="text-action-btn report" type="button" data-open="report" data-booking="<?php echo (int) $item['booking_id']; ?>" data-food="<?php echo h($item['food_name']); ?>">Report</button><?php if ((int) $item['report_count']): ?><span class="status-pill reported">Reported</span><?php endif; ?><span class="status-pill collected">Collected</span></div></article><?php endforeach; endif; ?></div></section></main></div>
<div class="modal-overlay hidden" id="reviewModal" role="dialog" aria-modal="true"><form class="modal-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['history_csrf']); ?>"><input type="hidden" name="action" value="review"><input type="hidden" name="booking_id" id="reviewBooking"><div class="modal-header"><div><span class="modal-kicker">Review</span><h2 id="reviewTitle">Write a Review</h2></div><button type="button" class="icon-btn ghost" data-close>&times;</button></div><div class="form-fields"><label>Rating <div class="rating-options" role="radiogroup"><input type="radio" name="rating" id="star5" value="5" required><label for="star5">5</label><input type="radio" name="rating" id="star4" value="4"><label for="star4">4</label><input type="radio" name="rating" id="star3" value="3"><label for="star3">3</label><input type="radio" name="rating" id="star2" value="2"><label for="star2">2</label><input type="radio" name="rating" id="star1" value="1"><label for="star1">1</label></div></label><label>Comment<textarea name="comment" rows="4" required placeholder="Share how the collection went..."></textarea></label><label>Picture <span class="upload-box"><input type="file" name="review_image" accept="image/*"><span class="upload-button">Choose Image</span></span></label></div><div class="modal-actions"><button type="button" class="btn btn-outline" data-close>Cancel</button><button type="submit" class="btn btn-primary">Submit Review</button></div></form></div>
<div class="modal-overlay hidden" id="reportModal" role="dialog" aria-modal="true"><form class="modal-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['history_csrf']); ?>"><input type="hidden" name="action" value="report"><input type="hidden" name="booking_id" id="reportBooking"><div class="modal-header"><div><span class="modal-kicker">Report</span><h2 id="reportTitle">Report a Problem</h2></div><button type="button" class="icon-btn ghost" data-close>&times;</button></div><div class="form-fields"><label>Type of issue<select name="issue_type" required><option value="" selected disabled>Select an issue type</option><option value="spoiled_unsafe_food">Spoiled or unsafe food</option><option value="wrong_pickup_address">Wrong pickup address</option><option value="fake_inaccurate_listing">Fake or inaccurate listing</option><option value="other">Other</option></select></label><label>Description<textarea name="comment" rows="4" required placeholder="Describe the problem..."></textarea></label><label>Evidence <span class="upload-box"><input type="file" name="evidence_image" accept="image/*"><span class="upload-button">Choose Image</span></span></label></div><div class="modal-actions"><button type="button" class="btn btn-outline" data-close>Cancel</button><button type="submit" class="btn btn-primary">Submit Report</button></div></form></div>
<div class="modal-overlay hidden" id="viewModal" role="dialog" aria-modal="true"><div class="modal-card"><div class="modal-header"><div><span class="modal-kicker">Your review</span><h2>Review details</h2></div><button type="button" class="icon-btn ghost" data-close>&times;</button></div><div class="readonly-review"><strong id="viewRating"></strong><p id="viewComment"></p><a id="viewImageLink" class="review-image-link hidden" target="_blank" rel="noopener"><img id="viewImage" alt="Your uploaded review image"></a></div></div></div><script src="../../assets/js/header.js"></script><script>const modals={review:document.getElementById('reviewModal'),report:document.getElementById('reportModal'),view:document.getElementById('viewModal')};document.querySelectorAll('[data-open]').forEach(b=>b.onclick=()=>{let x=b.dataset.open;document.getElementById(x+'Booking').value=b.dataset.booking;document.getElementById(x+'Title').textContent=(x==='review'?'Review ':'Report ')+b.dataset.food;modals[x].classList.remove('hidden')});document.querySelectorAll('[data-view-review]').forEach(b=>b.onclick=()=>{document.getElementById('viewRating').textContent=b.dataset.rating+' / 5 rating';document.getElementById('viewComment').textContent=b.dataset.comment;const imageLink=document.getElementById('viewImageLink'),image=document.getElementById('viewImage');if(b.dataset.image){image.src=b.dataset.image;imageLink.href=b.dataset.image;imageLink.classList.remove('hidden')}else{image.removeAttribute('src');imageLink.removeAttribute('href');imageLink.classList.add('hidden')}modals.view.classList.remove('hidden')});document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>Object.values(modals).forEach(m=>m.classList.add('hidden')));Object.values(modals).forEach(m=>m.onclick=e=>{if(e.target===m)m.classList.add('hidden')});</script></body></html>
