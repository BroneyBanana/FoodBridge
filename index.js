const landingHamburger = document.getElementById('landingHamburger');
  const navMenuWrapper = document.getElementById('navMenuWrapper');
  const iconMenu = landingHamburger.querySelector('.icon-menu');
  const iconClose = landingHamburger.querySelector('.icon-close');

  landingHamburger.addEventListener('click', () => {
    // Toggle the open class
    const isOpen = navMenuWrapper.classList.toggle('open');
    
    // Swap the icons based on the state
    if (isOpen) {
      iconMenu.style.display = 'none';
      iconClose.style.display = 'block';
    } else {
      iconMenu.style.display = 'block';
      iconClose.style.display = 'none';
    }
  });

document.querySelector('a[href="index.html#community-impact"]').addEventListener('click', function(e) {
  e.preventDefault();
  document.querySelector('#community-impact').scrollIntoView({
    behavior: 'smooth'
  });
});

// Smooth scroll for View Impact button
document.querySelector('a[href="#community-impact"]').addEventListener('click', function(e) {
  e.preventDefault();
  document.querySelector('#community-impact').scrollIntoView({
    behavior: 'smooth'
  });
});

// Smooth scroll for How It Works navbar link
document.querySelector('a[href="index.html#how-it-works"]').addEventListener('click', function(e) {
  e.preventDefault();
  document.querySelector('#how-it-works').scrollIntoView({
    behavior: 'smooth'
  });
});
