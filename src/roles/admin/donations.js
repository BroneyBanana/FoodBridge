const buttons = document.querySelectorAll('.filter-buttons button');
// FIX: Grab rows inside the correct class, skipping the header row
const tableRows = document.querySelectorAll('.donation-table tr:not(:first-child)');

buttons.forEach(btn => {
  btn.addEventListener('click', function() {
    // 1. Manage active button visual states
    document.querySelector('.filter-buttons button.active-button').classList.remove('active-button');
    this.classList.add('active-button');

    // 2. Get the filter value (e.g., "all", "active", "completed")
    const filterValue = this.textContent.trim().toLowerCase();

    // 3. Loop through every table row and decide to show or hide it
    tableRows.forEach(row => {
      // Find the text inside the status badge for this specific row
      const badgeElement = row.querySelector('.status-badge');
      
      if (!badgeElement) return; // Skip if row doesn't have a badge
      
      const rowStatus = badgeElement.textContent.trim().toLowerCase();

      // Check filtering conditions
      if (filterValue === 'all') {
        row.style.display = ''; // Shows the row normally
      } else if (rowStatus === filterValue) {
        row.style.display = ''; // Shows the row if it matches exactly
      } else {
        row.style.display = 'none'; // Hides the row completely
      }
    });
  });
});