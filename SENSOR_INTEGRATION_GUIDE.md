# Smart Hive Solution - Sensor Integration Guide

## Overview
This guide explains how to integrate IoT sensors with the Smart Hive Solution for real-time hive monitoring.

## API Endpoints

### 1. Sensor Registration
**Endpoint:** `POST /api/sensor/register`
**Authentication:** Admin required
**Purpose:** Register new sensors in the system

```json
{
    "sensor_id": "sensor_001",
    "hive_id": "hive_001", 
    "sensor_type": "multi",
    "location": "Field A, Zone 1"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Sensor registered successfully",
    "sensor_id": "sensor_001",
    "sensor_key": "abc123def456..."
}
```

### 2. Send Sensor Data
**Endpoint:** `POST /api/sensor/data`
**Authentication:** Sensor ID + Key
**Purpose:** Send real-time sensor readings

```json
{
    "sensor_id": "sensor_001",
    "sensor_key": "abc123def456...",
    "hive_id": "hive_001",
    "temperature": 25.5,
    "humidity": 65.2,
    "gas_level": 45.0,
    "hive_weight": 12.5,
    "battery_level": 85,
    "signal_strength": 75
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Sensor data recorded"
}
```

### 3. Check Sensor Status
**Endpoint:** `GET /api/sensor/status?sensor_id=sensor_001`
**Purpose:** Get latest sensor data and configuration

**Response:**
```json
{
    "status": "success",
    "sensor_id": "sensor_001",
    "latest_data": {
        "temperature": 25.5,
        "humidity": 65.2,
        "gas_level": 45.0,
        "hive_weight": 12.5,
        "battery_level": 85,
        "signal_strength": 75,
        "timestamp": "2024-01-15T10:30:00Z"
    },
    "sensor_config": {
        "sensor_id": "sensor_001",
        "hive_id": "hive_001",
        "sensor_type": "multi",
        "location": "Field A, Zone 1",
        "is_active": true
    }
}
```

## Sensor Data Fields

| Field | Type | Description | Range | Unit |
|-------|------|-------------|-------|------|
| `temperature` | decimal | Ambient temperature | -40 to 85 | °C |
| `humidity` | decimal | Relative humidity | 0 to 100 | % |
| `gas_level` | decimal | Gas concentration | 0 to 1000 | ppm |
| `hive_weight` | decimal | Total hive weight | 0 to 100 | kg |
| `battery_level` | integer | Battery charge | 0 to 100 | % |
| `signal_strength` | integer | Signal quality | 0 to 100 | % |

## Alert Thresholds

The system automatically creates alerts based on these thresholds:

### Temperature Alerts
- **Critical Low:** < 10°C
- **Warning Low:** < 15°C  
- **Warning High:** > 35°C
- **Critical High:** > 40°C

### Humidity Alerts
- **Critical Low:** < 20%
- **Warning Low:** < 30%
- **Warning High:** > 80%
- **Critical High:** > 90%

### Gas Level Alerts
- **Warning:** > 100 ppm
- **Critical:** > 200 ppm

### Battery Alerts
- **Warning:** < 20%
- **Critical:** < 10%

## Sensor Implementation Examples

### Arduino/ESP32 Example

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// Configuration
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
const char* serverUrl = "https://your-domain.com/api/sensor/data";

// Sensor credentials
const char* sensorId = "sensor_001";
const char* sensorKey = "abc123def456...";
const char* hiveId = "hive_001";

// Sensor pins
#define TEMP_SENSOR_PIN A0
#define HUMIDITY_SENSOR_PIN A1
#define GAS_SENSOR_PIN A2
#define WEIGHT_SENSOR_PIN A3

void setup() {
    Serial.begin(115200);
    WiFi.begin(ssid, password);
    
    while (WiFi.status() != WL_CONNECTED) {
        delay(1000);
        Serial.println("Connecting to WiFi...");
    }
    
    Serial.println("WiFi connected!");
}

void loop() {
    if (WiFi.status() == WL_CONNECTED) {
        sendSensorData();
    }
    
    delay(300000); // Send data every 5 minutes
}

void sendSensorData() {
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");
    
    // Read sensor values
    float temperature = readTemperature();
    float humidity = readHumidity();
    float gasLevel = readGasLevel();
    float hiveWeight = readHiveWeight();
    int batteryLevel = getBatteryLevel();
    int signalStrength = WiFi.RSSI();
    
    // Create JSON payload
    DynamicJsonDocument doc(1024);
    doc["sensor_id"] = sensorId;
    doc["sensor_key"] = sensorKey;
    doc["hive_id"] = hiveId;
    doc["temperature"] = temperature;
    doc["humidity"] = humidity;
    doc["gas_level"] = gasLevel;
    doc["hive_weight"] = hiveWeight;
    doc["battery_level"] = batteryLevel;
    doc["signal_strength"] = signalStrength;
    
    String jsonString;
    serializeJson(doc, jsonString);
    
    int httpResponseCode = http.POST(jsonString);
    
    if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.println("Response: " + response);
    } else {
        Serial.println("Error sending data: " + String(httpResponseCode));
    }
    
    http.end();
}

float readTemperature() {
    // Implement temperature sensor reading
    int rawValue = analogRead(TEMP_SENSOR_PIN);
    float voltage = rawValue * (3.3 / 4095.0);
    float temperature = (voltage - 0.5) * 100; // TMP36 sensor
    return temperature;
}

float readHumidity() {
    // Implement humidity sensor reading
    int rawValue = analogRead(HUMIDITY_SENSOR_PIN);
    float humidity = (rawValue / 4095.0) * 100;
    return humidity;
}

float readGasLevel() {
    // Implement gas sensor reading
    int rawValue = analogRead(GAS_SENSOR_PIN);
    float gasLevel = (rawValue / 4095.0) * 1000;
    return gasLevel;
}

float readHiveWeight() {
    // Implement weight sensor reading
    int rawValue = analogRead(WEIGHT_SENSOR_PIN);
    float weight = (rawValue / 4095.0) * 100; // Scale to 0-100kg
    return weight;
}

int getBatteryLevel() {
    // Implement battery level reading
    int rawValue = analogRead(A4);
    int batteryLevel = (rawValue / 4095.0) * 100;
    return batteryLevel;
}
```

### Python Example (Raspberry Pi)

```python
import requests
import json
import time
import random
from datetime import datetime

class SmartHiveSensor:
    def __init__(self, sensor_id, sensor_key, hive_id, server_url):
        self.sensor_id = sensor_id
        self.sensor_key = sensor_key
        self.hive_id = hive_id
        self.server_url = server_url
        
    def read_sensors(self):
        """Read sensor values (implement actual sensor reading)"""
        return {
            'temperature': round(random.uniform(20, 30), 1),
            'humidity': round(random.uniform(40, 80), 1),
            'gas_level': round(random.uniform(20, 100), 1),
            'hive_weight': round(random.uniform(10, 20), 1),
            'battery_level': round(random.uniform(80, 100)),
            'signal_strength': round(random.uniform(70, 100))
        }
    
    def send_data(self):
        """Send sensor data to server"""
        sensor_data = self.read_sensors()
        
        payload = {
            'sensor_id': self.sensor_id,
            'sensor_key': self.sensor_key,
            'hive_id': self.hive_id,
            **sensor_data
        }
        
        try:
            response = requests.post(
                f"{self.server_url}/api/sensor/data",
                json=payload,
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                print(f"Data sent successfully: {result}")
                return True
            else:
                print(f"Error sending data: {response.status_code} - {response.text}")
                return False
                
        except requests.exceptions.RequestException as e:
            print(f"Network error: {e}")
            return False
    
    def run(self, interval=300):
        """Run sensor loop"""
        print(f"Starting sensor {self.sensor_id} for hive {self.hive_id}")
        
        while True:
            try:
                success = self.send_data()
                if success:
                    print(f"Data sent at {datetime.now()}")
                else:
                    print(f"Failed to send data at {datetime.now()}")
                    
            except KeyboardInterrupt:
                print("Sensor stopped by user")
                break
            except Exception as e:
                print(f"Unexpected error: {e}")
            
            time.sleep(interval)

# Usage
if __name__ == "__main__":
    sensor = SmartHiveSensor(
        sensor_id="sensor_001",
        sensor_key="abc123def456...",
        hive_id="hive_001",
        server_url="https://your-domain.com"
    )
    
    sensor.run(interval=300)  # Send data every 5 minutes
```

### Node.js Example

```javascript
const axios = require('axios');

class SmartHiveSensor {
    constructor(sensorId, sensorKey, hiveId, serverUrl) {
        this.sensorId = sensorId;
        this.sensorKey = sensorKey;
        this.hiveId = hiveId;
        this.serverUrl = serverUrl;
    }
    
    async readSensors() {
        // Implement actual sensor reading here
        return {
            temperature: Math.round((Math.random() * 10 + 20) * 10) / 10,
            humidity: Math.round((Math.random() * 40 + 40) * 10) / 10,
            gas_level: Math.round((Math.random() * 80 + 20) * 10) / 10,
            hive_weight: Math.round((Math.random() * 10 + 10) * 10) / 10,
            battery_level: Math.round(Math.random() * 20 + 80),
            signal_strength: Math.round(Math.random() * 30 + 70)
        };
    }
    
    async sendData() {
        try {
            const sensorData = await this.readSensors();
            
            const payload = {
                sensor_id: this.sensorId,
                sensor_key: this.sensorKey,
                hive_id: this.hiveId,
                ...sensorData
            };
            
            const response = await axios.post(
                `${this.serverUrl}/api/sensor/data`,
                payload,
                { timeout: 30000 }
            );
            
            console.log('Data sent successfully:', response.data);
            return true;
            
        } catch (error) {
            console.error('Error sending data:', error.message);
            return false;
        }
    }
    
    async run(interval = 300000) { // 5 minutes default
        console.log(`Starting sensor ${this.sensorId} for hive ${this.hiveId}`);
        
        while (true) {
            try {
                const success = await this.sendData();
                if (success) {
                    console.log(`Data sent at ${new Date().toISOString()}`);
                } else {
                    console.log(`Failed to send data at ${new Date().toISOString()}`);
                }
            } catch (error) {
                console.error('Unexpected error:', error);
            }
            
            await new Promise(resolve => setTimeout(resolve, interval));
        }
    }
}

// Usage
const sensor = new SmartHiveSensor(
    'sensor_001',
    'abc123def456...',
    'hive_001',
    'https://your-domain.com'
);

sensor.run(300000); // Send data every 5 minutes
```

## Security Considerations

1. **Sensor Authentication:** Each sensor has a unique ID and key
2. **HTTPS Only:** All communication must use HTTPS
3. **Rate Limiting:** API endpoints have rate limiting to prevent abuse
4. **Data Validation:** All sensor data is validated before storage
5. **Access Control:** Admin functions require authentication

## Troubleshooting

### Common Issues

1. **Authentication Failed**
   - Check sensor ID and key
   - Ensure sensor is registered and active
   - Verify credentials in admin panel

2. **Connection Timeout**
   - Check network connectivity
   - Verify server URL and SSL certificate
   - Check firewall settings

3. **Data Not Appearing**
   - Check sensor registration status
   - Verify data format and field names
   - Check server logs for errors

4. **High Battery Drain**
   - Increase transmission interval
   - Optimize sensor reading frequency
   - Use sleep modes between readings

### Debug Mode

Enable debug logging by setting `APP_DEBUG=true` in the environment file.

## Support

For technical support or questions about sensor integration:
- Check the admin panel at `/admin-sensors.html`
- Review server logs in `/var/log/smart_hive/`
- Contact system administrator

## Best Practices

1. **Data Transmission**
   - Send data every 5-15 minutes
   - Include battery level and signal strength
   - Implement retry logic for failed transmissions

2. **Power Management**
   - Use deep sleep modes between readings
   - Monitor battery levels closely
   - Implement low battery alerts

3. **Data Quality**
   - Validate sensor readings before transmission
   - Handle sensor failures gracefully
   - Implement data smoothing for noisy sensors

4. **Network Reliability**
   - Implement connection retry logic
   - Store data locally if network fails
   - Use multiple transmission attempts
