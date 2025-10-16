#!/bin/bash

# Smart Hive Solution - Docker Setup Script
echo "🐝 Setting up Smart Hive Solution with Docker..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop first."
    exit 1
fi

echo "✅ Docker is running"

# Build and start containers
echo "🔨 Building and starting containers..."
docker-compose up --build -d

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 10

# Check if containers are running
echo "📊 Container Status:"
docker-compose ps

# Test the application
echo "🧪 Testing application..."
sleep 5

# Check if the app is responding
if curl -s http://localhost:8080 > /dev/null; then
    echo "✅ Application is running at http://localhost:8080"
else
    echo "❌ Application is not responding"
fi

# Check if phpMyAdmin is responding
if curl -s http://localhost:8081 > /dev/null; then
    echo "✅ phpMyAdmin is running at http://localhost:8081"
else
    echo "❌ phpMyAdmin is not responding"
fi

echo ""
echo "🎉 Setup complete!"
echo ""
echo "📱 Access your application:"
echo "   • Frontend: http://localhost:8080"
echo "   • phpMyAdmin: http://localhost:8081"
echo "   • Database: localhost:3306"
echo ""
echo "🔑 Default credentials:"
echo "   • Username: admin"
echo "   • Password: password"
echo ""
echo "📋 Useful commands:"
echo "   • Stop: docker-compose down"
echo "   • Restart: docker-compose restart"
echo "   • Logs: docker-compose logs -f"
echo "   • Shell access: docker-compose exec app sh"
