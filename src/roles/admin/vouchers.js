// Context Elements Pointers
const gridContainer = document.getElementById("vouchersGridTarget");
const voucherModal = document.getElementById("voucherFormModal");
const voucherForm = document.getElementById("voucherForm");
const modalTitle = document.getElementById("modalFormTitle");

// Form Fields Pointers
const idField = document.getElementById("voucherIdField");
const partnerField = document.getElementById("partnerField");
const rewardField = document.getElementById("rewardField");
const codeField = document.getElementById("codeField"); // Promo code field
const dateField = document.getElementById("dateField");
const donationField = document.getElementById("donationField");

// Action Event Buttons Pointers
const openCreateModalBtn = document.getElementById("openCreateModalBtn");
const closeModalBtn = document.getElementById("closeModalBtn");
const cancelFormBtn = document.getElementById("cancelFormBtn");

/**
 * Fetch and render vouchers from MySQL database
 */
function renderVouchersWorkspace() {
  if (!gridContainer) return;

  fetch('vouchers_api.php?action=fetch_all')
    .then(res => res.json())
    .then(res => {
      if (res.status === 'success') {
        displayVouchers(res.data);
      } else {
        gridContainer.innerHTML = `<p style="color:#e53e3e; text-align:center;">Failed to load vouchers.</p>`;
      }
    })
    .catch(err => {
      console.error("Fetch Error:", err);
      gridContainer.innerHTML = `<p style="color:#e53e3e; text-align:center;">Database connection error.</p>`;
    });
}

/**
 * Renders the dashboard workspace cards array
 */
function displayVouchers(vouchers) {
  if (!vouchers || vouchers.length === 0) {
    gridContainer.style.display = "block";
    gridContainer.innerHTML = `
      <div style="background:#fff; border-radius:28px; padding:48px; text-align:center; border:1.5px solid rgba(28,43,30,0.05); max-width:600px; margin:0 auto;">
        <p style="font-family:var(--font-sans); color:#666; font-size:1.1rem; font-weight:500; margin:0;">No distribution vouchers cataloged. Create one above.</p>
      </div>
    `;
    return;
  }

  gridContainer.style.display = "grid";
  gridContainer.innerHTML = vouchers.map(v => {
    const avatarInitials = v.brand_name ? v.brand_name.substring(0, 2).toUpperCase() : "VC";
    
    // Format expiration date string
    const rawDate = v.expiration_date ? v.expiration_date.split(' ')[0] : '';
    const formattedDate = rawDate ? new Date(rawDate).toLocaleDateString('en-GB', {
      day: '2-digit', month: '2-digit', year: 'numeric'
    }) : 'N/A';

    const donationTarget = parseInt(v.required_donations, 10) || 0;

    return `
      <article class="admin-voucher-card" data-id="${v.voucher_id}">
        <div class="voucher-card-top">
          <div class="brand-initial-avatar">${avatarInitials}</div>
          <div class="ticket-node-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 12a2 2 0 0 0-2-2V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v4a2 2 0 0 0-2 2 2 2 0 0 0 2 2v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4a2 2 0 0 0 2-2z"></path>
            </svg>
          </div>
        </div>
        
        <div class="voucher-details-block">
          <h3>${escapeHtml(v.brand_name)}</h3>
          <p>${escapeHtml(v.reward_title)}</p>
          ${v.voucher_code ? `<div style="font-family: monospace; font-size: 0.85rem; font-weight: 700; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; width: fit-content; margin: 4px 0;">Code: ${escapeHtml(v.voucher_code)}</div>` : ''}
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
          <button class="mgmt-btn edit" onclick="initiateEditFlow(${v.voucher_id}, '${escapeQuotes(v.brand_name)}', '${escapeQuotes(v.reward_title)}', '${escapeQuotes(v.voucher_code || '')}', '${rawDate}', ${donationTarget})">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4Z"></path></svg>
            Edit
          </button>
          <button class="mgmt-btn delete" onclick="triggerDeleteOperation(${v.voucher_id})">
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
  if (!voucherModal) return;
  if (show) {
    voucherModal.classList.add("active");
  } else {
    voucherModal.classList.remove("active");
    voucherForm.reset();
    idField.value = "";
  }
}

// Attach initialization hooks to layout triggers
if (openCreateModalBtn) {
  openCreateModalBtn.addEventListener("click", () => {
    modalTitle.innerText = "Create New Voucher";
    toggleFormModal(true);
  });
}

if (closeModalBtn) closeModalBtn.addEventListener("click", () => toggleFormModal(false));
if (cancelFormBtn) cancelFormBtn.addEventListener("click", () => toggleFormModal(false));

/**
 * Edit Mode Routing Handlers
 */
window.initiateEditFlow = function(id, partner, reward, code, expiry, donation) {
  modalTitle.innerText = "Modify Voucher Parameter";
  idField.value = id;
  partnerField.value = partner;
  rewardField.value = reward;
  if (codeField) codeField.value = code;
  dateField.value = expiry;
  donationField.value = donation || 0;

  toggleFormModal(true);
};

/**
 * Delete Target Execution Handlers (Database DELETE)
 */
window.triggerDeleteOperation = function(id) {
  if (confirm("Are you certain you want to remove this voucher reward entry?")) {
    fetch('vouchers_api.php?action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ voucher_id: parseInt(id, 10) })
    })
    .then(res => res.json())
    .then(res => {
      if (res.status === 'success') {
        renderVouchersWorkspace();
      } else {
        alert(res.message || "Failed to delete voucher.");
      }
    })
    .catch(err => {
      console.error("Delete Error:", err);
      alert("Could not connect to database.");
    });
  }
};

/**
 * Form Submit Processing (Save / Create Interceptions to Database)
 */
voucherForm.addEventListener("submit", (e) => {
  e.preventDefault();

  const currentId = idField.value;
  const isEdit = currentId !== "";

  const payload = {
    voucher_id: isEdit ? parseInt(currentId, 10) : null,
    brand_name: partnerField.value.trim(),
    reward_title: rewardField.value.trim(),
    voucher_code: codeField ? codeField.value.trim() : "VOUCHER-" + Math.floor(Math.random() * 10000),
    expiration_date: dateField.value,
    required_donations: parseInt(donationField.value, 10) || 0
  };

  const targetApi = isEdit ? 'vouchers_api.php?action=update' : 'vouchers_api.php?action=create';

  fetch(targetApi, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    if (res.status === 'success') {
      toggleFormModal(false);
      renderVouchersWorkspace();
    } else {
      alert(res.message || "An error occurred while saving.");
    }
  })
  .catch(err => {
    console.error("Save Error:", err);
    alert("Failed to communicate with database server.");
  });
});

// Helper functions to escape HTML and quote characters inside string attributes
function escapeHtml(str) {
  return String(str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function escapeQuotes(str) {
  return String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Run layout tracking evaluations on load loop execution
document.addEventListener("DOMContentLoaded", renderVouchersWorkspace);