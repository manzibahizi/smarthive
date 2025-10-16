@echo off
echo 🐝 Setting up Smart Hive Solution with Docker...

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker is not running. Please start Docker Desktop first.
    pause
    exit /b 1
)

echo ✅ Docker is running

REM Build and start containers
echo 🔨 Building and starting containers...
docker-compose up --build -d

REM Wait for services to be ready
echo ⏳ Waiting for services to start...
timeout /t 10 /nobreak >nul

REM Check container status
echo 📊 Container Status:
docker-compose ps

REM Test the application
echo 🧪 Testing application...
timeout /t 5 /nobreak >nul

REM Check if the app is responding
curl -s http://localhost:8080 >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Application is running at http://localhost:8080
) else (
    echo ❌ Application is not responding
)

REM Check if phpMyAdmin is responding
curl -s http://localhost:8081 >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ phpMyAdmin is running at http://localhost:8081
) else (
    echo ❌ phpMyAdmin is not responding
)

echo.
echo 🎉 Setup complete!
echo.
echo 📱 Access your application:
echo    • Frontend: http://localhost:8080
echo    • phpMyAdmin: http://localhost:8081
echo    • Database: localhost:3306
echo.
echo 🔑 Default credentials:
echo    • Username: admin
echo    • Password: password
echo.
echo 📋 Useful commands:
echo    • Stop: docker-compose down
echo    • Restart: docker-compose restart
echo    • Logs: docker-compose logs -f
echo    • Shell access: docker-compose exec app sh
echo.
pause
