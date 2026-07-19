// 2. Map & View Logic
const mapCurrentPos = { lat: 3.1333, lng: 101.6833 };
const heatData = [
  [3.1422, 101.6875, "Healthy Bowls", "Vegan Salad Bowls"],
  [3.1425, 101.6870, "Green Bites", "Organic Wraps"],
  [3.1420, 101.6880, "Juice Works", "Detox Juices"],
  [3.1428, 101.6878, "Salad Atelier", "Custom Salads"],
  [3.1450, 101.6900, "Eco Bakery", "Sourdough Loaf"],
  [3.1455, 101.6905, "Pastry Hub", "Croissants"],
  [3.1380, 101.6840, "Perdana Cafe", "Nasi Lemak"],
  [3.1320, 101.6860, "KL Express", "Chicken Rice"]
];

let mapInitialized = false;
let map;

const pinSVG = `
  <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M18 2C11.373 2 6 7.373 6 14c0 8.5 12 20 12 20s12-11.5 12-20c0-6.627-5.373-12-12-12z" 
          fill="#1a5d38" stroke="#ffffff" stroke-width="2.5" 
          filter="drop-shadow(0px 4px 4px rgba(0,0,0,0.3))"/>
    <circle cx="18" cy="14" r="5" fill="#ffffff"/>
  </svg>
`;

const customPin = L.divIcon({
  className: 'custom-marker',
  html: pinSVG,
  iconSize: [36, 36],
  iconAnchor: [18, 36],
  popupAnchor: [0, -32]
});

function switchView(view, btn) {
  // Toggle UI
  document.querySelectorAll('.Filter').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('listView').style.display = view === 'list' ? 'block' : 'none';
  document.getElementById('mapView').style.display = view === 'map' ? 'block' : 'none';

  if (view === 'map') {
    if (!mapInitialized) {
      map = L.map('map').setView([3.1412, 101.6865], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
      }).addTo(map);

      // Heatmap
      L.heatLayer(heatData.map(item => [item[0], item[1], 1]), {
        radius: 40, blur: 25, maxZoom: 14, max: 3,
        gradient: {0.4: 'green', 0.7: 'yellow', 1.0: 'red'}
      }).addTo(map);

      // Markers
      heatData.forEach(item => {
        const marker = L.marker([item[0], item[1]], { icon: customPin }).addTo(map);
        marker.on('click', async () => {
          marker.bindPopup(`<div class="popup-title">${item[3]}</div><div class="popup-subtitle">${item[2]}</div><div class="popup-distance">⏳ Calculating...</div>`).openPopup();
          const dist = await getDistance(item[0], item[1]);
          marker.setPopupContent(`<div class="popup-title">${item[3]}</div><div class="popup-subtitle">${item[2]}</div><div class="popup-distance">${dist ? dist + 'km away' : '⚠️ Distance N/A'}</div>${dist ? '<button class="popup-btn">Book Pickup</button>' : ''}`).openPopup();
        });
      });
      mapInitialized = true;
    } else {
      setTimeout(() => map.invalidateSize(), 10);
    }
  }
}

async function getDistance(destLat, destLng) {
  try {
    const response = await fetch(`get_distance.php?startLat=${mapCurrentPos.lat}&startLng=${mapCurrentPos.lng}&destLat=${destLat}&destLng=${destLng}`);
    const data = await response.json();
    return (data.routes?.[0]?.summary?.lengthInMeters / 1000).toFixed(1) || null;
  } catch (e) { return null; }
}


// ---------------- Category filter (list view) ---------------- //
document.querySelectorAll('.categoryBtn').forEach(function (button) {
  button.addEventListener('click', function () {

    document.querySelectorAll('.categoryBtn').forEach(function (btn) {
      btn.classList.remove('active');
    });
    button.classList.add('active');

    const selectedCategory = button.dataset.filter;

    document.querySelectorAll('.donationCard').forEach(function (card) {
      if (selectedCategory === 'all' || card.dataset.category === selectedCategory) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });

  });
});


// ---------------- Pickup Time booking modal ---------------- //
let currentDonationId = null;

document.querySelectorAll('.BookDonation').forEach(function (button) {
  button.addEventListener('click', function () {

    currentDonationId = button.dataset.donationId;
    console.log('Clicked button, donationId is:', currentDonationId); // TEMP diagnostic — remove once confirmed working

    const maxQty = button.dataset.quantity;
    const unit = button.dataset.unit;

    document.getElementById('maxQuantity').textContent = maxQty;
    document.getElementById('unitLabel').textContent = unit;
    document.getElementById('quantityInput').max = maxQty;
    document.getElementById('pickupModalError').textContent = '';

    fetch(`get_slots.php?donation_id=${currentDonationId}`)
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Could not load slots');
        }
        return response.json();
      })
      .then(function (slots) {
        const select = document.getElementById('slotSelect');
        select.innerHTML = '';

        if (slots.length === 0) {
          select.innerHTML = '<option value="">No slots available</option>';
        } else {
          slots.forEach(function (slot) {
            const option = document.createElement('option');
            option.value = slot.pickup_slot_id;
            option.textContent = slot.timeslot;
            select.appendChild(option);
          });
        }

        document.getElementById('pickupModal').style.display = 'flex';
      })
      .catch(function () {
        document.getElementById('pickupModalError').textContent = 'Failed to load pickup slots.';
        document.getElementById('pickupModal').style.display = 'flex';
      });

  });
});

document.getElementById('closePickupModal').addEventListener('click', function () {
  document.getElementById('pickupModal').style.display = 'none';
});

document.getElementById('confirmBookingBtn').addEventListener('click', function () {
  const pickupSlotId = document.getElementById('slotSelect').value;
  const quantity = document.getElementById('quantityInput').value;
  const errorBox = document.getElementById('pickupModalError');

  if (!pickupSlotId || !quantity || quantity <= 0) {
    errorBox.textContent = 'Please select a slot and enter a valid quantity.';
    return;
  }

  const formData = new FormData();
  formData.append('donation_id', currentDonationId);
  formData.append('pickup_slot_id', pickupSlotId);
  formData.append('quantity', quantity);

  fetch('book_slot.php', {
    method: 'POST',
    body: formData
  })
    .then(function (response) {
      return response.json().then(function (data) {
        return { ok: response.ok, data: data };
      });
    })
    .then(function (result) {
      if (result.ok && result.data.success) {
        location.reload();
      } else {
        errorBox.textContent = result.data.error || 'Booking failed.';
      }
    })
    .catch(function () {
      errorBox.textContent = 'Something went wrong. Please try again.';
    });
});