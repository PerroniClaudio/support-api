#!/bin/bash
set -e

# 🌐 Spreetzitt Cloud Run - Setup Domini Personalizzati
# Configura domini personalizzati e SSL automatico

echo "🌐 Setup Domini Personalizzati"
echo "=============================="

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

# Configurazione (può essere override da .env.prod)
PROJECT_ID=$(gcloud config get-value project)
REGION="europe-west1"
BACKEND_SERVICE="spreetzitt-backend"
FRONTEND_SERVICE="spreetzitt-frontend"

# Domini di default (sarà chiesto all'utente)
BACKEND_DOMAIN=""
FRONTEND_DOMAIN=""

# Carica configurazione da .env.prod se disponibile
load_config() {
    if [[ -f "config/.env.prod" ]]; then
        source config/.env.prod
        BACKEND_DOMAIN=${BACKEND_DOMAIN:-$BACKEND_DOMAIN}
        FRONTEND_DOMAIN=${FRONTEND_DOMAIN:-$FRONTEND_DOMAIN}
        BACKEND_SERVICE=${BACKEND_SERVICE_NAME:-$BACKEND_SERVICE}
        FRONTEND_SERVICE=${FRONTEND_SERVICE_NAME:-$FRONTEND_SERVICE}
    fi
}

# Verifica prerequisiti
check_prerequisites() {
    log_info "Verifico prerequisiti..."
    
    if [[ -z "$PROJECT_ID" ]]; then
        log_error "Progetto Google Cloud non configurato"
        exit 1
    fi
    
    # Verifica che i servizi esistano
    if ! gcloud run services describe "$BACKEND_SERVICE" --region="$REGION" >/dev/null 2>&1; then
        log_error "Servizio $BACKEND_SERVICE non trovato. Esegui prima il deploy!"
        exit 1
    fi
    
    if ! gcloud run services describe "$FRONTEND_SERVICE" --region="$REGION" >/dev/null 2>&1; then
        log_error "Servizio $FRONTEND_SERVICE non trovato. Esegui prima il deploy!"
        exit 1
    fi
    
    log_success "Prerequisiti verificati"
}

# Chiede domini all'utente
ask_domains() {
    log_info "Configurazione domini personalizzati"
    echo ""
    
    if [[ -z "$BACKEND_DOMAIN" ]]; then
        read -p "Dominio per il backend (es: api.tuodominio.com): " BACKEND_DOMAIN
    else
        read -p "Dominio per il backend [$BACKEND_DOMAIN]: " INPUT_BACKEND
        BACKEND_DOMAIN=${INPUT_BACKEND:-$BACKEND_DOMAIN}
    fi
    
    if [[ -z "$FRONTEND_DOMAIN" ]]; then
        read -p "Dominio per il frontend (es: app.tuodominio.com): " FRONTEND_DOMAIN
    else
        read -p "Dominio per il frontend [$FRONTEND_DOMAIN]: " INPUT_FRONTEND
        FRONTEND_DOMAIN=${INPUT_FRONTEND:-$FRONTEND_DOMAIN}
    fi
    
    echo ""
    log_info "Domini configurati:"
    echo "   Backend:  $BACKEND_DOMAIN"
    echo "   Frontend: $FRONTEND_DOMAIN"
    echo ""
}

# Verifica domini in Search Console
verify_domains() {
    log_warning "IMPORTANTE: Prima di continuare, verifica che i domini siano configurati in Google Search Console!"
    echo ""
    echo "1. Vai su: https://search.google.com/search-console"
    echo "2. Aggiungi e verifica questi domini:"
    echo "   - $BACKEND_DOMAIN"
    echo "   - $FRONTEND_DOMAIN"
    echo ""
    read -p "Hai verificato i domini in Search Console? (y/N): " VERIFIED
    
    if [[ ! $VERIFIED =~ ^[Yy]$ ]]; then
        log_error "Verifica i domini in Search Console prima di continuare"
        exit 1
    fi
    
    log_success "Domini verificati"
}

# Mostra URL attuali dei servizi
show_current_urls() {
    log_info "URL attuali dei servizi:"
    
    BACKEND_URL=$(gcloud run services describe "$BACKEND_SERVICE" --region="$REGION" --format="value(status.url)")
    FRONTEND_URL=$(gcloud run services describe "$FRONTEND_SERVICE" --region="$REGION" --format="value(status.url)")
    
    echo "   Backend:  $BACKEND_URL"
    echo "   Frontend: $FRONTEND_URL"
    echo ""
}

# Crea mapping domini
create_domain_mappings() {
    log_info "Creo mapping domini..."
    
    # Backend domain mapping
    log_info "Configurazione dominio backend: $BACKEND_DOMAIN"
    if gcloud run domain-mappings describe "$BACKEND_DOMAIN" --region="$REGION" >/dev/null 2>&1; then
        log_warning "Mapping per $BACKEND_DOMAIN già esistente"
    else
        gcloud run domain-mappings create \
            --service="$BACKEND_SERVICE" \
            --domain="$BACKEND_DOMAIN" \
            --region="$REGION"
        log_success "Mapping backend creato"
    fi
    
    # Frontend domain mapping
    log_info "Configurazione dominio frontend: $FRONTEND_DOMAIN"
    if gcloud run domain-mappings describe "$FRONTEND_DOMAIN" --region="$REGION" >/dev/null 2>&1; then
        log_warning "Mapping per $FRONTEND_DOMAIN già esistente"
    else
        gcloud run domain-mappings create \
            --service="$FRONTEND_SERVICE" \
            --domain="$FRONTEND_DOMAIN" \
            --region="$REGION"
        log_success "Mapping frontend creato"
    fi
}

# Mostra record DNS da configurare
show_dns_records() {
    log_info "Record DNS da configurare nel tuo provider:"
    echo ""
    
    # Ottieni record DNS necessari
    log_info "Backend ($BACKEND_DOMAIN):"
    gcloud run domain-mappings describe "$BACKEND_DOMAIN" --region="$REGION" \
        --format="table(status.resourceRecords[].name,status.resourceRecords[].type,status.resourceRecords[].rrdata)" 2>/dev/null || {
        log_warning "Record DNS non ancora disponibili per $BACKEND_DOMAIN"
        echo "   Tipo: CNAME"
        echo "   Nome: $(echo $BACKEND_DOMAIN | cut -d. -f1)"
        echo "   Valore: ghs.googlehosted.com"
    }
    
    echo ""
    log_info "Frontend ($FRONTEND_DOMAIN):"
    gcloud run domain-mappings describe "$FRONTEND_DOMAIN" --region="$REGION" \
        --format="table(status.resourceRecords[].name,status.resourceRecords[].type,status.resourceRecords[].rrdata)" 2>/dev/null || {
        log_warning "Record DNS non ancora disponibili per $FRONTEND_DOMAIN"
        echo "   Tipo: CNAME"
        echo "   Nome: $(echo $FRONTEND_DOMAIN | cut -d. -f1)"
        echo "   Valore: ghs.googlehosted.com"
    }
    
    echo ""
    log_warning "Configura questi record DNS nel tuo provider e attendi 5-60 minuti per la propagazione"
}

# Verifica stato SSL
check_ssl_status() {
    log_info "Stato SSL certificates:"
    
    # Controlla backend
    BACKEND_SSL=$(gcloud run domain-mappings describe "$BACKEND_DOMAIN" --region="$REGION" \
        --format="value(status.conditions[0].status)" 2>/dev/null || echo "Unknown")
    
    # Controlla frontend
    FRONTEND_SSL=$(gcloud run domain-mappings describe "$FRONTEND_DOMAIN" --region="$REGION" \
        --format="value(status.conditions[0].status)" 2>/dev/null || echo "Unknown")
    
    echo "   Backend ($BACKEND_DOMAIN): $BACKEND_SSL"
    echo "   Frontend ($FRONTEND_DOMAIN): $FRONTEND_SSL"
    
    if [[ "$BACKEND_SSL" == "True" && "$FRONTEND_SSL" == "True" ]]; then
        log_success "SSL configurato correttamente per entrambi i domini!"
    else
        log_warning "SSL ancora in configurazione. Riprova tra qualche minuto."
    fi
}

# Test domini
test_domains() {
    log_info "Test domini..."
    
    # Test backend
    log_info "Test backend: https://$BACKEND_DOMAIN"
    if curl -s -I "https://$BACKEND_DOMAIN/health" | grep -q "200 OK"; then
        log_success "Backend raggiungibile e funzionante!"
    else
        log_warning "Backend non ancora raggiungibile (DNS propagation in corso)"
    fi
    
    # Test frontend
    log_info "Test frontend: https://$FRONTEND_DOMAIN"
    if curl -s -I "https://$FRONTEND_DOMAIN" | grep -q "200 OK"; then
        log_success "Frontend raggiungibile e funzionante!"
    else
        log_warning "Frontend non ancora raggiungibile (DNS propagation in corso)"
    fi
}

# Lista domini configurati
list_domains() {
    log_info "Domini attualmente configurati:"
    gcloud run domain-mappings list --region="$REGION" --format="table(DOMAIN,SERVICE,URL)"
}

# Rimuovi mapping domini
remove_domain_mappings() {
    log_warning "ATTENZIONE: Questa operazione rimuoverà i mapping dei domini!"
    read -p "Sei sicuro di voler continuare? (y/N): " CONFIRM
    
    if [[ $CONFIRM =~ ^[Yy]$ ]]; then
        log_info "Rimuovo mapping domini..."
        
        if [[ -n "$BACKEND_DOMAIN" ]]; then
            gcloud run domain-mappings delete "$BACKEND_DOMAIN" --region="$REGION" --quiet
            log_success "Mapping $BACKEND_DOMAIN rimosso"
        fi
        
        if [[ -n "$FRONTEND_DOMAIN" ]]; then
            gcloud run domain-mappings delete "$FRONTEND_DOMAIN" --region="$REGION" --quiet
            log_success "Mapping $FRONTEND_DOMAIN rimosso"
        fi
    else
        log_info "Operazione annullata"
    fi
}

# Menu principale
show_menu() {
    echo ""
    log_info "Cosa vuoi fare?"
    echo "1) Setup completo domini"
    echo "2) Mostra record DNS da configurare"
    echo "3) Verifica stato SSL"
    echo "4) Test domini"
    echo "5) Lista domini configurati"
    echo "6) Rimuovi mapping domini"
    echo "7) Esci"
    echo ""
    
    read -p "Scegli un'opzione (1-7): " CHOICE
    
    case $CHOICE in
        1)
            setup_complete
            ;;
        2)
            show_dns_records
            show_menu
            ;;
        3)
            check_ssl_status
            show_menu
            ;;
        4)
            test_domains
            show_menu
            ;;
        5)
            list_domains
            show_menu
            ;;
        6)
            remove_domain_mappings
            show_menu
            ;;
        7)
            log_success "Goodbye!"
            exit 0
            ;;
        *)
            log_error "Opzione non valida"
            show_menu
            ;;
    esac
}

# Setup completo
setup_complete() {
    show_current_urls
    ask_domains
    verify_domains
    create_domain_mappings
    show_dns_records
    
    log_success "Setup domini completato!"
    log_info "Ora configura i record DNS e attendi la propagazione (5-60 min)"
    
    show_menu
}

# Main execution
main() {
    load_config
    check_prerequisites
    
    if [[ $# -eq 0 ]]; then
        show_menu
    else
        case $1 in
            setup)
                setup_complete
                ;;
            test)
                test_domains
                ;;
            status)
                check_ssl_status
                ;;
            list)
                list_domains
                ;;
            *)
                log_error "Comando non riconosciuto: $1"
                log_info "Comandi disponibili: setup, test, status, list"
                exit 1
                ;;
        esac
    fi
}

# Esegui solo se chiamato direttamente
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
