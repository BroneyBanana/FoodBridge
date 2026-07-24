// `notificationsData` will be injected via PHP

function renderNotifications() {
  const container = document.getElementById("notificationsContainer");
  if (!container) return;

  container.innerHTML = notificationsData.map(item => `
    <article class="notification-card ${item.highlight ? 'highlighted' : ''}" data-type="${item.type}">
      <div class="notif-icon-badge" aria-hidden="true">
        ${item.icon}
      </div>
      <div class="notif-body">
        <h2 class="notif-title">${item.title}</h2>
        <p class="notif-message">${item.message}</p>
      </div>
      <div class="notif-timestamp">${item.timeAgo}</div>
    </article>
  `).join("");
}

document.addEventListener("DOMContentLoaded", renderNotifications);