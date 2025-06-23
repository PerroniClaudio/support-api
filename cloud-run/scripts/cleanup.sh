#!/bin/bash
set -e

# 🧹 Spreetzitt Cloud Run - Cleanup Risorse
# Rimuove servizi, secrets e domini per cleanup completo

echo "🧹 Cleanup Risorse Cloud Run"
echo "============================"

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
REGION="europe-west8"
BACKEND_SERVICE="spreetzitt-backend"
FRONTEND_SERVICE="spreetzitt-frontend"

# Carica configurazione da .env.prod
load_config() {
    if [[ -f "config/.env.prod" ]]; then
        source config/.env.prod
        BACKEND_SERVICE=${BACKEND_SERVICE_NAME:-$BACKEND_SERVICE}
        FRONTEND_SERVICE=${FRONTEND_SERVICE_NAME:-$FRONTEND_SERVICE}
        REGION=${GOOGLE_CLOUD_REGION:-$REGION}
    fi
}

# Lista risorse da eliminare
list_resources() {
    log_info "Risorse attualmente esistenti:"
    echo ""
    
    # Cloud Run Services
    log_info "🚀 Cloud Run Services:"
    if gcloud run services list --region=$REGION --format="table(SERVICE,URL,LAST DEPLOYED)" | grep -E "(spreetzitt|$BACKEND_SERVICE|$FRONTEND_SERVICE)"; then
        echo ""
    else
        echo "   Nessun servizio Cloud Run trovato"
    fi
    
    # Domain Mappings
    log_info "🌐 Domain Mappings:"
    if gcloud run domain-mappings list --region=$REGION --format="table(DOMAIN,SERVICE)" 2>/dev/null | grep -v "DOMAIN"; then
        echo ""
    else
        echo "   Nessun domain mapping trovato"
    fi
    
    # Secrets
    log_info "🔐 Secrets:"
    if gcloud secrets list --format="table(NAME,CREATE_TIME)" | grep -E "(app-|db-|jwt-|mail-|redis-)"; then
        echo ""
    else
        echo "   Nessun secret trovato"
    fi
    
    # Container Images
    log_info "🐳 Container Images:"
    if gcloud container images list --repository="gcr.io/$PROJECT_ID" --format="table(NAME)" | grep spreetzitt; then
        echo ""
    else
        echo "   Nessuna immagine container trovata"
    fi
}

# Rimuovi servizi Cloud Run
remove_services() {
    log_info "Rimozione servizi Cloud Run..."
    
    # Backend
    if gcloud run services describe "$BACKEND_SERVICE" --region="$REGION" >/dev/null 2>&1; then
        log_info "Rimuovo servizio backend: $BACKEND_SERVICE"
        gcloud run services delete "$BACKEND_SERVICE" --region="$REGION" --quiet
        log_success "Servizio backend rimosso"
    else
        log_warning "Servizio backend non trovato"
    fi
    
    # Frontend
    if gcloud run services describe "$FRONTEND_SERVICE" --region="$REGION" >/dev/null 2>&1; then
        log_info "Rimuovo servizio frontend: $FRONTEND_SERVICE"
        gcloud run services delete "$FRONTEND_SERVICE" --region="$REGION" --quiet
        log_success "Servizio frontend rimosso"
    else
        log_warning "Servizio frontend non trovato"
    fi
}

# Rimuovi domain mappings
remove_domain_mappings() {
    log_info "Rimozione domain mappings..."
    
    # Lista tutti i domain mappings
    local domains=$(gcloud run domain-mappings list --region="$REGION" --format="value(DOMAIN)" 2>/dev/null || echo "")
    
    if [[ -n "$domains" ]]; then
        for domain in $domains; do
            log_info "Rimuovo domain mapping: $domain"
            gcloud run domain-mappings delete "$domain" --region="$REGION" --quiet
            log_success "Domain mapping $domain rimosso"
        done
    else
        log_warning "Nessun domain mapping trovato"
    fi
}

# Rimuovi secrets
remove_secrets() {
    log_info "Rimozione secrets..."
    
    local secrets=(
        "app-key"
        "db-password"
        "jwt-secret"
        "mail-password"
        "meilisearch-key"
        "redis-password"
    )
    
    for secret in "${secrets[@]}"; do
        if gcloud secrets describe "$secret" >/dev/null 2>&1; then
            log_info "Rimuovo secret: $secret"
            gcloud secrets delete "$secret" --quiet
            log_success "Secret $secret rimosso"
        fi
    done
}

# Rimuovi immagini container
remove_container_images() {
    log_info "Rimozione immagini container..."
    
    # Lista immagini spreetzitt
    local images=$(gcloud container images list --repository="gcr.io/$PROJECT_ID" --format="value(NAME)" | grep spreetzitt || echo "")
    
    if [[ -n "$images" ]]; then
        for image in $images; do
            log_info "Rimuovo immagine: $image"
            gcloud container images delete "$image" --quiet --force-delete-tags
            log_success "Immagine $image rimossa"
        done
    else
        log_warning "Nessuna immagine container trovata"
    fi
}

# Pulizia Cloud Build
cleanup_cloud_build() {
    log_info "Pulizia Cloud Build..."
    
    # Lista build recenti di spreetzitt
    local builds=$(gcloud builds list --filter="source.repoSource.repoName~spreetzitt OR substitutions.REPO_NAME~spreetzitt" --format="value(ID)" --limit=10 2>/dev/null || echo "")
    
    if [[ -n "$builds" ]]; then
        read -p "Vuoi rimuovere anche le build history? (y/N): " REMOVE_BUILDS
        if [[ $REMOVE_BUILDS =~ ^[Yy]$ ]]; then
            for build_id in $builds; do
                log_info "Rimozione build: $build_id"
                gcloud builds cancel "$build_id" --quiet 2>/dev/null || true
            done
            log_success "Build history pulita"
        fi
    fi
}

# Verifica pulizia completa
verify_cleanup() {
    log_info "Verifica pulizia completa..."
    
    local remaining_resources=0
    
    # Verifica servizi
    if gcloud run services list --region=$REGION --format="value(SERVICE)" | grep -E "(spreetzitt|$BACKEND_SERVICE|$FRONTEND_SERVICE)" >/dev/null 2>&1; then
        log_warning "Alcuni servizi Cloud Run ancora presenti"
        ((remaining_resources++))
    fi
    
    # Verifica domain mappings
    if gcloud run domain-mappings list --region=$REGION --format="value(DOMAIN)" 2>/dev/null | grep -v "^$" >/dev/null 2>&1; then
        log_warning "Alcuni domain mappings ancora presenti"
        ((remaining_resources++))
    fi
    
    # Verifica secrets
    if gcloud secrets list --format="value(NAME)" | grep -E "(app-|db-|jwt-|mail-|redis-)" >/dev/null 2>&1; then
        log_warning "Alcuni secrets ancora presenti"
        ((remaining_resources++))
    fi
    
    if [[ $remaining_resources -eq 0 ]]; then
        log_success "Pulizia completata con successo!"
    else
        log_warning "Pulizia parziale - alcune risorse potrebbero richiedere rimozione manuale"
    fi
}

# Menu di selezione
show_cleanup_menu() {
    echo ""
    log_warning "ATTENZIONE: Questa operazione rimuoverà permanentemente le risorse!"
    echo ""
    log_info "Cosa vuoi rimuovere?"
    echo "1) Solo servizi Cloud Run"
    echo "2) Solo domain mappings"
    echo "3) Solo secrets"
    echo "4) Solo immagini container"
    echo "5) TUTTO (servizi + domini + secrets + immagini)"
    echo "6) Pulizia selettiva"
    echo "7) Annulla"
    echo ""
    
    read -p "Scegli un'opzione (1-7): " CHOICE
    
    case $CHOICE in
        1)
            confirm_and_execute "servizi Cloud Run" remove_services
            ;;
        2)
            confirm_and_execute "domain mappings" remove_domain_mappings
            ;;
        3)
            confirm_and_execute "secrets" remove_secrets
            ;;
        4)
            confirm_and_execute "immagini container" remove_container_images
            ;;
        5)
            confirm_and_execute "TUTTE le risorse" full_cleanup
            ;;
        6)
            selective_cleanup
            ;;
        7)
            log_info "Operazione annullata"
            exit 0
            ;;
        *)
            log_error "Opzione non valida"
            show_cleanup_menu
            ;;
    esac
}

# Conferma ed esegui
confirm_and_execute() {
    local description=$1
    local action=$2
    
    echo ""
    log_warning "Stai per rimuovere: $description"
    log_info "Progetto: $PROJECT_ID"
    log_info "Region: $REGION"
    echo ""
    
    read -p "Sei SICURO di voler continuare? (y/N): " CONFIRM
    
    if [[ $CONFIRM =~ ^[Yy]$ ]]; then
        $action
        verify_cleanup
    else
        log_info "Operazione annullata"
    fi
}

# Pulizia completa
full_cleanup() {
    remove_domain_mappings
    remove_services
    remove_secrets
    remove_container_images
    cleanup_cloud_build
}

# Pulizia selettiva
selective_cleanup() {
    echo ""
    log_info "Pulizia selettiva - scegli cosa rimuovere:"
    
    read -p "Rimuovere servizi Cloud Run? (y/N): " REMOVE_SERVICES
    read -p "Rimuovere domain mappings? (y/N): " REMOVE_DOMAINS
    read -p "Rimuovere secrets? (y/N): " REMOVE_SECRETS
    read -p "Rimuovere immagini container? (y/N): " REMOVE_IMAGES
    read -p "Pulire Cloud Build history? (y/N): " REMOVE_BUILDS
    
    echo ""
    log_warning "Conferma operazioni selezionate:"
    [[ $REMOVE_SERVICES =~ ^[Yy]$ ]] && echo "   ✅ Rimuovi servizi Cloud Run"
    [[ $REMOVE_DOMAINS =~ ^[Yy]$ ]] && echo "   ✅ Rimuovi domain mappings"
    [[ $REMOVE_SECRETS =~ ^[Yy]$ ]] && echo "   ✅ Rimuovi secrets"
    [[ $REMOVE_IMAGES =~ ^[Yy]$ ]] && echo "   ✅ Rimuovi immagini container"
    [[ $REMOVE_BUILDS =~ ^[Yy]$ ]] && echo "   ✅ Pulisci Cloud Build history"
    echo ""
    
    read -p "Confermi le operazioni? (y/N): " CONFIRM
    
    if [[ $CONFIRM =~ ^[Yy]$ ]]; then
        [[ $REMOVE_DOMAINS =~ ^[Yy]$ ]] && remove_domain_mappings
        [[ $REMOVE_SERVICES =~ ^[Yy]$ ]] && remove_services
        [[ $REMOVE_SECRETS =~ ^[Yy]$ ]] && remove_secrets
        [[ $REMOVE_IMAGES =~ ^[Yy]$ ]] && remove_container_images
        [[ $REMOVE_BUILDS =~ ^[Yy]$ ]] && cleanup_cloud_build
        verify_cleanup
    else
        log_info "Operazione annullata"
    fi
}

# Main execution
main() {
    load_config
    
    if [[ -z "$PROJECT_ID" ]]; then
        log_error "Progetto Google Cloud non configurato"
        exit 1
    fi
    
    list_resources
    show_cleanup_menu
}

# Parsing argomenti
while [[ $# -gt 0 ]]; do
    case $1 in
        --list)
            load_config
            list_resources
            exit 0
            ;;
        --services)
            load_config
            confirm_and_execute "servizi Cloud Run" remove_services
            exit 0
            ;;
        --domains)
            load_config
            confirm_and_execute "domain mappings" remove_domain_mappings
            exit 0
            ;;
        --secrets)
            load_config
            confirm_and_execute "secrets" remove_secrets
            exit 0
            ;;
        --images)
            load_config
            confirm_and_execute "immagini container" remove_container_images
            exit 0
            ;;
        --all)
            load_config
            confirm_and_execute "TUTTE le risorse" full_cleanup
            exit 0
            ;;
        --help|-h)
            echo "Uso: $0 [opzioni]"
            echo ""
            echo "Opzioni:"
            echo "  --list        Lista risorse esistenti"
            echo "  --services    Rimuovi solo servizi Cloud Run"
            echo "  --domains     Rimuovi solo domain mappings"
            echo "  --secrets     Rimuovi solo secrets"
            echo "  --images      Rimuovi solo immagini container"
            echo "  --all         Rimuovi tutto"
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
