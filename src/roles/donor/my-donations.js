// ============================================================
// 1. SEGMENT SWITCH TOGGLER & COMPONENT VISIBILITY MANAGER LOOP
// ============================================================
const buttons = document.querySelectorAll('.donation-filter .filter-button');
const cards = document.querySelectorAll('.my-donations .donations-card');

buttons.forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelector('.donation-filter .filter-button.active').classList.remove('active');
    this.classList.add('active');

    const filterCondition = this.textContent.trim().toLowerCase();

    cards.forEach(card => {
      const itemStatus = card.getAttribute('data-status').toLowerCase();

      if (filterCondition === 'all') {
        card.style.display = '';
      } else if (itemStatus === filterCondition) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
});

// ============================================================
// 2. MULTI-STEP SLIDER CAROUSEL NAVIGATION ENGINE CONTROLLER
// ============================================================
const openButtons = document.querySelectorAll('.tutorial-packup');
const tutorial = document.getElementById('tutorial');

const tutorialTextParent = document.querySelector('.tutorial-text');
const imageSlides = document.querySelectorAll('.image-step-slide');
const textSlides = document.querySelectorAll('.text-step-slide');

let currentStepIndex = 0;
const totalSteps = textSlides.length;

// NO ANIMATIONS: Switches steps instantly
function renderCurrentStep() {
  // Find the currently active elements on screen
  const activeImage = document.querySelector('.image-step-slide.active');
  const activeText = document.querySelector('.text-step-slide.active');

  // Deactivate the old slides instantly
  if (activeImage) activeImage.classList.remove('active');
  if (activeText) activeText.classList.remove('active');

  // Find the new HTML elements based on our current index tracker
  const newImage = document.querySelector(`.image-step-slide[data-step="${currentStepIndex}"]`);
  const newText = document.querySelector(`.text-step-slide[data-step="${currentStepIndex}"]`);

  // Activate the new slides instantly
  if (newImage) newImage.classList.add('active');
  if (newText) newText.classList.add('active');
}

// CAPTURE ALL CLICKS ON BUTTONS DYNAMICALLY
tutorialTextParent.addEventListener('click', (e) => {
  if (e.target.closest('.next-button')) {
    if (currentStepIndex < totalSteps - 1) {
      currentStepIndex++;
      renderCurrentStep();
    } else {
      tutorial.classList.remove('open');
    }
  }

  if (e.target.closest('.previous-button')) {
    if (currentStepIndex > 0) {
      currentStepIndex--;
      renderCurrentStep();
    }
  }
});

// Interactive event triggers to open modal
openButtons.forEach(button => {
  button.addEventListener('click', () => {
    currentStepIndex = 0;
    
    // Reset all slides to be inactive, except the first one (idx === 0)
    imageSlides.forEach((slide, idx) => {
      slide.classList.toggle('active', idx === 0);
    });
    textSlides.forEach((slide, idx) => {
      slide.classList.toggle('active', idx === 0);
    });

    tutorial.classList.add('open');
  });
});

// Backdrop shroud overlay outside click dismiss gatekeeper
tutorial.addEventListener('click', (e) => {
  if (e.target === tutorial) {
    tutorial.classList.remove('open');
  }
});

// ── Show QR Modal Logic ──
const showQrModal    = document.getElementById('show-qr-modal');
const btnShowQrList  = document.querySelectorAll('.show-qr');
const btnCloseShowQr = document.getElementById('show-qr-close-btn');

function openShowQR() {
  showQrModal.classList.remove('hidden');
}

function closeShowQR() {
  showQrModal.classList.add('hidden');
}

// Attach event listener to all "Show QR Code" buttons on the page
btnShowQrList.forEach(btn => {
  btn.addEventListener('click', openShowQR);
});

if (btnCloseShowQr) {
  btnCloseShowQr.addEventListener('click', closeShowQR);
}

// Close on outside click
showQrModal.addEventListener('click', e => { 
  if (e.target === showQrModal) closeShowQR(); 
});

// Close on Escape key
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !showQrModal.classList.contains('hidden')) {
    closeShowQR();
  }
});

// ============================================================
// 3. AUTOMATICALLY CLOSE SUCCESS MESSAGE AFTER 5 SECONDS
// ============================================================
const success = document.getElementById('success');
const closeBtn = document.getElementById('closeBtn');

if (success) {
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      success.remove();
    });
  }

  setTimeout(() => {
    success.remove();
  }, 4000);
}