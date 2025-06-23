#!/bin/bash

# 🌐 Cloud Run DNS & Environment Setup
# Gestione domini personalizzati e variabili d'ambiente

set -euo pipefail

# Configurazione
PROJECT_ID="your-gcp-project-id"
REGION="europe-west8"

# I tuoi domini
FRONTEND_DOMAIN="app.yourdomain.com"
BACKEND_DOMAIN="api.yourdomain.com"

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }
highlight() { echo -e "${CYAN}🔗 $1${NC}"; }

# 1. Mostra URLs Cloud Run
show_cloud_run_urls() {
    info "🌐 URLs Cloud Run attuali:"
    
    # Backend URL
    local backend_url
    backend_url=$(gcloud run services describe spreetzitt-backend \
        --platform=managed \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --format="value(status.url)" 2>/dev/null || echo "Not deployed")
    
    # Frontend URL  
    local frontend_url
    frontend_url=$(gcloud run services describe spreetzitt-frontend \
        --platform=managed \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --format="value(status.url)" 2>/dev/null || echo "Not deployed")
    
    echo
    highlight "Backend:  $backend_url"
    highlight "Frontend: $frontend_url"
    echo
    
    info "⚠️  Questi URL cambiano ad ogni deploy! Usa domini personalizzati."
}

# 2. Setup domini personalizzati
setup_custom_domains() {
    info "🔗 Setup domini personalizzati..."
    
    echo "Step 1: Verifica domini in Google Search Console"
    echo "→ https://search.google.com/search-console"
    echo
    
    read -p "Hai verificato i domini $FRONTEND_DOMAIN e $BACKEND_DOMAIN? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        warning "Verifica prima i domini e riprova"
        return 1
    fi
    
    # Mappa dominio frontend
    info "📱 Mappando dominio frontend: $FRONTEND_DOMAIN"
    gcloud run domain-mappings create \
        --service=spreetzitt-frontend \
        --domain="$FRONTEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID"
    
    # Mappa dominio backend
    info "🔧 Mappando dominio backend: $BACKEND_DOMAIN"
    gcloud run domain-mappings create \
        --service=spreetzitt-backend \
        --domain="$BACKEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID"
    
    # Mostra record DNS necessari
    show_dns_records
}

# 3. Mostra record DNS da configurare
show_dns_records() {
    info "📋 Record DNS da configurare:"
    
    # Ottieni record DNS per frontend
    local frontend_records
    frontend_records=$(gcloud run domain-mappings describe "$FRONTEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --format="value(status.resourceRecords[].name,status.resourceRecords[].rrdata)" 2>/dev/null || echo "")
    
    # Ottieni record DNS per backend  
    local backend_records
    backend_records=$(gcloud run domain-mappings describe "$BACKEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --format="value(status.resourceRecords[].name,status.resourceRecords[].rrdata)" 2>/dev/null || echo "")
    
    echo
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    CONFIGURAZIONE DNS                        ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo
    
    if [[ -n "$frontend_records" ]]; then
        echo "🔹 FRONTEND ($FRONTEND_DOMAIN):"
        echo "$frontend_records" | while IFS=$'\t' read -r name value; do
            echo "   $name → $value"
        done
        echo
    fi
    
    if [[ -n "$backend_records" ]]; then
        echo "🔹 BACKEND ($BACKEND_DOMAIN):"
        echo "$backend_records" | while IFS=$'\t' read -r name value; do
            echo "   $name → $value"
        done
        echo
    fi
    
    warning "⚠️  Configura questi record nel tuo provider DNS!"
    info "🕐 La propagazione DNS richiede 5-60 minuti"
}

# 4. Setup variabili d'ambiente e secrets
setup_environment() {
    info "🔐 Setup variabili d'ambiente e secrets..."
    
    # Carica .env.prod se esiste
    local env_file="../.env.prod"
    if [[ ! -f "$env_file" ]]; then
        error "File $env_file non trovato!"
        echo "Crea il file con le tue variabili:"
        echo "  APP_KEY=base64:..."
        echo "  DB_PASSWORD=..."
        echo "  REDIS_PASSWORD=..."
        return 1
    fi
    
    source "$env_file"
    
    # Crea secrets per dati sensibili
    create_secrets
    
    # Configura variabili d'ambiente non sensibili
    set_environment_variables
}

# 5. Crea Google Cloud Secrets
create_secrets() {
    info "🗝️  Creazione Google Cloud Secrets..."
    
    # Lista secrets da creare
    local secrets=(
        "APP_KEY:$APP_KEY"
        "DB_PASSWORD:$DB_PASSWORD"
        "REDIS_PASSWORD:${REDIS_PASSWORD:-}"
        "MAIL_PASSWORD:${MAIL_PASSWORD:-}"
    )
    
    for secret_pair in "${secrets[@]}"; do
        local secret_name="${secret_pair%%:*}"
        local secret_value="${secret_pair#*:}"
        
        if [[ -z "$secret_value" ]]; then
            warning "Valore vuoto per secret $secret_name, salto..."
            continue
        fi
        
        # Rimuovi secret esistente se presente
        gcloud secrets delete "spreetzitt-${secret_name,,}" --quiet 2>/dev/null || true
        
        # Crea nuovo secret
        echo "$secret_value" | gcloud secrets create "spreetzitt-${secret_name,,}" \
            --data-file=- \
            --project="$PROJECT_ID"
        
        success "Secret creato: spreetzitt-${secret_name,,}"
    done
}

# 6. Configura variabili d'ambiente sui servizi
set_environment_variables() {
    info "🌍 Configurazione variabili d'ambiente..."
    
    # Variabili per backend
    local backend_env="APP_ENV=production,APP_DEBUG=false,DB_CONNECTION=${DB_CONNECTION:-mysql},DB_HOST=${DB_HOST},DB_PORT=${DB_PORT:-3306},DB_DATABASE=${DB_DATABASE},DB_USERNAME=${DB_USERNAME},SCOUT_DRIVER=database"
    
    # Variabili per frontend
    local frontend_env="VITE_API_URL=https://${BACKEND_DOMAIN}"
    
    # Aggiorna backend
    info "🔧 Aggiornamento backend..."
    gcloud run services update spreetzitt-backend \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --set-env-vars="$backend_env" \
        --set-secrets="APP_KEY=spreetzitt-app_key:latest,DB_PASSWORD=spreetzitt-db_password:latest,REDIS_PASSWORD=spreetzitt-redis_password:latest"
    
    # Aggiorna frontend
    info "🎨 Aggiornamento frontend..."
    gcloud run services update spreetzitt-frontend \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --set-env-vars="$frontend_env"
    
    success "Variabili d'ambiente configurate"
}

# 7. Test configurazione domini
test_domains() {
    info "🧪 Test configurazione domini..."
    
    echo "Testing connessioni..."
    
    # Test backend
    if curl -f -s "https://$BACKEND_DOMAIN/health" >/dev/null 2>&1; then
        success "✅ Backend OK: https://$BACKEND_DOMAIN"
    else
        warning "⚠️  Backend non raggiungibile: https://$BACKEND_DOMAIN"
        echo "   Potrebbe essere la propagazione DNS..."
    fi
    
    # Test frontend
    if curl -f -s "https://$FRONTEND_DOMAIN" >/dev/null 2>&1; then
        success "✅ Frontend OK: https://$FRONTEND_DOMAIN"
    else
        warning "⚠️  Frontend non raggiungibile: https://$FRONTEND_DOMAIN"
        echo "   Potrebbe essere la propagazione DNS..."
    fi
    
    echo
    info "🔍 Verifica DNS con:"
    echo "  nslookup $FRONTEND_DOMAIN"
    echo "  nslookup $BACKEND_DOMAIN"
}

# 8. Mostra configurazione completa
show_configuration() {
    info "📋 Configurazione completa:"
    
    echo
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                  SPREETZITT CLOUD RUN CONFIG                 ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo
    
    # URLs
    echo "🌐 DOMINI:"
    echo "   Frontend: https://$FRONTEND_DOMAIN"
    echo "   Backend:  https://$BACKEND_DOMAIN"
    echo
    
    # Secrets
    echo "🔐 SECRETS (Google Cloud Secret Manager):"
    gcloud secrets list --filter="name:spreetzitt-" --format="value(name)" --project="$PROJECT_ID" | sed 's/^/   /'
    echo
    
    # Servizi
    echo "🚀 SERVIZI CLOUD RUN:"
    gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt" --format="table(metadata.name,status.url,status.conditions[0].status)"
    echo
    
    # DNS Status
    echo "📡 STATUS DNS:"
    echo "   Verifica con: nslookup $FRONTEND_DOMAIN"
    echo "   Verifica con: nslookup $BACKEND_DOMAIN"
    echo
    
    success "🎉 Configurazione completata!"
}

# 9. Procedura completa setup
full_setup() {
    info "🚀 Setup completo Cloud Run + Domini personalizzati..."
    
    show_cloud_run_urls
    echo
    
    read -p "Vuoi configurare domini personalizzati? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        setup_custom_domains
    fi
    
    echo
    read -p "Vuoi configurare variabili d'ambiente? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        setup_environment
    fi
    
    echo
    show_configuration
    
    echo
    info "🕐 Attendi 5-60 minuti per la propagazione DNS"
    info "🧪 Poi testa con: $0 test"
}

# Menu principale
case "${1:-help}" in
    "urls")
        show_cloud_run_urls
        ;;
    "domains")
        setup_custom_domains
        ;;
    "dns")
        show_dns_records
        ;;
    "env")
        setup_environment
        ;;
    "test")
        test_domains
        ;;
    "config")
        show_configuration
        ;;
    "setup")
        full_setup
        ;;
    "help"|*)
        echo -e "${BLUE}🌐 Cloud Run DNS & Environment Setup${NC}"
        echo
        echo "Gestisce domini personalizzati e variabili d'ambiente per Cloud Run"
        echo
        echo "Utilizzo: $0 <comando>"
        echo
        echo "Comandi:"
        echo "  urls     - Mostra URLs Cloud Run attuali"
        echo "  domains  - Setup domini personalizzati"
        echo "  dns      - Mostra record DNS da configurare"
        echo "  env      - Setup variabili d'ambiente e secrets"
        echo "  test     - Test connessioni domini"
        echo "  config   - Mostra configurazione completa"
        echo "  setup    - Setup completo guidato"
        echo
        echo "📋 Prima dell'uso:"
        echo "  1. Modifica FRONTEND_DOMAIN e BACKEND_DOMAIN in questo script"
        echo "  2. Crea file ../.env.prod con le tue variabili"
        echo "  3. Verifica domini in Google Search Console"
        echo
        echo "Esempio:"
        echo "  $0 setup  # Setup completo guidato"
        ;;
esac
