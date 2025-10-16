@echo off
REM Smart Hive Solution Deployment Script for Windows
echo 🚀 Deploying Smart Hive Solution...

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker is not running. Please start Docker and try again.
    exit /b 1
)

REM Stop existing containers
echo [DEPLOY] Stopping existing containers...
docker-compose down

REM Remove old images if --clean flag is provided
if "%1"=="--clean" (
    echo [DEPLOY] Cleaning up old images...
    docker image prune -f
    docker rmi smart-hive-solution:latest 2>nul
)

REM Build and start services
echo [DEPLOY] Building and starting services...
docker-compose up -d --build

REM Wait for services to be ready
echo [DEPLOY] Waiting for services to be ready...
timeout /t 10 /nobreak >nul

REM Check if services are running
echo [DEPLOY] Checking service status...
docker-compose ps

REM Test application health
echo [DEPLOY] Testing application health...
curl -f http://localhost:8080/health >nul 2>&1
if %errorlevel% equ 0 (
    echo [INFO] ✅ Application is healthy!
) else (
    echo [WARNING] ⚠️ Application health check failed, but services are running
)

REM Show deployment summary
echo.
echo [INFO] 🎉 Deployment completed!
echo.
echo 🌐 Access your application:
echo   Main App:    http://localhost:8080
echo   phpMyAdmin:  http://localhost:8081
echo   MySQL:       localhost:3306
echo.
echo 🔧 Management commands:
echo   docker-compose logs -f app     # View application logs
echo   docker-compose exec app sh     # Access application container
echo   docker-compose exec mysql mysql -u root -p smart_hive  # Access database
echo   docker-compose restart app    # Restart application
echo   docker-compose down           # Stop all services
echo.
echo 📊 Default login credentials:
echo   Username: Admin
echo   Password: Admin123
echo.
pause
