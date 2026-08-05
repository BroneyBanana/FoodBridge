<?php
require_once __DIR__ . '/database/db.php';
session_start();
// Already logged in? Skip the landing page and go straight to their dashboard.
if (!empty($_SESSION['user']['role'])) {
  header('Location: src/roles/' . $_SESSION['user']['role'] . '/dashboard.php');
  exit;
}

// Live stats for the landing page
$mealsRescued   = 0;
$activeDonors   = 0;
$receiversFed   = 0;
$totalDonors    = 0;
$totalQtyRescued = 0;
$completionRate = 0;

$result = mysqli_query($dbConn, "SELECT COUNT(*) AS total FROM donations");
if ($result) {
  $mealsRescued = (int) mysqli_fetch_assoc($result)['total'];
}

$result = mysqli_query($dbConn, "SELECT COUNT(*) AS total FROM users WHERE role = 'donor' AND status = 'active'");
if ($result) {
  $activeDonors = (int) mysqli_fetch_assoc($result)['total'];
}

$result = mysqli_query($dbConn, "SELECT COUNT(DISTINCT receiver_id) AS total FROM bookings WHERE status = 'collected'");
if ($result) {
  $receiversFed = (int) mysqli_fetch_assoc($result)['total'];
}

// Total registered donors (not just currently active) for the "Join X+ People Donate" tile
$result = mysqli_query($dbConn, "SELECT COUNT(*) AS total FROM users WHERE role = 'donor'");
if ($result) {
  $totalDonors = (int) mysqli_fetch_assoc($result)['total'];
}

// Total food rescued via completed donations, for the surplus-rescued tile (all units combined)
$result = mysqli_query($dbConn, "SELECT COALESCE(SUM(quantity), 0) AS total_qty FROM donations WHERE status = 'completed'");
if ($result) {
  $totalQtyRescued = (float) mysqli_fetch_assoc($result)['total_qty'];
}

// Completion rate (completed donations / total donations) for the "65%" tile
$result = mysqli_query($dbConn, "SELECT COUNT(*) AS total, SUM(status = 'completed') AS completed FROM donations");
if ($result) {
  $row = mysqli_fetch_assoc($result);
  $donationTotal     = (int) $row['total'];
  $donationCompleted = (int) $row['completed'];
  $completionRate    = $donationTotal > 0 ? round(($donationCompleted / $donationTotal) * 100) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FoodBridge - Share what you can. Take what you need.</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="src/assets/css/global.css">
  <link rel="stylesheet" href="src/assets/css/header.css">
  <link rel="stylesheet" href="index.css">
</head>
<body>
  <div class="noise-bg"></div>

  <nav class="global-navbar">
  <a href="index.php" class="navbar-brand">
    <div class="navbar-logo">
        <img src="./src/assets/images/logo.png" alt="Logo" />
    </div>
  </a>
  
  <button class="landing-hamburger" id="landingHamburger" aria-label="Toggle menu">
    <svg class="icon-menu" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <line x1="3" y1="12" x2="21" y2="12"></line>
      <line x1="3" y1="6" x2="21" y2="6"></line>
      <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
    <svg class="icon-close" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
      <line x1="18" y1="6" x2="6" y2="18"></line>
      <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
  </button>
  
  <div class="nav-menu-wrapper" id="navMenuWrapper">
    <div class="navbar-links">
      <a href="index.php" class="navbar-link">Home</a>
      <a href="index.php#how-it-works" class="navbar-link">How It Works</a>
      <a href="index.php#community-impact" class="navbar-link">Community Impact</a>
    </div>
    
    <div class="navbar-actions">
      <a href="./src/auth/login.php" class="btn btn-outline" style="padding: 10px 15px; font-size: 0.85rem; width: 100%;">Log In</a>
      <a href="./src/auth/register.php" class="btn btn-primary" style="padding: 10px 24px; font-size: 0.85rem; width: 100%;">Get Started</a>
    </div>
  </div>
</nav>

  <div class="main-wrapper">
    <main class="dashboard-content">
      <div class="intro">
        <h1 class="page-heading">Food that's left behind — find a home.</h1>
        <p class="page-subheading">Malaysia's first food rescue platform, optimized for our community in a more easy way.</p>
        <div class="action-button">
          <a href="./src/auth/register.php" class="btn btn-primary" style="padding: 15px 24px; font-size: 0.85rem;">Donate Now</a>
          <div>
            <a href="#community-impact" class="btn btn-outline" style="padding: 15px 24px; font-size: 0.85rem; gap: 5px; border: 0;"><svg xmlns="http://www.w3.org/2000/svg" width=".85rem" height=".85rem" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play w-4 h-4 fill-forest group-hover:scale-110 transition-transform"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg> View Impact</a>
          </div>
        </div>
      </div>

      <section class="tiles-body">
        <div class="bento-col col-1">
          <div class="bento-tile tile-forest tile-stat">
            <h2 class="tile-number"><?php echo $completionRate; ?>%</h2>
            <p class="tile-desc"><?php echo number_format($totalQtyRescued); ?> units of surplus food rescued, preventing greenhouse gas emissions. FoodBridge thrives.</p>
          </div>
    
          <div class="bento-tile tile-forest tile-heard">
            <div class="smile-icon">
              <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
            </div>
            <h3>Let them<br>be heard</h3>
          </div>
        </div>

        <div class="bento-col col-2 offset-down">
          <div class="bento-tile tile-image" style="background-image: url('https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&q=80&w=600');">
            <div class="tile-overlay">
              <span class="tile-badge">LOCAL</span>
              <h3 class="tile-title-overlay">Fresh Produce for 200 families in Petaling Jaya</h3>
            </div>
          </div>
        </div>

        <div class="bento-col col-3 offset-deep">
          <div class="bento-tile tile-light tile-donate">
            <h2 class="tile-heading-medium">Join <span class="text-highlight"><?php echo number_format($totalDonors); ?>+</span> People Donate</h2>
          </div>
        </div>

        <div class="bento-col col-4 offset-down">
          <div class="bento-tile tile-image" style="background-image: url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&q=80&w=600');">
            <div class="tile-overlay">
              <span class="tile-badge">COMMUNITY</span>
              <h3 class="tile-title-overlay">Sponsor meals for local shelters</h3>
            </div>
          </div>
        </div>

        <div class="bento-col col-5">
          <div class="bento-tile tile-lime tile-explore">
            <a href="./src/auth/register.php" class="tile-footer" style="text-decoration: none; color: inherit;">
              <span>Explore more</span>
            </a>
          </div>

          <div class="bento-tile tile-forest tile-surplus">
            <div class="dot-indicator">
              <div class="dot-inner"></div>
            </div>
            <h3>Your home for surplus</h3>
          </div>
        </div>
      </section>

      <section class="stats">
        <div class="stats-grid">
          
          <div class="stat-item">
            <div class="stat-number">+<?php echo number_format($mealsRescued); ?></div>
            <div class="stat-divider"></div>
            <div class="stat-label">Meals Rescued</div>
          </div>

          <div class="stat-item">
            <div class="stat-number"><?php echo number_format($activeDonors); ?></div>
            <div class="stat-divider"></div>
            <div class="stat-label">Active Donors</div>
          </div>

          <div class="stat-item">
            <div class="stat-number"><?php echo number_format($receiversFed); ?></div>
            <div class="stat-divider"></div>
            <div class="stat-label">Receivers Fed</div>
          </div>

        </div>
      </section>

      <section class="how-it-works-section" id="how-it-works">
        <div class="section-header">
          <h2 class="section-title">HOW IT WORKS</h2>
          <p class="page-subheading">A seamless flow from surplus to someone in need, ensuring zero waste.</p>
        </div>

        <div class="steps-container">
          <div class="step-row">
            <div class="step-text">
              <div class="step-badge">1</div>
              <h3 class="step-heading">List Excess Food</h3>
              <p class="step-description">Restaurants and donors easily snap a photo, add details like expiry and allergies, and list surplus food in seconds.</p>
            </div>
            <div class="step-visual">
              <div class="step-card step-tall">
                <div class="step-photo-area">
                  <img class="photo-image" src="./src/assets/images/food.avif" alt="Food Image" />
                </div>
                <div class="step-text-title">Prawn Mee</div>
                <div class="step-btn-dark">List Item</div>
              </div>
            </div>
          </div>

          <div class="step-row reverse">
            <div class="step-text">
              <div class="step-badge">2</div>
              <h3 class="step-heading">Browse & Book</h3>
              <p class="step-description">Receivers browse available food nearby on our live map, filter by dietary needs, and book a secure pickup time slot.</p>
            </div>
            
            <div class="step-visual"> 
              <div class="step-card step-wide">
                <div class="step-header-row">
                  <div class="step-avatar">
                    <img class="small-food-photo" src="./src/assets/images/food.avif" alt="Food Image" />
                  </div>
                  <div class="step-lines">
                    <div class="step-text-title">Prawn Mee</div>
                    <div class="step-text-subtitle">12 KM Away</div>
                  </div>
                </div>
                <div class="step-btn-dark">Book Pickup</div>
              </div>
            </div> 
          </div>

          <div class="step-row">
            <div class="step-text">
              <div class="step-badge">3</div>
              <h3 class="step-heading">Pickup via QR</h3>
              <p class="step-description">Arrive at the location, scan the secure QR code to verify the pickup, and collect your rescued meal safely.</p>
            </div>
            <div class="step-visual">
              <div class="step-card step-square step-qr-card">
                <div class="qr-code-container">
                  <img src="https://images.pexels.com/photos/12935051/pexels-photo-12935051.jpeg" alt="Sample pickup QR code." class="qr-code-image">
                </div>
                <div class="step-btn-dark">Scan QR Code</div>
              </div>
            </div>
          </div>
        </div>    
      </section>


      <section id="community-impact">
        <div class="impactCont">
          <div class="impactHeader">
            <h2 class="impactTitle">Community Impact</h2>
            <p class="impactSubheading">See the real difference we're making together.</p>
          </div>

          <div class="impactGrid">
            <div class="impactCard">
              <img src="./src/assets/images/impact1.jpg" alt="Emergency Relief">
              <div class="impactCardContent">
                <h3 class="impactCardTitle">EMERGENCY RELIEF</h3>
                <p class="impactCardDesc">People Fed During Recent Floods</p>
              </div>
            </div>
            <div class="impactCard">
              <img src="./src/assets/images/impact2.jpg" alt="Education">
              <div class="impactCardContent">
                <h3 class="impactCardTitle">EDUCATION</h3>
                <p class="impactCardDesc">No data</p>
              </div>
            </div>
            <div class="impactCard">
              <img src="./src/assets/images/impact3.jpg" alt="Community">
              <div class="impactCardContent">
                <h3 class="impactCardTitle">COMMUNITY</h3>
                <p class="impactCardDesc">No data</p>
              </div>
            </div>
            <div class="impactCard">
              <img src="./src/assets/images/impact4.jpg" alt="Welfare">
              <div class="impactCardContent">
                <h3 class="impactCardTitle">WELFARE</h3>
                <p class="impactCardDesc">Sponsor daily meals for Orphans</p>
              </div>
            </div>
            <div class="impactCard">
              <img src="./src/assets/images/impact5.jpg" alt="Environment">
              <div class="impactCardContent">
                <h3 class="impactCardTitle">ENVIRONMENT</h3>
                <p class="impactCardDesc">Zero Waste Neighborhoods</p>
              </div>
            </div>
            <div class="impactCard">
              <img src="./src/assets/images/impact6.jpg" alt="SHELTER">
              <div class="impactCardContent">
                <h3 class="impactCardTitle">SHELTER</h3>
                <p class="impactCardDesc">Hot meals for the Homeless</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="join-community">
        <div class="joinCont">
          <h2 class="joinTitle">Ready to Make a Difference?</h2>
          <p class="joinSubheading">Join our community of food rescuers and help turn surplus into smiles.</p>
          <div class="joinBtn">
            <a href="./src/auth/register.php" class="joinBtn">Yes, I Want To Join The Community!</a>
          </div>
        </div>
      </section>


      <div class="marquee-section">
        <div class="marquee-row marquee-row-1">
          <div class="marquee-track">
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
          </div>
        </div>
        <div class="marquee-row marquee-row-2">
          <div class="marquee-track reverse">
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
            <span>* let's help each other</span>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="footer">
        <div class="footer-inner">
          <div class="footer-brand">
            <h3 class="footer-logo">FoodBridge</h3>
            <p class="footer-tagline">ZERO WASTE. ZERO HUNGER.</p>
          </div>
          <nav class="footer-links">
            <a href="./src/auth/login.php">Login</a>
            <a href="./src/auth/register.php">Signup</a>
          </nav>
          <p class="footer-copy">© 2026 FoodBridge. All rights reserved.</p>
        </div>
      </footer>

    </main>
  </div>

  <script src="index.js"></script>
</body>
</html>