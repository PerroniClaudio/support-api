#!/bin/bash

# 🌐 Connessione Dominio Aruba a Google Cloud Run
# Guida passo-passo per configurare DNS su Aruba

set -euo pipefail

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }
highlight() { echo -e "${CYAN}🔗 $1${NC}"; }
step() { echo -e "${MAGENTA}📋 $1${NC}"; }

# Configurazione (MODIFICA QUESTI VALORI)
YOUR_DOMAIN="tuodominio.com"                    # Il tuo dominio principale
FRONTEND_SUBDOMAIN="app"                        # Sottodominio per frontend
BACKEND_SUBDOMAIN="api"                         # Sottodominio per backend
PROJECT_ID="your-gcp-project-id"               # Il tuo project ID GCP
REGION="europe-west8"                          # Regione GCP

# Domini finali
FRONTEND_DOMAIN="${FRONTEND_SUBDOMAIN}.${YOUR_DOMAIN}"
BACKEND_DOMAIN="${BACKEND_SUBDOMAIN}.${YOUR_DOMAIN}"

# Funzione principale
setup_aruba_dns() {
    echo -e "${BLUE}🌐 Setup DNS Aruba per Google Cloud Run${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo
    
    info "Domini configurati:"
    highlight "Frontend: https://$FRONTEND_DOMAIN"
    highlight "Backend:  https://$BACKEND_DOMAIN"
    echo
    
    # Step 1: Verifica domini in Google Search Console
    step "STEP 1: Verifica domini in Google Search Console"
    verify_domains_google
    
    # Step 2: Mappa domini a Cloud Run
    step "STEP 2: Mappa domini a Cloud Run"
    map_domains_cloud_run
    
    # Step 3: Ottieni record DNS
    step "STEP 3: Ottieni record DNS da configurare"
    get_dns_records
    
    # Step 4: Guida configurazione Aruba
    step "STEP 4: Configurazione DNS su Aruba"
    configure_aruba_dns
    
    # Step 5: Test finale
    step "STEP 5: Test configurazione"
    test_dns_configuration
}

# Step 1: Verifica domini Google
verify_domains_google() {
    info "🔍 Verifica domini in Google Search Console..."
    echo
    echo "1. Vai su: ${CYAN}https://search.google.com/search-console${NC}"
    echo "2. Aggiungi proprietà → Prefisso URL"
    echo "3. Inserisci: ${CYAN}https://$FRONTEND_DOMAIN${NC}"
    echo "4. Inserisci: ${CYAN}https://$BACKEND_DOMAIN${NC}"
    echo "5. Segui la procedura di verifica (file HTML o record DNS)"
    echo
    
    read -p "Hai completato la verifica dei domini? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        warning "Completa prima la verifica dei domini e riprova"
        exit 1
    fi
    
    success "Domini verificati in Search Console"
}

# Step 2: Mappa domini a Cloud Run
map_domains_cloud_run() {
    info "🔗 Mappatura domini a Cloud Run..."
    
    # Verifica che i servizi esistano
    if ! gcloud run services describe spreetzitt-backend --region="$REGION" --project="$PROJECT_ID" &>/dev/null; then
        error "Servizio spreetzitt-backend non trovato. Esegui prima il deploy!"
        exit 1
    fi
    
    if ! gcloud run services describe spreetzitt-frontend --region="$REGION" --project="$PROJECT_ID" &>/dev/null; then
        error "Servizio spreetzitt-frontend non trovato. Esegui prima il deploy!"
        exit 1
    fi
    
    # Mappa frontend
    info "📱 Mappando $FRONTEND_DOMAIN al frontend..."
    gcloud run domain-mappings create \
        --service=spreetzitt-frontend \
        --domain="$FRONTEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID" || true
    
    # Mappa backend
    info "🔧 Mappando $BACKEND_DOMAIN al backend..."
    gcloud run domain-mappings create \
        --service=spreetzitt-backend \
        --domain="$BACKEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID" || true
    
    success "Domini mappati a Cloud Run"
}

# Step 3: Ottieni record DNS
get_dns_records() {
    info "📋 Ottenimento record DNS..."
    
    # Attendi che Google generi i record
    sleep 5
    
    # Ottieni record per frontend
    local frontend_records
    frontend_records=$(gcloud run domain-mappings describe "$FRONTEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --format="csv[no-heading](status.resourceRecords.name,status.resourceRecords.type,status.resourceRecords.rrdata)" 2>/dev/null)
    
    # Ottieni record per backend
    local backend_records
    backend_records=$(gcloud run domain-mappings describe "$BACKEND_DOMAIN" \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --format="csv[no-heading](status.resourceRecords.name,status.resourceRecords.type,status.resourceRecords.rrdata)" 2>/dev/null)
    
    # Salva record in file per riferimento
    cat > dns_records_aruba.txt << EOF
# Record DNS da configurare su Aruba per $YOUR_DOMAIN
# Generato il $(date)

# FRONTEND ($FRONTEND_DOMAIN)
$frontend_records

# BACKEND ($BACKEND_DOMAIN)  
$backend_records

# ⚠️ IMPORTANTE: Configura questi record nel pannello DNS di Aruba
EOF
    
    success "Record DNS salvati in dns_records_aruba.txt"
    
    # Mostra record
    echo
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    RECORD DNS PER ARUBA                      ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo
    
    if [[ -n "$frontend_records" ]]; then
        echo "🔹 FRONTEND ($FRONTEND_DOMAIN):"
        echo "$frontend_records" | while IFS=',' read -r name type value; do
            echo "   Tipo: $type | Nome: $name | Valore: $value"
        done
        echo
    fi
    
    if [[ -n "$backend_records" ]]; then
        echo "🔹 BACKEND ($BACKEND_DOMAIN):"
        echo "$backend_records" | while IFS=',' read -r name type value; do
            echo "   Tipo: $type | Nome: $name | Valore: $value"
        done
        echo
    fi
}

# Step 4: Guida configurazione Aruba
configure_aruba_dns() {
    info "🔧 Configurazione DNS su Aruba..."
    echo
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    PROCEDURA ARUBA                           ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo
    
    echo "1. 🌐 Accedi al pannello Aruba:"
    highlight "   https://admin.aruba.it"
    echo
    
    echo "2. 📋 Vai alla sezione DNS:"
    echo "   → I tuoi servizi"
    echo "   → Domini"
    echo "   → Gestisci ($YOUR_DOMAIN)"
    echo "   → Gestione DNS"
    echo
    
    echo "3. ➕ Aggiungi i record DNS:"
    echo
    
    # Leggi i record dal file
    if [[ -f "dns_records_aruba.txt" ]]; then
        echo "   📱 Per il FRONTEND ($FRONTEND_DOMAIN):"
        grep -A 5 "FRONTEND" dns_records_aruba.txt | grep -v "^#" | grep -v "^$" | while IFS=',' read -r name type value; do
            if [[ -n "$name" && -n "$type" && -n "$value" ]]; then
                echo "   ${CYAN}→ Tipo: $type | Host: ${name/.$YOUR_DOMAIN/} | Punta a: $value${NC}"
            fi
        done
        echo
        
        echo "   🔧 Per il BACKEND ($BACKEND_DOMAIN):"
        grep -A 5 "BACKEND" dns_records_aruba.txt | grep -v "^#" | grep -v "^$" | while IFS=',' read -r name type value; do
            if [[ -n "$name" && -n "$type" && -n "$value" ]]; then
                echo "   ${CYAN}→ Tipo: $type | Host: ${name/.$YOUR_DOMAIN/} | Punta a: $value${NC}"
            fi
        done
    fi
    
    echo
    echo "4. ⏰ Attendi propagazione DNS:"
    echo "   → Aruba: 15-30 minuti"
    echo "   → Globale: fino a 48 ore (solitamente 2-6 ore)"
    echo
    
    echo "5. 🔍 Verifica configurazione:"
    echo "   → Usa: nslookup $FRONTEND_DOMAIN"
    echo "   → Usa: nslookup $BACKEND_DOMAIN"
    echo
    
    success "Guida configurazione Aruba completata"
    
    read -p "Hai configurato i record DNS su Aruba? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        success "Perfetto! Ora attendi la propagazione DNS"
    else
        warning "Configura i record DNS e riprova più tardi"
    fi
}

# Step 5: Test configurazione
test_dns_configuration() {
    info "🧪 Test configurazione DNS..."
    echo
    
    # Test risoluzione DNS
    echo "🔍 Test risoluzione DNS:"
    
    # Test frontend
    if nslookup "$FRONTEND_DOMAIN" >/dev/null 2>&1; then
        success "✅ DNS $FRONTEND_DOMAIN risolve correttamente"
    else
        warning "⚠️  DNS $FRONTEND_DOMAIN non ancora propagato"
    fi
    
    # Test backend
    if nslookup "$BACKEND_DOMAIN" >/dev/null 2>&1; then
        success "✅ DNS $BACKEND_DOMAIN risolve correttamente"
    else
        warning "⚠️  DNS $BACKEND_DOMAIN non ancora propagato"
    fi
    
    echo
    echo "🌐 Test connessioni HTTPS:"
    
    # Test frontend HTTPS
    if curl -f -s "https://$FRONTEND_DOMAIN" >/dev/null 2>&1; then
        success "✅ Frontend OK: https://$FRONTEND_DOMAIN"
    else
        warning "⚠️  Frontend non raggiungibile: https://$FRONTEND_DOMAIN"
        echo "   Possibili cause: DNS non propagato, SSL non ancora attivo"
    fi
    
    # Test backend HTTPS
    if curl -f -s "https://$BACKEND_DOMAIN/health" >/dev/null 2>&1; then
        success "✅ Backend OK: https://$BACKEND_DOMAIN"
    else
        warning "⚠️  Backend non raggiungibile: https://$BACKEND_DOMAIN"
        echo "   Possibili cause: DNS non propagato, SSL non ancora attivo"
    fi
    
    echo
    info "📊 Strumenti di debug DNS:"
    echo "   → nslookup $FRONTEND_DOMAIN"
    echo "   → nslookup $BACKEND_DOMAIN"
    echo "   → dig $FRONTEND_DOMAIN"
    echo "   → https://whatsmydns.net"
    echo
    
    if [[ -f "dns_records_aruba.txt" ]]; then
        info "📄 Record DNS salvati in: dns_records_aruba.txt"
    fi
}

# Menu con shortcuts
case "${1:-help}" in
    "setup"|"full")
        setup_aruba_dns
        ;;
    "domains"|"verify")
        verify_domains_google
        ;;
    "map")
        map_domains_cloud_run
        ;;
    "records"|"dns")
        get_dns_records
        ;;
    "aruba"|"configure")
        configure_aruba_dns
        ;;
    "test"|"check")
        test_dns_configuration
        ;;
    "help"|*)
        echo -e "${BLUE}🌐 Setup DNS Aruba per Google Cloud Run${NC}"
        echo
        echo "Collega il tuo dominio Aruba a Google Cloud Run"
        echo
        echo "Utilizzo: $0 <comando>"
        echo
        echo "Comandi:"
        echo "  setup     - Setup completo guidato"
        echo "  verify    - Verifica domini in Google Search Console"
        echo "  map       - Mappa domini a Cloud Run"
        echo "  records   - Ottieni record DNS"
        echo "  aruba     - Guida configurazione Aruba"
        echo "  test      - Test configurazione DNS"
        echo
        echo "📋 Prima dell'uso:"
        echo "  1. Modifica YOUR_DOMAIN nello script"
        echo "  2. Assicurati che i servizi Cloud Run siano deployati"
        echo "  3. Verifica domini in Google Search Console"
        echo
        echo "Esempio:"
        echo "  $0 setup   # Setup completo"
        echo "  $0 test    # Test dopo configurazione"
        echo
        echo "🔧 Configurazione attuale:"
        echo "  Dominio: $YOUR_DOMAIN"
        echo "  Frontend: $FRONTEND_DOMAIN"
        echo "  Backend: $BACKEND_DOMAIN"
        ;;
esac
