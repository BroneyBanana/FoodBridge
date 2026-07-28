function checkAndRenderNotifications() {
  const targetArea = document.getElementById("notificationsTarget");
  if (!targetArea) return;

  if (typeof notificationsList === 'undefined' || notificationsList.length === 0) {
    targetArea.innerHTML = `
      <div class="empty-notifications-card">
        <p>No new notifications.</p>
      </div>
    `;
  } else {
    targetArea.innerHTML = "";

    notificationsList.forEach(notif => {
      const card = document.createElement("div");
      card.className = "notification-card";

      card.innerHTML = `
        <div class="notification-header">
          <h3 class="notification-title">${notif.title}</h3>
          <span class="notification-time">${notif.time}</span>
        </div>
        <p class="notification-desc">${notif.description}</p>
      `;
      targetArea.appendChild(card);
    });
  }
}

document.addEventListener("DOMContentLoaded", checkAndRenderNotifications);
