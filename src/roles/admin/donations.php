<?php
    session_start();

    require_once "../../../database/db.php";

    if(!isset($_SESSION['user'])) {
        header("Location: ../../auth/login.html");
        exit();
    }

    $sqlDonations = "SELECT donations.*, users.full_name FROM donations JOIN users ON donations.donor_id = users.user_id ORDER BY expiry_at DESC";
    $stmtDonations = mysqli_prepare($dbConn, $sqlDonations);
    mysqli_stmt_execute($stmtDonations);
    $result = mysqli_stmt_get_result($stmtDonations);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donations - Admin - FoodBridge</title>

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
  <link rel="stylesheet" href="donations.css">
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
        <a href="users.html" class="dashboard-nav-item">Users</a>
        <a href="vouchers.html" class="dashboard-nav-item">Vouchers</a>
        <a href="donations.html" class="dashboard-nav-item active">Donations</a>
        <a href="trust-rules.html" class="dashboard-nav-item">Trust Rules</a>
        <a href="reports.html" class="dashboard-nav-item">Reports</a>
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
      <h1 class="page-heading">Donations Log</h1>
      <p class="page-subheading">Track and manage active, completed, and flagged food donations across the platform.</p>

      <div class="dashboard-container">
        <section class="stats-section">
          <div class="stats-card">
            <div class="stats-icon">
              <img src="../../assets/images/people.png" alt="This is Total Users Icon.">
            </div>
            <?php 
              $sqlUsers = "SELECT COUNT(*) AS totalUsers FROM users";
              $resultUsers = mysqli_query($dbConn, $sqlUsers);
              $totalUsers = mysqli_fetch_assoc($resultUsers); 
            ?>
            <div class="stats-info">
              <p>Total Users</p>
              <h2><?php echo $totalUsers['totalUsers']; ?></h2>
            </div>
          </div>
          
          <div class="stats-card">
            <div class="stats-icon">
              <img src="../../assets/images/charity.png" alt="This is Donations Icon.">
            </div>
            <?php
              $sqlCountDonations = "SELECT COUNT(*) AS totalDonations FROM donations";
              $resultDonations = mysqli_query($dbConn, $sqlCountDonations);
              $totalDonations = mysqli_fetch_assoc($resultDonations);
            ?>
            <div class="stats-info">
              <p>Donations</p>
              <h2><?php echo $totalDonations['totalDonations']; ?></h2>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-icon">
              <img src="../../assets/images/check.png" alt="This is Successful Pickup Icon.">
            </div>
            <?php
              $sqlSuccessPickup = "SELECT COUNT(*) AS totalSuccessPickup FROM donations WHERE status = 'completed'";
              $resultSuccessPickup = mysqli_query($dbConn, $sqlSuccessPickup);
              $totalSuccessPickup = mysqli_fetch_assoc($resultSuccessPickup);
            ?>
            <div class="stats-info">
              <p>Success Pickups</p>
              <h2><?php echo $totalSuccessPickup['totalSuccessPickup']; ?></h2>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-icon">
              <img src="../../assets/images/vegetarian.png" alt="This is Food Saved Icon.">
            </div>
            <?php
              $sqlFoodSaved = "SELECT SUM(quantity) AS totalFoodSaved FROM donations WHERE unit = 'kg' && status = 'completed'";
              $resultFoodSaved = mysqli_query($dbConn, $sqlFoodSaved);
              $totalFoodSaved = mysqli_fetch_assoc($resultFoodSaved);

              $quantityDisplay = (int) round($totalFoodSaved['totalFoodSaved']);
            ?>
            <div class="stats-info">
              <p>Food Saved (kg)</p>
              <h2><?php echo $quantityDisplay; ?></h2>
            </div>
          </div>
        </section>

        <section class="donation-section">
          <div class="filter-buttons">
            <button class="active-button"><b>All</b></button>
            <button><b>Active</b></button>
            <button><b>Expired</b></button>
            <button><b>Completed</b></button>
          </div>
          <table class="donation-table">
            <tr>
              <th>FOOD</th>
              <th>DONOR</th>
              <th>EXPIRY DATE</th>
              <th>QUANTTY</th>
              <th>STATUS</th>
            </tr>
            <?php
              while ($row = mysqli_fetch_assoc($result)) {
            ?>
            <tr>
              <td>
                <b><?php echo htmlspecialchars($row['food_name']); ?></b><br>
                <?php echo htmlspecialchars($row['category']); ?>
              </td>
              <td><?php echo htmlspecialchars($row['full_name']); ?></td>
              <td><?php echo htmlspecialchars($row['expiry_at']); ?></td>
              <td><?php echo htmlspecialchars($row['quantity']); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
              <td><span class="status-badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span></td>
            </tr>
            <?php
              }

              mysqli_stmt_close($stmtDonations);
            ?>
          </table>
        </section>

      </div>
      <div class="content-body"></div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="donations.js"></script>
</body>

</html>