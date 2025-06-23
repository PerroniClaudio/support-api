#!/bin/bash
set -e

# 🚀 Spreetzitt Cloud Run - Setup Iniziale
# Questo script prepara l'ambiente per il deploy su Cloud Run

echo "🚀 Spreetzitt Cloud Run - Setup Iniziale"
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

# Verifica prerequisiti
check_prerequisites() {
    log_info "Verifico prerequisiti..."
    
    # Verifica gcloud
    if ! command -v gcloud &> /dev/null; then
        log_error "gcloud CLI non trovato. Installa: brew install google-cloud-sdk"
        exit 1
    fi
    
    # Verifica Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker non trovato. Installa Docker Desktop"
        exit 1
    fi
    
    # Verifica autenticazione gcloud
    if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" | grep -q "@"; then
        log_warning "Non sei autenticato su gcloud"
        log_info "Eseguo autenticazione..."
        gcloud auth login
    fi
    
    log_success "Prerequisiti verificati"
}

# Configura progetto GCP
setup_gcp_project() {
    log_info "Configuro progetto Google Cloud..."
    
    # Mostra progetti disponibili
    echo "Progetti disponibili:"
    gcloud projects list --format="table(projectId,name,lifecycleState)"
    
    # Chiedi ID progetto se non settato
    CURRENT_PROJECT=$(gcloud config get-value project 2>/dev/null || echo "")
    if [[ -z "$CURRENT_PROJECT" ]]; then
        read -p "Inserisci l'ID del tuo progetto Google Cloud: " PROJECT_ID
        gcloud config set project $PROJECT_ID
    else
        log_info "Progetto attuale: $CURRENT_PROJECT"
        read -p "Vuoi cambiare progetto? (y/N): " CHANGE_PROJECT
        if [[ $CHANGE_PROJECT =~ ^[Yy]$ ]]; then
            read -p "Inserisci l'ID del nuovo progetto: " PROJECT_ID
            gcloud config set project $PROJECT_ID
        fi
    fi
    
    # Abilita APIs necessarie
    log_info "Abilito APIs necessarie..."
    gcloud services enable \
        run.googleapis.com \
        cloudbuild.googleapis.com \
        secretmanager.googleapis.com \
        redis.googleapis.com \
        sql-component.googleapis.com
    
    log_success "Progetto configurato"
}

# Crea file di configurazione di base
create_config_files() {
    log_info "Creo file di configurazione..."
    
    # Copia template .env se non esiste
    if [[ ! -f "config/.env.prod" ]]; then
        if [[ -f "config/.env.template" ]]; then
            cp config/.env.template config/.env.prod
            log_success "Creato config/.env.prod da template"
            log_warning "IMPORTANTE: Modifica config/.env.prod con i tuoi valori!"
        fi
    fi
    
    # Crea .gcloudignore se non esiste
    if [[ ! -f "../.gcloudignore" ]]; then
        cat > ../.gcloudignore << 'EOF'
.git
.github
.gitignore
README.md
PRODUCTION.md
node_modules
vendor
.env*
.DS_Store
*.log
EOF
        log_success "Creato .gcloudignore"
    fi
}

# Verifica struttura progetto
verify_project_structure() {
    log_info "Verifico struttura progetto..."
    
    # Verifica cartelle principali
    if [[ ! -d "../support-api" ]]; then
        log_error "Cartella support-api non trovata!"
        exit 1
    fi
    
    if [[ ! -d "../frontend" ]]; then
        log_error "Cartella frontend non trovata!"
        exit 1
    fi
    
    # Verifica composer.json nel backend
    if [[ ! -f "../support-api/composer.json" ]]; then
        log_error "composer.json non trovato in support-api!"
        exit 1
    fi
    
    # Verifica package.json nel frontend
    if [[ ! -f "../frontend/package.json" ]]; then
        log_error "package.json non trovato in frontend!"
        exit 1
    fi
    
    log_success "Struttura progetto verificata"
}

# Test connessione database (opzionale)
test_database_connection() {
    log_info "Test connessione database (opzionale)..."
    
    if [[ -f "config/.env.prod" ]]; then
        source config/.env.prod
        
        if [[ -n "$DB_HOST" && -n "$DB_DATABASE" ]]; then
            read -p "Vuoi testare la connessione al database? (y/N): " TEST_DB
            if [[ $TEST_DB =~ ^[Yy]$ ]]; then
                log_info "Testing database connection..."
                # Usa il script esistente se disponibile
                if [[ -f "../scripts/test-database-connection.sh" ]]; then
                    ../scripts/test-database-connection.sh
                else
                    log_warning "Script test database non trovato, salto il test"
                fi
            fi
        fi
    fi
}

# Menu interattivo
show_menu() {
    echo ""
    log_info "Setup completato! Cosa vuoi fare ora?"
    echo "1) Deploy completo (backend + frontend)"
    echo "2) Deploy solo backend"
    echo "3) Deploy solo frontend"
    echo "4) Setup domini personalizzati"
    echo "5) Crea Google Cloud Secrets"
    echo "6) Esci"
    echo ""
    
    read -p "Scegli un'opzione (1-6): " CHOICE
    
    case $CHOICE in
        1)
            log_info "Avvio deploy completo..."
            ./deploy.sh
            ;;
        2)
            log_info "Avvio deploy backend..."
            ./scripts/deploy-backend.sh
            ;;
        3)
            log_info "Avvio deploy frontend..."
            ./scripts/deploy-frontend.sh
            ;;
        4)
            log_info "Avvio setup domini..."
            ./scripts/dns-setup.sh
            ;;
        5)
            log_info "Avvio creazione secrets..."
            ./scripts/create-secrets.sh
            ;;
        6)
            log_success "Setup terminato!"
            exit 0
            ;;
        *)
            log_error "Opzione non valida"
            show_menu
            ;;
    esac
}

# Main execution
main() {
    check_prerequisites
    setup_gcp_project
    verify_project_structure
    create_config_files
    test_database_connection
    
    log_success "Setup iniziale completato!"
    log_warning "RICORDA: Modifica config/.env.prod prima del deploy!"
    
    show_menu
}

# Esegui solo se chiamato direttamente
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
