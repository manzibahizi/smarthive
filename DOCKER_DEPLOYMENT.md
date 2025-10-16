# 🐝 Smart Hive Solution - Docker Deployment

## Quick Start

### Option 1: GitHub Actions (Automatic)
1. Push your code to GitHub
2. GitHub Actions will automatically build and push Docker image
3. Image will be available at `ghcr.io/your-username/smart-hive-solution`

### Option 2: Server Build (Manual)
```bash
# Linux/Mac
chmod +x deploy-server.sh
./deploy-server.sh

# Windows
deploy-server.bat
```

### Option 3: Docker Compose (Development)
```bash
docker-compose up --build -d
```

## Environment Variables

Create a `.env` file with:
```env
DB_HOST=your-database-host
DB_NAME=smart_hive
DB_USER=your-db-user
DB_PASS=your-secure-password
APP_ENV=production
```

## Access Points

- **Application:** http://localhost:8080
- **phpMyAdmin:** http://localhost:8081 (if using docker-compose)
- **Default Login:** admin / password

## Docker Commands

```bash
# Build image
docker build -f Dockerfile.php -t smart-hive-solution:latest .

# Run container
docker run -d --name smart-hive-app -p 8080:80 smart-hive-solution:latest

# View logs
docker logs smart-hive-app

# Stop container
docker stop smart-hive-app

# Remove container
docker rm smart-hive-app
```

## Production Deployment

For production servers:
1. Set environment variables
2. Use external database
3. Configure SSL/TLS
4. Set up monitoring
5. Configure backups

## Troubleshooting

- Check container logs: `docker logs smart-hive-app`
- Verify database connection
- Check port availability
- Review environment variables
