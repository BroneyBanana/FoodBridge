let selectedVoucherId = null;
let selectedVoucherTitle = '';
let currentVoucherCode = '';

// Attach Click Handlers to Redeem Buttons
document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.voucher-card');

  cards.forEach(card => {
    const btn = card.querySelector('.redeem-btn');
    if (!btn) return;

    btn.addEventListener('click', () => {
      if (card.classList.contains('is-locked') || card.classList.contains('is-redeemed')) {
        return;
      }

      selectedVoucherId = card.getAttribute('data-id');
      selectedVoucherTitle = card.getAttribute('data-title');

      document.getElementById('confirmationVoucherTitle').innerText = selectedVoucherTitle;
      openModal('confirmationRedeemModal');
    });
  });
});

// Perform Voucher Redemption AJAX
function redeemVoucher() {
  if (!selectedVoucherId) return;

  closeModal('confirmationRedeemModal');

  fetch('redeemVoucher.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ voucher_id: parseInt(selectedVoucherId) })
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'success') {
      currentVoucherCode = data.voucher_code || 'N/A';

      document.getElementById('successVoucherTitle').innerText = selectedVoucherTitle;
      document.getElementById('displayVoucherCode').innerText = currentVoucherCode;

      openModal('successRedeemModal');
    } else {
      document.getElementById('errorVoucherTitle').innerText = selectedVoucherTitle;
      openModal('errorRedeemModal');
    }
  })
  .catch(error => {
    console.error('Redemption error:', error);
    document.getElementById('errorVoucherTitle').innerText = selectedVoucherTitle;
    openModal('errorRedeemModal');
  });
}

// Copy Voucher Code to Clipboard
function copyVoucherCode() {
  if (!currentVoucherCode || currentVoucherCode === 'N/A') return;

  navigator.clipboard.writeText(currentVoucherCode).then(() => {
    const copyBtn = document.getElementById('copyCodeBtn');
    copyBtn.innerText = 'Copied!';
    setTimeout(() => {
      copyBtn.innerText = 'Copy Code';
    }, 2000);
  });
}

// Modal Helpers
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('active');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('active');
}

function closeConfirmationModal() {
  closeModal('confirmationRedeemModal');
}

function closeSuccessModal() {
  closeModal('successRedeemModal');
  location.reload(); // Refresh page to display "REDEEMED" badge on card
}

function closeErrorModal() {
  closeModal('errorRedeemModal');
}