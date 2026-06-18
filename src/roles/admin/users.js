let currentScore = 100;
let currentScoreElem = null; // To update the table row score

function openScoreModal(btnElem, name, score, type) {
  currentScore = score;
  
  // Find the score element in the table to update it later
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

function saveScoreAdj() {
  const note = document.getElementById('sm-note').value.trim();
  if (!note) { 
    alert('Please add a reason.'); 
    return; 
  }
  
  if (currentScoreElem) {
    currentScoreElem.textContent = currentScore;
    // Update color based on score
    if (currentScore >= 75) {
      currentScoreElem.className = 'trust-score score-high';
    } else if (currentScore >= 50) {
      currentScoreElem.className = 'trust-score score-medium';
    } else {
      currentScoreElem.className = 'trust-score score-low';
    }
  }

  closeScoreModal();
}

function warnUser(btn) {
  const row = btn.closest('tr');
  const statusCell = row.querySelector('.status-cell');
  statusCell.innerHTML = '<span class="badge badge-warn">WARN</span>';
  
  const actionsDiv = btn.closest('.action-buttons');
  actionsDiv.querySelector('.warn').style.display = 'none';
  actionsDiv.querySelector('.ban').style.display = 'none';
  actionsDiv.querySelector('.restore').style.display = 'inline-block';
}

function banUser(btn) {
  const row = btn.closest('tr');
  const statusCell = row.querySelector('.status-cell');
  statusCell.innerHTML = '<span class="badge badge-ban">BANNED</span>';
  
  const actionsDiv = btn.closest('.action-buttons');
  actionsDiv.querySelector('.warn').style.display = 'none';
  actionsDiv.querySelector('.ban').style.display = 'none';
  actionsDiv.querySelector('.restore').style.display = 'inline-block';
}

function restoreUser(btn) {
  const row = btn.closest('tr');
  const statusCell = row.querySelector('.status-cell');
  statusCell.innerHTML = '<span class="badge badge-active">ACTIVE</span>';
  
  const actionsDiv = btn.closest('.action-buttons');
  actionsDiv.querySelector('.warn').style.display = 'inline-block';
  actionsDiv.querySelector('.ban').style.display = 'inline-block';
  actionsDiv.querySelector('.restore').style.display = 'none';
}
