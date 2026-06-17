const reports = [
  {id:1, from:'Siti Lailah', fromType:'receiver', against:'Nurul Rashid', againstType:'donor', issue:'Spoiled or unsafe food', time:'2h ago', body:'Food collected was visibly spoiled — rice had mould and bread was stale. Packaging was intact but smell was bad.', status:'pending', deduction:null, note:''},
  {id:2, from:'Kamarul Musa', fromType:'receiver', against:'Farid Zulkifli', againstType:'donor', issue:'Fake or inaccurate listing', time:'1d ago', body:'Listed "20kg fresh vegetables" but actual collection was barely 5kg of wilted greens. Clear mismatch between listing and reality.', status:'pending', deduction:null, note:''},
  {id:3, from:'Adam Tan', fromType:'receiver', against:'Siti Bakery', againstType:'donor', issue:'No-show', time:'5h ago', body:'Donor was not at the location during the collection window and did not answer calls.', status:'pending', deduction:null, note:''},
  {id:4, from:'Ahmad Hafiz', fromType:'receiver', against:'Kamarul Musa', againstType:'donor', issue:'Rude or aggressive behaviour', time:'3d ago', body:'Donor was rude and dismissive during pickup, making us wait 45 minutes.', status:'pending', deduction:null, note:''}
];

let currentDeductions = {};
let currentFilter = 'all';

function filterReports(f, el) {
  currentFilter = f;
  document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  renderReports();
}

function renderReports() {
  const list = document.getElementById('reports-list');
  let filtered = reports;
  if(currentFilter === 'donor') filtered = reports.filter(r => r.againstType === 'donor');
  else if(currentFilter === 'receiver') filtered = reports.filter(r => r.againstType === 'receiver');

  const pending = filtered.filter(r => r.status === 'pending');
  const history = filtered.filter(r => r.status !== 'pending');

  let html = '';

  if(pending.length > 0) {
    html += `<div class="section-label" style="font-family: var(--font-display); font-size: 24px; color: var(--color-forest); border-bottom: 2px solid var(--color-border); padding-bottom: 8px; margin-top: 24px; text-transform: none; letter-spacing: normal;">Pending Reports</div>`;
    pending.forEach(r => { html += renderReport(r); });
  }

  if(history.length > 0) {
    html += `<div class="section-label" style="font-family: var(--font-display); font-size: 24px; color: var(--color-forest); border-bottom: 2px solid var(--color-border); padding-bottom: 8px; margin-top: 48px; text-transform: none; letter-spacing: normal;">Report History</div>`;
    history.forEach(r => { html += renderReport(r); });
  }

  if(pending.length === 0 && history.length === 0) {
    html = `<div style="text-align:center;padding:2rem;font-size:14px;color:var(--color-text-muted)">No reports in this view.</div>`;
  }
  
  list.innerHTML = html;
}

function renderReport(r) {
  const suggested = 0;
  const cur = currentDeductions[r.id] !== undefined ? currentDeductions[r.id] : suggested;

  let statusBadge = '';
  if(r.status === 'resolved') statusBadge = `<span class="badge bs-res">Resolved</span>`;
  else if(r.status === 'dismissed') statusBadge = `<span class="badge bs-dis">Dismissed</span>`;

  const fromBadge = r.fromType === 'receiver' ? `<span class="badge br">Receiver</span>` : `<span class="badge bd">Donor</span>`;
  const againstBadge = r.againstType === 'donor' ? `<span class="badge bd">Donor</span>` : `<span class="badge br">Receiver</span>`;

  let actionArea = '';
  if(r.status === 'pending') {
    const deductArea = `
      <div class="verdict-panel" id="vp-${r.id}" style="display:none">
        <div class="vp-title">Set score adjustment for ${r.against}</div>
        <div class="adj-row">
          <span style="font-size:13px;color:var(--color-text-muted);font-weight:600;">Adjust:</span>
          <button class="adj-btn" onclick="adjPts(${r.id},-5)">−</button>
          <div class="adj-val" id="pts-display-${r.id}" style="color:${cur > 0 ? '#dc2626' : 'inherit'}">${cur > 0 ? '− ' : ''}${Math.abs(cur)} pts</div>
          <button class="adj-btn" onclick="adjPts(${r.id},5)">+</button>
        </div>
        <textarea class="note-area" id="note-${r.id}" rows="2" placeholder="Reason for this adjustment amount (required)..."></textarea>
        <div class="confirm-actions">
          <button class="btn btn-accent" onclick="confirmDeduct(${r.id})">Confirm adjustment</button>
          <button class="btn btn-outline" onclick="cancelVerdict(${r.id})">Cancel</button>
        </div>
      </div>`;

    actionArea = `
      <div class="action-row" id="actions-${r.id}">
        <button class="btn btn-accent btn-sm" onclick="markLegitimate(${r.id})">Resolve</button>
        <button class="btn btn-outline btn-sm" onclick="dismissReport(${r.id})">Dismiss</button>
      </div>
      ${deductArea}`;
  } else if(r.status === 'resolved') {
    actionArea = `<div class="resolved-notice">Resolved — ${r.deduction > 0 ? '−' : ''}${r.deduction} pts adjusted for ${r.against}. Note: "${r.note}"</div>`;
  } else {
    actionArea = `<div class="dismissed-notice">Dismissed — no action taken against ${r.against}.</div>`;
  }

  return `
    <div class="ritem ${r.status}" id="ritem-${r.id}">
      <div class="rhead">
        <div class="rparties">
          ${fromBadge} <strong style="color:var(--color-forest)">${r.from}</strong> 
          <span style="font-size:13px;color:var(--color-text-muted)">reporting</span> 
          ${againstBadge} <strong style="color:var(--color-forest)">${r.against}</strong>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          ${statusBadge}
          <div class="rtime">${r.time}</div>
        </div>
      </div>
      <div style="font-weight: 600; font-size: 14px; margin-bottom: 8px; color: var(--color-forest);">Issue: ${r.issue}</div>
      <div class="rbody">${r.body}</div>
      ${actionArea}
    </div>`;
}

function markLegitimate(id) {
  const vp = document.getElementById('vp-'+id);
  const actions = document.getElementById('actions-'+id);
  actions.style.display = 'none';
  vp.style.display = 'block';
}

function cancelVerdict(id) {
  const vp = document.getElementById('vp-'+id);
  const actions = document.getElementById('actions-'+id);
  vp.style.display = 'none';
  actions.style.display = 'flex';
}

function adjPts(id, delta) {
  if(currentDeductions[id] === undefined) {
    currentDeductions[id] = 0;
  }
  currentDeductions[id] = Math.max(0, Math.min(50, currentDeductions[id] + delta));
  
  const display = document.getElementById('pts-display-'+id);
  display.textContent = (currentDeductions[id] > 0 ? '− ' : '') + currentDeductions[id] + ' pts';
  display.style.color = currentDeductions[id] > 0 ? '#dc2626' : 'inherit';
}

function confirmDeduct(id) {
  const noteElem = document.getElementById('note-'+id);
  const note = noteElem.value.trim();
  if(!note) {
    noteElem.style.borderColor = '#dc2626';
    noteElem.placeholder = 'A reason is required before confirming.';
    return;
  }
  const r = reports.find(x => x.id === id);
  const pts = currentDeductions[id] !== undefined ? currentDeductions[id] : 0;
  r.status = 'resolved';
  r.deduction = pts;
  r.note = note;
  updateCounts();
  renderReports();
}

function dismissReport(id) {
  const r = reports.find(x => x.id === id);
  r.status = 'dismissed';
  updateCounts();
  renderReports();
}

function updateCounts() {
  document.getElementById('cnt-pending').textContent = reports.filter(r => r.status === 'pending').length;
  document.getElementById('cnt-resolved').textContent = 17 + reports.filter(r => r.status === 'resolved').length;
  document.getElementById('cnt-dismissed').textContent = 4 + reports.filter(r => r.status === 'dismissed').length;
}

// Initial render
document.addEventListener('DOMContentLoaded', () => {
  renderReports();
});
