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
});