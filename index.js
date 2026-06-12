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


  sigma balsdfgbheqdsfghn