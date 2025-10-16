#!/bin/bash

# Smart Hive Solution - Server Deployment Script
# This script builds and deploys the Docker image on the server

set -e

echo "🐝 Smart Hive Solution - Server Deployment"
echo "=========================================="

# Configuration
IMAGE_NAME="smart-hive-solution"
TAG="latest"
CONTAINER_NAME="smart-hive-app"
PORT="8080"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed. Please install Docker first."
    exit 1
fi

print_status "Docker is available"

# Stop existing container if running
if [ "$(docker ps -q -f name=$CONTAINER_NAME)" ]; then
    print_warning "Stopping existing container..."
    docker stop $CONTAINER_NAME
fi

# Remove existing container if exists
if [ "$(docker ps -aq -f name=$CONTAINER_NAME)" ]; then
    print_warning "Removing existing container..."
    docker rm $CONTAINER_NAME
fi

# Build the Docker image
print_status "Building Docker image..."
docker build -f Dockerfile.php -t $IMAGE_NAME:$TAG .

if [ $? -eq 0 ]; then
    print_status "Docker image built successfully"
else
    print_error "Failed to build Docker image"
    exit 1
fi

# Run the container
print_status "Starting container..."
docker run -d \
    --name $CONTAINER_NAME \
    -p $PORT:80 \
    --restart unless-stopped \
    -e DB_HOST=${DB_HOST:-localhost} \
    -e DB_NAME=${DB_NAME:-smart_hive} \
    -e DB_USER=${DB_USER:-root} \
    -e DB_PASS=${DB_PASS:-password} \
    $IMAGE_NAME:$TAG

if [ $? -eq 0 ]; then
    print_status "Container started successfully"
else
    print_error "Failed to start container"
    exit 1
fi

# Wait for container to be ready
print_status "Waiting for application to start..."
sleep 10

# Check if container is running
if [ "$(docker ps -q -f name=$CONTAINER_NAME)" ]; then
    print_status "Container is running"
    
    # Test the application
    if curl -f http://localhost:$PORT > /dev/null 2>&1; then
        print_status "Application is responding at http://localhost:$PORT"
    else
        print_warning "Application may not be fully ready yet"
    fi
    
    echo ""
    echo "🎉 Deployment completed successfully!"
    echo ""
    echo "📱 Access your application:"
    echo "   • URL: http://localhost:$PORT"
    echo "   • Container: $CONTAINER_NAME"
    echo ""
    echo "📋 Useful commands:"
    echo "   • View logs: docker logs $CONTAINER_NAME"
    echo "   • Stop: docker stop $CONTAINER_NAME"
    echo "   • Restart: docker restart $CONTAINER_NAME"
    echo "   • Shell access: docker exec -it $CONTAINER_NAME sh"
    
else
    print_error "Container failed to start"
    echo "Check logs with: docker logs $CONTAINER_NAME"
    exit 1
fi
