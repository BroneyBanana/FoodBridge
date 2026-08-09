// 2. Map & View Logic
// Default center (e.g., Kuala Lumpur or user's current location)
const mapCurrentPos = { lat: 3.0554, lng: 101.6982 }; 

if ("geolocation" in navigator) {
  navigator.geolocation.getCurrentPosition(
    function(position) {
      // Success! Update coordinates with the real location
      mapCurrentPos.lat = position.coords.latitude;
      mapCurrentPos.lng = position.coords.longitude;
      
      // If the user already opened the map before the GPS located them, recenter it
      if (typeof mapInitialized !== 'undefined' && mapInitialized && map) {
        map.setView([mapCurrentPos.lat, mapCurrentPos.lng], 12);
      }
    },
    function(error) {
      console.warn("Location access denied or unavailable. Using default coordinates.");
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
} else {
  console.warn("Geolocation is not supported by this browser.");
}

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
      map = L.map('map').setView([mapCurrentPos.lat, mapCurrentPos.lng], 12);
      
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
      }).addTo(map);

      // Fetch dynamic donations passed from PHP
      const dynamicDonations = window.APP_CONFIG.donations || [];

      // Filter out any donations that are missing latitude/longitude just to be safe
      const validLocations = dynamicDonations.filter(d => d.latitude && d.longitude);

      // 1. Build Dynamic Heatmap
      const heatPoints = validLocations.map(d => [d.latitude, d.longitude, 1]);
      L.heatLayer(heatPoints, {
        radius: 40, blur: 25, maxZoom: 14, max: 3,
        gradient: { 0.4: 'green', 0.7: 'yellow', 1.0: 'red' }
      }).addTo(map);

      // 2. Build Dynamic Markers
      validLocations.forEach(donation => {
        const marker = L.marker([donation.latitude, donation.longitude], { icon: customPin }).addTo(map);
        
        marker.on('click', async () => {
          marker.bindPopup(`
            <div class="popup-title">${donation.food_name}</div>
            <div class="popup-subtitle">By ${donation.donor_name}</div>
            <div class="popup-distance">⏳ Calculating...</div>
          `).openPopup();
          
          const dist = await getDistance(donation.latitude, donation.longitude);
          
          marker.setPopupContent(`
            <div class="popup-title">${donation.food_name}</div>
            <div class="popup-subtitle">By ${donation.donor_name}</div>
            <div class="popup-distance">${dist ? dist + 'km away' : '⚠️ Distance N/A'}</div>
            ${dist ? `<button class="popup-btn" onclick="document.querySelector('.BookDonation[data-donation-id=\\'${donation.donation_id}\\']').click()">Book Pickup</button>` : ''}
          `).openPopup();
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

        if (slots.error) {
          // handles get_slots.php's hogging-check response (an object, not an array)
          document.getElementById('pickupModalError').textContent = slots.error;
          select.innerHTML = '<option value="">No slots available</option>';
        } else if (slots.length === 0) {
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

document.addEventListener("DOMContentLoaded", function() {
  // 1. Get starting coordinates from the global APP_CONFIG object
  const startLat = window.APP_CONFIG.receiverLat;
  const startLng = window.APP_CONFIG.receiverLng;
  
  const distanceElements = document.querySelectorAll('.async-distance');

  // If we don't have valid starting coordinates, show N/A
  if (!startLat || !startLng || startLat === 0) {
    distanceElements.forEach(el => el.innerHTML = "📍 N/A");
    return;
  }

  // 2. Fetch distances sequentially
  async function calculateAllDistances() {
    for (let el of distanceElements) {
      const destLat = el.getAttribute('data-dest-lat');
      const destLng = el.getAttribute('data-dest-lng');

      if (destLat && destLng) {
        try {
          // IMPORTANT: Update this URL path to point to where your get_distance.php file actually is!
          // For example: '../../api/get_distance.php'
          const fetchUrl = `./get_distance.php?startLat=${startLat}&startLng=${startLng}&destLat=${destLat}&destLng=${destLng}`;
          
          const response = await fetch(fetchUrl);
          
          if (!response.ok) throw new Error('API Error');
          
          const data = await response.json();
          
          // Parse TomTom API response structure
          if (data.routes && data.routes.length > 0) {
            const meters = data.routes[0].summary.lengthInMeters;
            const km = (meters / 1000).toFixed(1);
            el.innerHTML = `📍 ${km} km`;
          } else {
            el.innerHTML = "📍 N/A";
          }
        } catch (error) {
          console.error("Error fetching distance:", error);
          el.innerHTML = "📍 N/A";
        }
      }
    }
  }

  calculateAllDistances();
});