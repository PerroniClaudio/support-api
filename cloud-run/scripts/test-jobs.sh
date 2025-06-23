#!/bin/bash

# 🧪 Test Laravel Jobs su Google Cloud Run
# Parte del sistema Spreetzitt automatizzato

set -euo pipefail

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Funzioni utility
log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; exit 1; }
log_test() { echo -e "${PURPLE}🧪 $1${NC}"; }
log_result() { echo -e "${CYAN}📊 $1${NC}"; }

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
DOMAIN="${DOMAIN:-}"

# Verifica variabili essenziali
[[ -z "$PROJECT_ID" ]] && log_error "PROJECT_ID non definito in $ENV_FILE"

# Opzioni di test
TEST_CRITICAL=false
TEST_REPORTS=false
TEST_ALL=false
TEST_DISPATCH=false
TEST_SERVICES=false
TEST_JOBS=false
TEST_CONNECTIONS=false
TEST_PERFORMANCE=false

# Parse argomenti
show_help() {
    cat << EOF
🧪 Test Laravel Jobs su Google Cloud Run

Uso: $0 [OPZIONI]

OPZIONI:
    --critical       Test worker critici (email, notifiche)
    --reports        Test worker report (analytics, export)
    --all            Test completo tutti i workers [Default]
    --dispatch       Test dispatch job via API
    --services       Test solo Cloud Run Services
    --jobs           Test solo Cloud Run Jobs
    --connections    Test connessioni database e queue
    --performance    Test performance e scaling
    -h, --help       Mostra questo aiuto

ESEMPI:
    $0                    Test completo tutti i worker
    $0 --critical         Test solo worker critici
    $0 --dispatch         Test dispatch job via API
    $0 --performance      Test performance e scaling

TEST ESEGUITI:
    • Health check workers
    • Connessioni database
    • Dispatch job di test
    • Verifica processing
    • Performance metrics
    • Error handling
EOF
}

# Parse parametri
while [[ $# -gt 0 ]]; do
    case $1 in
        --critical)
            TEST_CRITICAL=true
            shift
            ;;
        --reports)
            TEST_REPORTS=true
            shift
            ;;
        --all)
            TEST_ALL=true
            shift
            ;;
        --dispatch)
            TEST_DISPATCH=true
            shift
            ;;
        --services)
            TEST_SERVICES=true
            shift
            ;;
        --jobs)
            TEST_JOBS=true
            shift
            ;;
        --connections)
            TEST_CONNECTIONS=true
            shift
            ;;
        --performance)
            TEST_PERFORMANCE=true
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

# Default: test all se nessuna opzione specificata
if [[ "$TEST_CRITICAL" == false && "$TEST_REPORTS" == false && "$TEST_ALL" == false && "$TEST_DISPATCH" == false && "$TEST_SERVICES" == false && "$TEST_JOBS" == false && "$TEST_CONNECTIONS" == false && "$TEST_PERFORMANCE" == false ]]; then
    TEST_ALL=true
fi

# Funzioni di test
test_service_health() {
    local service_name="$1"
    local queue_name="$2"
    
    log_test "Testing service health: $service_name"
    
    # Get service URL
    local service_url
    if service_url=$(gcloud run services describe "$service_name" --region="$REGION" --project="$PROJECT_ID" --format="value(status.url)" 2>/dev/null); then
        
        # Test health endpoint
        local health_response
        if health_response=$(curl -s -w "%{http_code}" -m 30 "$service_url/worker-health" 2>/dev/null); then
            local status_code="${health_response: -3}"
            local response_body="${health_response%???}"
            
            if [[ "$status_code" == "200" ]]; then
                log_success "Service $service_name: HEALTHY"
                log_result "Response: $response_body"
            else
                log_warning "Service $service_name: Status $status_code"
                log_result "Response: $response_body"
            fi
        else
            log_warning "Service $service_name: Health check fallito (timeout/network error)"
        fi
        
        # Test metrics endpoint (if exists)
        if curl -s -m 10 "$service_url/metrics" >/dev/null 2>&1; then
            log_success "Metrics endpoint disponibile"
        fi
        
    else
        log_warning "Service $service_name non trovato o non accessibile"
    fi
}

test_job_execution() {
    local job_name="$1"
    local queue_name="$2"
    
    log_test "Testing job execution: $job_name"
    
    # Trigger job execution
    local execution_name
    if execution_name=$(gcloud run jobs execute "$job_name" --region="$REGION" --project="$PROJECT_ID" --format="value(metadata.name)" --async 2>/dev/null); then
        log_success "Job $job_name eseguito: $execution_name"
        
        # Wait and check status
        local retries=0
        local max_retries=30
        
        while [[ $retries -lt $max_retries ]]; do
            local status
            if status=$(gcloud run jobs executions describe "$execution_name" --region="$REGION" --project="$PROJECT_ID" --format="value(status.conditions[0].status)" 2>/dev/null); then
                case "$status" in
                    "True")
                        log_success "Job execution completata con successo"
                        
                        # Get execution details
                        local details
                        if details=$(gcloud run jobs executions describe "$execution_name" --region="$REGION" --project="$PROJECT_ID" --format="json" 2>/dev/null); then
                            local start_time=$(echo "$details" | jq -r '.status.startTime // "N/A"')
                            local completion_time=$(echo "$details" | jq -r '.status.completionTime // "N/A"')
                            local succeeded=$(echo "$details" | jq -r '.status.succeededCount // 0')
                            local failed=$(echo "$details" | jq -r '.status.failedCount // 0')
                            
                            log_result "Start: $start_time"
                            log_result "Completion: $completion_time"
                            log_result "Succeeded: $succeeded, Failed: $failed"
                        fi
                        break
                        ;;
                    "False")
                        log_warning "Job execution fallita"
                        
                        # Get failure details
                        local failure_reason
                        if failure_reason=$(gcloud run jobs executions describe "$execution_name" --region="$REGION" --project="$PROJECT_ID" --format="value(status.conditions[0].message)" 2>/dev/null); then
                            log_result "Failure reason: $failure_reason"
                        fi
                        break
                        ;;
                    "Unknown"|"")
                        ((retries++))
                        sleep 5
                        ;;
                esac
            else
                log_warning "Errore nel recupero dello status"
                break
            fi
        done
        
        if [[ $retries -eq $max_retries ]]; then
            log_warning "Timeout nel controllo dello status"
        fi
        
    else
        log_warning "Errore nell'esecuzione del job $job_name"
    fi
}

test_api_dispatch() {
    local queue_name="$1"
    
    if [[ -z "$DOMAIN" ]]; then
        log_warning "DOMAIN non configurato, skip test API dispatch"
        return
    fi
    
    log_test "Testing API job dispatch per queue: $queue_name"
    
    local api_url="https://$DOMAIN/api/process-queue"
    local payload="{\"queue\":\"$queue_name\"}"
    
    # Test dispatch via API
    local response
    if response=$(curl -s -w "%{http_code}" -X POST -H "Content-Type: application/json" -d "$payload" -m 30 "$api_url" 2>/dev/null); then
        local status_code="${response: -3}"
        local response_body="${response%???}"
        
        if [[ "$status_code" == "200" ]]; then
            log_success "API dispatch successful"
            log_result "Response: $response_body"
        else
            log_warning "API dispatch failed: Status $status_code"
            log_result "Response: $response_body"
        fi
    else
        log_warning "API dispatch failed (timeout/network error)"
    fi
}

test_database_connection() {
    log_test "Testing database connection"
    
    # Use a worker service to test DB connection
    local services
    if services=$(gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" --limit=1 2>/dev/null); then
        local service_name
        service_name=$(echo "$services" | head -n1)
        
        if [[ -n "$service_name" ]]; then
            local service_url
            if service_url=$(gcloud run services describe "$service_name" --region="$REGION" --project="$PROJECT_ID" --format="value(status.url)" 2>/dev/null); then
                
                # Test DB connection via health check
                local response
                if response=$(curl -s -m 30 "$service_url/worker-health" 2>/dev/null | jq -r '.status // "unknown"' 2>/dev/null); then
                    if [[ "$response" == "healthy" ]]; then
                        log_success "Database connection: OK"
                    else
                        log_warning "Database connection: $response"
                    fi
                else
                    log_warning "Unable to test database connection"
                fi
            fi
        fi
    else
        log_warning "No worker services found for DB test"
    fi
}

test_queue_performance() {
    log_test "Testing queue performance"
    
    # Test multiple job dispatches
    local test_jobs=5
    local start_time=$(date +%s)
    
    log_info "Dispatching $test_jobs test jobs..."
    
    for i in $(seq 1 $test_jobs); do
        if [[ -n "$DOMAIN" ]]; then
            curl -s -X POST \
                -H "Content-Type: application/json" \
                -d "{\"queue\":\"test\",\"job\":\"test-job-$i\"}" \
                "https://$DOMAIN/api/process-queue" >/dev/null 2>&1 &
        fi
    done
    
    wait
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    log_result "Dispatched $test_jobs jobs in ${duration}s"
    log_result "Rate: $(echo "scale=2; $test_jobs / $duration" | bc -l 2>/dev/null || echo "N/A") jobs/sec"
}

show_worker_summary() {
    log_info "📊 Worker Summary"
    echo
    
    # Services
    log_result "=== Cloud Run Services ==="
    local services
    if services=$(gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="table(name,region,url,ready)" 2>/dev/null); then
        echo "$services"
    else
        echo "No services found"
    fi
    echo
    
    # Jobs
    log_result "=== Cloud Run Jobs ==="
    local jobs
    if jobs=$(gcloud run jobs list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="table(name,region,created)" 2>/dev/null); then
        echo "$jobs"
    else
        echo "No jobs found"
    fi
    echo
    
    # Recent executions
    log_result "=== Recent Job Executions ==="
    local all_jobs
    if all_jobs=$(gcloud run jobs list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r job; do
            if [[ -n "$job" ]]; then
                echo "Job: $job"
                gcloud run jobs executions list --job="$job" --region="$REGION" --project="$PROJECT_ID" --limit=3 --format="table(name,status,creationTimestamp)" 2>/dev/null || echo "  No executions found"
                echo
            fi
        done <<< "$all_jobs"
    fi
}

# Main test logic
log_info "🧪 Avviando test Laravel Workers"
echo "Progetto: $PROJECT_ID"
echo "Regione: $REGION"
[[ -n "$DOMAIN" ]] && echo "Dominio: $DOMAIN"
echo

# Test connessioni database
if [[ "$TEST_CONNECTIONS" == true || "$TEST_ALL" == true ]]; then
    test_database_connection
    echo
fi

# Test worker services
if [[ "$TEST_SERVICES" == true || "$TEST_ALL" == true || "$TEST_CRITICAL" == true ]]; then
    log_info "🔧 Testing Worker Services"
    
    local services
    if services=$(gcloud run services list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r service; do
            if [[ -n "$service" ]]; then
                if [[ "$TEST_CRITICAL" == true && "$service" != *"critical"* ]]; then
                    continue
                fi
                test_service_health "$service" "default"
                echo
            fi
        done <<< "$services"
    else
        log_warning "Nessun worker service trovato"
    fi
fi

# Test worker jobs
if [[ "$TEST_JOBS" == true || "$TEST_ALL" == true || "$TEST_REPORTS" == true ]]; then
    log_info "🎯 Testing Worker Jobs"
    
    local jobs
    if jobs=$(gcloud run jobs list --region="$REGION" --project="$PROJECT_ID" --filter="metadata.name:spreetzitt-worker" --format="value(metadata.name)" 2>/dev/null); then
        while IFS= read -r job; do
            if [[ -n "$job" ]]; then
                if [[ "$TEST_REPORTS" == true && "$job" != *"reports"* ]]; then
                    continue
                fi
                test_job_execution "$job" "default"
                echo
            fi
        done <<< "$jobs"
    else
        log_warning "Nessun worker job trovato"
    fi
fi

# Test API dispatch
if [[ "$TEST_DISPATCH" == true || "$TEST_ALL" == true ]]; then
    log_info "🚀 Testing API Dispatch"
    
    if [[ "$TEST_CRITICAL" == true ]]; then
        test_api_dispatch "critical"
        test_api_dispatch "emails"
    elif [[ "$TEST_REPORTS" == true ]]; then
        test_api_dispatch "reports"
        test_api_dispatch "analytics"
    else
        test_api_dispatch "default"
        test_api_dispatch "critical"
        test_api_dispatch "reports"
    fi
    echo
fi

# Test performance
if [[ "$TEST_PERFORMANCE" == true ]]; then
    log_info "⚡ Testing Performance"
    test_queue_performance
    echo
fi

# Show summary
show_worker_summary

log_success "✨ Test workers completato!"

# Suggerimenti
log_info "💡 Prossimi passi:"
echo "   1. Monitor workers: ./scripts/monitor-workers.sh"
echo "   2. Check logs: ./scripts/monitor-workers.sh --logs"
echo "   3. Performance monitoring: https://console.cloud.google.com/run?project=$PROJECT_ID"

if [[ -n "$DOMAIN" ]]; then
    echo "   4. API endpoint: https://$DOMAIN/api/process-queue"
fi
