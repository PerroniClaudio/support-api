#!/bin/bash

# 🚀 Deploy Spreetzitt su Google Cloud Run
# Perfetto per e2-medium con scaling automatico

set -euo pipefail

# Configurazione
PROJECT_ID="your-gcp-project-id"
REGION="europe-west1"
SERVICE_NAME="spreetzitt"

# Colori
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }

deploy_backend() {
    info "Deploy backend su Cloud Run..."
    
    # Build e push immagine
    gcloud builds submit \
        --tag gcr.io/$PROJECT_ID/spreetzitt-backend:latest \
        --file docker/prod.backend.dockerfile \
        .
    
    # Deploy su Cloud Run
    gcloud run deploy spreetzitt-backend \
        --image gcr.io/$PROJECT_ID/spreetzitt-backend:latest \
        --platform managed \
        --region $REGION \
        --allow-unauthenticated \
        --memory 1Gi \
        --cpu 1 \
        --concurrency 100 \
        --min-instances 0 \
        --max-instances 10 \
        --set-env-vars "APP_ENV=production,DB_HOST=$DB_HOST,DB_DATABASE=$DB_DATABASE" \
        --set-secrets "APP_KEY=app_key:latest,DB_PASSWORD=db_password:latest"
    
    success "Backend deployato"
}

deploy_frontend() {
    info "Deploy frontend su Cloud Run..."
    
    # Build e push
    gcloud builds submit \
        --tag gcr.io/$PROJECT_ID/spreetzitt-frontend:latest \
        --file docker/prod.frontend.dockerfile \
        .
    
    # Deploy
    gcloud run deploy spreetzitt-frontend \
        --image gcr.io/$PROJECT_ID/spreetzitt-frontend:latest \
        --platform managed \
        --region $REGION \
        --allow-unauthenticated \
        --memory 512Mi \
        --cpu 1 \
        --concurrency 1000 \
        --min-instances 0 \
        --max-instances 5
    
    success "Frontend deployato"
}

setup_load_balancer() {
    info "Configuro Load Balancer..."
    
    # Crea load balancer con Cloud CDN
    gcloud compute url-maps create spreetzitt-lb \
        --default-service spreetzitt-frontend
    
    # Aggiungi regole per API
    gcloud compute url-maps add-path-matcher spreetzitt-lb \
        --path-matcher-name api-matcher \
        --path-rules "/api/*=spreetzitt-backend"
    
    success "Load Balancer configurato"
}

# Esegui deploy
deploy_backend
deploy_frontend
setup_load_balancer

echo
success "🎉 Deploy Cloud Run completato!"
info "Vantaggi:"
echo "  💰 Pay-per-use (no costi quando inattivo)"
echo "  📈 Auto-scaling 0-10 istanze"
echo "  🔧 Zero manutenzione infrastruttura"
echo "  🌐 CDN integrato"
echo
warning "⚠️  IMPORTANTE: Configura MeiliSearch separatamente!"
echo "Opzioni disponibili:"
echo "  1. MeiliSearch Cloud: https://cloud.meilisearch.com (raccomandato)"
echo "  2. VM dedicata: ./meilisearch-setup.sh vm"
echo "  3. Database full-text: ./meilisearch-setup.sh database"
