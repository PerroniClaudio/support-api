#!/bin/bash

# 🔄 Deploy Laravel Workers su Google Cloud Run
# Parte del sistema Spreetzitt automatizzato

set -euo pipefail

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni utility
log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; exit 1; }

# Directory dello script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLOUD_RUN_DIR="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$CLOUD_RUN_DIR")"

# File di configurazione
ENV_FILE="$CLOUD_RUN_DIR/config/.env.prod"

# Verifica file di configurazione
[[ ! -f "$ENV_FILE" ]] && log_error "File di configurazione $ENV_FILE non trovato"

# Carica configurazione
source "$ENV_FILE"

# Variabili di default se non definite nel .env
PROJECT_ID="${PROJECT_ID:-}"
REGION="${REGION:-europe-west1}"
DOMAIN="${DOMAIN:-}"
IMAGE_TAG="${IMAGE_TAG:-latest}"

# Verifica variabili essenziali
[[ -z "$PROJECT_ID" ]] && log_error "PROJECT_ID non definito in $ENV_FILE"

# Opzioni di deploy
DEPLOY_JOBS=false
DEPLOY_SERVICE=false
DEPLOY_HYBRID=false
DEPLOY_CRITICAL=false
DEPLOY_REPORTS=false

# Parse argomenti
show_help() {
    cat << EOF
🔄 Deploy Laravel Workers su Google Cloud Run

Uso: $0 [OPZIONI]

OPZIONI:
    --jobs-only      Deploy solo Cloud Run Jobs per task batch
    --service-only   Deploy solo Cloud Run Service per worker continui
    --hybrid         Deploy setup ibrido (service + jobs) [Raccomandato]
    --critical       Deploy solo worker per job critici
    --reports        Deploy solo worker per report pesanti
    -h, --help       Mostra questo aiuto

ESEMPI:
    $0 --hybrid         Setup completo raccomandato
    $0 --service-only   Solo worker sempre attivo
    $0 --jobs-only      Solo job batch on-demand

CONFIGURAZIONE:
    Modifica $ENV_FILE per personalizzare il deploy.
EOF
}

# Parse parametri
while [[ $# -gt 0 ]]; do
    case $1 in
        --jobs-only)
            DEPLOY_JOBS=true
            shift
            ;;
        --service-only)
            DEPLOY_SERVICE=true
            shift
            ;;
        --hybrid)
            DEPLOY_HYBRID=true
            shift
            ;;
        --critical)
            DEPLOY_CRITICAL=true
            shift
            ;;
        --reports)
            DEPLOY_REPORTS=true
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            log_error "Opzione sconosciuta: $1. Usa --help per vedere le opzioni disponibili."
            ;;
    esac
done

# Default: hybrid se nessuna opzione specificata
if [[ "$DEPLOY_JOBS" == false && "$DEPLOY_SERVICE" == false && "$DEPLOY_HYBRID" == false && "$DEPLOY_CRITICAL" == false && "$DEPLOY_REPORTS" == false ]]; then
    DEPLOY_HYBRID=true
    log_info "Nessuna opzione specificata, usando setup ibrido (raccomandato)"
fi

# Funzioni di deploy
deploy_worker_service() {
    local service_name="$1"
    local queue_name="$2"
    local memory="$3"
    local min_instances="$4"
    local max_instances="$5"

    log_info "Deploying worker service: $service_name (queue: $queue_name)"

    gcloud run deploy "$service_name" \
        --source="$PROJECT_ROOT/support-api" \
        --region="$REGION" \
        --platform=managed \
        --memory="$memory" \
        --cpu=1 \
        --min-instances="$min_instances" \
        --max-instances="$max_instances" \
        --concurrency=1 \
        --timeout=3600 \
        --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database,QUEUE_QUEUE=$queue_name,LOG_CHANNEL=stderr" \
        --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest" \
        --no-allow-unauthenticated \
        --port=8080 \
        --command="php" \
        --args="artisan,queue:work,--queue=$queue_name,--timeout=300,--memory=256,--tries=3,--sleep=3" \
        --project="$PROJECT_ID"

    log_success "Worker service $service_name deployato"
}

deploy_worker_job() {
    local job_name="$1"
    local queue_name="$2"
    local memory="$3"
    local cpu="$4"

    log_info "Deploying worker job: $job_name (queue: $queue_name)"

    # Assicurati che l'immagine esista
    local image="gcr.io/$PROJECT_ID/spreetzitt-backend:$IMAGE_TAG"
    
    gcloud run jobs create "$job_name" \
        --image="$image" \
        --region="$REGION" \
        --memory="$memory" \
        --cpu="$cpu" \
        --max-retries=3 \
        --parallelism=1 \
        --task-count=1 \
        --task-timeout=3600 \
        --command="php" \
        --args="artisan,queue:work,--queue=$queue_name,--stop-when-empty,--timeout=300,--memory=512,--tries=3" \
        --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database,QUEUE_QUEUE=$queue_name,LOG_CHANNEL=stderr" \
        --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest" \
        --project="$PROJECT_ID" \
        --replace || log_warning "Job $job_name già esistente, aggiornato"

    log_success "Worker job $job_name deployato"
}

setup_cloud_scheduler() {
    local job_name="$1"
    local schedule="$2"
    local description="$3"

    log_info "Setup Cloud Scheduler: $job_name"

    # Crea scheduler job per processare queue periodicamente
    gcloud scheduler jobs create http "process-$job_name" \
        --location="$REGION" \
        --schedule="$schedule" \
        --uri="https://$DOMAIN/api/process-queue" \
        --http-method=POST \
        --headers="Content-Type=application/json" \
        --message-body='{"queue":"'$job_name'"}' \
        --description="$description" \
        --project="$PROJECT_ID" \
        2>/dev/null || log_warning "Scheduler job process-$job_name già esistente"

    log_success "Cloud Scheduler configurato per $job_name"
}

# Main deploy logic
log_info "🚀 Iniziando deploy workers per progetto: $PROJECT_ID"

# Verifica che gcloud sia configurato
if ! gcloud config get-value project &>/dev/null; then
    log_error "gcloud non configurato. Esegui 'gcloud auth login' e 'gcloud config set project $PROJECT_ID'"
fi

# Abilita API necessarie
log_info "Abilitando API necessarie..."
gcloud services enable run.googleapis.com cloudbuild.googleapis.com cloudscheduler.googleapis.com --project="$PROJECT_ID" --quiet

# Deploy basato sulle opzioni
if [[ "$DEPLOY_HYBRID" == true ]]; then
    log_info "🔧 Deploy setup ibrido (raccomandato)"
    
    # Worker service per job critici (sempre attivo)
    deploy_worker_service "spreetzitt-worker-critical" "critical,default,emails,notifications" "512Mi" "1" "5"
    
    # Cloud Run Job per job pesanti (on-demand)
    deploy_worker_job "spreetzitt-worker-reports" "reports,analytics,exports" "2Gi" "2"
    
    # Setup schedulers
    if [[ -n "$DOMAIN" ]]; then
        setup_cloud_scheduler "reports" "0 */6 * * *" "Process reports queue every 6 hours"
        setup_cloud_scheduler "analytics" "0 2 * * *" "Process analytics queue daily at 2 AM"
    fi

elif [[ "$DEPLOY_SERVICE" == true ]]; then
    log_info "⚡ Deploy worker service continuo"
    deploy_worker_service "spreetzitt-worker" "default" "1Gi" "1" "10"

elif [[ "$DEPLOY_JOBS" == true ]]; then
    log_info "🎯 Deploy Cloud Run Jobs"
    deploy_worker_job "spreetzitt-worker" "default" "1Gi" "1"

elif [[ "$DEPLOY_CRITICAL" == true ]]; then
    log_info "🚨 Deploy worker critici"
    deploy_worker_service "spreetzitt-worker-critical" "critical,default,emails,notifications" "512Mi" "1" "5"

elif [[ "$DEPLOY_REPORTS" == true ]]; then
    log_info "📊 Deploy worker report"
    deploy_worker_job "spreetzitt-worker-reports" "reports,analytics,exports" "2Gi" "2"
fi

log_success "✨ Deploy workers completato!"
log_info "📋 Prossimi passi:"
echo "   1. Verifica deployment: ./scripts/monitor-workers.sh"
echo "   2. Test job dispatch: ./scripts/test-jobs.sh"
echo "   3. Configura DNS se necessario: ./scripts/dns-setup.sh"

if [[ -n "$DOMAIN" ]]; then
    echo "   4. API endpoint: https://$DOMAIN/api/process-queue"
fi

log_info "🔍 Per monitorare i workers:"
echo "   gcloud run services list --region=$REGION"
echo "   gcloud run jobs list --region=$REGION"
