const notificationsList = [];

function checkAndRenderNotifications() {
  const targetArea = document.getElementById("notificationsTarget");
  if (!targetArea) return;

  if (notificationsList.length === 0) {
    targetArea.innerHTML = `
      <div class="empty-notifications-card">
        <p>No new notifications.</p>
      </div>
    `;
  } else {
    targetArea.innerHTML = "";
  }
}

document.addEventListener("DOMContentLoaded", checkAndRenderNotifications);
