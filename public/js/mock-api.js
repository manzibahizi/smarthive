// Mock API responses for static deployment
window.mockAPI = {
    // Mock hives data
    hives: [
        { id: 1, name: "IMENA", device_id: "12HDJCSW233", location: "RUBAVU", status: "active" },
        { id: 2, name: "Ishimwe", device_id: "12j233n24", location: "Rulindo", status: "active" },
        { id: 3, name: "RWANDA", device_id: "123tgnmsd452", location: "nyarugenge", status: "active" },
        { id: 4, name: "KIRENGA", device_id: "123hijdci454rredf", location: "kabuga", status: "active" }
    ],
    
    // Mock weather data
    weather: {
        temperature: 24 + Math.floor(Math.random() * 10),
        humidity: 65 + Math.floor(Math.random() * 15),
        pressure: 1013 + Math.floor(Math.random() * 20),
        wind_speed: 5 + Math.floor(Math.random() * 20),
        wind_direction: ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'][Math.floor(Math.random() * 8)],
        conditions: ['Sunny', 'Partly Cloudy', 'Cloudy', 'Rainy'][Math.floor(Math.random() * 4)]
    },
    
    // Mock gas sensor data
    gas: {
        value: 180 + Math.floor(Math.random() * 40),
        unit: 'ppm',
        safe_max: 200,
        warning_threshold: 250
    },
    
    // Mock alerts
    alerts: [
        { type: 'temperature', level: 'warning', message: 'High temperature detected', value: 33, unit: '°C' },
        { type: 'humidity', level: 'warning', message: 'High humidity detected', value: 77, unit: '%' },
        { type: 'gas', level: 'warning', message: 'Gas level is high!', value: 204, unit: 'ppm' }
    ]
};

// Override fetch to return mock data
const originalFetch = window.fetch;
window.fetch = function(url, options) {
    if (url.includes('/api/hives')) {
        return Promise.resolve({
            json: () => Promise.resolve({ status: 'success', hives: window.mockAPI.hives })
        });
    }
    if (url.includes('/api/weather/current')) {
        return Promise.resolve({
            json: () => Promise.resolve({ status: 'success', weather: window.mockAPI.weather })
        });
    }
    if (url.includes('/api/sensors/gas')) {
        return Promise.resolve({
            json: () => Promise.resolve({ status: 'success', sensor: window.mockAPI.gas })
        });
    }
    if (url.includes('/api/alerts')) {
        return Promise.resolve({
            json: () => Promise.resolve({ status: 'success', alerts: window.mockAPI.alerts })
        });
    }
    if (url.includes('/api/auth/me')) {
        return Promise.resolve({
            json: () => Promise.resolve({ authenticated: true, user: { username: 'Admin', role: 'admin' } })
        });
    }
    if (url.includes('/api/auth/logout')) {
        return Promise.resolve({
            json: () => Promise.resolve({ status: 'success' })
        });
    }
    
    // For other requests, use original fetch
    return originalFetch.apply(this, arguments);
};
