#!/bin/bash

# 🔍 Script per testare l'health del frontend prima del deploy
# Questo script testa il container frontend localmente per verificare
# che risponda correttamente agli healthcheck

set -e

echo "🔍 Testing Frontend Health Check..."

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Build dell'immagine
echo -e "${YELLOW}📦 Building frontend image...${NC}"
cd /Users/claudioperroni/Documents/ift/spreetzitt/server/frontend
docker build -f ../cloud-run/docker/frontend.dockerfile -t test-spreetzitt-frontend .

# Avvia il container in background
echo -e "${YELLOW}🚀 Starting container...${NC}"
CONTAINER_ID=$(docker run -d -p 8080:8080 test-spreetzitt-frontend)

# Attendi che il container si avvii
echo -e "${YELLOW}⏳ Waiting for container to start...${NC}"
sleep 10

# Test degli endpoint
echo -e "${YELLOW}🩺 Testing health endpoints...${NC}"

# Test startup probe
if curl -f -s http://localhost:8080/startup > /dev/null; then
    echo -e "${GREEN}✅ Startup probe: OK${NC}"
else
    echo -e "${RED}❌ Startup probe: FAILED${NC}"
    docker logs $CONTAINER_ID
    docker stop $CONTAINER_ID
    docker rm $CONTAINER_ID
    exit 1
fi

# Test health probe
if curl -f -s http://localhost:8080/health > /dev/null; then
    echo -e "${GREEN}✅ Health probe: OK${NC}"
else
    echo -e "${RED}❌ Health probe: FAILED${NC}"
    docker logs $CONTAINER_ID
    docker stop $CONTAINER_ID
    docker rm $CONTAINER_ID
    exit 1
fi

# Test home page
if curl -f -s http://localhost:8080/ > /dev/null; then
    echo -e "${GREEN}✅ Home page: OK${NC}"
else
    echo -e "${RED}❌ Home page: FAILED${NC}"
    docker logs $CONTAINER_ID
    docker stop $CONTAINER_ID
    docker rm $CONTAINER_ID
    exit 1
fi

# Cleanup
echo -e "${YELLOW}🧹 Cleaning up...${NC}"
docker stop $CONTAINER_ID
docker rm $CONTAINER_ID

echo -e "${GREEN}🎉 All tests passed! Frontend is ready for Cloud Run deployment.${NC}"
