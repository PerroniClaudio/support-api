#!/bin/bash

# 🚀 Spreetzitt Swarm Deployment Script
# Gestisce deploy, rollback e monitoraggio dello stack Docker Swarm

set -euo pipefail

# Configurazione
STACK_NAME="spreetzitt"
COMPOSE_FILE="docker-compose.swarm.yml"
ENV_FILE="../.env.prod"
BACKUP_DIR="/opt/spreetzitt/backups"
LOG_FILE="/var/log/spreetzitt-deploy.log"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
    log "INFO: $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
    log "SUCCESS: $1"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    log "WARNING: $1"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    log "ERROR: $1"
}

# Funzioni principali
check_prerequisites() {
    info "Verifico prerequisiti..."
    
    # Verifica Docker Swarm
    if ! docker info --format '{{.Swarm.LocalNodeState}}' | grep -q "active"; then
        error "Docker Swarm non è attivo. Esegui: docker swarm init"
        exit 1
    fi
    
    # Verifica file di configurazione
    if [[ ! -f "$ENV_FILE" ]]; then
        error "File $ENV_FILE non trovato"
        exit 1
    fi
    
    # Verifica secrets
    check_secrets
    
    success "Prerequisiti verificati"
}

check_secrets() {
    local secrets=("app_key" "redis_password" "meilisearch_key")
    
    for secret in "${secrets[@]}"; do
        if ! docker secret ls --format "{{.Name}}" | grep -q "^${secret}$"; then
            warning "Secret '$secret' non trovato, creandolo..."
            create_secret "$secret"
        fi
    done
}

create_secret() {
    local secret_name="$1"
    local env_var_name=$(echo "$secret_name" | tr '[:lower:]' '[:upper:]')
    
    # Carica valore da .env.prod
    source "$ENV_FILE"
    local secret_value
    
    case "$secret_name" in
        "app_key") secret_value="${APP_KEY}" ;;
        "redis_password") secret_value="${REDIS_PASSWORD}" ;;
        "meilisearch_key") secret_value="${MEILISEARCH_KEY}" ;;
        *) error "Secret sconosciuto: $secret_name"; exit 1 ;;
    esac
    
    if [[ -z "$secret_value" ]]; then
        error "Valore per secret '$secret_name' non trovato in $ENV_FILE"
        exit 1
    fi
    
    echo "$secret_value" | docker secret create "$secret_name" -
    success "Secret '$secret_name' creato"
}

deploy() {
    local version="${1:-latest}"
    
    info "🚀 Avvio deploy della versione: $version"
    
    check_prerequisites
    
    # Backup configurazione attuale
    backup_current_config
    
    # Deploy stack
    info "Deploying stack $STACK_NAME..."
    
    export VERSION="$version"
    source "$ENV_FILE"
    
    docker stack deploy \
        --compose-file "$COMPOSE_FILE" \
        --with-registry-auth \
        "$STACK_NAME"
    
    success "Stack deployato con successo"
    
    # Verifica health
    verify_deployment
}

rollback() {
    warning "🔄 Avvio rollback..."
    
    # Ottieni versione precedente dai backup
    local previous_version
    previous_version=$(ls -t "$BACKUP_DIR" | head -n 2 | tail -n 1 | cut -d'_' -f2)
    
    if [[ -z "$previous_version" ]]; then
        error "Nessuna versione precedente trovata per il rollback"
        exit 1
    fi
    
    info "Rollback alla versione: $previous_version"
    
    export VERSION="$previous_version"
    source "$ENV_FILE"
    
    docker stack deploy \
        --compose-file "$COMPOSE_FILE" \
        --with-registry-auth \
        "$STACK_NAME"
    
    success "Rollback completato alla versione $previous_version"
    verify_deployment
}

backup_current_config() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local current_version="${VERSION:-latest}"
    local backup_file="$BACKUP_DIR/config_${current_version}_${timestamp}.yml"
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup composizione attuale
    docker stack config "$STACK_NAME" > "$backup_file" 2>/dev/null || true
    
    info "Backup configurazione salvato: $backup_file"
}

verify_deployment() {
    local max_attempts=30
    local attempt=1
    
    info "🔍 Verifica stato deployment..."
    
    while [[ $attempt -le $max_attempts ]]; do
        local running_services
        running_services=$(docker stack services "$STACK_NAME" --format "{{.Replicas}}" | grep -c "^[1-9]" || true)
        local total_services
        total_services=$(docker stack services "$STACK_NAME" --format "{{.Name}}" | wc -l)
        
        if [[ "$running_services" -eq "$total_services" && "$total_services" -gt 0 ]]; then
            success "✅ Tutti i servizi sono operativi ($running_services/$total_services)"
            show_stack_status
            return 0
        fi
        
        info "Tentativo $attempt/$max_attempts - Servizi operativi: $running_services/$total_services"
        sleep 10
        ((attempt++))
    done
    
    error "❌ Deployment non completato entro il timeout"
    show_stack_status
    exit 1
}

show_stack_status() {
    info "📊 Stato dello stack:"
    docker stack services "$STACK_NAME"
    
    echo
    info "📋 Processi in esecuzione:"
    docker stack ps "$STACK_NAME" --no-trunc
}

logs() {
    local service="${1:-}"
    local follow="${2:-false}"
    
    if [[ -n "$service" ]]; then
        if [[ "$follow" == "true" ]]; then
            docker service logs -f "${STACK_NAME}_${service}"
        else
            docker service logs "${STACK_NAME}_${service}"
        fi
    else
        info "📋 Log di tutti i servizi:"
        for service in $(docker stack services "$STACK_NAME" --format "{{.Name}}" | cut -d'_' -f2); do
            echo -e "\n${BLUE}=== Logs per $service ===${NC}"
            docker service logs --tail 50 "${STACK_NAME}_${service}"
        done
    fi
}

scale() {
    local service="$1"
    local replicas="$2"
    
    info "🔧 Scaling $service a $replicas repliche..."
    
    docker service scale "${STACK_NAME}_${service}=${replicas}"
    
    success "Scaling completato per $service"
}

cleanup() {
    warning "🧹 Pulizia risorse non utilizzate..."
    
    docker system prune -f
    docker volume prune -f
    
    # Rimuovi backup vecchi (oltre 30 giorni)
    find "$BACKUP_DIR" -name "config_*.yml" -mtime +30 -delete
    
    success "Pulizia completata"
}

remove_stack() {
    warning "🗑️  Rimozione stack $STACK_NAME..."
    
    read -p "Sei sicuro di voler rimuovere lo stack? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker stack rm "$STACK_NAME"
        success "Stack rimosso"
    else
        info "Operazione annullata"
    fi
}

# Menu principale
case "${1:-help}" in
    "deploy")
        deploy "${2:-latest}"
        ;;
    "rollback")
        rollback
        ;;
    "status")
        show_stack_status
        ;;
    "logs")
        logs "${2:-}" "${3:-false}"
        ;;
    "scale")
        scale "$2" "$3"
        ;;
    "cleanup")
        cleanup
        ;;
    "remove")
        remove_stack
        ;;
    "help"|*)
        echo -e "${BLUE}🐳 Spreetzitt Swarm Manager${NC}"
        echo
        echo "Utilizzo: $0 <comando> [opzioni]"
        echo
        echo "Comandi disponibili:"
        echo "  deploy [version]     - Deploy dello stack (default: latest)"
        echo "  rollback            - Rollback alla versione precedente"
        echo "  status              - Mostra stato dello stack"
        echo "  logs [service] [follow] - Mostra log (opzionale: servizio specifico)"
        echo "  scale <service> <replicas> - Scala un servizio"
        echo "  cleanup             - Pulisce risorse non utilizzate"
        echo "  remove              - Rimuove completamente lo stack"
        echo "  help                - Mostra questo messaggio"
        echo
        echo "Esempi:"
        echo "  $0 deploy v1.2.3    - Deploy versione specifica"
        echo "  $0 logs backend true - Segui log del backend"
        echo "  $0 scale frontend 3  - Scala frontend a 3 repliche"
        ;;
esac
