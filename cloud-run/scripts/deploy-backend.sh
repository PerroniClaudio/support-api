#!/bin/bash
set -e

# 🚀 Spreetzitt Cloud Run - Deploy Solo Backend
# Deploy rapido del solo backend Laravel

echo "🚀 Deploy Backend Laravel"
echo "========================"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni helper
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Configurazione
PROJECT_ID=$(gcloud config get-value project)
REGION="europe-west1"
SERVICE_NAME="spreetzitt-backend"

# Carica configurazione da .env.prod
load_config() {
    if [[ -f "config/.env.prod" ]]; then
        source config/.env.prod
        SERVICE_NAME=${BACKEND_SERVICE_NAME:-$SERVICE_NAME}
        REGION=${GOOGLE_CLOUD_REGION:-$REGION}
    fi
}

# Verifica prerequisiti
check_prerequisites() {
    log_info "Verifico prerequisiti..."
    
    if [[ -z "$PROJECT_ID" ]]; then
        log_error "Progetto Google Cloud non configurato"
        exit 1
    fi
    
    if [[ ! -d "../support-api" ]]; then
        log_error "Directory support-api non trovata!"
        exit 1
    fi
    
    if [[ ! -f "../support-api/composer.json" ]]; then
        log_error "composer.json non trovato in support-api!"
        exit 1
    fi
    
    log_success "Prerequisiti verificati"
}

# Deploy backend
deploy_backend() {
    log_info "Deploy backend Laravel in corso..."
    
    # Carica variabili ambiente
    if [[ -f "config/.env.prod" ]]; then
        source config/.env.prod
    fi
    
    # Build environment variables string
    ENV_VARS="APP_ENV=production,SCOUT_DRIVER=database"
    
    # Aggiungi altre variabili se presenti
    [[ -n "$DB_HOST" ]] && ENV_VARS+=",DB_HOST=$DB_HOST"
    [[ -n "$DB_DATABASE" ]] && ENV_VARS+=",DB_DATABASE=$DB_DATABASE"
    [[ -n "$APP_URL" ]] && ENV_VARS+=",APP_URL=$APP_URL"
    
    # Build secrets string
    SECRETS="APP_KEY=app-key:latest,DB_PASSWORD=db-password:latest"
    [[ -n "$JWT_SECRET" ]] && SECRETS+=",JWT_SECRET=jwt-secret:latest"
    
    # Deploy
    gcloud run deploy $SERVICE_NAME \
        --source ../support-api \
        --region $REGION \
        --memory 1Gi \
        --cpu 1 \
        --concurrency 80 \
        --timeout 300 \
        --min-instances 0 \
        --max-instances 10 \
        --set-env-vars="$ENV_VARS" \
        --set-secrets="$SECRETS" \
        --allow-unauthenticated \
        --port 8080
    
    log_success "Backend deployato con successo!"
}

# Test health check
test_health_check() {
    log_info "Test health check..."
    
    # Ottieni URL del servizio
    SERVICE_URL=$(gcloud run services describe $SERVICE_NAME --region=$REGION --format="value(status.url)")
    
    if [[ -n "$SERVICE_URL" ]]; then
        log_info "URL Backend: $SERVICE_URL"
        
        # Test health check
        log_info "Test endpoint /health..."
        if curl -s "$SERVICE_URL/health" | grep -q "healthy"; then
            log_success "Health check OK!"
        else
            log_warning "Health check fallito o endpoint non disponibile"
        fi
    else
        log_error "Impossibile ottenere URL del servizio"
    fi
}

# Mostra logs
show_logs() {
    log_info "Mostra logs recenti..."
    gcloud run logs tail $SERVICE_NAME --region=$REGION --limit=50
}

# Mostra informazioni servizio
show_service_info() {
    log_info "Informazioni servizio:"
    gcloud run services describe $SERVICE_NAME --region=$REGION \
        --format="table(metadata.name,status.url,status.latestCreatedRevision,status.traffic[].percent)"
}

# Main execution
main() {
    load_config
    check_prerequisites
    
    log_warning "Stai per deployare il backend su Google Cloud Run"
    log_info "Progetto: $PROJECT_ID"
    log_info "Servizio: $SERVICE_NAME"
    log_info "Region: $REGION"
    echo ""
    
    read -p "Confermi il deploy? (y/N): " CONFIRM
    
    if [[ ! $CONFIRM =~ ^[Yy]$ ]]; then
        log_info "Deploy annullato"
        exit 0
    fi
    
    deploy_backend
    test_health_check
    show_service_info
    
    echo ""
    log_success "🎉 Deploy backend completato!"
    echo ""
    log_info "📋 Prossimi passi:"
    echo "   1. Testa l'applicazione usando l'URL sopra"
    echo "   2. Monitora logs: gcloud run logs tail $SERVICE_NAME --region=$REGION"
    echo "   3. Configura domini: ./dns-setup.sh"
    echo ""
}

# Parsing argomenti
while [[ $# -gt 0 ]]; do
    case $1 in
        --logs)
            load_config
            show_logs
            exit 0
            ;;
        --info)
            load_config
            show_service_info
            exit 0
            ;;
        --test)
            load_config
            test_health_check
            exit 0
            ;;
        --help|-h)
            echo "Uso: $0 [opzioni]"
            echo ""
            echo "Opzioni:"
            echo "  --logs        Mostra logs del servizio"
            echo "  --info        Mostra informazioni servizio"
            echo "  --test        Test health check"
            echo "  --help, -h    Mostra questo aiuto"
            exit 0
            ;;
        *)
            log_error "Opzione sconosciuta: $1"
            exit 1
            ;;
    esac
done

# Esegui main se nessun argomento
main
