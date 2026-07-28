
document.addEventListener('DOMContentLoaded', () => {
  const dateBadge = document.getElementById('todayDate');

  if (dateBadge) {
    const today = new Date();
    dateBadge.textContent = today.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    });
  }

  const clickableCards = document.querySelectorAll('.pickup-card');

  clickableCards.forEach((card) => {
    const goToPage = () => {
      const target = card.dataset.href;

      if (target) {
        window.location.href = target;
      }
    };

    card.addEventListener('click', (event) => {
      if (event.target.closest('.btn')) {
        return;
      }

      goToPage();
    });

    card.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        goToPage();
      }
    });
  });
});
