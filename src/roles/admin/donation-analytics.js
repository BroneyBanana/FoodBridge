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
    // 3. Heatmap (Leaflet)
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

        map = L.map('heatmapContainer').setView([3.0554, 101.6982], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        if (typeof heatmapDonations !== 'undefined' && heatmapDonations.length) {
            const points = heatmapDonations.map(d => [d.latitude, d.longitude, 1]);
            L.heatLayer(points, {
                radius: 35,
                blur: 20,
                maxZoom: 14,
                gradient: { 0.4: 'green', 0.7: 'yellow', 1.0: 'red' }
            }).addTo(map);

            heatmapDonations.forEach(d => {
                const marker = L.circleMarker([d.latitude, d.longitude], {
                    radius: 5,
                    color: '#1a5d38',
                    fillOpacity: 0.7
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