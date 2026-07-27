function formatPeriod(startStr, endStr) {
  // startStr / endStr look like "2026-01-01 00:00:00"
  const start = new Date(startStr.replace(" ", "T"));
  const end = new Date(endStr.replace(" ", "T"));
  const opts = { month: "short", year: "numeric" };
  return `${start.toLocaleDateString("en-US", opts)} - ${end.toLocaleDateString("en-US", opts)}`;
}

function satisfactionToRating(rate) {
  switch (rate) {
    case "Excellent": return 5.0;
    case "Good": return 4.0;
    case "Average": return 3.0;
    case "Poor": return 2.0;
    default: return 0.0;
  }
}

function generateStarsHTML(rating) {
  let starsHTML = "";
  const fullStars = Math.round(rating);
  for (let i = 1; i <= 5; i++) {
    starsHTML += i <= fullStars ? '<span class="star filled">★</span>' : '<span class="star">★</span>';
  }
  return starsHTML;
}

function buildCertificateCard(cert) {
  const article = document.createElement("article");
  article.className = "certificate-card";
  article.dataset.certId = cert.certificate_id;

  const rating = satisfactionToRating(cert.receiver_satisfaction_rate);

  article.innerHTML = `
    <div class="cert-badge-wrapper" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="12" cy="8" r="6"/>
        <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
      </svg>
    </div>

    <h2>${escapeHTML(cert.certificate_name)}</h2>
    <p class="cert-recipient">${escapeHTML(cert.donor_name)}</p>

    <div class="cert-metrics-row">
      <div class="metric-group">
        <span class="metric-label">Period</span>
        <span class="metric-value">${escapeHTML(formatPeriod(cert.period_start, cert.period_end))}</span>
      </div>
      <div class="metric-group text-right">
        <span class="metric-label">Donations</span>
        <span class="metric-value highlight">${escapeHTML(String(cert.food_donated_count))}</span>
      </div>
    </div>

    <div class="cert-satisfaction">
      <div class="satisfaction-label">Receiver Satisfaction</div>
      <div class="star-rating" aria-label="Rating: ${rating} out of 5 stars">
        ${generateStarsHTML(rating)}
      </div>
      <div class="score-text">${rating.toFixed(1)} / 5.0</div>
    </div>

    <button class="btn-revoke" type="button" onclick="revokeCertificate(${cert.certificate_id})">Revoke Certificate</button>
  `;

  return article;
}

function escapeHTML(str) {
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML;
}

function setupDonorAutoFill() {
  const selectMenu = document.getElementById("certDonorSelect");
  if (!selectMenu) return;

  selectMenu.addEventListener("change", (e) => {
    const selectedOption = e.target.selectedOptions[0];
    if (!selectedOption) return;
    const donations = selectedOption.getAttribute("data-donations");
    if (donations !== null) {
      document.getElementById("certDonations").value = donations;
    }
  });
}

function setupModalEvents() {
  const modal = document.getElementById("certModal");
  const openBtn = document.getElementById("openModalBtn");
  const closeBtn = document.getElementById("closeModalBtn");

  if (openBtn && modal && closeBtn) {
    openBtn.addEventListener("click", () => modal.classList.add("active"));
    closeBtn.addEventListener("click", () => modal.classList.remove("active"));
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.remove("active");
    });
  }
}

async function handleFormSubmit(event) {
  event.preventDefault();

  const form = event.target;
  const submitBtn = form.querySelector(".btn-submit-action");
  const formData = new FormData(form);
  formData.append("ajax", "1");

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Saving...";
  }

  try {
    const response = await fetch("certificates.php", {
      method: "POST",
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      const container = document.getElementById("certificatesContainer");
      const noDataMsg = container.querySelector(".no-data");
      if (noDataMsg) noDataMsg.remove();

      const newCard = buildCertificateCard(data.certificate);
      container.prepend(newCard);

      form.reset();
      document.getElementById("certModal").classList.remove("active");
    } else {
      alert(data.message || "Something went wrong while saving the certificate.");
    }
  } catch (err) {
    console.error("Create certificate failed:", err);
    alert("Could not reach the server. Please try again.");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = "Generate & Create";
    }
  }
}

async function revokeCertificate(certificateId) {
  if (!confirm("Are you sure you want to revoke this certificate?")) return;

  const formData = new FormData();
  formData.append("action", "delete_certificate");
  formData.append("certificate_id", certificateId);
  formData.append("ajax", "1");

  try {
    const response = await fetch("certificates.php", {
      method: "POST",
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      const card = document.querySelector(`.certificate-card[data-cert-id="${data.certificate_id}"]`);
      if (card) card.remove();

      const container = document.getElementById("certificatesContainer");
      if (container && container.children.length === 0) {
        const emptyMsg = document.createElement("p");
        emptyMsg.className = "no-data";
        emptyMsg.textContent = "No certificates generated yet.";
        container.appendChild(emptyMsg);
      }
    } else {
      alert(data.message || "Could not revoke this certificate.");
    }
  } catch (err) {
    console.error("Revoke certificate failed:", err);
    alert("Could not reach the server. Please try again.");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  setupModalEvents();
  setupDonorAutoFill();

  const creationForm = document.getElementById("createCertificateForm");
  if (creationForm) {
    creationForm.addEventListener("submit", handleFormSubmit);
  }
});