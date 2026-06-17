const STORAGE_KEY = 'foodbridge_history_reviews';
const REPORT_KEY = 'foodbridge_receiver_reports';

const completedHistory = [
  {
    id: 'HIS-1001',
    food: 'Nasi Lemak Biasa',
    donor: 'Mama Nasi Lemak',
    date: '12 June 2026',
    image: '../../assets/images/food1.jpg',
    status: 'Collected'
  },
  {
    id: 'HIS-1002',
    food: 'Vegetarian Rice Box',
    donor: 'Green Kitchen',
    date: '10 June 2026',
    image: '../../assets/images/vegetarian.png',
    status: 'Collected'
  },
  {
    id: 'HIS-1003',
    food: 'Bread and Pastries',
    donor: 'Sunrise Bakery',
    date: '8 June 2026',
    image: '../../assets/images/food2.jpg',
    status: 'Collected'
  },
  {
    id: 'HIS-1004',
    food: 'Mixed Lunch Packs',
    donor: 'Community Cafe',
    date: '3 June 2026',
    image: '../../assets/images/food3.jpeg',
    status: 'Collected'
  }
];

const defaultReviews = {
  'HIS-1001': {
    rating: '5',
    comment: 'Food was packed well and collection was smooth.',
    pictureName: 'collection-photo.jpg',
    pictureUrl: '../../assets/images/food1.jpg'
  }
};

let reviews = loadData(STORAGE_KEY, defaultReviews);
let reports = loadData(REPORT_KEY, {
  'HIS-1003': {
    problem: 'The pastries looked stale when collected.',
    images: ['pastry-issue.jpg']
  }
});
let activeHistoryId = null;

const historyList = document.getElementById('historyList');
const reviewModal = document.getElementById('reviewModal');
const reviewForm = document.getElementById('reviewForm');
const reportModal = document.getElementById('reportModal');
const reportForm = document.getElementById('reportForm');
const reviewFields = document.getElementById('reviewFields');
const reviewActions = document.getElementById('reviewActions');
const readonlyReview = document.getElementById('readonlyReview');

function loadData(key, fallback) {
  try {
    return JSON.parse(localStorage.getItem(key)) || fallback;
  } catch {
    return fallback;
  }
}

function saveData(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

function renderHistory() {
  historyList.innerHTML = completedHistory.map(item => {
    const hasReview = Boolean(reviews[item.id]);

    return `
      <article class="history-card">
        <div class="history-info">
          <h3>${item.food}</h3>
          <p class="history-meta">${item.donor} &bull; ${item.date}</p>
        </div>
        <div class="card-actions">
          <button class="text-action-btn review" type="button" data-review-id="${item.id}">${hasReview ? 'View Review' : 'Do Review'}</button>
          <button class="text-action-btn report" type="button" data-report-id="${item.id}">Report</button>
          <span class="status-pill collected">Collected</span>
        </div>
      </article>
    `;
  }).join('');
}

function openReview(historyId) {
  activeHistoryId = historyId;
  reviewForm.reset();
  updateUploadName('review', []);
  const item = completedHistory.find(history => history.id === historyId);
  const savedReview = reviews[historyId];
  document.getElementById('reviewModalTitle').textContent = savedReview ? 'Your Review' : `Review ${item.food}`;

  if (savedReview) {
    const imagePreview = savedReview.pictureUrl
      ? `<a class="review-image-link" href="${savedReview.pictureUrl}" target="_blank" rel="noopener">
          <img src="${savedReview.pictureUrl}" alt="Uploaded review image">
        </a>`
      : '<p class="history-date">No image uploaded</p>';

    readonlyReview.innerHTML = `
      <strong>${savedReview.rating} / 5 rating</strong>
      <p>${savedReview.comment}</p>
      ${imagePreview}
    `;
    readonlyReview.classList.remove('hidden');
    reviewFields.classList.add('hidden');
    reviewActions.classList.add('hidden');
  } else {
    readonlyReview.classList.add('hidden');
    reviewFields.classList.remove('hidden');
    reviewActions.classList.remove('hidden');
  }

  reviewModal.classList.remove('hidden');
}

function openReport(historyId) {
  activeHistoryId = historyId;
  reportForm.reset();
  updateUploadName('report', []);
  const item = completedHistory.find(history => history.id === historyId);
  document.getElementById('reportModalTitle').textContent = `Report ${item.food}`;
  reportModal.classList.remove('hidden');
}

function closeModals() {
  reviewModal.classList.add('hidden');
  reportModal.classList.add('hidden');
  activeHistoryId = null;
}

historyList.addEventListener('click', event => {
  const reviewButton = event.target.closest('[data-review-id]');
  const reportButton = event.target.closest('[data-report-id]');

  if (reviewButton) openReview(reviewButton.dataset.reviewId);
  if (reportButton) openReport(reportButton.dataset.reportId);
});

reviewForm.addEventListener('submit', event => {
  event.preventDefault();
  if (!activeHistoryId || reviews[activeHistoryId]) return;

  const formData = new FormData(reviewForm);
  const picture = formData.get('picture');

  const saveReview = picture?.name
    ? readFileAsDataUrl(picture).then(pictureUrl => {
        reviews[activeHistoryId] = {
          rating: formData.get('rating'),
          comment: formData.get('comment'),
          pictureName: picture.name,
          pictureUrl
        };
      })
    : Promise.resolve().then(() => {
        reviews[activeHistoryId] = {
          rating: formData.get('rating'),
          comment: formData.get('comment'),
          pictureName: '',
          pictureUrl: ''
        };
      });

  saveReview.then(() => {
    saveData(STORAGE_KEY, reviews);
    closeModals();
    renderHistory();
  });
});

reportForm.addEventListener('submit', event => {
  event.preventDefault();
  if (!activeHistoryId) return;

  const formData = new FormData(reportForm);
  reports[activeHistoryId] = {
    issueType: formData.get('issueType'),
    problem: formData.get('problem'),
    images: formData.getAll('images').filter(file => file.name).map(file => file.name)
  };
  saveData(REPORT_KEY, reports);
  closeModals();
  renderHistory();
});

document.querySelectorAll('[data-close-modal]').forEach(button => {
  button.addEventListener('click', closeModals);
});

document.querySelectorAll('.modal-overlay').forEach(modal => {
  modal.addEventListener('click', event => {
    if (event.target === modal) closeModals();
  });
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeModals();
});

document.querySelectorAll('[data-file-input]').forEach(input => {
  input.addEventListener('change', event => {
    updateUploadName(event.target.dataset.fileInput, event.target.files);
  });
});

function updateUploadName(type, files) {
  const label = document.getElementById(type === 'review' ? 'reviewFileName' : 'reportFileName');
  const fileList = Array.from(files || []);
  if (!fileList.length) {
    label.textContent = type === 'review' ? 'No image selected' : 'No images selected';
    return;
  }
  label.textContent = fileList.map(file => file.name).join(', ');
}

function readFileAsDataUrl(file) {
  return new Promise(resolve => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.readAsDataURL(file);
  });
}

renderHistory();
