// ── QR Scanner ──
const modal       = document.getElementById('qr-modal');
const video       = document.getElementById('qr-video');
const errorMsg    = document.getElementById('qr-error');
const successMsg  = document.getElementById('qr-success-msg');
const closeBtn    = document.getElementById('qr-close-btn');
const scannerWrap = document.getElementById('qr-scanner-wrap');

let stream   = null;
let rafId    = null;
let scanning = false;

const canvas = document.createElement('canvas');
const ctx    = canvas.getContext('2d');

function openScanner() {
  modal.classList.remove('hidden');
  errorMsg.style.display   = 'none';
  successMsg.style.display = 'none';
  scannerWrap.classList.remove('success');
  scanning = true;
  startCamera();
}

function closeScanner() {
  modal.classList.add('hidden');
  scanning = false;
  stopCamera();
}

async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = stream;
    video.addEventListener('loadedmetadata', () => {
      video.play();
      requestAnimationFrame(scanFrame);
    }, { once: true });
  } catch (err) {
    errorMsg.textContent   = getCameraError(err);
    errorMsg.style.display = 'block';
  }
}

function stopCamera() {
  if (rafId)  { cancelAnimationFrame(rafId); rafId = null; }
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  video.srcObject = null;
}

function scanFrame() {
  if (!scanning) return;
  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
    if (code) { onQRDetected(code.data); return; }
  }
  rafId = requestAnimationFrame(scanFrame);
}

function onQRDetected(data) {
  scanning = false;
  stopCamera();
  scannerWrap.classList.add('success');
  successMsg.textContent   = 'Scanned: ' + data;
  successMsg.style.display = 'block';
  setTimeout(closeScanner, 2000);
}

function getCameraError(err) {
  if (err.name === 'NotAllowedError')  return 'Camera permission denied. Please allow camera access and try again.';
  if (err.name === 'NotFoundError')    return 'No camera found on this device.';
  if (err.name === 'NotReadableError') return 'Camera is already in use by another app.';
  return 'Could not access camera: ' + err.message;
}

document.querySelectorAll('.scanQR').forEach(btn => btn.addEventListener('click', openScanner));
closeBtn.addEventListener('click', closeScanner);
modal.addEventListener('click', e => { if (e.target === modal) closeScanner(); });
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeScanner();
});


// ── Cancel Confirmation Modal ──
const cancelModal      = document.getElementById('cancel-modal');
const cancelKeepBtn    = document.getElementById('cancel-keep-btn');
const cancelConfirmBtn = document.getElementById('cancel-confirm-btn');
const cancelItemName   = document.getElementById('cancel-item-name');

let pendingCancelCard = null;

function openCancelModal(card) {
  pendingCancelCard = card;
  cancelItemName.textContent = card.querySelector('.bookingCardFoodTitle')?.textContent || 'this booking';
  cancelModal.classList.remove('hidden');
}

function closeCancelModal() {
  cancelModal.classList.add('hidden');
  pendingCancelCard = null;
}

function confirmCancel() {
  if (pendingCancelCard) {
    pendingCancelCard.style.transition = 'opacity 0.25s, transform 0.25s';
    pendingCancelCard.style.opacity    = '0';
    pendingCancelCard.style.transform  = 'scale(0.97)';
    setTimeout(() => pendingCancelCard.remove(), 250);
  }
  closeCancelModal();
}

document.querySelectorAll('.Cancel').forEach(btn => {
  btn.addEventListener('click', () => openCancelModal(btn.closest('.bookingCard')));
});
cancelKeepBtn.addEventListener('click', closeCancelModal);
cancelConfirmBtn.addEventListener('click', confirmCancel);
cancelModal.addEventListener('click', e => { if (e.target === cancelModal) closeCancelModal(); });
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !cancelModal.classList.contains('hidden')) closeCancelModal();
});


// ── Directions Map Modal ──
const TOMTOM_KEY = 'gs85aMga5cMBIDXUMyWr0dqEvDlZdDXV';
const FIXED_DEST = [3.1390, 101.6869]; // change to actual pickup coords

const mapModal    = document.getElementById('map-modal');
const mapCloseBtn = document.getElementById('map-close-btn');

let leafletMap     = null;
let mapUserMarker  = null;
let mapDestMarker  = null;
let mapRouteBorder = null;
let mapRoutePath   = null;
let mapCurrentPos  = null;
let mapGeoWatch    = null;

function openMapModal(bookingTitle, donorName) {
  document.getElementById('map-modal-title').textContent = bookingTitle || 'Directions';
  document.getElementById('map-modal-donor').textContent = donorName || '';
  document.getElementById('map-distance').textContent    = '—';
  document.getElementById('map-eta').textContent         = '—';
  document.getElementById('map-traffic').textContent     = 'Locating…';
  document.getElementById('map-traffic').style.color     = '#bbb';

  mapModal.classList.remove('hidden');

  if (!leafletMap) {
    leafletMap = L.map('directions-map', { zoomControl: false }).setView(FIXED_DEST, 14);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '©OpenStreetMap ©CartoDB'
    }).addTo(leafletMap);
    L.control.zoom({ position: 'bottomright' }).addTo(leafletMap);

    const destIcon = L.divIcon({
      className: '',
      html: `<div style="width:16px;height:16px;background:#ff3b30;border-radius:50% 50% 50% 4px;transform:rotate(-45deg);border:2.5px solid #fff;box-shadow:0 0 12px rgba(255,59,48,0.6)"></div>`,
      iconSize: [16, 16], iconAnchor: [8, 14]
    });
    mapDestMarker = L.marker(FIXED_DEST, { icon: destIcon }).addTo(leafletMap);
    L.circleMarker(FIXED_DEST, {
      radius: 24, color: '#ff3b30', weight: 1.5, fillColor: '#ff3b30', fillOpacity: 0.08
    }).addTo(leafletMap);
  }

  setTimeout(() => leafletMap.invalidateSize(), 60);

  if (navigator.geolocation) {
    mapGeoWatch = navigator.geolocation.watchPosition(mapGPSSuccess, mapGPSError, {
      enableHighAccuracy: true, timeout: 10000, maximumAge: 0
    });
  } else {
    document.getElementById('map-traffic').textContent = 'GPS unavailable';
  }
}

function closeMapModal() {
  mapModal.classList.add('hidden');
  if (mapGeoWatch !== null) { navigator.geolocation.clearWatch(mapGeoWatch); mapGeoWatch = null; }
}

function mapGPSSuccess(pos) {
  mapCurrentPos = L.latLng(pos.coords.latitude, pos.coords.longitude);
  if (!mapUserMarker) {
    mapUserMarker = L.circleMarker(mapCurrentPos, {
      radius: 8, fillColor: '#007AFF', color: '#fff', weight: 2.5, fillOpacity: 1
    }).addTo(leafletMap);
    leafletMap.fitBounds([mapCurrentPos, FIXED_DEST], { padding: [48, 48] });
  } else {
    mapUserMarker.setLatLng(mapCurrentPos);
  }
  mapUpdateRoute();
}

function mapUpdateRoute() {
  if (!mapCurrentPos) return;
  
  const url = `get_distance.php?startLat=${mapCurrentPos.lat}&startLng=${mapCurrentPos.lng}&destLat=${FIXED_DEST[0]}&destLng=${FIXED_DEST[1]}`;
  
  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.routes?.length) return;
      
      const route    = data.routes[0];
      const coords   = route.legs[0].points.map(p => [p.latitude, p.longitude]);
      const distKm   = (route.summary.lengthInMeters / 1000).toFixed(2);
      const timeMins = Math.ceil(route.summary.travelTimeInSeconds / 60);
      const speedKmh = distKm / (route.summary.travelTimeInSeconds / 3600);

      document.getElementById('map-distance').textContent = distKm;
      document.getElementById('map-eta').textContent      = timeMins;

      let color = '#2ecc71', label = 'Smooth';
      if       (speedKmh < 18) { color = '#e74c3c'; label = 'Heavy'; }
      else if (speedKmh < 35) { color = '#f1c40f'; label = 'Moderate'; }

      document.getElementById('map-traffic').textContent = label;
      document.getElementById('map-traffic').style.color = color;

      if (!mapRouteBorder) {
        mapRouteBorder = L.polyline(coords, { color: 'rgba(0,0,0,0.08)', weight: 14, lineCap: 'round', lineJoin: 'round' }).addTo(leafletMap);
        mapRoutePath   = L.polyline(coords, { color, weight: 5, opacity: 1, lineCap: 'round', lineJoin: 'round' }).addTo(leafletMap);
      } else {
        mapRouteBorder.setLatLngs(coords);
        mapRoutePath.setLatLngs(coords);
        mapRoutePath.setStyle({ color });
      }
    })
    .catch(err => {
      console.error("Route fetch error:", err);
      document.getElementById('map-traffic').textContent = 'Error loading route';
    });
}

function mapGPSError() {
  document.getElementById('map-traffic').textContent = 'GPS unavailable';
  document.getElementById('map-traffic').style.color = '#bbb';
}

document.querySelectorAll('.Directions').forEach(btn => {
  btn.addEventListener('click', function () {
    const card  = btn.closest('.bookingCardDetails');
    const title = card?.querySelector('.bookingCardFoodTitle')?.textContent || 'Directions';
    const donor = card?.querySelector('.bookingCardFoodDonor')?.textContent || '';
    openMapModal(title, donor);
  });
});

mapCloseBtn.addEventListener('click', closeMapModal);
mapModal.addEventListener('click', e => { if (e.target === mapModal) closeMapModal(); });
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !mapModal.classList.contains('hidden')) closeMapModal();
});