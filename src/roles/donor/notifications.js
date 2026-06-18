const notificationsData = [
  {
    type: "action",
    title: "Action Required: Food expiring soon",
    message: "Your listed Assorted Breads & Pastries will expire in 3 hours.",
    timeAgo: "10 mins ago",
    highlight: true,
    icon: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>`
  },
  {
    type: "completed",
    title: "Donation Completed",
    message: "Fresh Bakery KL successfully collected your 20kg vegetables.",
    timeAgo: "2 hours ago",
    highlight: true,
    icon: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`
  },
  {
    type: "voucher",
    title: "New Voucher Unlocked",
    message: "You reached Trust Score 98! A new GrabFood voucher is waiting for you.",
    timeAgo: "1 day ago",
    highlight: false,
    icon: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`
  },
  {
    type: "update",
    title: "Community Update",
    message: "We rescued over 1,500kg of food this week thanks to heroes like you!",
    timeAgo: "3 days ago",
    highlight: false,
    icon: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`
  }
];

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