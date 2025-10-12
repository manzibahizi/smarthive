document.addEventListener('DOMContentLoaded', function() {
    // Add logout on pages with navbar
    const maybeNavbar = document.querySelector('.navbar');
    if (maybeNavbar && !document.getElementById('logoutBtn')) {
        const right = document.createElement('div');
        right.className = 'ms-auto';
        right.innerHTML = '<button id="logoutBtn" class="btn btn-primary btn-sm">Logout</button>';
        maybeNavbar.querySelector('.container-fluid')?.appendChild(right);
        const btn = document.getElementById('logoutBtn');
        if (btn) {
            btn.addEventListener('click', async () => {
                await fetch('/api/auth/logout', { method: 'POST' });
                window.location.href = '/';
            });
        }
    }
    // Sidebar toggle
    document.getElementById('sidebarCollapse').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Links now navigate to separate pages; no smooth scroll handler

    // Temperature Chart
    const temperatureCanvas = document.getElementById('temperatureChart');
    if (temperatureCanvas) {
    const temperatureCtx = temperatureCanvas.getContext('2d');
    new Chart(temperatureCtx, {
        type: 'line',
        data: {
            labels: ['6am', '9am', '12pm', '3pm', '6pm', '9pm'],
            datasets: [{
                label: 'Temperature (°C)',
                data: [22, 24, 26, 25, 23, 21],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    }

    // Activity Chart
    const activityCanvas = document.getElementById('activityChart');
    if (activityCanvas) {
    const activityCtx = activityCanvas.getContext('2d');
    new Chart(activityCtx, {
        type: 'bar',
        data: {
            labels: ['Hive 1', 'Hive 2', 'Hive 3', 'Hive 4'],
            datasets: [{
                label: 'Activity Level',
                data: [85, 65, 90, 75],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 206, 86)',
                    'rgb(75, 192, 192)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    }

    // Gas Level Chart
    const gasCanvas = document.getElementById('gasChart');
    let gasChart = null;
    if (gasCanvas) {
    const gasCtx = gasCanvas.getContext('2d');
    gasChart = new Chart(gasCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Gas (ppm)',
                data: [],
                borderColor: 'rgb(220, 53, 69)',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
    }

    // Humidity Chart
    const humidityCanvas = document.getElementById('humidityChart');
    let humidityChart = null;
    if (humidityCanvas) {
    const humidityCtx = humidityCanvas.getContext('2d');
    humidityChart = new Chart(humidityCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Humidity (%)',
                data: [],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
    }

    // Fetch real-time data
    function fetchData() {
        // Load dynamic active hives count
        fetch('/api/hives')
            .then(response => response.json())
            .then(data => {
                const list = data.hives || [];
                const active = list.filter(h => (h.status||'').toLowerCase() === 'active').length;
                const el = document.getElementById('activeHivesCount');
                if (el) el.textContent = active;
            })
            .catch(error => console.error('Error fetching hives:', error));

        fetch('/api/weather/current')
            .then(response => response.json())
            .then(data => {
                const w = data.weather || {};
                const el = document.getElementById('weatherSummary');
                if (el) {
                    el.textContent = `Temp: ${w.temp_c}°C, Humidity: ${w.humidity}%, ${w.condition}`;
                }
                
                // Update individual dashboard cards
                const tempEl = document.getElementById('currentTemp');
                if (tempEl) tempEl.textContent = `${w.temp_c || '--'}°C`;
                
                const humidityEl = document.getElementById('currentHumidity');
                if (humidityEl) humidityEl.textContent = `${w.humidity || '--'}%`;
                
                // Update humidity chart
                if (humidityChart) {
                    const label = new Date().toLocaleTimeString();
                    humidityChart.data.labels.push(label);
                    humidityChart.data.datasets[0].data.push(Number(w.humidity || 0));
                    if (humidityChart.data.labels.length > 12) {
                        humidityChart.data.labels.shift();
                        humidityChart.data.datasets[0].data.shift();
                    }
                    humidityChart.update();
                }
            })
            .catch(error => console.error('Error fetching weather data:', error));

        fetch('/api/sensors/gas')
            .then(response => response.json())
            .then(data => {
                const value = data.value || 0;
                const label = new Date(data.timestamp || Date.now()).toLocaleTimeString();
                const gasValueEl = document.getElementById('gasValue');
                if (gasValueEl) gasValueEl.textContent = value;

                // Keep last 12 points
                if (gasChart) {
                    gasChart.data.labels.push(label);
                    gasChart.data.datasets[0].data.push(value);
                    if (gasChart.data.labels.length > 12) {
                        gasChart.data.labels.shift();
                        gasChart.data.datasets[0].data.shift();
                    }
                    gasChart.update();
                }
            })
            .catch(error => console.error('Error fetching gas data:', error));

        // Simulate hive weight (in real app, this would come from API)
        const weightEl = document.getElementById('currentWeight');
        if (weightEl) {
            const simulatedWeight = 12 + Math.random() * 8; // 12-20kg range
            weightEl.textContent = `${simulatedWeight.toFixed(1)}kg`;
        }
    }

    // Pull alerts and surface notifications
    function fetchAlerts() {
        fetch('/api/alerts')
            .then(r => r.json())
            .then(data => {
                const alerts = data.alerts || [];
                
                // Update alerts list on dashboard
                const container = document.getElementById('alertsList');
                if (container) {
                    container.innerHTML = alerts.slice(-5).reverse().map(a => `
                        <div class="alert alert-${a.level === 'critical' ? 'danger' : 'warning'} mb-2">
                            <strong>${a.type.toUpperCase()}</strong>: ${a.message} (${a.value}${a.unit || ''})
                        </div>
                    `).join('');
                }
                
                // Update alert badge in sidebar
                const badge = document.getElementById('alertsBadge');
                if (badge) {
                    if (alerts.length > 0) {
                        badge.textContent = alerts.length;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
    }

    // Fetch data every 1 minute
    fetchData();
    fetchAlerts();
    setInterval(() => { fetchData(); fetchAlerts(); }, 60000);
}); 