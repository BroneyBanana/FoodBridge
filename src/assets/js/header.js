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