#!/bin/bash

# 🔧 Setup iniziale per Docker Swarm
# Configura l'ambiente di produzione per Spreetzitt

set -euo pipefail

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

check_prerequisites() {
    info "Verifico prerequisiti sistema..."
    
    # Verifica Docker
    if ! command -v docker &> /dev/null; then
        error "Docker non installato. Installa Docker prima di continuare."
        exit 1
    fi
    
    # Verifica Docker Compose
    if ! docker compose version &> /dev/null; then
        error "Docker Compose non disponibile. Aggiorna Docker alla versione più recente."
        exit 1
    fi
    
    success "Docker OK"
}

init_swarm() {
    info "Inizializzo Docker Swarm..."
    
    if docker info --format '{{.Swarm.LocalNodeState}}' | grep -q "active"; then
        warning "Docker Swarm già attivo"
    else
        docker swarm init
        success "Docker Swarm inizializzato"
    fi
    
    # Mostra info nodo
    docker node ls
}

setup_directories() {
    info "Creo struttura directory..."
    
    local base_dir="/opt/spreetzitt"
    local dirs=(
        "$base_dir/backups"
        "$base_dir/logs"
        "$base_dir/nginx"
        "$base_dir/sslcert"
        "$base_dir/php"
    )
    
    for dir in "${dirs[@]}"; do
        sudo mkdir -p "$dir"
        info "Directory creata: $dir"
    done
    
    # Imposta permessi
    sudo chown -R $USER:$USER "$base_dir"
    success "Struttura directory creata"
}

copy_configs() {
    info "Copio file di configurazione..."
    
    local base_dir="/opt/spreetzitt"
    
    # Copia configurazioni Nginx
    cp ../nginx/default.prod.conf "$base_dir/nginx/"
    
    # Copia configurazioni PHP
    cp ../php/supervisord.prod.conf "$base_dir/php/"
    
    # Copia certificati SSL se esistenti
    if [[ -f "../sslcert/certificate.crt" ]]; then
        cp ../sslcert/* "$base_dir/sslcert/"
        success "Certificati SSL copiati"
    else
        warning "Certificati SSL non trovati in ../sslcert/"
        info "Dovrai aggiungere manualmente:"
        info "  - $base_dir/sslcert/certificate.crt"
        info "  - $base_dir/sslcert/private.key"
    fi
    
    success "File di configurazione copiati"
}

setup_env() {
    info "Configuro file di ambiente..."
    
    local env_file="../.env.prod"
    
    if [[ ! -f "$env_file" ]]; then
        warning "File $env_file non trovato"
        
        if [[ -f "../.env.example" ]]; then
            cp "../.env.example" "$env_file"
            info "Template copiato da .env.example"
        else
            # Crea template base
            cat > "$env_file" << 'EOF'
# Laravel App
APP_NAME=Spreetzitt
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Google Cloud
DB_CONNECTION=mysql
DB_HOST=your-gcp-database-ip
DB_PORT=3306
DB_DATABASE=spreetzitt_prod
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Redis
REDIS_PASSWORD=your_redis_password

# MeiliSearch
MEILISEARCH_KEY=your_meilisearch_master_key

# Email
MAIL_MAILER=smtp
MAIL_HOST=your.smtp.host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Cache
CACHE_DRIVER=redis

# Queue
QUEUE_CONNECTION=redis
EOF
            info "Template base creato"
        fi
        
        warning "⚠️  IMPORTANTE: Configura le variabili in $env_file prima del deploy!"
        echo
        echo "Variabili da configurare:"
        echo "  - APP_KEY (genera con: php artisan key:generate)"
        echo "  - Database Google Cloud credentials"
        echo "  - Redis password"
        echo "  - MeiliSearch key"
        echo "  - Email configuration"
    else
        success "File $env_file già esistente"
    fi
}

create_docker_secrets() {
    info "Creo Docker secrets..."
    
    local env_file="../.env.prod"
    
    if [[ ! -f "$env_file" ]]; then
        warning "File $env_file non trovato. Salto creazione secrets."
        return
    fi
    
    source "$env_file"
    
    # Lista secrets da creare
    local secrets=(
        "app_key:$APP_KEY"
        "redis_password:$REDIS_PASSWORD"
        "meilisearch_key:$MEILISEARCH_KEY"
    )
    
    for secret_pair in "${secrets[@]}"; do
        local secret_name="${secret_pair%%:*}"
        local secret_value="${secret_pair#*:}"
        
        if [[ -z "$secret_value" ]]; then
            warning "Valore vuoto per secret '$secret_name'"
            continue
        fi
        
        # Rimuovi secret esistente se presente
        docker secret rm "$secret_name" 2>/dev/null || true
        
        # Crea nuovo secret
        echo "$secret_value" | docker secret create "$secret_name" -
        success "Secret '$secret_name' creato"
    done
}

test_setup() {
    info "Testo configurazione..."
    
    # Verifica Docker Swarm
    if ! docker info --format '{{.Swarm.LocalNodeState}}' | grep -q "active"; then
        error "Docker Swarm non attivo"
        return 1
    fi
    
    # Verifica file necessari
    local required_files=(
        "/opt/spreetzitt/nginx/default.prod.conf"
        "/opt/spreetzitt/php/supervisord.prod.conf"
        "../.env.prod"
        "docker-compose.swarm.yml"
        "deploy.sh"
    )
    
    for file in "${required_files[@]}"; do
        if [[ ! -f "$file" ]]; then
            error "File mancante: $file"
            return 1
        fi
    done
    
    success "Configurazione OK"
}

show_next_steps() {
    echo
    success "🎉 Setup completato!"
    echo
    info "Prossimi passi:"
    echo "1. 📝 Configura le variabili in ../.env.prod"
    echo "2. 🔐 Aggiungi certificati SSL in /opt/spreetzitt/sslcert/"
    echo "3. 🚀 Esegui il primo deploy:"
    echo "   ./deploy.sh deploy"
    echo
    info "Comandi utili:"
    echo "  ./deploy.sh status    # Stato dello stack"
    echo "  ./deploy.sh logs      # Visualizza log"
    echo "  ./deploy.sh rollback  # Rollback versione precedente"
    echo
    warning "Non dimenticare di configurare i GitHub Secrets per il CI/CD!"
}

# Main execution
main() {
    echo -e "${BLUE}🐳 Spreetzitt Docker Swarm Setup${NC}"
    echo
    
    check_prerequisites
    init_swarm
    setup_directories
    copy_configs
    setup_env
    create_docker_secrets
    test_setup
    show_next_steps
}

# Esegui solo se chiamato direttamente
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
