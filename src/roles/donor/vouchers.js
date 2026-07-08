// Simulated user donation progress (e.g., user has donated 3 packs of food so far)
let userDonationProgress = 3;

const vouchersDataset = [
  {
    id: "vouch-01",
    initials: "GR",
    brand: "GrabFood",
    reward: "RM10 Off Delivery",
    expiry: "VALID UNTIL 31/12/2026",
    code: "GRAB10FOOD",
    isRedeemed: false,
    requiredDonation: 5 // Needs 5 packs to unlock
  },
  {
    id: "vouch-02",
    initials: "JA",
    brand: "Jaya Grocer",
    reward: "5% Off Bill",
    expiry: "VALID UNTIL 31/10/2026",
    code: "JAYAGR5OFF",
    isRedeemed: false,
    requiredDonation: 2 // Unlocked! (3 >= 2)
  },
  {
    id: "vouch-03",
    initials: "TE",
    brand: "Tealive",
    reward: "Free Upsize",
    expiry: "VALID UNTIL 31/08/2026",
    code: "TEALIVEUP",
    isRedeemed: true,
    requiredDonation: 0 // Unlocked by default
  },
  {
    id: "vouch-04",
    initials: "FO",
    brand: "Foodpanda",
    reward: "RM5 Off",
    expiry: "VALID UNTIL 30/11/2026",
    code: "PANDA5RM",
    isRedeemed: false,
    requiredDonation: 10 // Needs 10 packs to unlock
  },
  {
    id: "vouch-05",
    initials: "BA",
    brand: "Bask Bear",
    reward: "10% Off Coffee",
    expiry: "VALID UNTIL 30/09/2026",
    code: "BASKBEAR10",
    isRedeemed: true,
    requiredDonation: 1 // Unlocked! (3 >= 1)
  }
];

function renderVouchersGrid() {
  const targetGrid = document.getElementById("vouchersGrid");
  if (!targetGrid) return;

  targetGrid.innerHTML = vouchersDataset.map(item => {
    // 1. Check if the voucher is locked
    const isLocked = userDonationProgress < item.requiredDonation;
    const remainingPacks = item.requiredDonation - userDonationProgress;

    // 2. Set conditional classes
    let modifierClass = "";
    if (item.isRedeemed) modifierClass = "is-redeemed";
    if (isLocked) modifierClass = "is-locked";

    return `
      <article class="voucher-card ${modifierClass}">
        
        ${item.isRedeemed ? `<div class="redeemed-overlay-badge">Redeemed</div>` : ''}
        ${isLocked ? `<div class="locked-overlay-badge">Donate ${remainingPacks} more pax of food to unlock</div>` : ''}

        <div class="voucher-card-header">
          <div class="voucher-avatar">${item.initials}</div>
          <div class="ticket-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
              <path d="M13 5v2"/>
              <path d="M13 17v2"/>
              <path d="M13 11v2"/>
            </svg>
          </div>
        </div>

        <div class="voucher-details">
          <span class="partner-brand">${item.brand}</span>
          <h2 class="voucher-reward-title">${item.reward}</h2>
          <span class="expiry-stamp">${item.expiry}</span>
        </div>

        <button 
          class="btn-copy-code" 
          type="button" 
          onclick="copyVoucherCode('${item.code}', this)"
          ${isLocked ? 'disabled' : ''}
        >
          <svg class="copy-svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
            <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
          </svg>
          <span>${isLocked ? 'Locked' : 'Redeem'}</span>
        </button>

      </article>
    `;
  }).join("");
}

// Clipboard Logic Interaction
function copyVoucherCode(codeText, elementButton) {
  navigator.clipboard.writeText(codeText).then(() => {
    const textSpan = elementButton.querySelector("span");
    const nativeOriginalText = textSpan.textContent;
    
    textSpan.textContent = "Copied!";
    elementButton.style.background = "var(--color-lime)";
    elementButton.style.color = "var(--color-forest)";

    setTimeout(() => {
      textSpan.textContent = nativeOriginalText;
      elementButton.style.background = "";
      elementButton.style.color = "";
    }, 1800);
  }).catch(err => {
    console.error("Failed to redeem voucher: ", err);
  });
}

document.addEventListener("DOMContentLoaded", renderVouchersGrid);