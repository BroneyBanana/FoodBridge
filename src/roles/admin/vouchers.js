// State Store: Seed array containing data configurations matching original screenshots
let platformVouchers = [
  { id: "v1", partner: "GrabFood", reward: "RM10 Off Delivery", expiry: "2026-12-31" },
  { id: "v2", partner: "Jaya Grocer", reward: "5% Off Bill", expiry: "2026-10-31" },
  { id: "v3", partner: "Tealive", reward: "Free Upsize", expiry: "2026-08-31" },
  { id: "v4", partner: "Foodpanda", reward: "RM5 Off", expiry: "2026-11-30" },
  { id: "v5", partner: "Bask Bear", reward: "10% Off Coffee", expiry: "2026-09-30" }
];

// Context Elements Pointers
const gridContainer = document.getElementById("vouchersGridTarget");
const voucherModal = document.getElementById("voucherFormModal");
const voucherForm = document.getElementById("voucherForm");
const modalTitle = document.getElementById("modalFormTitle");

// Form Fields Pointers
const idField = document.getElementById("voucherIdField");
const partnerField = document.getElementById("partnerField");
const rewardField = document.getElementById("rewardField");
const dateField = document.getElementById("dateField");

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
    // Slice clean two letter string blocks for branding badges
    const avatarInitials = v.partner ? v.partner.substring(0, 2).toUpperCase() : "VC";
    
    // Format presentation string values safely
    const formattedDate = new Date(v.expiry).toLocaleDateString('en-GB', {
      day: '2-digit', month: '2-digit', year: 'numeric'
    });

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
    expiry: dateField.value
  };

  if (currentId) {
    // Execution branch A: Update data values inline
    platformVouchers = platformVouchers.map(v => v.id === currentId ? dataPayload : v);
  } else {
    // Execution branch B: Add newly compiled structures to the list
    platformVouchers.unshift(dataPayload);
  }

  toggleFormModal(false);
  renderVouchersWorkspace();
});

// Run layout tracking evaluations on load loop execution
document.addEventListener("DOMContentLoaded", renderVouchersWorkspace);