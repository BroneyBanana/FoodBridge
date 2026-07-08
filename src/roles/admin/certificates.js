// Registered Donor Database Mock Records
const donorsDatabase = [
  { id: "d1", name: "Fresh Bakery KL", totalDonations: 45, averageRating: 4.8 },
  { id: "d2", name: "Green Grocers", totalDonations: 30, averageRating: 4.5 },
  { id: "d3", name: "Mama Nasi Lemak", totalDonations: 52, averageRating: 4.9 },
  { id: "d4", name: "Cafe 1920", totalDonations: 20, averageRating: 4.2 }
];

// Active global certificates array stream
const certificatesData = [
  {
    id: 1,
    title: "Food Hero Q1 2026",
    recipient: "Fresh Bakery KL",
    period: "Jan 2026 – Mar 2026",
    donations: 45,
    rating: 4.8
  },
  {
    id: 2,
    title: "Community Champion Q4 2025",
    recipient: "Green Grocers",
    period: "Oct 2025 – Dec 2025",
    donations: 30,
    rating: 4.5
  }
];

function generateStarsHTML(rating) {
  let starsHTML = "";
  const fullStars = Math.round(rating);
  for (let i = 1; i <= 5; i++) {
    starsHTML += i <= fullStars ? '<span class="star filled">★</span>' : '<span class="star">★</span>';
  }
  return starsHTML;
}

function populateDonorDropdown() {
  const selectMenu = document.getElementById("certDonorSelect");
  if (!selectMenu) return;

  donorsDatabase.forEach(donor => {
    const option = document.createElement("option");
    option.value = donor.id;
    option.textContent = donor.name;
    selectMenu.appendChild(option);
  });

  selectMenu.addEventListener("change", (e) => {
    const chosenDonor = donorsDatabase.find(d => d.id === e.target.value);
    if (chosenDonor) {
      document.getElementById("certDonations").value = chosenDonor.totalDonations;
      document.getElementById("certRating").value = chosenDonor.averageRating.toFixed(1);
    }
  });
}

function renderCertificates() {
  const container = document.getElementById("certificatesContainer");
  if (!container) return;

  container.innerHTML = certificatesData.map(cert => `
    <article class="certificate-card">
      <div class="cert-badge-wrapper" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
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

      <button class="btn-revoke" type="button" onclick="revokeCertificate(${cert.id})">Revoke Certificate</button>
    </article>
  `).join("");
}

function setupModalEvents() {
  const modal = document.getElementById("certModal");
  const openBtn = document.getElementById("openModalBtn");
  const closeBtn = document.getElementById("closeModalBtn");

  if(openBtn && modal && closeBtn) {
    openBtn.addEventListener("click", () => modal.classList.add("active"));
    closeBtn.addEventListener("click", () => modal.classList.remove("active"));
    modal.addEventListener("click", (e) => {
      if(e.target === modal) modal.classList.remove("active");
    });
  }
}

function handleFormSubmit(event) {
  event.preventDefault();
  const selectMenu = document.getElementById("certDonorSelect");
  const titleInput = document.getElementById("certTitle");
  const periodInput = document.getElementById("certPeriod");
  const donationsInput = document.getElementById("certDonations");
  const ratingInput = document.getElementById("certRating");

  const chosenDonor = donorsDatabase.find(d => d.id === selectMenu.value);
  if (!chosenDonor) return;

  const newCertificate = {
    id: Date.now(),
    title: titleInput.value,
    recipient: chosenDonor.name,
    period: periodInput.value,
    donations: parseInt(donationsInput.value, 10),
    rating: parseFloat(ratingInput.value)
  };

  certificatesData.unshift(newCertificate);
  renderCertificates();
  
  document.getElementById("certModal").classList.remove("active");
  event.target.reset();
}

function revokeCertificate(id) {
  if (confirm("Are you sure you want to revoke this certificate?")) {
    const targetIdx = certificatesData.findIndex(item => item.id === id);
    if (targetIdx > -1) {
      certificatesData.splice(targetIdx, 1);
      renderCertificates();
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  populateDonorDropdown();
  renderCertificates();
  setupModalEvents();
  
  const creationForm = document.getElementById("createCertificateForm");
  if (creationForm) {
    creationForm.addEventListener("submit", handleFormSubmit);
  }
});