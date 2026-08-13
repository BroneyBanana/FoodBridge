function formatPeriod(startStr, endStr) {
  if (!startStr || !endStr) return "N/A";
  
  const start = new Date(startStr.replace(" ", "T"));
  const end = new Date(endStr.replace(" ", "T"));

  if (isNaN(start.getTime()) || isNaN(end.getTime())) {
    return "Invalid Period";
  }

  const opts = { month: "short", year: "numeric" };
  return `${start.toLocaleDateString("en-US", opts)} - ${end.toLocaleDateString("en-US", opts)}`;
}

function satisfactionToRating(rate) {
  switch (rate) {
    case "Excellent": return 5.0;
    case "Good": return 4.0;
    case "Average": return 3.0;
    case "Poor": return 2.0;
    default: return typeof rate === "number" ? rate : 0.0;
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

function escapeHTML(str) {
  if (str === null || str === undefined) return "";
  const div = document.createElement("div");
  div.textContent = String(str);
  return div.innerHTML;
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
        <span class="metric-value highlight">${escapeHTML(String(cert.food_donated_count ?? 0))}</span>
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

function setupAutoCalculation() {
  const donorSelect   = document.getElementById("certDonorSelect");
  const startDateInput = document.getElementById("periodStart");
  const endDateInput   = document.getElementById("periodEnd");
  const donationsInput = document.getElementById("certDonations");
  const ratingSelect   = document.getElementById("certRating");

  async function calculate() {
    const donorId   = donorSelect?.value?.trim() || "";
    const startDate = startDateInput?.value?.trim() || "";   
    const endDate   = endDateInput?.value?.trim() || "";

    // Clear fields when any required value is missing
    if (!donorId || !startDate || !endDate) {
      if (donationsInput) donationsInput.value = "";
      if (ratingSelect)   ratingSelect.value = "Good";
      return;
    }

    if (startDate > endDate) {
      alert("Start Date cannot be after End Date.");
      return;
    }

    if (donationsInput) {
      donationsInput.value = "";
      donationsInput.placeholder = "Calculating...";
    }

    try {
      const url = `certificates.php?action=calculate_metrics`
                + `&donor_id=${encodeURIComponent(donorId)}`
                + `&period_start=${encodeURIComponent(startDate)}`
                + `&period_end=${encodeURIComponent(endDate)}`
                + `&ajax=1`;

      const response = await fetch(url);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();

      if (data.success) {
        if (donationsInput) {
          donationsInput.value = data.food_donated_count ?? 0;
        }
        if (ratingSelect) {
          // PHP returns "Excellent" / "Good" / "Average" / "Poor"
          ratingSelect.value = data.receiver_satisfaction_rate || "Good";
        }
      } else {
        console.error("Calculation failed:", data.message);
        if (donationsInput) donationsInput.value = 0;
      }
    } catch (err) {
      console.error("Failed to fetch calculation:", err);
      if (donationsInput) donationsInput.value = 0;
    } finally {
      if (donationsInput) donationsInput.placeholder = "0";
    }
  }

  if (donorSelect)   donorSelect.addEventListener("change", calculate);
  if (startDateInput) {
    startDateInput.addEventListener("change", calculate);
    startDateInput.addEventListener("blur", calculate);
  }
  if (endDateInput) {
    endDateInput.addEventListener("change", calculate);
    endDateInput.addEventListener("blur", calculate);
  }
}

function setupModalEvents() {
  const modal   = document.getElementById("certModal");
  const openBtn = document.getElementById("openModalBtn");
  const closeBtn = document.getElementById("closeModalBtn");

  if (openBtn && modal) {
    openBtn.addEventListener("click", () => modal.classList.add("active"));
  }
  if (closeBtn && modal) {
    closeBtn.addEventListener("click", () => modal.classList.remove("active"));
  }
  if (modal) {
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

    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (data.success) {
      const container = document.getElementById("certificatesContainer");
      if (container) {
        const noDataMsg = container.querySelector(".no-data");
        if (noDataMsg) noDataMsg.remove();

        const newCard = buildCertificateCard(data.certificate);
        container.prepend(newCard);
      }

      form.reset();
      const modal = document.getElementById("certModal");
      if (modal) modal.classList.remove("active");
    } else {
      alert(data.message || "Something went wrong while saving the certificate.");
    }
  } catch (err) {
    console.error("Create certificate failed:", err);
    alert("Could not reach the server or parse the response. Please check server logs.");
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

    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
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
    alert("Could not reach the server or process the revocation. Please try again.");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  setupModalEvents();
  setupAutoCalculation();

  const creationForm = document.getElementById("createCertificateForm");
  if (creationForm) {
    creationForm.addEventListener("submit", handleFormSubmit);
  }
});