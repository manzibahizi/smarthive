document.addEventListener('DOMContentLoaded', function() {
    // Initialize loading states
    initializeLoadingStates();
    
    // Add logout on pages with navbar
    const maybeNavbar = document.querySelector('.navbar');
    if (maybeNavbar && !document.getElementById('logoutBtn')) {
        const right = document.createElement('div');
        right.className = 'ms-auto';
        right.innerHTML = '<button id="logoutBtn" class="btn btn-primary btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Logout</button>';
        maybeNavbar.querySelector('.container-fluid')?.appendChild(right);
        const btn = document.getElementById('logoutBtn');
        if (btn) {
            btn.addEventListener('click', async () => {
                if (confirm('Are you sure you want to logout?')) {
                    try {
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Logging out...';
                        btn.disabled = true;
                        await fetch('/api/auth/logout', { method: 'POST' });
                        window.location.href = '/';
                    } catch (error) {
                        console.error('Logout error:', error);
                        btn.innerHTML = '<i class="fas fa-sign-out-alt me-1"></i>Logout';
                        btn.disabled = false;
                        alert('Logout failed. Please try again.');
                    }
                }
            });
        }
    }
    // Sidebar toggle
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    }

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
        // Load dynamic active hives count with better error handling
        fetch('/api/hives')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const list = data.hives || [];
                const active = list.filter(h => (h.status||'').toLowerCase() === 'active').length;
                const el = document.getElementById('activeHivesCount');
                if (el) {
                    el.textContent = active;
                    el.classList.remove('text-muted');
                }
            })
            .catch(error => {
                console.error('Error fetching hives:', error);
                const el = document.getElementById('activeHivesCount');
                if (el) {
                    el.textContent = '--';
                    el.classList.add('text-muted');
                }
            });

        fetch('/api/weather/current')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const w = data.weather || {};
                const el = document.getElementById('weatherSummary');
                if (el) {
                    el.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-cloud-sun text-primary me-3 fa-2x"></i>
                            <div>
                                <div class="h6 mb-1">Current Conditions</div>
                                <div class="text-muted">
                                    <i class="fas fa-thermometer-half me-1"></i>${w.temp_c || '--'}°C | 
                                    <i class="fas fa-tint me-1"></i>${w.humidity || '--'}% | 
                                    <i class="fas fa-eye me-1"></i>${w.condition || 'Unknown'}
                                </div>
                            </div>
                        </div>
                    `;
                    el.className = 'alert alert-info';
                }
                
                // Update individual dashboard cards
                const tempEl = document.getElementById('currentTemp');
                if (tempEl) tempEl.textContent = `${w.temp_c || '--'}°C`;
                
                const humidityEl = document.getElementById('currentHumidity');
                if (humidityEl) humidityEl.textContent = `${w.humidity || '--'}%`;
                
                // Update humidity chart with enhanced styling
                if (humidityChart) {
                    const label = new Date().toLocaleTimeString();
                    humidityChart.data.labels.push(label);
                    humidityChart.data.datasets[0].data.push(Number(w.humidity || 0));
                    if (humidityChart.data.labels.length > 12) {
                        humidityChart.data.labels.shift();
                        humidityChart.data.datasets[0].data.shift();
                    }
                    
                    // Add threshold lines for optimal humidity range
                    humidityChart.options.plugins.annotation = {
                        annotations: {
                            line1: {
                                type: 'line',
                                yMin: 50,
                                yMax: 50,
                                borderColor: 'rgb(34, 197, 94)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    content: 'Optimal Min (50%)',
                                    enabled: true,
                                    position: 'end'
                                }
                            },
                            line2: {
                                type: 'line',
                                yMin: 60,
                                yMax: 60,
                                borderColor: 'rgb(34, 197, 94)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    content: 'Optimal Max (60%)',
                                    enabled: true,
                                    position: 'end'
                                }
                            }
                        }
                    };
                    
                    humidityChart.update();
                }
            })
            .catch(error => {
                console.error('Error fetching weather:', error);
                const el = document.getElementById('weatherSummary');
                if (el) {
                    el.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle text-warning me-3 fa-2x"></i>
                            <div>
                                <div class="h6 mb-1">Weather Unavailable</div>
                                <div class="text-muted">Unable to load weather data</div>
                            </div>
                        </div>
                    `;
                    el.className = 'alert alert-warning';
                }
                
                // Update individual dashboard cards with error state
                const tempEl = document.getElementById('currentTemp');
                if (tempEl) {
                    tempEl.textContent = '--°C';
                    tempEl.classList.add('text-muted');
                }
                
                const humidityEl = document.getElementById('currentHumidity');
                if (humidityEl) {
                    humidityEl.textContent = '--%';
                    humidityEl.classList.add('text-muted');
                }
            });

        fetch('/api/sensors/gas')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
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
            .catch(error => {
                console.error('Error fetching gas data:', error);
                const gasValueEl = document.getElementById('gasValue');
                if (gasValueEl) {
                    gasValueEl.textContent = '--';
                    gasValueEl.classList.add('text-muted');
                }
            });

        // Simulate hive weight (in real app, this would come from API)
        const weightEl = document.getElementById('currentWeight');
        if (weightEl) {
            const simulatedWeight = 12 + Math.random() * 8; // 12-20kg range
            weightEl.textContent = `${simulatedWeight.toFixed(1)}kg`;
        }
    }

    // Enhanced alert system with notifications
    let lastAlertCount = 0;
    let notificationPermission = false;

    // Request notification permission
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            notificationPermission = permission === 'granted';
        });
    }

    // Pull alerts and surface notifications
    function fetchAlerts() {
        fetch('/api/alerts')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const alerts = data.alerts || [];
                
                // Check for new alerts and show notifications
                if (alerts.length > lastAlertCount && notificationPermission) {
                    const newAlerts = alerts.slice(lastAlertCount);
                    newAlerts.forEach(alert => {
                        showNotification(alert);
                    });
                }
                lastAlertCount = alerts.length;
                
                // Update alerts list on dashboard
                const container = document.getElementById('alertsList');
                if (container) {
                    container.innerHTML = alerts.slice(-5).reverse().map(a => `
                        <div class="alert alert-${a.level === 'critical' ? 'danger' : 'warning'} mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas ${getAlertIcon(a.type)} me-2"></i>
                                <div>
                                    <strong>${a.type.toUpperCase()}</strong>: ${a.message}
                                    <br><small class="text-muted">Value: ${a.value}${a.unit || ''} | ${getTimeAgo(a.created_at)}</small>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
                
                // Update alert badge in sidebar
                const badge = document.getElementById('alertsBadge');
                if (badge) {
                    if (alerts.length > 0) {
                        badge.textContent = alerts.length;
                        badge.style.display = 'inline';
                        // Add pulsing animation for critical alerts
                        const criticalAlerts = alerts.filter(a => a.level === 'critical');
                        if (criticalAlerts.length > 0) {
                            badge.classList.add('pulse');
                        } else {
                            badge.classList.remove('pulse');
                        }
                    } else {
                        badge.style.display = 'none';
                        badge.classList.remove('pulse');
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching alerts:', error);
                const container = document.getElementById('alertsList');
                if (container) {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Unable to load alerts. Please check your connection.
                        </div>
                    `;
                }
                
                const badge = document.getElementById('alertsBadge');
                if (badge) {
                    badge.style.display = 'none';
                    badge.classList.remove('pulse');
                }
            });
    }

    function showNotification(alert) {
        if (!notificationPermission) return;
        
        const notification = new Notification(`Smart Hive Alert - ${alert.type.toUpperCase()}`, {
            body: `${alert.message}\nValue: ${alert.value}${alert.unit || ''}`,
            icon: '/favicon.ico',
            tag: `alert-${alert.type}-${alert.created_at}`,
            requireInteraction: alert.level === 'critical'
        });
        
        // Auto-close after 5 seconds for warnings, 10 seconds for critical
        setTimeout(() => {
            notification.close();
        }, alert.level === 'critical' ? 10000 : 5000);
    }

    function getAlertIcon(type) {
        const icons = {
            'temperature': 'fa-thermometer-half',
            'humidity': 'fa-tint',
            'gas': 'fa-wind',
            'hive-deleted': 'fa-trash',
            'default': 'fa-exclamation-triangle'
        };
        return icons[type] || icons.default;
    }

    function getTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${diffDays}d ago`;
    }

    // Fetch data every 1 minute
    fetchData();
    fetchAlerts();
    setInterval(() => { fetchData(); fetchAlerts(); }, 60000);
});

// Loading states and utility functions
function initializeLoadingStates() {
    // Add loading spinners to buttons when clicked
    document.addEventListener('click', function(e) {
        if (e.target.matches('button[type="submit"], .btn-primary, .btn-success, .btn-delete')) {
            const btn = e.target;
            if (!btn.disabled && !btn.querySelector('.fa-spinner')) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + originalText;
                btn.disabled = true;
                
                // Re-enable after 3 seconds if still disabled
                setTimeout(() => {
                    if (btn.disabled) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }, 3000);
            }
        }
    });
}

// Enhanced API call function with error handling
async function apiCall(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return { success: true, data };
    } catch (error) {
        console.error('API call failed:', error);
        return { success: false, error: error.message };
    }
}

// Show notification function
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

// Form validation helper
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
} 