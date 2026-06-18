let resizeTimer;

window.addEventListener('resize', () => {
  // 1. Add the stopper class while the window is being dragged
  document.body.classList.add('resize-animation-stopper');
  
  // 2. Clear the timer if the user is still dragging
  clearTimeout(resizeTimer);
  
  // 3. Remove the class 400ms after they let go of the mouse
  resizeTimer = setTimeout(() => {
    document.body.classList.remove('resize-animation-stopper');
  }, 400); 
});

const hamburgerBtn = document.getElementById('hamburgerBtn');
const navOverlay = document.getElementById('navOverlay');

// Open the menu when hamburger is clicked
hamburgerBtn.addEventListener('click', () => {
navOverlay.classList.toggle('open');
});

// Close the menu if the user clicks anywhere on the dark transparent overlay
navOverlay.addEventListener('click', (e) => {
if (e.target === navOverlay) {
    navOverlay.classList.remove('open');
}
});

// Dynamic Header Initials synchronization
document.addEventListener('DOMContentLoaded', () => {
  const updateHeaderAvatar = () => {
    const isPageAdmin = window.location.pathname.includes('/admin/');
    const isPageDonor = window.location.pathname.includes('/donor/');
    const isPageReceiver = window.location.pathname.includes('/receiver/');

    let name = '';
    
    if (isPageAdmin) {
      const adminProfile = JSON.parse(localStorage.getItem('foodbridgeAdminProfile') || '{}');
      name = adminProfile.name || 'Daniel Ong';
    } else if (isPageDonor || isPageReceiver) {
      const userProfile = JSON.parse(localStorage.getItem('foodbridgeProfile') || '{}');
      name = userProfile.name || 'Your Name';
    }

    if (name) {
      const initials = name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part[0])
        .join('')
        .toUpperCase();
      
      let avatarImage = '';
      if (isPageAdmin) {
        const adminProfile = JSON.parse(localStorage.getItem('foodbridgeAdminProfile') || '{}');
        avatarImage = adminProfile.avatarImage || '';
      } else if (isPageDonor || isPageReceiver) {
        const userProfile = JSON.parse(localStorage.getItem('foodbridgeProfile') || '{}');
        avatarImage = userProfile.avatarImage || '';
      }

      const avatars = document.querySelectorAll('.profile-avatar');
      avatars.forEach(avatar => {
        if (avatarImage) {
          avatar.innerHTML = `<img src="${avatarImage}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;" />`;
        } else {
          avatar.textContent = initials || 'FB';
        }
      });
    }
  };

  updateHeaderAvatar();
  
  // Expose it globally so we can trigger it from profile pages after saving changes
  window.updateHeaderAvatar = updateHeaderAvatar;
});