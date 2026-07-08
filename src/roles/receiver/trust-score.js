<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trust Score - Receiver - FoodBridge</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&family=Syne:wght@400..800&display=swap" rel="stylesheet">
  
  <!-- Global Styles -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  
  <!-- Page Specific Styles -->
  <link rel="stylesheet" href="trust-score.css">
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
        <a href="browse-donations.html" class="dashboard-nav-item">Browse Food</a>
        <a href="bookings.html" class="dashboard-nav-item">My Bookings</a>
        <a href="trust-score.html" class="dashboard-nav-item active">Trust Score</a>
        <a href="history.html" class="dashboard-nav-item">History</a>
      </nav>
    </div>
    
    <div class="dashboard-actions">
      <a class="action-btn-circle hide-mobile" title="Notifications" style="position: relative;" href="notifications.html">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>
        <span style="position: absolute; top: 8px; right: 8px; width: 6px; height: 6px; background-color: #ff4757; border-radius: 50%;"></span>
      </a>

      <a href="profile.html" class="profile-avatar">DO</a>
      
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
  <div class="dashboard-wrapper">
    <main class="dashboard-content">
      <h1 class="page-heading">Trust Score</h1>
      <p class="page-subheading">View details about your rating level, score deductions, and history of collections.</p>
      
      <div class="content-body">
        <section class="trust-score-panel" aria-label="Trust score summary">
          <h2>Trust Score</h2>

          <div class="score-ring" aria-label="Trust score 92 out of 100">
            <div class="score-ring-inner">
              <strong>92</strong>
              <span>Score</span>
            </div>
          </div>

          <div class="score-breakdown">
            <div class="score-row">
              <span class="score-label">On-Time Pickup Rate</span>
              <div class="score-track">
                <div class="score-fill lime" style="width: 95%;"></div>
              </div>
              <strong>95%</strong>
            </div>

            <div class="score-row">
              <span class="score-label">Reviews Submitted</span>
              <div class="score-track">
                <div class="score-fill forest" style="width: 60%;"></div>
              </div>
              <strong>60%</strong>
            </div>

            <div class="score-row">
              <span class="score-label">Violations (Missed/Late)</span>
              <div class="score-track">
                <div class="score-fill alert" style="width: 5%;"></div>
              </div>
              <strong>5%</strong>
            </div>
          </div>
        </section>

        <section class="trust-history-panel" aria-label="Trust score history">
          <div class="history-header">
            <div>
              <span class="history-eyebrow">Score Activity</span>
              <h2>Trust Score History</h2>
            </div>
            <span class="history-total">Current score: 92</span>
          </div>

          <div class="history-list">
            <article class="history-item positive">
              <div class="history-marker">+</div>
              <div class="history-copy">
                <div class="history-title-row">
                  <h3>On-time pickup completed</h3>
                  <strong>+5 marks</strong>
                </div>
                <span>Today, 9:30 AM</span>
              </div>
            </article>

            <article class="history-item negative">
              <div class="history-marker">-</div>
              <div class="history-copy">
                <div class="history-title-row">
                  <h3>Missed pickup warning (donor reported no-show)</h3>
                  <strong>-5 marks</strong>
                </div>
                <span>Yesterday, 6:20 PM</span>
              </div>
            </article>

            <article class="history-item positive">
              <div class="history-marker">+</div>
              <div class="history-copy">
                <div class="history-title-row">
                  <h3>Helpful review submitted</h3>
                  <strong>+3 marks</strong>
                </div>
                <span>15 Jun 2026</span>
              </div>
            </article>

            <article class="history-item negative">
              <div class="history-marker">-</div>
              <div class="history-copy">
                <div class="history-title-row">
                  <h3>Late cancellation (booking was cancelled less than 30 minutes)</h3>
                  <strong>-2 marks</strong>
                </div>
                <span>11 Jun 2026</span>
              </div>
            </article>
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- Page Specific Logic -->
  <script src="../../assets/js/header.js"></script>
  <script src="trust-score.js"></script>
</body>
</html>
