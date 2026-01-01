# =====================================================
# DATAPOLIS PRO - Frontend Production Dockerfile
# =====================================================

# Stage 1: Build
FROM node:20-alpine AS builder

WORKDIR /app

# Install build tools (if needed) and set production env for the build
ENV NODE_ENV=production

# Copy package files
COPY package*.json ./

# Install dependencies (including dev deps required for build)
RUN npm ci

# Copy source
COPY . .

# Production build
RUN npm run build

# Stage 2: Production
FROM nginx:alpine

LABEL maintainer="DATAPOLIS SpA"
LABEL version="2.5.0"

# Ensure curl available for healthchecks
RUN apk add --no-cache curl

# Copy built assets
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx config if present
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Set permissions
RUN chmod -R 755 /usr/share/nginx/html

# Healthcheck (use curl which we installed)
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/ || exit 1

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
