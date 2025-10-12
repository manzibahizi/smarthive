# Simple static site deployment
FROM nginx:alpine

# Copy static files to nginx directory
COPY public/ /usr/share/nginx/html/

# Copy nginx configuration for SPA routing
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Expose port
EXPOSE 80

# Start nginx
CMD ["nginx", "-g", "daemon off;"]