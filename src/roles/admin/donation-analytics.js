// donation-analytics.js
document.addEventListener('DOMContentLoaded', function () {

    // ------------------------------------------------------------------
    // 1. Category Chart (Pie)
    // ------------------------------------------------------------------
    const catCtx = document.getElementById('categoryChart');
    if (catCtx && typeof categoryData !== 'undefined') {
        new Chart(catCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.data,
                    backgroundColor: ['#2ecc71', '#f1c40f', '#e67e22', '#3498db', '#9b59b6', '#1abc9c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, padding: 10 }
                    }
                }
            }
        });
    }

    // ------------------------------------------------------------------
    // 2. Trend Chart (Line)
    // ------------------------------------------------------------------
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx && typeof trendData !== 'undefined') {
        new Chart(trendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: trendData.months,
                datasets: [{
                    label: 'Quantity',
                    data: trendData.totals,
                    borderColor: '#1a5d38',
                    backgroundColor: 'rgba(26,93,56,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // ------------------------------------------------------------------
    // 3. Heatmap (Leaflet) – Snapchat Style
    // ------------------------------------------------------------------
    let mapInitialized = false;
    let map;

    function initMap() {
        if (mapInitialized) return;
        const container = document.getElementById('heatmapContainer');
        if (!container) return;
        if (typeof L === 'undefined') {
            console.warn('Leaflet not loaded – skipping map.');
            return;
        }

        // Standard OpenStreetMap tiles (same theme as browse-donations.js)
        map = L.map('heatmapContainer').setView([3.0554, 101.6982], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        if (typeof heatmapDonations !== 'undefined' && heatmapDonations.length) {
            // Build points: [lat, lng, intensity] – intensity based on quantity (capped at 10)
            const points = heatmapDonations.map(d => {
                const intensity = Math.min(parseFloat(d.quantity) || 1) * 2;
                return [d.latitude, d.longitude, intensity];
            });

            // Snapchat‑inspired heatmap settings: vibrant glow, smooth blending
            L.heatLayer(points, {
                radius: 70,          // much wider so nearby points fuse into one blob
                blur: 55,            // heavy blur softens edges into a smooth gradient
                maxZoom: 18,
                max: 1.0,            // lower "max" makes even 1-2 points saturate to red/green
                minOpacity: 0.35,    // keeps faint outer glow visible instead of fading to nothing
                gradient: {
                    0.25: '#22c55e',  // green
                    0.45: '#facc15',  // yellow
                    0.65: '#f97316',  // orange
                    0.85: '#ef4444'   // red (hot core)
                }
            }).addTo(map);

            heatmapDonations.forEach(d => {
                const marker = L.circleMarker([d.latitude, d.longitude], {
                    radius: 3,
                    color: '#ffffff',
                    fillOpacity: 0.2,
                    weight: 0.5
                }).addTo(map);
                marker.bindPopup(`
                    <b>${d.food_name}</b><br>
                    ${d.quantity} ${d.unit}<br>
                    by ${d.donor_name} (score ${d.trust_score})<br>
                    📍 ${d.pickup_address}<br>
                    ⏳ ${d.next_slot ? new Date(d.next_slot).toLocaleString() : 'No upcoming slot'}
                `);
            });

        }

        // Try to centre map on user's location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => map.setView([pos.coords.latitude, pos.coords.longitude], 12),
                () => { }
            );
        }
        mapInitialized = true;
    }

    initMap();
});