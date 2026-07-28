<?php
  session_start();

  if(!isset($_SESSION['user'])){
      header("Location: ../../auth/login.php");
      exit();
  }

  require_once "../../../database/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Donations - Donor - FoodBridge</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">
  
  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  
  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="my-donations.css">
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
        <a href="my-donations.php" class="dashboard-nav-item active">My Donations</a>
        <a href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
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
        <span style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; background-color: #ff4757; border-radius: 50%;"></span>
      </a>

      <a href="profile.php" class="profile-avatar">DO</a>
      
      <a href="../../auth/login.php" class="action-btn-circle hide-mobile" title="Log Out">
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
  <?php
    if(!empty($_SESSION['success'])) {
      echo '<div class = "success" id  = "success">
              <span class = "successIcon"><img src="../../assets/images/success.png" alt="Success Icon"></span>
              <span class = "successMessage">Food donation added successfully!</span>
              <button class = "closeBtn" id = "closeBtn">&times;</button>      
            </div>';
      unset($_SESSION['success']);
    }

    $sqlExpiry = "UPDATE donations SET status = 'expired' WHERE status = 'active' AND expiry_at <= NOW()";
    mysqli_query($dbConn, $sqlExpiry);

    $sqlDonation = "SELECT * FROM donations WHERE donor_id = ? ORDER BY donation_id DESC";
    $stmtDonation = mysqli_prepare($dbConn, $sqlDonation);
    mysqli_stmt_bind_param(
      $stmtDonation,
      "i",
      $_SESSION['user']['id']
    );
    mysqli_stmt_execute($stmtDonation);
    $result = mysqli_stmt_get_result($stmtDonation);
  ?>
  <div class="dashboard-wrapper">
    <main class="dashboard-content">
      <h1 class="page-heading">My Donations</h1>
      <p class="page-subheading">Manage active food listings, simulate QR codes scan, and view pickup histories.</p>
      
      <div class = "my-donations-content">
        <div class = "donation-filter">
          <button class = "filter-button active">All</button>
          <button class = "filter-button">Active</button>
          <button class = "filter-button">Completed</button>
          <button class = "filter-button">Expired</button>
        </div>

        <div class = "my-donations">
        <?php
          while ($row = mysqli_fetch_assoc($result)) {
        ?>
          <div class = "donations-card" data-status = "<?php echo htmlspecialchars($row['status']); ?>">
            <div class = "card-image">
              <img src="../../<?php echo htmlspecialchars($row['image_url']); ?>" alt="This is a food image.">
              <span class = "card-status"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span>
            </div>
            <div class = "card-content">
              <div class = "card-header">
                <h2 class = "card-title"><?php echo htmlspecialchars($row['food_name']); ?></h2>
              </div>
              <div class = "allergy-tags">
                <?php 
                  $sqlAllergy = "SELECT allergy_name FROM donation_allergy_tags WHERE donation_id = ?";

                  $stmtAllergy = mysqli_prepare($dbConn, $sqlAllergy);

                  mysqli_stmt_bind_param($stmtAllergy, "i", $row['donation_id']);

                  mysqli_stmt_execute($stmtAllergy);

                  $result2 = mysqli_stmt_get_result($stmtAllergy);

                  while($tag = mysqli_fetch_assoc($result2)) {
                    $allergyName = [
                      "nuts" => "Nuts",
                      "dairy" => "Dairy",
                      "gluten" => "Gluten",
                      "shellfish" => "Shellfish",
                      "eggs" => "Eggs",
                      "soy" => "Soy",
                      "vegan-safe" => "Vegan Safe",
                      "none" => "None"
                    ];
                ?>
                <span class = "tags"><?php echo htmlspecialchars($allergyName[$tag['allergy_name']]); ?></span>
                <?php
                  }

                  mysqli_stmt_close($stmtAllergy);
                ?>
              </div>
              <?php
              // put this because if not the value will show as cookedMeal
                $categoryName = [
                  "cookedMeal" => "Cooked Meal",
                  "rawProduce" => "Raw Produce",
                  "bakery" => "Bakery",
                  "beverages" => "Beverages",
                  "cannedGoods" => "Canned Goods",
                  "others" => "Others"
              ];

              $quantityDisplay = $row['quantity'];

              if(in_array($row['unit'], ['portions', 'pieces'])) {
                $quantityDisplay = (int) round($quantityDisplay);
              }

              ?>
              <p class = "card-descript"><?php echo htmlspecialchars($quantityDisplay); ?> <?php echo htmlspecialchars($row['unit']); ?> • <?php echo htmlspecialchars($categoryName[$row['category']]); ?></p>
              
              <p class = "expiry-date"><strong>Expires: </strong><?php echo htmlspecialchars(date("M j, Y, g:i A", strtotime($row['expiry_at']))); ?></p>
              <?php 
                if ($row['status'] == "active") {
              ?>
                <button class = "show-qr">Show QR Code for Pickup</button>
              <?php
                } else {
              ?>
                <button class = "show-qr" disabled title = "Pickup already <?php echo htmlspecialchars($row['status']); ?>">
                  <?php
                    if ($row['status'] === 'completed') {
                      echo 'Pickup Completed!';
                    } else {
                      echo 'Donation Expired!';
                    }
                  ?>
                </button>
              <?php
                }
              ?>
              <button class = "tutorial-packup">How to pack</button>
            </div>
          </div>
  <?php
    }

    mysqli_stmt_close($stmtDonation);
  ?>
        </div>
      </div>
      <div class = "tutorial" id = "tutorial">
        <div class = "tutorial-card">
          <div class="tutorial-image">
            <div class="image-step-slide active" data-step="0">
              <img src="../../assets/images/tutorial1.jpg" alt="This is a tutorial 1 image.">
              <div class="tutorial-image-step">01</div>
            </div>
            <div class="image-step-slide" data-step="1">
              <img src="../../assets/images/tutorial2.jpg" alt="This is a tutorial 2 image.">
              <div class="tutorial-image-step">02</div>
            </div>
            <div class="image-step-slide" data-step="2">
              <img src="../../assets/images/tutorial3.jpg" alt="This is a tutorial 3 image.">
              <div class="tutorial-image-step">03</div>
            </div>
            <div class="image-step-slide" data-step="3">
              <img src="../../assets/images/tutorial4.jpg" alt="This is a tutorial 4 image.">
              <div class="tutorial-image-step">04</div>
            </div>
          </div>
          
          <div class = "tutorial-text">
            <div class="text-step-slide active" data-step="0">
              <span class="tutorial-step">Step 1 of 4</span>
              <h2 class="tutorial-title">Hygiene & Safety Check</h2>
              <p class="tutorial-description">Ensure all surfaces, hands, and utensils are thoroughly sanitized. Food safety is our absolute highest priority. Separate raw and cooked foods completely to prevent cross-contamination. Only pack food that is completely safe for immediate human consumption.</p>
              <div class="tutorial-navigation">
                <button type="button" class="previous-button disabled" aria-label="Previous step">&lsaquo;</button>
                <button type="button" class="next-button" id="next-button">Next Step <span class="arrow-button">&rarr;</span></button>
              </div>
            </div>

            <div class="text-step-slide" data-step="1">
              <span class="tutorial-step">Step 2 of 4</span>
              <h2 class="tutorial-title">Weighing & Portioning</h2>
              <p class="tutorial-description">Carefully divide large batches into single or family-sized portions. This makes it much easier for receivers to collect. Use food-grade, leak-proof containers. If possible, weigh the packages so the receiver knows exactly what they are getting.</p>
              <div class="tutorial-navigation">
                <button type="button" class="previous-button" aria-label="Previous step">&lsaquo;</button>
                <button type="button" class="next-button" id="next-button">Next Step <span class="arrow-button">&rarr;</span></button>
              </div>
            </div>

            <div class="text-step-slide" data-step="2">
              <span class="tutorial-step">Step 3 of 4</span>
              <h2 class="tutorial-title">Sealing & Packaging</h2>
              <p class="tutorial-description">Secure lids tightly to prevent any spills during transport. If you are packing hot food, ensure the container is heat-safe and allow steam to vent slightly if necessary before sealing. Clean the outside of the container.</p>
              <div class="tutorial-navigation">
                <button type="button" class="previous-button" aria-label="Previous step">&lsaquo;</button>
                <button type="button" class="next-button" id="next-button">Next Step <span class="arrow-button">&rarr;</span></button>
              </div>
            </div>

            <div class="text-step-slide" data-step="3">
              <span class="tutorial-step">Step 4 of 4</span>
              <h2 class="tutorial-title">QR Labelling & Handoff</h2>
              <p class="tutorial-description">Click 'Show QR Code' on your dashboard. Print or clearly write the tracking code on a label and affix it securely to the top of the container. When the receiver arrives, let them scan the code to complete the secure handoff.</p>
              <div class="tutorial-navigation">
                <button type="button" class="previous-button" aria-label="Previous step">&lsaquo;</button>
                <button type="button" class="next-button" id="next-button">I am Ready <span class="arrow-button">&rarr;</span></button>
              </div>
            </div>

            
          </div>
        </div>
      </div>

      <div class="content-body"></div>
    </main>
  </div>

  <!-- qr code part -->
  <div id="show-qr-modal" class="show-qr-modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Show QR Code">
    <div class="show-qr-card">
      <div class="show-qr-header">
        <h2 class="show-qr-title">Donation Ready</h2>
        <p class="show-qr-desc">Show this code to the receiver upon collection.</p>
      </div>
      
      <div class="show-qr-wrap">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=FoodBridge-Pickup-Auth&color=000000&bgcolor=ffffff" alt="Your Donation QR Code" id="display-qr-img">
      </div>

      <p class="qr-success-msg" style="display: block; margin-top: 10px;">QR Token: </p>

      <div class="qr-modal-actions">
        <button class="qr-btn-close" id="show-qr-close-btn">Close</button>
      </div>
    </div>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="my-donations.js"></script>
</body>
</html>
