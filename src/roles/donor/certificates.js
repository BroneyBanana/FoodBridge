const certificatesData = [
  {
    title: "Food Hero Q1 2026",
    recipient: "Fresh Bakery KL",
    period: "Jan 2026 – Mar 2026",
    donations: 45,
    rating: 4.8
  },
  {
    title: "Community Champion Q4 2025",
    recipient: "Green Grocers",
    period: "Oct 2025 – Dec 2025",
    donations: 30,
    rating: 4.5
  },
  {
    title: "Community Champion Q4 2025",
    recipient: "Mama Nasi Lemak",
    period: "Jan 2026 – Mar 2026",
    donations: 52,
    rating: 4.9
  },
  {
    title: "Community Champion Q4 2025",
    recipient: "Cafe 1920",
    period: "Oct 2025 – Dec 2025",
    donations: 20,
    rating: 4.2
  }
];

function generateStarsHTML(rating) {
  let starsHTML = "";
  const fullStars = Math.round(rating);
  
  for (let i = 1; i <= 5; i++) {
    if (i <= fullStars) {
      starsHTML += '<span class="star filled">★</span>';
    } else {
      starsHTML += '<span class="star">★</span>';
    }
  }
  return starsHTML;
}

function renderCertificates() {
  const container = document.getElementById("certificatesContainer");
  if (!container) return;

  container.innerHTML = certificatesData.map(cert => `
    <article class="certificate-card">
      <div class="cert-badge-wrapper" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="6"/>
          <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
        </svg>
      </div>

      <h2>${cert.title}</h2>
      <p class="cert-recipient">${cert.recipient}</p>

      <div class="cert-metrics-row">
        <div class="metric-group">
          <span class="metric-label">Period</span>
          <span class="metric-value">${cert.period}</span>
        </div>
        <div class="metric-group text-right">
          <span class="metric-label">Donations</span>
          <span class="metric-value highlight">${cert.donations}</span>
        </div>
      </div>

      <div class="cert-satisfaction">
        <div class="satisfaction-label">Receiver Satisfaction</div>
        <div class="star-rating" aria-label="Rating: ${cert.rating} out of 5 stars">
          ${generateStarsHTML(cert.rating)}
        </div>
        <div class="score-text">${cert.rating.toFixed(1)} / 5.0</div>
      </div>

      <button class="btn-download" type="button">Download Certificate</button>
    </article>
  `).join("");
}

document.addEventListener("DOMContentLoaded", renderCertificates);