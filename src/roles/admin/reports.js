const STORAGE_KEY = 'foodbridge_admin_current_reports_v4';

const defaultReports = [
  {
    id: 'REP-2001',
    status: 'pending',
    receiver: 'Daniel Ong',
    donor: 'Sunrise Bakery',
    donation: 'Bread and Pastries',
    date: '12 June 2026',
    problem: 'The bread smelled sour and some pastries had visible mould.',
    evidence: [
      { name: 'spoiled-bread.jpg', url: '../../assets/images/food2.jpg' },
      { name: 'mould-photo.jpg', url: '../../assets/images/food3.jpeg' }
    ]
  },
  {
    id: 'REP-2002',
    status: 'pending',
    receiver: 'Aisyah Rahman',
    donor: 'Mama Nasi Lemak',
    donation: 'Nasi Lemak Biasa',
    date: '11 June 2026',
    problem: 'Pickup location was different from the listed address and the donor did not update the booking.',
    evidence: [
      { name: 'wrong-address.jpg', url: '../../assets/images/impact1.jpg' }
    ]
  },
  {
    id: 'REP-2003',
    status: 'pending',
    receiver: 'Mei Ling',
    donor: 'Community Cafe',
    donation: 'Mixed Lunch Packs',
    date: '9 June 2026',
    problem: 'Food packaging was leaking during collection.',
    evidence: [
      { name: 'leaking-packaging.jpg', url: '../../assets/images/food1.jpg' }
    ]
  }
];

let reports = loadReports();
const reportList = document.getElementById('adminReportList');
const emptyReports = document.getElementById('emptyReports');
const penaltyOptions = [
  { label: 'Spoiled food', points: 10 },
  { label: 'Fake item', points: 15 },
  { label: 'Wrong address', points: 8 },
  { label: 'Wrong food information', points: 6 },
  { label: 'Unsafe packaging', points: 5 }
];

function loadReports() {
  try {
    const savedReports = JSON.parse(localStorage.getItem(STORAGE_KEY));
    return savedReports ? savedReports.map(normalizeReport) : defaultReports;
  } catch {
    return defaultReports;
  }
}

function normalizeReport(report) {
  return {
    ...report,
    evidence: report.evidence.map(item => {
      if (typeof item !== 'string') return item;
      return { name: item, url: '../../assets/images/food1.jpg' };
    })
  };
}

function saveReports() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(reports));
}

function renderReports() {
  const currentReports = reports.filter(report => report.status === 'pending');

  reportList.innerHTML = currentReports.map(report => `
    <article class="admin-report-card">
      <div>
        <div class="report-title-row">
          <h3>${report.donation}</h3>
        </div>
        <p class="report-meta">Reported by ${report.receiver} - Donor: ${report.donor} - ${report.date}</p>
        <p class="report-problem">${report.problem}</p>
        <div class="evidence-list">
          ${report.evidence.map(item => `
            <a class="evidence-link" href="${item.url}" download="${item.name}">
              <img src="${item.url}" alt="${item.name}">
              <span>${item.name}</span>
            </a>
          `).join('')}
        </div>
      </div>
      <aside class="decision-box">
        ${renderDecisionActions(report)}
      </aside>
    </article>
  `).join('');

  emptyReports.classList.toggle('hidden', currentReports.length > 0);
}

function renderDecisionActions(report) {
  return `
    <p class="decision-title">Select admin action</p>
    <div class="decision-actions">
      ${penaltyOptions.map(option => `
        <button class="btn btn-danger" type="button" data-penalty="${option.points}" data-report-id="${report.id}">
          ${option.label} (-${option.points})
        </button>
      `).join('')}
      <button class="btn btn-outline" type="button" data-reject="${report.id}">Reject Report</button>
    </div>
  `;
}

function resolveReport(reportId, status, penaltyPoints = 0) {
  reports = reports.map(report => {
    if (report.id !== reportId || report.status !== 'pending') return report;
    return {
      ...report,
      status,
      penaltyPoints
    };
  });
  saveReports();
  renderReports();
}

reportList.addEventListener('click', event => {
  const penaltyButton = event.target.closest('[data-penalty]');
  const rejectButton = event.target.closest('[data-reject]');

  if (penaltyButton) {
    resolveReport(
      penaltyButton.dataset.reportId,
      'resolved',
      Number(penaltyButton.dataset.penalty)
    );
  }

  if (rejectButton) resolveReport(rejectButton.dataset.reject, 'rejected');
});

renderReports();
