// State Store: Seed array updated with requiredDonation constraints
let platformVouchers = [
  { id: "v1", partner: "GrabFood", reward: "RM10 Off Delivery", expiry: "2026-12-31", requiredDonation: 5 },
  { id: "v2", partner: "Jaya Grocer", reward: "5% Off Bill", expiry: "2026-10-31", requiredDonation: 2 },
  { id: "v3", partner: "Tealive", reward: "Free Upsize", expiry: "2026-08-31", requiredDonation: 0 }, // 0 means unlocked by default
  { id: "v4", partner: "Foodpanda", reward: "RM5 Off", expiry: "2026-11-30", requiredDonation: 10 },
  { id: "v5", partner: "Bask Bear", reward: "10% Off Coffee", expiry: "2026-09-30", requiredDonation: 1 }
];

// Context Elements Pointers
const gridContainer = document.getElementById("vouchersGridTarget");
const voucherModal = document.getElementById("voucherFormModal");
const voucherForm = document.getElementById("voucherForm");
const modalTitle = document.getElementById("modalFormTitle");

// Form Fields Pointers (Added donationField)
const idField = document.getElementById("voucherIdField");
const partnerField = document.getElementById("partnerField");
const rewardField = document.getElementById("rewardField");
const dateField = document.getElementById("dateField");
const donationField = document.getElementById("donationField"); // <-- NEW

// Action Event Buttons Pointers
const openCreateModalBtn = document.getElementById("openCreateModalBtn");
const closeModalBtn = document.getElementById("closeModalBtn");
const cancelFormBtn = document.getElementById("cancelFormBtn");

/**
 * Renders the dashboard workspace cards array 
 */
function renderVouchersWorkspace() {
  if (!gridContainer) return;
  
  if (platformVouchers.length === 0) {
    gridContainer.style.display = "block";
    gridContainer.innerHTML = `
      <div style="background:#fff; border-radius:28px; padding:48px; text-align:center; border:1.5px solid rgba(28,43,30,0.05); max-width:600px; margin:0 auto;">
        <p style="font-family:var(--font-sans); color:#666; font-size:1.1rem; font-weight:500; margin:0;">No distribution vouchers cataloged. Create one above.</p>
      </div>
    `;
    return;
  }

  gridContainer.style.display = "grid";
  gridContainer.innerHTML = platformVouchers.map(v => {
    const avatarInitials = v.partner ? v.partner.substring(0, 2).toUpperCase() : "VC";
    
    const formattedDate = new Date(v.expiry).toLocaleDateString('en-GB', {
      day: '2-digit', month: '2-digit', year: 'numeric'
    });

    // Safeguard missing requiredDonation targets safely
    const donationTarget = v.requiredDonation || 0;

    return `
      <article class="admin-voucher-card" data-id="${v.id}">
        <div class="voucher-card-top">
          <div class="brand-initial-avatar">${avatarInitials}</div>
          <div class="ticket-node-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 12a2 2 0 0 0-2-2V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v4a2 2 0 0 0-2 2 2 2 0 0 0 2 2v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4a2 2 0 0 0 2-2z"></path>
            </svg>
          </div>
        </div>
        
        <div class="voucher-details-block">
          <h3>${v.partner}</h3>
          <p>${v.reward}</p>
          <div class="voucher-validity-date">Valid Until ${formattedDate}</div>
          
          <div class="voucher-lock-threshold" style="margin-top: 10px; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; color: ${donationTarget > 0 ? '#e65c00' : '#2e7d32'}; font-weight: 600;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              ${donationTarget > 0 
                ? '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>' 
                : '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path>'
              }
            </svg>
            <span>${donationTarget > 0 ? `Requires ${donationTarget} Pax Donation` : 'Unlocked for All'}</span>
          </div>
        </div>

        <div class="voucher-management-actions">
          <button class="mgmt-btn edit" onclick="initiateEditFlow('${v.id}')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4Z"></path></svg>
            Edit
          </button>
          <button class="mgmt-btn delete" onclick="triggerDeleteOperation('${v.id}')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Delete
          </button>
        </div>
      </article>
    `;
  }).join("");
}

/**
 * Modal visibility control loops
 */
function toggleFormModal(show = false) {
  if (show) {
    voucherModal.classList.add("active");
  } else {
    voucherModal.classList.remove("active");
    voucherForm.reset();
    idField.value = "";
  }
}

// Attach initialization hooks to layout triggers
openCreateModalBtn.addEventListener("click", () => {
  modalTitle.innerText = "Create New Voucher";
  toggleFormModal(true);
});

closeModalBtn.addEventListener("click", () => toggleFormModal(false));
cancelFormBtn.addEventListener("click", () => toggleFormModal(false));

/**
 * Edit Mode Routing Handlers
 */
window.initiateEditFlow = function(id) {
  const targetVoucher = platformVouchers.find(v => v.id === id);
  if (!targetVoucher) return;

  modalTitle.innerText = "Modify Voucher Parameter";
  idField.value = targetVoucher.id;
  partnerField.value = targetVoucher.partner;
  rewardField.value = targetVoucher.reward;
  dateField.value = targetVoucher.expiry;
  donationField.value = targetVoucher.requiredDonation || 0; // <-- NEW: Hydrates the edit field

  toggleFormModal(true);
};

/**
 * Delete Target Execution Handlers
 */
window.triggerDeleteOperation = function(id) {
  if (confirm("Are you certain you want to remove this voucher reward entry?")) {
    platformVouchers = platformVouchers.filter(v => v.id !== id);
    renderVouchersWorkspace();
  }
};

/**
 * Form Submit Processing (Save / Create Interceptions)
 */
voucherForm.addEventListener("submit", (e) => {
  e.preventDefault();

  const currentId = idField.value;
  const dataPayload = {
    id: currentId || "v_" + Date.now(),
    partner: partnerField.value.trim(),
    reward: rewardField.value.trim(),
    expiry: dateField.value,
    requiredDonation: parseInt(donationField.value, 10) || 0 // <-- NEW: Saves target value as an integer
  };

  if (currentId) {
    platformVouchers = platformVouchers.map(v => v.id === currentId ? dataPayload : v);
  } else {
    platformVouchers.unshift(dataPayload);
  }

  toggleFormModal(false);
  renderVouchersWorkspace();
});

// Run layout tracking evaluations on load loop execution
document.addEventListener("DOMContentLoaded", renderVouchersWorkspace);