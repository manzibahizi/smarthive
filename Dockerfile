# Simple static site deployment
FROM nginx:alpine

# Copy static files to nginx directory
COPY public/ /usr/share/nginx/html/

# Expose port
EXPOSE 80

# Start nginx
CMD ["nginx", "-g", "daemon off;"]