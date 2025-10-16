#!/bin/bash

# Smart Hive Solution - Production Deployment Script
# This script sets up the Smart Hive Solution for production hosting

set -e

echo "🚀 Starting Smart Hive Solution Deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/smart_hive_solution"
DOMAIN_NAME="your-domain.com"
DB_NAME="smart_hive"
DB_USER="smart_hive_user"
DB_PASS=$(openssl rand -base64 32)

echo -e "${BLUE}📋 Deployment Configuration:${NC}"
echo "Project Directory: $PROJECT_DIR"
echo "Domain: $DOMAIN_NAME"
echo "Database: $DB_NAME"
echo "Database User: $DB_USER"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Please run as root (use sudo)${NC}"
    exit 1
fi

# Update system packages
echo -e "${YELLOW}📦 Updating system packages...${NC}"
apt update && apt upgrade -y

# Install required packages
echo -e "${YELLOW}📦 Installing required packages...${NC}"
apt install -y nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip php8.1-opcache mysql-server unzip curl

# Create project directory
echo -e "${YELLOW}📁 Creating project directory...${NC}"
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# Copy project files (assuming they're in current directory)
echo -e "${YELLOW}📁 Copying project files...${NC}"
cp -r . $PROJECT_DIR/

# Set proper permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 777 $PROJECT_DIR/storage
chmod -R 777 $PROJECT_DIR/public

# Configure MySQL
echo -e "${YELLOW}🗄️ Configuring MySQL...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Create environment file
echo -e "${YELLOW}⚙️ Creating environment configuration...${NC}"
cat > $PROJECT_DIR/.env << EOF
# Smart Hive Solution - Production Configuration
DB_HOST=localhost
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS

APP_NAME="Smart Hive Solution"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$DOMAIN_NAME

# Security
APP_KEY=$(openssl rand -base64 32)
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Sensor Configuration
SENSOR_RATE_LIMIT=10
SENSOR_TIMEOUT=30
SENSOR_RETRY_ATTEMPTS=3

# Monitoring
MONITORING_ENABLED=true
MONITORING_INTERVAL=60
DATA_RETENTION_DAYS=365

# Logging
LOG_LEVEL=info
LOG_FILE=/var/log/smart_hive/app.log

# Performance
CACHE_ENABLED=true
CACHE_TTL=300
OPCACHE_ENABLED=true

# SSL
SSL_ENABLED=true
SSL_FORCE_REDIRECT=true

# CORS
CORS_ALLOWED_ORIGINS=*
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60
EOF

# Configure PHP-FPM
echo -e "${YELLOW}🐘 Configuring PHP-FPM...${NC}"
cat > /etc/php/8.1/fpm/pool.d/smart_hive.conf << EOF
[smart_hive]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm-smart_hive.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 1000
EOF

# Configure Nginx
echo -e "${YELLOW}🌐 Configuring Nginx...${NC}"
cat > /etc/nginx/sites-available/smart_hive << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN_NAME www.$DOMAIN_NAME;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN_NAME www.$DOMAIN_NAME;
    
    # SSL Configuration (you'll need to add your SSL certificates)
    # ssl_certificate /etc/ssl/certs/$DOMAIN_NAME.crt;
    # ssl_certificate_key /etc/ssl/private/$DOMAIN_NAME.key;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    root $PROJECT_DIR/public;
    index index.php index.html;
    
    # Logging
    access_log /var/log/nginx/smart_hive_access.log;
    error_log /var/log/nginx/smart_hive_error.log;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/json;
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # API routes with CORS for sensors
    location /api/ {
        try_files \$uri /index.php\$is_args\$args;
        
        add_header Access-Control-Allow-Origin "*" always;
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" always;
        
        if (\$request_method = 'OPTIONS') {
            add_header Access-Control-Allow-Origin "*";
            add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
            add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With";
            add_header Access-Control-Max-Age 1728000;
            add_header Content-Type "text/plain charset=UTF-8";
            add_header Content-Length 0;
            return 204;
        }
    }
    
    # Handle PHP files
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.1-fpm-smart_hive.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    
    # Main application
    location / {
        try_files \$uri \$uri/ /index.php\$is_args\$args;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~ /(config|app|storage|vendor)/ {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/smart_hive /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t

# Create log directory
mkdir -p /var/log/smart_hive
chown www-data:www-data /var/log/smart_hive

# Create systemd service for monitoring
cat > /etc/systemd/system/smart-hive-monitor.service << EOF
[Unit]
Description=Smart Hive Solution Monitor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/php $PROJECT_DIR/monitor.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

# Create monitoring script
cat > $PROJECT_DIR/monitor.php << 'EOF'
<?php
// Smart Hive Solution - System Monitor
require_once __DIR__ . '/app/Database.php';

while (true) {
    try {
        $pdo = \App\Database::getConnection();
        
        // Check sensor connectivity
        $stmt = $pdo->query('SELECT COUNT(*) as active_sensors FROM sensors WHERE is_active = 1');
        $activeSensors = $stmt->fetch()['active_sensors'];
        
        // Check for stale data (no updates in last hour)
        $stmt = $pdo->query('SELECT COUNT(*) as stale_sensors FROM sensors s LEFT JOIN sensor_data sd ON s.sensor_id = sd.sensor_id WHERE s.is_active = 1 AND (sd.timestamp IS NULL OR sd.timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR))');
        $staleSensors = $stmt->fetch()['stale_sensors'];
        
        // Log system status
        error_log("Smart Hive Monitor: Active sensors: $activeSensors, Stale sensors: $staleSensors");
        
        // Clean old data (keep only last 30 days)
        $stmt = $pdo->prepare('DELETE FROM sensor_data WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $stmt->execute();
        
        sleep(300); // Check every 5 minutes
    } catch (Exception $e) {
        error_log("Smart Hive Monitor Error: " . $e->getMessage());
        sleep(60); // Wait 1 minute on error
    }
}
EOF

# Set up log rotation
cat > /etc/logrotate.d/smart_hive << EOF
/var/log/smart_hive/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload nginx
    endscript
}
EOF

# Start services
echo -e "${YELLOW}🔄 Starting services...${NC}"
systemctl enable nginx
systemctl enable php8.1-fpm
systemctl enable mysql
systemctl start nginx
systemctl start php8.1-fpm
systemctl start mysql

# Enable monitoring service
systemctl enable smart-hive-monitor
systemctl start smart-hive-monitor

# Create backup script
cat > /usr/local/bin/smart-hive-backup << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/smart_hive"
DATE=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/var/www/smart_hive_solution"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump smart_hive > $BACKUP_DIR/database_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C $PROJECT_DIR .

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /usr/local/bin/smart-hive-backup

# Set up daily backup cron job
echo "0 2 * * * /usr/local/bin/smart-hive-backup" | crontab -u root -

echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo "1. Configure SSL certificates for HTTPS"
echo "2. Update DNS records to point to this server"
echo "3. Register sensors using the admin interface"
echo "4. Configure sensor devices to send data to: https://$DOMAIN_NAME/api/sensor/data"
echo ""
echo -e "${BLUE}🔗 Important URLs:${NC}"
echo "Main Application: https://$DOMAIN_NAME"
echo "Admin Panel: https://$DOMAIN_NAME/admin/users"
echo "Sensor Management: https://$DOMAIN_NAME/admin-sensors.html"
echo "API Documentation: https://$DOMAIN_NAME/api/"
echo ""
echo -e "${BLUE}🔐 Database Credentials:${NC}"
echo "Database: $DB_NAME"
echo "Username: $DB_USER"
echo "Password: $DB_PASS"
echo ""
echo -e "${YELLOW}⚠️ Remember to:${NC}"
echo "- Update the domain name in nginx configuration"
echo "- Install SSL certificates"
echo "- Configure firewall rules"
echo "- Test sensor connectivity"
echo ""
echo -e "${GREEN}🎉 Smart Hive Solution is ready for production!${NC}"
