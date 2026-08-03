document.addEventListener('DOMContentLoaded', () => {
  const dateBadge = document.getElementById('todayDate');
  if (dateBadge) {
    const today = new Date();
    dateBadge.textContent = today.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    });
  }

  renderCategoryChart();
});

document.addEventListener('DOMContentLoaded', () => {
  const dateBadge = document.getElementById('todayDate');
  if (dateBadge) {
    const today = new Date();
    dateBadge.textContent = today.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    });
  }

  renderReportStatusChart();
});

function renderReportStatusChart() {
  const canvas = document.getElementById('reportStatusChart');
  if (!canvas || typeof Chart === 'undefined') return;

  const labels = Object.keys(reportStatusData);
  const values = Object.values(reportStatusData);

  const statusColors = {
    'Pending': '#B78103',
    'Resolved': '#637f14',
    'Dismissed': '#8a8a8a'
  };

  new Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Reports',
        data: values,
        backgroundColor: labels.map(l => statusColors[l] || '#1C2B1E'),
        borderRadius: 8,
        maxBarThickness: 60
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0, font: { family: 'DM Sans' } },
          grid: { color: 'rgba(28, 43, 30, 0.06)' }
        },
        x: {
          ticks: { font: { family: 'DM Sans', weight: '600' }, color: '#1C2B1E' },
          grid: { display: false }
        }
      }
    }
  });
}