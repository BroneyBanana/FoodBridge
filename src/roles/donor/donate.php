<?php
  session_start();

  if(!isset($_SESSION['user'])){
    header("Location: ../../auth/login.php");
    exit();
  }
  $userAvatar = $_SESSION['user']['avatarImage'] ?? '';
  $userName = $_SESSION['user']['name'] ?? 'User';
  $initials = strtoupper(substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donate Food - Donor - FoodBridge</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="donate.css">
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
        <a href="donate.php" class="dashboard-nav-item active">Donate</a>
        <a href="my-donations.php" class="dashboard-nav-item">My Donations</a>
        <a href="leaderboard.php" class="dashboard-nav-item">Leaderboard</a>
        <a href="vouchers.php" class="dashboard-nav-item">Vouchers</a>
        <a href="certificates.php" class="dashboard-nav-item">Certificates</a>
        <a href="trust-score.php" class="dashboard-nav-item">Trust Score</a>
        <a href="review.php" class="dashboard-nav-item">Review</a>
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
          style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; ; border-radius: 50%;"></span>
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

  <?php
    if (!empty($_SESSION['errors'])) {
      echo '<div class="success" id = "success">
              <span class = "successIcon"><img src = "../../assets/images/remove.png" alt = "Error Icon."></span>
              <span class = "successMessage">';
      foreach ($_SESSION['errors'] as $error) {
        echo htmlspecialchars($error) . '<br>';
      }
      echo '</span>
            <button class = "closeBtn" id = "closeBtn">&times;</button>
            </div>';
      unset($_SESSION['errors']);
    }
  ?>

  <div class="dashboard-wrapper">
    <main class="dashboard-content">
      <h1 class="page-heading">Donate Surplus Food</h1>
      <p class="page-subheading">Create a new food listing, set allergy tags, and define convenient pickup slots.</p>

      <div class="box-details">
        <form method="POST" action="donateProcess.php" id="publish-donation-form" enctype="multipart/form-data">

          <div class="upload-panel">
            <img src="../../assets/images/photo.png" alt="This is a Photo icon">

            <h3>Food Photo</h3>

            <label class="image-upload">
              <input type="file" name="foodImage" id="foodImage" accept="image/*" required>

              <div class="upload-content">
                <div class="upload-icon">
                  <img src="../../assets/images/add.png" alt="Add Icon">
                </div>

                <h4>Upload Image</h4>

                <p>Drag and drop a clear photo of the food item</p>
              </div>
            </label>

            <div class="upload-guidelines">
              <h5>Photo Guidelines</h5>

              <ul>
                <li>Ensure good lighting</li>
                <li>Show actual portion size</li>
                <li>Packaging should be visible</li>
              </ul>
            </div>
          </div>

          <div class="publish-donations-box">

            <div class="box-header">
              <h2>Food Details</h2>

              <div class="close-button">
                <a href="dashboard.php">&times;</a>
              </div>
            </div>

            <div class="form-panel">
              <div class="form-group full-width">
                <label for="foodName">Food Name</label>
                <input type="text" id="foodName" name="foodName" placeholder="e.g. Nasi Lemak" required>
              </div>
              <div class="form-group">
                <label for="category">Category</label>
                <select name="category" id="category" required>
                  <option value="">Select Category</option>
                  <option value="cookedMeal">Cooked Meal</option>
                  <option value="rawProduce">Raw Produce</option>
                  <option value="bakery">Bakery</option>
                  <option value="beverages">Beverages</option>
                  <option value="cannedGoods">Canned Goods</option>
                  <option value="others">Others</option>
                </select>
              </div>
              <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" required>
              </div>
              <div class="form-group">
                <label for="unit">Unit</label>
                <select name="unit" id="unit" required>
                  <option value="">Select Unit</option>
                  <option value="portions">portions</option>
                  <option value="kg">kg</option>
                  <option value="pieces">pieces</option>
                </select>
              </div>
              <div class="form-group">
                <label for="expiryDate">Expiry Date & Time</label>
                <input type="datetime-local" name="expiryDate" id="expiryDate" required>
              </div>
              <div class="form-group full-width">
                <label for="allergyTags">Allergy Tags</label>
                <div class="allergyTagsCheckBox">
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="nuts" value="nuts">
                    <span>Nuts</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="dairy" value="dairy">
                    <span>Dairy</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="gluten" value="gluten">
                    <span>Gluten</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="shellfish" value="shellfish">
                    <span>Shellfish</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="eggs" value="eggs">
                    <span>Eggs</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="soy" value="soy">
                    <span>Soy</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="vegan-safe" value="vegan-safe">
                    <span>Vegan Safe</span>
                  </label>
                  <label class="checkBoxTags">
                    <input type="checkbox" name="allergies[]" id="none" value="none">
                    <span>None</span>
                  </label>
                </div>
              </div>
              <div class="form-group full-width">
                <label for="pickupAddress">Pickup Address</label>
                <input type="text" id="pickupAddress" name="pickupAddress" placeholder="Your address..." required>
              </div>

              <div class="form-group full-width">
                <div class="slots-header">
                  <label for="pickupSlots">Pickup Slots (Max 3)</label>
                  <button type="button" id="addSlotButton" class="addSlotButton">+ Add Time Slot</button>
                </div>

                <p id="noSlotsText" class="noSlotsText">No slots added. Receivers need a time to book.</p>

                <div id="slots" class="slots"></div>
              </div>

            </div>
            <div class="form-submit">
              <button type="submit" class="submitButton">Add Food for Donation</button>
            </div>
        </form>
      </div>
      <div class="content-body"></div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="donate.js"></script>
</body>

</html>
