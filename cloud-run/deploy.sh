#!/bin/bash
set -e

# 🚀 Spreetzitt Cloud Run - Deploy Completo
# Deploy di backend e frontend su Google Cloud Run

echo "🚀 Spreetzitt Cloud Run - Deploy Completo"
echo "========================================"

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
BACKEND_SERVICE="spreetzitt-backend"
FRONTEND_SERVICE="spreetzitt-frontend"

# Verifica prerequisiti
check_prerequisites() {
    log_info "Verifico prerequisiti..."
    
    if [[ -z "$PROJECT_ID" ]]; then
        log_error "Progetto Google Cloud non configurato. Esegui: gcloud config set project YOUR_PROJECT_ID"
        exit 1
    fi
    
    if [[ ! -f "config/.env.prod" ]]; then
        log_error "File config/.env.prod non trovato. Copia e modifica config/.env.template"
        exit 1
    fi
    
    log_success "Prerequisiti verificati"
}

# Crea secrets da .env.prod
create_secrets() {
    log_info "Creo Google Cloud Secrets..."
    
    # Esegui script di creazione secrets
    if [[ -f "scripts/create-secrets.sh" ]]; then
        ./scripts/create-secrets.sh
    else
        log_warning "Script create-secrets.sh non trovato, salto creazione secrets"
    fi
}

# Deploy backend
deploy_backend() {
    log_info "Deploy backend Laravel..."
    
    # Carica variabili ambiente
    source config/.env.prod
    
    # Build e deploy
    gcloud run deploy $BACKEND_SERVICE \
        --source ../support-api \
        --region $REGION \
        --memory 1Gi \
        --cpu 1 \
        --concurrency 80 \
        --timeout 300 \
        --min-instances 0 \
        --max-instances 10 \
        --set-env-vars="APP_ENV=production,SCOUT_DRIVER=database,DB_HOST=${DB_HOST:-},DB_DATABASE=${DB_DATABASE:-spreetzitt}" \
        --set-secrets="APP_KEY=app-key:latest,DB_PASSWORD=db-password:latest" \
        --allow-unauthenticated \
        --port 8080
    
    log_success "Backend deployato con successo!"
    
    # Mostra URL
    BACKEND_URL=$(gcloud run services describe $BACKEND_SERVICE --region=$REGION --format="value(status.url)")
    log_info "Backend URL: $BACKEND_URL"
}

# Deploy frontend
deploy_frontend() {
    log_info "Deploy frontend React..."
    
    # Carica variabili ambiente
    source config/.env.prod
    
    # Aggiorna VITE_API_URL se presente
    if [[ -n "$BACKEND_URL" ]]; then
        export VITE_API_URL="$BACKEND_URL"
    fi
    
    # Build e deploy con le configurazioni semplici ma aggiungiamo health checks dopo
    gcloud run deploy $FRONTEND_SERVICE \
        --source ../frontend \
        --region $REGION \
        --memory 512Mi \
        --cpu 1 \
        --concurrency 1000 \
        --timeout 60 \
        --min-instances 0 \
        --max-instances 5 \
        --allow-unauthenticated \
        --port 8080
    
    log_success "Frontend deployato con successo!"
    
    # Mostra URL
    FRONTEND_URL=$(gcloud run services describe $FRONTEND_SERVICE --region=$REGION --format="value(status.url)")
    log_info "Frontend URL: $FRONTEND_URL"
}

# Test health checks
test_health_checks() {
    log_info "Test health checks..."
    
    if [[ -n "$BACKEND_URL" ]]; then
        log_info "Test backend health check..."
        if curl -s "$BACKEND_URL/health" > /dev/null; then
            log_success "Backend health check OK"
        else
            log_warning "Backend health check fallito"
        fi
    fi
    
    if [[ -n "$FRONTEND_URL" ]]; then
        log_info "Test frontend..."
        if curl -s "$FRONTEND_URL" > /dev/null; then
            log_success "Frontend raggiungibile"
        else
            log_warning "Frontend non raggiungibile"
        fi
    fi
}

# Mostra riepilogo finale
show_summary() {
    echo ""
    log_success "🎉 Deploy completato con successo!"
    echo "================================="
    echo ""
    log_info "📋 URLs dei servizi:"
    [[ -n "$BACKEND_URL" ]] && echo "   🔗 Backend:  $BACKEND_URL"
    [[ -n "$FRONTEND_URL" ]] && echo "   🔗 Frontend: $FRONTEND_URL"
    echo ""
    
    log_info "📊 Prossimi passi:"
    echo "   1. Testa le applicazioni usando gli URL sopra"
    echo "   2. Configura domini personalizzati: ./scripts/dns-setup.sh"
    echo "   3. Monitora logs: gcloud run logs tail $BACKEND_SERVICE --region=$REGION"
    echo "   4. Configura CI/CD usando config/cloudbuild.yaml"
    echo ""
    
    log_info "💰 Stima costi mensili: €8-15 (vs €30 e2-medium)"
    echo ""
}

# Cleanup in caso di errore
cleanup_on_error() {
    log_error "Deploy fallito. Cleanup in corso..."
    # Qui potresti aggiungere logica di cleanup se necessario
}

# Trap per gestire errori
trap cleanup_on_error ERR

# Menu di conferma
confirm_deploy() {
    echo ""
    log_warning "Stai per deployare Spreetzitt su Google Cloud Run"
    log_info "Progetto: $PROJECT_ID"
    log_info "Region: $REGION"
    echo ""
    read -p "Confermi il deploy? (y/N): " CONFIRM
    
    if [[ ! $CONFIRM =~ ^[Yy]$ ]]; then
        log_info "Deploy annullato"
        exit 0
    fi
}

# Main execution
main() {
    check_prerequisites
    confirm_deploy
    create_secrets
    deploy_backend
    deploy_frontend
    test_health_checks
    show_summary
}

# Parsing argomenti command line
while [[ $# -gt 0 ]]; do
    case $1 in
        --backend-only)
            DEPLOY_BACKEND_ONLY=true
            shift
            ;;
        --frontend-only)
            DEPLOY_FRONTEND_ONLY=true
            shift
            ;;
        --skip-secrets)
            SKIP_SECRETS=true
            shift
            ;;
        --help|-h)
            echo "Uso: $0 [opzioni]"
            echo ""
            echo "Opzioni:"
            echo "  --backend-only    Deploy solo backend"
            echo "  --frontend-only   Deploy solo frontend"
            echo "  --skip-secrets    Salta creazione secrets"
            echo "  --help, -h        Mostra questo aiuto"
            exit 0
            ;;
        *)
            log_error "Opzione sconosciuta: $1"
            exit 1
            ;;
    esac
done

# Esegui deploy con opzioni
if [[ "$DEPLOY_BACKEND_ONLY" == "true" ]]; then
    check_prerequisites
    [[ "$SKIP_SECRETS" != "true" ]] && create_secrets
    deploy_backend
    test_health_checks
elif [[ "$DEPLOY_FRONTEND_ONLY" == "true" ]]; then
    check_prerequisites
    deploy_frontend
    test_health_checks
else
    main
fi
