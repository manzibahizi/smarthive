-- Smart Hive Solution Database Initialization
CREATE DATABASE IF NOT EXISTS smart_hive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_hive;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT "",
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM("admin","user") NOT NULL DEFAULT "user",
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user
INSERT IGNORE INTO users (username, password_hash, email, role, is_active) 
VALUES ('Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@smarthive.com', 'admin', 1);

-- Hives table
CREATE TABLE IF NOT EXISTS hives (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sensors table
CREATE TABLE IF NOT EXISTS sensors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hive_id INT UNSIGNED NOT NULL,
    type ENUM('temperature','humidity','weight','gas','sound') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hive_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    level ENUM('warning','critical') NOT NULL,
    message TEXT NOT NULL,
    value DECIMAL(10,2) DEFAULT NULL,
    unit VARCHAR(20) DEFAULT NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (hive_id) REFERENCES hives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training applications table
CREATE TABLE IF NOT EXISTS training_applications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    experience TEXT NOT NULL,
    note TEXT,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better performance
CREATE INDEX idx_sensors_hive_id ON sensors(hive_id);
CREATE INDEX idx_sensors_type ON sensors(type);
CREATE INDEX idx_sensors_created_at ON sensors(created_at);
CREATE INDEX idx_alerts_hive_id ON alerts(hive_id);
CREATE INDEX idx_alerts_level ON alerts(level);
CREATE INDEX idx_alerts_is_resolved ON alerts(is_resolved);
CREATE INDEX idx_training_applications_training_id ON training_applications(training_id);
CREATE INDEX idx_training_applications_status ON training_applications(status);
