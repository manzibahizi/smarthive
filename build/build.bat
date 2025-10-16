@echo off
REM Smart Hive Solution Build Script for Windows
echo 🚀 Building Smart Hive Solution...

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker is not running. Please start Docker and try again.
    exit /b 1
)

REM Create build directories
if not exist "build\docker" mkdir "build\docker"
if not exist "build\nginx" mkdir "build\nginx"
if not exist "build\php" mkdir "build\php"
if not exist "build\mysql" mkdir "build\mysql"
if not exist "build\production" mkdir "build\production"

REM Copy environment file if it doesn't exist
if not exist ".env" (
    echo [WARNING] .env file not found. Creating from template...
    copy "env.example" ".env"
)

REM Build the Docker image
echo [INFO] Building Docker image...
docker build -f build\docker\Dockerfile -t smart-hive-solution:latest .

if %errorlevel% equ 0 (
    echo [INFO] ✅ Docker image built successfully!
) else (
    echo [ERROR] ❌ Docker build failed!
    exit /b 1
)

REM Create production build
echo [INFO] Creating production build...
docker run --rm -v "%cd%\build\production:/app" smart-hive-solution:latest sh -c "cp -r /var/www/html/* /app/ && chown -R 1000:1000 /app"

if %errorlevel% equ 0 (
    echo [INFO] ✅ Production build created in build\production\
) else (
    echo [WARNING] ⚠️ Production build creation failed, but Docker image is ready
)

REM Show build summary
echo.
echo [INFO] Build completed successfully!
echo.
echo 📦 Available commands:
echo   docker-compose up -d          # Start all services
echo   docker-compose down           # Stop all services
echo   docker-compose logs -f app    # View application logs
echo   docker-compose exec app sh    # Access application container
echo.
echo 🌐 Access points:
echo   Application: http://localhost:8080
echo   phpMyAdmin:  http://localhost:8081
echo   MySQL:       localhost:3306
echo.
echo 🔧 Default credentials:
echo   Admin user: Admin / Admin123
echo   MySQL root: root / smart_hive_password
echo.
pause
