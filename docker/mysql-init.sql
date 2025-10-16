-- Smart Hive Solution Database Initialization
-- This script creates the necessary tables for the application

USE smart_hive;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sensors table
CREATE TABLE IF NOT EXISTS sensors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id VARCHAR(50) UNIQUE NOT NULL,
    sensor_key VARCHAR(255) NOT NULL,
    hive_id VARCHAR(50) NOT NULL,
    location VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sensor data table
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    hive_id VARCHAR(50) NOT NULL,
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    gas_level DECIMAL(5,2),
    hive_weight DECIMAL(8,2),
    battery_level DECIMAL(5,2),
    signal_strength DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sensor_id (sensor_id),
    INDEX idx_hive_id (hive_id),
    INDEX idx_recorded_at (recorded_at)
);

-- Hive status table
CREATE TABLE IF NOT EXISTS hive_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hive_id VARCHAR(50) UNIQUE NOT NULL,
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    gas_level DECIMAL(5,2),
    hive_weight DECIMAL(8,2),
    battery_level DECIMAL(5,2),
    signal_strength DECIMAL(5,2),
    health_status ENUM('healthy', 'warning', 'critical') DEFAULT 'healthy',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hive_id (hive_id),
    INDEX idx_health_status (health_status)
);

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hive_id VARCHAR(50) NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hive_id (hive_id),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at)
);

-- Insert default admin user
INSERT INTO users (username, password, email, phone, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@smarthive.com', '+1234567890', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- Insert sample sensor
INSERT INTO sensors (sensor_id, sensor_key, hive_id, location) 
VALUES ('SENSOR_001', 'sensor_key_123', 'HIVE_001', 'Main Apiary')
ON DUPLICATE KEY UPDATE sensor_id=sensor_id;
