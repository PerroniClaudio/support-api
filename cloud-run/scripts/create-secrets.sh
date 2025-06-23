#!/bin/bash
set -e

# 🔐 Spreetzitt Cloud Run - Gestione Google Cloud Secrets
# Crea e gestisce secrets da file .env.prod

echo "🔐 Gestione Google Cloud Secrets"
echo "================================"

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
ENV_FILE="config/.env.prod"

# Lista delle variabili sensibili che devono diventare secrets
SENSITIVE_VARS=(
    "APP_KEY"
    "DB_PASSWORD"
    "JWT_SECRET"
    "MAIL_PASSWORD"
    "MEILISEARCH_KEY"
    "REDIS_PASSWORD"
    "GOOGLE_CLOUD_KEY_FILE"
)

# Verifica prerequisiti
check_prerequisites() {
    log_info "Verifico prerequisiti..."
    
    if [[ -z "$PROJECT_ID" ]]; then
        log_error "Progetto Google Cloud non configurato"
        exit 1
    fi
    
    if [[ ! -f "$ENV_FILE" ]]; then
        log_error "File $ENV_FILE non trovato. Copia e modifica config/.env.template"
        exit 1
    fi
    
    # Verifica che l'API Secret Manager sia abilitata
    if ! gcloud services list --enabled --filter="name:secretmanager.googleapis.com" --format="value(name)" | grep -q secretmanager; then
        log_warning "API Secret Manager non abilitata. Abilito..."
        gcloud services enable secretmanager.googleapis.com
    fi
    
    log_success "Prerequisiti verificati"
}

# Legge valore da .env.prod
get_env_value() {
    local var_name=$1
    grep "^${var_name}=" "$ENV_FILE" | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//'
}

# Verifica se un secret esiste già
secret_exists() {
    local secret_name=$1
    gcloud secrets describe "$secret_name" >/dev/null 2>&1
}

# Crea o aggiorna un secret
create_or_update_secret() {
    local var_name=$1
    local secret_name=$(echo "$var_name" | tr '[:upper:]' '[:lower:]' | tr '_' '-')
    local var_value=$(get_env_value "$var_name")
    
    if [[ -z "$var_value" ]]; then
        log_warning "Variabile $var_name non trovata o vuota in $ENV_FILE"
        return
    fi
    
    if secret_exists "$secret_name"; then
        log_info "Aggiorno secret esistente: $secret_name"
        echo -n "$var_value" | gcloud secrets versions add "$secret_name" --data-file=-
    else
        log_info "Creo nuovo secret: $secret_name"
        echo -n "$var_value" | gcloud secrets create "$secret_name" --data-file=-
    fi
    
    log_success "Secret $secret_name configurato"
}

# Lista tutti i secrets
list_secrets() {
    log_info "Secrets attualmente configurati:"
    gcloud secrets list --format="table(name,createTime)" --filter="name:app-* OR name:db-* OR name:jwt-* OR name:mail-* OR name:redis-*"
}

# Mostra variabili d'ambiente per Cloud Run
show_env_vars() {
    log_info "Variabili d'ambiente per Cloud Run (non sensibili):"
    
    # Lista variabili non sensibili
    while IFS= read -r line; do
        if [[ $line =~ ^[A-Z_]+=.* ]] && [[ ! $line =~ ^# ]]; then
            var_name=$(echo "$line" | cut -d '=' -f1)
            
            # Controlla se è una variabile sensibile
            is_sensitive=false
            for sensitive_var in "${SENSITIVE_VARS[@]}"; do
                if [[ "$var_name" == "$sensitive_var" ]]; then
                    is_sensitive=true
                    break
                fi
            done
            
            if [[ "$is_sensitive" == "false" ]]; then
                echo "   $line"
            fi
        fi
    done < "$ENV_FILE"
}

# Genera comando per Cloud Run deploy
generate_deploy_command() {
    log_info "Comando per deploy con secrets:"
    echo ""
    echo "gcloud run deploy spreetzitt-backend \\"
    echo "  --source ../support-api \\"
    echo "  --region europe-west8 \\"
    
    # Aggiungi secrets
    local secrets_args=""
    for var_name in "${SENSITIVE_VARS[@]}"; do
        local secret_name=$(echo "$var_name" | tr '[:upper:]' '[:lower:]' | tr '_' '-')
        if secret_exists "$secret_name"; then
            if [[ -n "$secrets_args" ]]; then
                secrets_args+=","
            fi
            secrets_args+="${var_name}=${secret_name}:latest"
        fi
    done
    
    if [[ -n "$secrets_args" ]]; then
        echo "  --set-secrets=\"$secrets_args\" \\"
    fi
    
    # Aggiungi variabili d'ambiente non sensibili
    local env_vars=""
    while IFS= read -r line; do
        if [[ $line =~ ^[A-Z_]+=.* ]] && [[ ! $line =~ ^# ]]; then
            var_name=$(echo "$line" | cut -d '=' -f1)
            var_value=$(echo "$line" | cut -d '=' -f2-)
            
            # Controlla se è una variabile sensibile
            is_sensitive=false
            for sensitive_var in "${SENSITIVE_VARS[@]}"; do
                if [[ "$var_name" == "$sensitive_var" ]]; then
                    is_sensitive=true
                    break
                fi
            done
            
            if [[ "$is_sensitive" == "false" ]] && [[ "$var_name" != "VITE_"* ]]; then
                if [[ -n "$env_vars" ]]; then
                    env_vars+=","
                fi
                env_vars+="${var_name}=${var_value}"
            fi
        fi
    done < "$ENV_FILE"
    
    if [[ -n "$env_vars" ]]; then
        echo "  --set-env-vars=\"$env_vars\" \\"
    fi
    
    echo "  --allow-unauthenticated"
    echo ""
}

# Test accesso ai secrets
test_secrets() {
    log_info "Test accesso ai secrets..."
    
    for var_name in "${SENSITIVE_VARS[@]}"; do
        local secret_name=$(echo "$var_name" | tr '[:upper:]' '[:lower:]' | tr '_' '-')
        
        if secret_exists "$secret_name"; then
            if gcloud secrets versions access latest --secret="$secret_name" >/dev/null 2>&1; then
                log_success "Secret $secret_name accessibile"
            else
                log_error "Impossibile accedere al secret $secret_name"
            fi
        fi
    done
}

# Pulizia secrets (per development)
cleanup_secrets() {
    log_warning "ATTENZIONE: Questa operazione eliminerà TUTTI i secrets!"
    read -p "Sei sicuro di voler continuare? (y/N): " CONFIRM
    
    if [[ $CONFIRM =~ ^[Yy]$ ]]; then
        log_info "Elimino secrets..."
        
        for var_name in "${SENSITIVE_VARS[@]}"; do
            local secret_name=$(echo "$var_name" | tr '[:upper:]' '[:lower:]' | tr '_' '-')
            
            if secret_exists "$secret_name"; then
                gcloud secrets delete "$secret_name" --quiet
                log_success "Secret $secret_name eliminato"
            fi
        done
    else
        log_info "Operazione annullata"
    fi
}

# Menu principale
show_menu() {
    echo ""
    log_info "Cosa vuoi fare?"
    echo "1) Crea/aggiorna tutti i secrets"
    echo "2) Lista secrets esistenti"
    echo "3) Mostra variabili d'ambiente"
    echo "4) Genera comando deploy"
    echo "5) Test accesso secrets"
    echo "6) Pulisci tutti i secrets"
    echo "7) Esci"
    echo ""
    
    read -p "Scegli un'opzione (1-7): " CHOICE
    
    case $CHOICE in
        1)
            create_all_secrets
            ;;
        2)
            list_secrets
            show_menu
            ;;
        3)
            show_env_vars
            show_menu
            ;;
        4)
            generate_deploy_command
            show_menu
            ;;
        5)
            test_secrets
            show_menu
            ;;
        6)
            cleanup_secrets
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

# Crea tutti i secrets
create_all_secrets() {
    log_info "Creo/aggiorno tutti i secrets..."
    
    for var_name in "${SENSITIVE_VARS[@]}"; do
        create_or_update_secret "$var_name"
    done
    
    log_success "Tutti i secrets sono stati configurati!"
    show_menu
}

# Main execution
main() {
    check_prerequisites
    
    if [[ $# -eq 0 ]]; then
        show_menu
    else
        case $1 in
            create)
                create_all_secrets
                ;;
            list)
                list_secrets
                ;;
            test)
                test_secrets
                ;;
            cleanup)
                cleanup_secrets
                ;;
            *)
                log_error "Comando non riconosciuto: $1"
                log_info "Comandi disponibili: create, list, test, cleanup"
                exit 1
                ;;
        esac
    fi
}

# Esegui solo se chiamato direttamente
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
