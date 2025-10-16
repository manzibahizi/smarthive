#!/bin/bash

# Smart Hive Solution Build Script
echo "🚀 Building Smart Hive Solution..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again."
    exit 1
fi

# Create build directory if it doesn't exist
mkdir -p build/docker build/nginx build/php build/mysql

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    print_warning ".env file not found. Creating from template..."
    cp env.example .env
fi

# Build the Docker image
print_status "Building Docker image..."
docker build -f build/docker/Dockerfile -t smart-hive-solution:latest .

if [ $? -eq 0 ]; then
    print_status "✅ Docker image built successfully!"
else
    print_error "❌ Docker build failed!"
    exit 1
fi

# Create production build
print_status "Creating production build..."
docker run --rm -v "$(pwd)/build/production:/app" smart-hive-solution:latest sh -c "
    cp -r /var/www/html/* /app/
    chown -R 1000:1000 /app
"

if [ $? -eq 0 ]; then
    print_status "✅ Production build created in build/production/"
else
    print_warning "⚠️ Production build creation failed, but Docker image is ready"
fi

# Show build summary
echo ""
print_status "Build completed successfully!"
echo ""
echo "📦 Available commands:"
echo "  docker-compose up -d          # Start all services"
echo "  docker-compose down           # Stop all services"
echo "  docker-compose logs -f app    # View application logs"
echo "  docker-compose exec app sh    # Access application container"
echo ""
echo "🌐 Access points:"
echo "  Application: http://localhost:8080"
echo "  phpMyAdmin:  http://localhost:8081"
echo "  MySQL:       localhost:3306"
echo ""
echo "🔧 Default credentials:"
echo "  Admin user: Admin / Admin123"
echo "  MySQL root: root / smart_hive_password"
echo ""
