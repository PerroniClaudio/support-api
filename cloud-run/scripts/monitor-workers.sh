#!/bin/bash

# 📊 Monitor Laravel Workers su Google Cloud Run
# Parte del sistema Spreetzitt automatizzato

set -euo pipefail

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Funzioni utility
log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; exit 1; }
log_data() { echo -e "${CYAN}📊 $1${NC}"; }

# Directory dello script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLOUD_RUN_DIR="$(dirname "$SCRIPT_DIR")"

# File di configurazione
ENV_FILE="$CLOUD_RUN_DIR/config/.env.prod"

# Verifica file di configurazione
[[ ! -f "$ENV_FILE" ]] && log_error "File di configurazione $ENV_FILE non trovato"

# Carica configurazione
source "$ENV_FILE"

# Variabili di default
PROJECT_ID="${PROJECT_ID:-}"
REGION="${REGION:-europe-west8}"

# Verifica variabili essenziali
[[ -z "$PROJECT_ID" ]] && log_error "PROJECT_ID non definito in $ENV_FILE"

# Opzioni di monitoring
MONITOR_SERVICES=false
MONITOR_JOBS=false
MONITOR_ALL=false
SHOW_LOGS=false
SHOW_STATS=false
FOLLOW_LOGS=false
WATCH_MODE=false

# Parse argomenti
show_help() {
    cat << EOF
📊 Monitor Laravel Workers su Google Cloud Run

Uso: $0 [OPZIONI]

OPZIONI:
    --service        Monitor solo Cloud Run Services
    --jobs           Monitor solo Cloud Run Jobs
    --all            Monitor services e jobs [Default]
    --logs           Mostra logs recenti
    --logs-follow    Segui logs in tempo reale
    --stats          Mostra statistiche dettagliate
    --watch          Modalità watch (aggiorna ogni 30s)
    -h, --help       Mostra questo aiuto

ESEMPI:
    $0                     Monitor completo (services + jobs)
    $0 --service           Solo worker services
    $0 --logs              Mostra logs recenti
    $0 --logs-follow       Segui logs in tempo reale
    $0 --stats --watch     Statistiche in modalità watch

INFORMAZIONI MOSTRATE:
    • Status e health dei worker
    • Numero di istanze attive
    • CPU e Memory usage
    • Requests processate
    • Job executions recenti
    • Errori e retry
EOF
}

# Parse parametri
while [[ $# -gt 0 ]]; do
    case $1 in
        --service)
            MONITOR_SERVICES=true
            shift
            ;;
        --jobs)
            MONITOR_JOBS=true
            shift
            ;;
        --all)
            MONITOR_ALL=true
            shift
            ;;
        --logs)
            SHOW_LOGS=true
            shift
            ;;
        --logs-follow)
            FOLLOW_LOGS=true
            shift
            ;;
        --stats)
            SHOW_STATS=true
            shift
            ;;
        --watch)
            WATCH_MODE=true
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

# Default: monitor all se nessuna opzione specificata
if [[ "$MONITOR_SERVICES" == false && "$MONITOR_JOBS" == false && "$MONITOR_ALL" == false && "$SHOW_LOGS" == false && "$FOLLOW_LOGS" == false && "$SHOW_STATS" == false ]]; then
    MONITOR_ALL=true
fi

# Funzioni di monitoring
format_timestamp() {
    local timestamp="$1"
    date -d "$timestamp" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo "$timestamp"
}

format_duration() {
    local seconds="$1"
    if [[ "$seconds" -lt 60 ]]; then
        echo "${seconds}s"
    elif [[ "$seconds" -lt 3600 ]]; then
        echo "$((seconds / 60))m $((seconds % 60))s"
    else
        echo "$((seconds / 3600))h $((seconds % 3600 / 60))m"
    fi
}

get_service_status() {
    local service_name="$1"
    
    log_data "=== Worker Service: $service_name ==="
    
    # Get service info
    local service_info
    if service_info=$(gcloud run services describe "$service_name" --region="$REGION" --project="$PROJECT_ID" --format="json" 2>/dev/null); then
        local url=$(echo "$service_info" | jq -r '.status.url // "N/A"')
        local ready=$(echo "$service_info" | jq -r '.status.conditions[] | select(.type=="Ready") | .status')
        local last_ready=$(echo "$service_info" | jq -r '.status.conditions[] | select(.type=="Ready") | .lastTransitionTime')
        local cpu=$(echo "$service_info" | jq -r '.spec.template.spec.template.spec.containers[0].resources.limits.cpu // "N/A"')
        local memory=$(echo "$service_info" | jq -r '.spec.template.spec.template.spec.containers[0].resources.limits.memory // "N/A"')
        local min_instances=$(echo "$service_info" | jq -r '.spec.template.metadata.annotations."autoscaling.knative.dev/minScale" // "0"')
        local max_instances=$(echo "$service_info" | jq -r '.spec.template.metadata.annotations."autoscaling.knative.dev/maxScale" // "100"')
        
        # Status color
        local status_color="$RED"
        [[ "$ready" == "True" ]] && status_color="$GREEN"
        
        echo -e "   Status: ${status_color}$ready${NC}"
        echo "   URL: $url"
        echo "   Last Ready: $(format_timestamp "$last_ready")"
        echo "   Resources: CPU=$cpu, Memory=$memory"
        echo "   Scaling: Min=$min_instances, Max=$max_instances"
        
        # Get recent revisions
        local revisions
        if revisions=$(gcloud run revisions list --service="$service_name" --region="$REGION" --project="$PROJECT_ID" --limit=3 --format="json" 2>/dev/null); then
            echo "   Recent Revisions:"
            echo "$revisions" | jq -r '.[] | "     • " + .metadata.name + " (" + (.status.conditions[] | select(.type=="Ready") | .status) + ") - " + .metadata.creationTimestamp'
        fi
        
    else
        echo -e "   ${RED}Service non trovato${NC}"
    fi
    echo
}

get_job_status() {
    local job_name="$1"
    
    log_data "=== Worker Job: $job_name ==="
    
    # Get job info
    local job_info
    if job_info=$(gcloud run jobs describe "$job_name" --region="$REGION" --project="$PROJECT_ID" --format="json" 2>/dev/null); then
        local cpu=$(echo "$job_info" | jq -r '.spec.template.spec.template.spec.containers[0].resources.limits.cpu // "N/A"')
        local memory=$(echo "$job_info" | jq -r '.spec.template.spec.template.spec.containers[0].resources.limits.memory // "N/A"')
        local max_retries=$(echo "$job_info" | jq -r '.spec.template.spec.backoffLimit // "N/A"')
        local parallelism=$(echo "$job_info" | jq -r '.spec.template.spec.parallelism // "N/A"')
        
        echo "   Resources: CPU=$cpu, Memory=$memory"
        echo "   Max Retries: $max_retries"
        echo "   Parallelism: $parallelism"
        
        # Get recent executions
        local executions
        if executions=$(gcloud run jobs executions list --job="$job_name" --region="$REGION" --project="$PROJECT_ID" --limit=5 --format="json" 2>/dev/null); then
            echo "   Recent Executions:"
            echo "$executions" | jq -r '.[] | 
                "     • " + .metadata.name + 
                " (" + (.status.conditions[] | select(.type=="Complete") | .status) + 
                ") - " + .metadata.creationTimestamp + 
                " [" + (.status.runningCount // 0 | tostring) + " running, " + 
                (.status.succeededCount // 0 | tostring) + " succeeded, " + 
                (.status.failedCount // 0 | tostring) + " failed]"'
        fi
        
    else
        echo -e "   ${RED}Job non trovato${NC}"
    fi
    echo
}

get_scheduler_status() {
    log_data "=== Cloud Scheduler Jobs ==="
    
    local scheduler_jobs
    if scheduler_jobs=$(gcloud scheduler jobs list --location="$REGION" --project="$PROJECT_ID" --format="json" 2>/dev/null); then
        echo "$scheduler_jobs" | jq -r '.[] | 
            select(.name | contains("process-")) | 
            "   • " + (.name | split("/")[-1]) + 
            " - " + .schedule + 
            " (" + .state + ")"'
    else
        echo "   Nessun scheduler job trovato"
    fi
    echo
}

show_logs() {
    local service_type="$1"  # "service" or "job"
    local resource_name="$2"
    local follow="$3"        # true/false
    
    log_info "📋 Logs per $service_type: $resource_name"
    
    local cmd="gcloud logging read"
    local filter=""
    
    if [[ "$service_type" == "service" ]]; then
        filter="resource.type=cloud_run_revision AND resource.labels.service_name=$resource_name"
    else
        filter="resource.type=cloud_run_job AND resource.labels.job_name=$resource_name"
    fi
    
    filter="$filter AND timestamp>=\\\"$(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%SZ)\\\""
    
    if [[ "$follow" == "true" ]]; then
        $cmd "$filter" --format="table(timestamp,severity,textPayload)" --project="$PROJECT_ID" --freshness=10s --order=desc --limit=50
        echo
        log_info "Seguendo logs in tempo reale (Ctrl+C per fermare)..."
        tail -f <($cmd "$filter" --format="value(timestamp,severity,textPayload)" --project="$PROJECT_ID" --freshness=1s) || true
    else
        $cmd "$filter" --format="table(timestamp,severity,textPayload)" --project="$PROJECT_ID" --order=desc --limit=50
    fi
}

monitor_workers() {
    clear
    echo -e "${BLUE}📊 Spreetzitt Workers Monitor${NC}"
    echo -e "${BLUE}================================${NC}"
    echo "Progetto: $PROJECT_ID"
    echo "Regione: $REGION"
    echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
    echo

    # Worker Services
    if [[ "$MONITOR_SERVICES" == true || "$MONITOR_ALL" == true ]]; then
        log_info "🔍 Checking Worker Services..."
        
        local services
        if services=$(gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
            if [[ -n "$services" ]]; then
                while IFS= read -r service; do
                    [[ -n "$service" ]] && get_service_status "$service"
                done <<< "$services"
            else
                log_warning "Nessun worker service trovato"
            fi
        fi
    fi

    # Worker Jobs
    if [[ "$MONITOR_JOBS" == true || "$MONITOR_ALL" == true ]]; then
        log_info "🎯 Checking Worker Jobs..."
        
        local jobs
        if jobs=$(gcloud run jobs list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
            if [[ -n "$jobs" ]]; then
                while IFS= read -r job; do
                    [[ -n "$job" ]] && get_job_status "$job"
                done <<< "$jobs"
            else
                log_warning "Nessun worker job trovato"
            fi
        fi
    fi

    # Cloud Scheduler
    if [[ "$MONITOR_ALL" == true ]]; then
        get_scheduler_status
    fi

    if [[ "$SHOW_STATS" == true ]]; then
        log_info "📈 Statistiche dettagliate disponibili tramite Google Cloud Console:"
        echo "   https://console.cloud.google.com/run?project=$PROJECT_ID"
        echo
    fi
}

# Main logic
if [[ "$FOLLOW_LOGS" == true ]]; then
    # Segui logs in tempo reale
    log_info "Seleziona il worker di cui seguire i logs:"
    
    # Lista workers disponibili
    local workers=()
    
    # Aggiungi services
    local services
    if services=$(gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r service; do
            [[ -n "$service" ]] && workers+=("service:$service")
        done <<< "$services"
    fi
    
    # Aggiungi jobs
    local jobs
    if jobs=$(gcloud run jobs list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r job; do
            [[ -n "$job" ]] && workers+=("job:$job")
        done <<< "$jobs"
    fi
    
    if [[ ${#workers[@]} -eq 0 ]]; then
        log_warning "Nessun worker trovato"
        exit 0
    fi
    
    echo "Workers disponibili:"
    for i in "${!workers[@]}"; do
        echo "  $((i+1)). ${workers[i]}"
    done
    
    read -p "Seleziona (1-${#workers[@]}): " selection
    
    if [[ "$selection" =~ ^[0-9]+$ ]] && [[ "$selection" -ge 1 ]] && [[ "$selection" -le ${#workers[@]} ]]; then
        local selected="${workers[$((selection-1))]}"
        local type="${selected%%:*}"
        local name="${selected##*:}"
        show_logs "$type" "$name" "true"
    else
        log_error "Selezione non valida"
    fi

elif [[ "$SHOW_LOGS" == true ]]; then
    # Mostra logs recenti
    log_info "Logs recenti workers:"
    
    # Services logs
    local services
    if services=$(gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r service; do
            [[ -n "$service" ]] && show_logs "service" "$service" "false"
        done <<< "$services"
    fi
    
    # Jobs logs
    local jobs
    if jobs=$(gcloud run jobs list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r job; do
            [[ -n "$job" ]] && show_logs "job" "$job" "false"
        done <<< "$jobs"
    fi

elif [[ "$WATCH_MODE" == true ]]; then
    # Modalità watch
    log_info "Modalità watch attiva (aggiornamento ogni 30s)"
    log_info "Premi Ctrl+C per fermare"
    
    while true; do
        monitor_workers
        sleep 30
    done

else
    # Monitor normale
    monitor_workers
fi

log_success "Monitor completato"
