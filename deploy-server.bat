@echo off
REM Smart Hive Solution - Windows Server Deployment Script

setlocal enabledelayedexpansion

echo 🐝 Smart Hive Solution - Server Deployment
echo ==========================================

REM Configuration
set IMAGE_NAME=smart-hive-solution
set TAG=latest
set CONTAINER_NAME=smart-hive-app
set PORT=8080

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker is not installed. Please install Docker Desktop first.
    pause
    exit /b 1
)

echo ✅ Docker is available

REM Stop existing container if running
docker ps -q -f name=%CONTAINER_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo ⚠️  Stopping existing container...
    docker stop %CONTAINER_NAME%
)

REM Remove existing container if exists
docker ps -aq -f name=%CONTAINER_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo ⚠️  Removing existing container...
    docker rm %CONTAINER_NAME%
)

REM Build the Docker image
echo ✅ Building Docker image...
docker build -f Dockerfile.php -t %IMAGE_NAME%:%TAG% .

if %errorlevel% neq 0 (
    echo ❌ Failed to build Docker image
    pause
    exit /b 1
)

echo ✅ Docker image built successfully

REM Run the container
echo ✅ Starting container...
docker run -d --name %CONTAINER_NAME% -p %PORT%:80 --restart unless-stopped %IMAGE_NAME%:%TAG%

if %errorlevel% neq 0 (
    echo ❌ Failed to start container
    pause
    exit /b 1
)

echo ✅ Container started successfully

REM Wait for application to start
echo ✅ Waiting for application to start...
timeout /t 10 /nobreak >nul

REM Check if container is running
docker ps -q -f name=%CONTAINER_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Container is running
    
    REM Test the application
    curl -f http://localhost:%PORT% >nul 2>&1
    if %errorlevel% equ 0 (
        echo ✅ Application is responding at http://localhost:%PORT%
    ) else (
        echo ⚠️  Application may not be fully ready yet
    )
    
    echo.
    echo 🎉 Deployment completed successfully!
    echo.
    echo 📱 Access your application:
    echo    • URL: http://localhost:%PORT%
    echo    • Container: %CONTAINER_NAME%
    echo.
    echo 📋 Useful commands:
    echo    • View logs: docker logs %CONTAINER_NAME%
    echo    • Stop: docker stop %CONTAINER_NAME%
    echo    • Restart: docker restart %CONTAINER_NAME%
    echo    • Shell access: docker exec -it %CONTAINER_NAME% sh
    
) else (
    echo ❌ Container failed to start
    echo Check logs with: docker logs %CONTAINER_NAME%
    pause
    exit /b 1
)

pause
