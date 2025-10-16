#!/bin/bash

# Smart Hive Solution Deployment Script
echo "🚀 Deploying Smart Hive Solution..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}[DEPLOY]${NC} $1"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again."
    exit 1
fi

# Stop existing containers
print_header "Stopping existing containers..."
docker-compose down

# Remove old images (optional)
if [ "$1" = "--clean" ]; then
    print_header "Cleaning up old images..."
    docker image prune -f
    docker rmi smart-hive-solution:latest 2>/dev/null || true
fi

# Build and start services
print_header "Building and starting services..."
docker-compose up -d --build

# Wait for services to be ready
print_header "Waiting for services to be ready..."
sleep 10

# Check if services are running
print_header "Checking service status..."
docker-compose ps

# Test application health
print_header "Testing application health..."
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    print_status "✅ Application is healthy!"
else
    print_warning "⚠️ Application health check failed, but services are running"
fi

# Show deployment summary
echo ""
print_status "🎉 Deployment completed!"
echo ""
echo "🌐 Access your application:"
echo "  Main App:    http://localhost:8080"
echo "  phpMyAdmin:  http://localhost:8081"
echo "  MySQL:       localhost:3306"
echo ""
echo "🔧 Management commands:"
echo "  docker-compose logs -f app     # View application logs"
echo "  docker-compose exec app sh     # Access application container"
echo "  docker-compose exec mysql mysql -u root -p smart_hive  # Access database"
echo "  docker-compose restart app    # Restart application"
echo "  docker-compose down           # Stop all services"
echo ""
echo "📊 Default login credentials:"
echo "  Username: Admin"
echo "  Password: Admin123"
echo ""
