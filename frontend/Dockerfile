# DATAPOLIS PRO - Frontend Development Dockerfile (moved into frontend context)

FROM node:20-alpine

LABEL maintainer="DATAPOLIS SpA"
LABEL version="2.5.0"

RUN apk add --no-cache \
    git \
    curl

WORKDIR /app

# Copy package files and install
COPY package*.json ./
RUN npm ci

# Copy application code
COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
