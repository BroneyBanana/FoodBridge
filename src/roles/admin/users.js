let currentScore = 100;
let currentScoreElem = null;
let currentUserId = null;

function openScoreModal(btnElem, name, score, type, userId) {
  currentScore = score;
  currentUserId = userId;

  const row = btnElem.closest('tr');
  currentScoreElem = row.querySelector('.trust-score');

  document.getElementById('sm-title').textContent = 'Adjust score — ' + name + ' (' + type + ')';
  document.getElementById('sm-score').textContent = score;
  document.getElementById('sm-note').value = '';
  document.getElementById('score-modal-wrap').classList.add('show');
}

function adjScore(d) {
  currentScore = Math.max(0, Math.min(100, currentScore + d));
  document.getElementById('sm-score').textContent = currentScore;
}

function closeScoreModal() {
  document.getElementById('score-modal-wrap').classList.remove('show');
}

async function saveScoreAdj() {
  const note = document.getElementById('sm-note').value.trim();
  if (!note) {
    alert('Please add a reason.');
    return;
  }

  try {
    const response = await fetch('users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'adjust_score', user_id: currentUserId, score: currentScore, reason: note })
    });
    const result = await response.json();
    if (result.success) {
      if (currentScoreElem) {
        currentScoreElem.textContent = currentScore;
        if (currentScore >= 75) {
          currentScoreElem.className = 'trust-score score-high';
        } else if (currentScore >= 50) {
          currentScoreElem.className = 'trust-score score-medium';
        } else {
          currentScoreElem.className = 'trust-score score-low';
        }
      }
      closeScoreModal();
    } else {
      alert('Error updating score: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Failed to connect to server.');
  }
}

async function updateUserStatus(userId, action, btn) {
  try {
    const response = await fetch('users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: action, user_id: userId })
    });
    const result = await response.json();
    if (result.success) {
      const row = btn.closest('tr');
      const statusCell = row.querySelector('.status-cell');
      const actionsDiv = btn.closest('.action-buttons');

      if (action === 'warn') {
        statusCell.innerHTML = '<span class="badge badge-warn">WARNED</span>';
        actionsDiv.querySelector('.warn').style.display = 'none';
        actionsDiv.querySelector('.ban').style.display = 'none';
        actionsDiv.querySelector('.restore').style.display = 'inline-block';
      } else if (action === 'ban') {
        statusCell.innerHTML = '<span class="badge badge-ban">BANNED</span>';
        actionsDiv.querySelector('.warn').style.display = 'none';
        actionsDiv.querySelector('.ban').style.display = 'none';
        actionsDiv.querySelector('.restore').style.display = 'inline-block';
      } else if (action === 'restore') {
        statusCell.innerHTML = '<span class="badge badge-active">ACTIVE</span>';
        actionsDiv.querySelector('.warn').style.display = 'inline-block';
        actionsDiv.querySelector('.ban').style.display = 'inline-block';
        actionsDiv.querySelector('.restore').style.display = 'none';
      }
    } else {
      alert('Error updating user: ' + (result.error || 'Unknown error'));
    }
  } catch (error) {
    alert('Failed to connect to server.');
  }
}

function warnUser(btn, userId) {
  updateUserStatus(userId, 'warn', btn);
}

function banUser(btn, userId) {
  updateUserStatus(userId, 'ban', btn);
}

function restoreUser(btn, userId) {
  updateUserStatus(userId, 'restore', btn);
}
// ============ FILTER AND SEARCH ============
document.addEventListener('DOMContentLoaded', function () {
  const filterBtns = document.querySelectorAll('.filter-btn');
  const searchInput = document.querySelector('.search-box input');
  const tableBody = document.querySelector('.users-table tbody');

  // If no table body or no rows, exit
  if (!tableBody) return;

  let currentFilter = 'all';
  let searchQuery = '';

  // Get all rows that are not the "empty state" row
  function getFilterableRows() {
    return Array.from(tableBody.querySelectorAll('tr'))
      .filter(row => !row.querySelector('td[colspan]'));
  }

  function applyFilters() {
    const rows = getFilterableRows();
    rows.forEach(row => {
      // Use data attribute for role (we'll add it in PHP)
      const role = row.dataset.role || 'unknown';
      const nameElem = row.querySelector('.user-name');
      const name = nameElem ? nameElem.textContent.trim().toLowerCase() : '';

      const matchesSearch = name.includes(searchQuery);
      const matchesFilter = (currentFilter === 'all' || role === currentFilter);

      row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
    });
  }

  // Attach click events to filter buttons
  if (filterBtns.length) {
    filterBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        // Get filter value from button text (e.g. "Donor" → "donor")
        currentFilter = this.textContent.trim().toLowerCase();
        applyFilters();
      });
    });
  }

  // Attach input event to search box
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      searchQuery = this.value.trim().toLowerCase();
      applyFilters();
    });
  }

  // Initial filter (show all)
  applyFilters();
});