<?php
session_start();
require_once '../../../database/db.php';

// check if the user aka receiver is logged in
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
//     header('Location: ../../auth/login.php');
//     exit();
// }
// $receiver_id = $_SESSION['user_id'];


if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'receiver') {
  header('Location: ../../auth/login.php'); // or the http_response_code/json version for the two AJAX files
  exit();
}

$receiver_id = $_SESSION['user']['id'];

// ----- QUERY 1: ALL ACTIVE DONATIONS ---- //
$sql_donations = "SELECT donations.donation_id, donations.food_name, donations.category,
      donations.quantity, donations.unit, donations.image_url, donations.pickup_address,
      users.full_name AS donor_name, users.trust_score,
      MAX(pickup_slots.timeslot) AS latest_slot,
      GROUP_CONCAT(DISTINCT donation_allergy_tags.allergy_name) AS allergy_tags
    FROM donations
    JOIN users ON donations.donor_id = users.user_id
    JOIN pickup_slots ON pickup_slots.donation_id = donations.donation_id
    LEFT JOIN donation_allergy_tags ON donation_allergy_tags.donation_id = donations.donation_id
    WHERE donations.status = 'active' AND donations.expiry_at > NOW() AND pickup_slots.timeslot > NOW()
    GROUP BY donations.donation_id
    ORDER BY latest_slot ASC";
// MAX(timeslot) takes the latest pickup slot for each donation
// GROUP_CONCAT combines multiple rows' values into one string (v1,v2,v3)

// execute the query 
$result_donations = mysqli_query($dbConn, $sql_donations);

// fetching ~
$donations = [];
while ($row = mysqli_fetch_assoc($result_donations)) {
  $donations[] = $row;
}

?>




<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Donations - Receiver - FoodBridge</title>

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
  <link rel="stylesheet" href="browse-donations.css">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        <a href="browse-donations.php" class="dashboard-nav-item active">Browse Food</a>
        <a href="bookings.php" class="dashboard-nav-item">My Bookings</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="history.php" class="dashboard-nav-item">History</a>
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

      <div class="pageTopBar">
        <div>
          <h1 class="page-heading">Browse Food Nearby</h1>
          <p class="page-subheading">View listings from local food donors, map layouts, and schedule convenient pickup
            slots.</p>
        </div>
        <div class="listMapFilter">
          <button class="Filter active" onclick="switchView('list', this)">List View</button>
          <button class="Filter" onclick="switchView('map', this)">Map View</button>
        </div>
      </div>

      <div class="PageCont">
        <div id="listView">
          <div class="bookingContent">

            <div class="categoryFilterCont">
              <button class="categoryBtn active" data-filter="all">All Categories</button>
              <button class="categoryBtn" data-filter="cookedMeal">Cooked Meals</button>
              <button class="categoryBtn" data-filter="rawProduce">Raw Produce</button>
              <button class="categoryBtn" data-filter="bakery">Bakery</button>
              <button class="categoryBtn" data-filter="cannedGoods">Canned Goods</button>
            </div>

            <div class="donationCardCont">
              <?php if (empty($donations)): ?>

                <p style="color: #777; text-align: center; padding: 20px;">
                  No active donations available right now.
                </p>

              <?php else: ?>

                <?php foreach ($donations as $donation): ?>

                  <?php
                  // time until the last slot's time 
                  $seconds_left = strtotime($donation['latest_slot']) - time();
                  $hours_left = floor($seconds_left / 3600);
                  $minutes_left = floor(($seconds_left % 3600) / 60);

                  // "gluten,dairy" -> ['gluten', 'dairy']
                  $tags = $donation['allergy_tags'] ? explode(',', $donation['allergy_tags']) : [];
                  ?>

                  <div class="donationCard" data-category="<?php echo htmlspecialchars($donation['category']); ?>">
                    <div class="donationImageCont">
                      <img src="../../<?php echo htmlspecialchars($donation['image_url']); ?>" alt="Donation Image">
                      <span class="expiryBadge">⏱ <?php echo $hours_left; ?>h <?php echo $minutes_left; ?>m remaining</span>
                    </div>
                    <div class="donationDetails">
                      <div class="donationHeader">
                        <h3><?php echo htmlspecialchars($donation['food_name']); ?></h3>
                        <span class="donationDistance">📍 1.2km</span>
                        <!-- YEOH JEH HERNE HERE AH THE DISTANCE -->
                      </div>
                      <p class="donationDonor">
                        By <?php echo htmlspecialchars($donation['donor_name']); ?> •
                        <span class="donorScore">Score <?php echo (int) $donation['trust_score']; ?></span>
                      </p>
                      <div class="allergyTags">
                        <?php foreach ($tags as $tag): ?>
                          <span class="tag"><?php echo htmlspecialchars(ucfirst($tag)); ?></span>
                        <?php endforeach; ?>
                      </div>
                      <button class="BookDonation" data-donation-id="<?php echo (int) $donation['donation_id']; ?>"
                        data-quantity="<?php echo htmlspecialchars($donation['quantity']); ?>"
                        data-unit="<?php echo htmlspecialchars($donation['unit']); ?>">
                        Select Pickup Time
                      </button>
                    </div>
                  </div>

                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div id="mapView" style="display: none; margin-bottom: 2rem;">
          <div id="map"></div>
        </div>

      </div>
      <div id="pickupModal" class="modal" style="display:none;">
        <div class="modal-content">
          <span class="close" id="closePickupModal">&times;</span>
          <h2>Select Pickup Time</h2>

          <label for="slotSelect">Available Slots</label>
          <select id="slotSelect"></select>

          <label for="quantityInput">Quantity (<span id="unitLabel"></span>, max <span id="maxQuantity"></span>)</label>
          <input type="number" id="quantityInput" min="0.01" step="0.01">

          <p id="pickupModalError" style="color:#e74c3c;"></p>

          <button id="confirmBookingBtn" class="confirmBtn">Confirm Booking</button>
        </div>
      </div>
      <div class="content-body"></div>
    </main>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="browse-donations.js"></script>
</body>