#!/bin/bash

# 🔍 Setup MeiliSearch per Cloud Run
# Soluzioni per integrare MeiliSearch con architettura Cloud Run

set -euo pipefail

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

# Configurazione
PROJECT_ID="your-gcp-project-id"
REGION="europe-west8"
ZONE="europe-west8-b"

# Opzione 1: MeiliSearch Cloud (Raccomandato)
setup_meilisearch_cloud() {
    info "🌊 Setup MeiliSearch Cloud..."
    
    echo "1. Vai su https://cloud.meilisearch.com"
    echo "2. Crea account e progetto"  
    echo "3. Ottieni API key e endpoint"
    echo "4. Configura secrets Google Cloud:"
    echo
    
    # Crea secrets per MeiliSearch Cloud
    read -p "Inserisci MeiliSearch Cloud URL: " meili_url
    read -p "Inserisci MeiliSearch Master Key: " meili_key
    
    # Crea secrets
    echo "$meili_url" | gcloud secrets create meilisearch-url --data-file=-
    echo "$meili_key" | gcloud secrets create meilisearch-key --data-file=-
    
    success "MeiliSearch Cloud configurato"
    
    # Aggiorna Cloud Run services
    gcloud run services update spreetzitt-backend \
        --set-secrets="MEILISEARCH_HOST=meilisearch-url:latest,MEILISEARCH_KEY=meilisearch-key:latest" \
        --region="$REGION"
        
    success "Cloud Run aggiornato con MeiliSearch Cloud"
}

# Opzione 2: VM dedicata MeiliSearch  
setup_meilisearch_vm() {
    info "🖥️  Setup MeiliSearch su VM dedicata..."
    
    # Crea VM micro per MeiliSearch
    gcloud compute instances create meilisearch-vm \
        --machine-type=e2-micro \
        --image-family=ubuntu-2004-lts \
        --image-project=ubuntu-os-cloud \
        --boot-disk-size=20GB \
        --boot-disk-type=pd-standard \
        --zone="$ZONE" \
        --tags=meilisearch-server \
        --metadata-from-file startup-script=meilisearch-startup.sh
    
    # Crea regola firewall per MeiliSearch
    gcloud compute firewall-rules create allow-meilisearch \
        --allow tcp:7700 \
        --source-ranges 0.0.0.0/0 \
        --target-tags meilisearch-server \
        --description "Allow MeiliSearch access"
    
    # Ottieni IP della VM
    local vm_ip
    vm_ip=$(gcloud compute instances describe meilisearch-vm \
        --zone="$ZONE" \
        --format="get(networkInterfaces[0].accessConfigs[0].natIP)")
    
    success "MeiliSearch VM creata: $vm_ip:7700"
    
    # Configura Cloud Run per usare la VM
    gcloud run services update spreetzitt-backend \
        --set-env-vars="MEILISEARCH_HOST=http://$vm_ip:7700" \
        --region="$REGION"
    
    info "Attendi 2-3 minuti per l'avvio di MeiliSearch sulla VM"
}

# Opzione 3: Alternative a MeiliSearch
setup_alternatives() {
    info "🔄 Alternative a MeiliSearch..."
    
    echo "Opzioni disponibili:"
    echo
    echo "1. 🔍 Algolia (Search-as-a-Service)"
    echo "   - Tier gratuito: 10k operazioni/mese"
    echo "   - Costo: €0-50/mese"
    echo "   - Pro: Velocissimo, globale"
    echo "   - Contro: Meno flessibile"
    echo
    echo "2. 🔍 Elasticsearch Service (Google Cloud)"
    echo "   - Costo: €50-100/mese"
    echo "   - Pro: Molto potente, flessibile"
    echo "   - Contro: Complesso, costoso"
    echo
    echo "3. 🔍 Typesense Cloud"
    echo "   - Simile a MeiliSearch"
    echo "   - Costo: €25-50/mese"
    echo "   - Pro: Open source, veloce"
    echo
    echo "4. 🔍 Database Full-Text Search"
    echo "   - Usa MySQL/PostgreSQL full-text"
    echo "   - Costo: €0 (già incluso)"
    echo "   - Pro: Semplice, integrato"
    echo "   - Contro: Meno performante"
    
    read -p "Vuoi configurare una di queste alternative? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        setup_database_fulltext
    fi
}

# Setup Database Full-Text Search (opzione economica)
setup_database_fulltext() {
    info "📊 Configurazione Database Full-Text Search..."
    
    cat > ../database/migrations/add_fulltext_search.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFulltextSearch extends Migration
{
    public function up()
    {
        // Aggiungi indici full-text per la ricerca
        Schema::table('posts', function (Blueprint $table) {
            $table->fullText(['title', 'content']);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->fullText(['name', 'email']);
        });
    }
    
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropFullText(['title', 'content']);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropFullText(['name', 'email']);
        });
    }
}
EOF

    cat > ../support-api/app/Services/DatabaseSearchService.php << 'EOF'
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class DatabaseSearchService
{
    public function search(string $query, array $models = [])
    {
        $results = [];
        
        foreach ($models as $model) {
            $results[$model] = $model::whereRaw(
                "MATCH(title, content) AGAINST(? IN BOOLEAN MODE)",
                [$query . '*']
            )->limit(10)->get();
        }
        
        return $results;
    }
}
EOF

    success "Database Full-Text Search configurato"
    info "Esegui: php artisan migrate per applicare le modifiche"
}

# Crea script startup per VM MeiliSearch
create_meilisearch_startup_script() {
    cat > meilisearch-startup.sh << 'EOF'
#!/bin/bash

# Startup script per MeiliSearch su VM

# Aggiorna sistema
apt-get update
apt-get install -y curl

# Installa MeiliSearch
curl -L https://install.meilisearch.com | sh

# Configura MeiliSearch
mkdir -p /opt/meilisearch
cd /opt/meilisearch

# Crea file di configurazione
cat > config.toml << 'CONFIG'
env = "production"
master_key = "your-secure-master-key-here"
db_path = "/opt/meilisearch/data"
http_addr = "0.0.0.0:7700"
log_level = "INFO"
max_indexing_memory = "256Mb"
CONFIG

# Crea systemd service
cat > /etc/systemd/system/meilisearch.service << 'SERVICE'
[Unit]
Description=MeiliSearch
After=network.target

[Service]
Type=simple
User=meilisearch
Group=meilisearch
ExecStart=/usr/local/bin/meilisearch --config-file-path /opt/meilisearch/config.toml
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
SERVICE

# Crea utente meilisearch
useradd -r -s /bin/false meilisearch
chown -R meilisearch:meilisearch /opt/meilisearch

# Avvia servizio
systemctl daemon-reload
systemctl enable meilisearch
systemctl start meilisearch

# Verifica stato
sleep 10
curl -s http://localhost:7700/health || echo "MeiliSearch non ancora pronto"
EOF

    chmod +x meilisearch-startup.sh
    success "Script startup MeiliSearch creato"
}

# Aggiorna Cloud Run deploy script
update_cloud_run_deploy() {
    info "📝 Aggiornamento script Cloud Run..."
    
    # Backup script esistente
    cp cloud-run-deploy.sh cloud-run-deploy.sh.backup
    
    # Aggiungi gestione MeiliSearch
    cat >> cloud-run-deploy.sh << 'EOF'

# Setup MeiliSearch per Cloud Run
setup_meilisearch() {
    local option="${1:-cloud}"
    
    case "$option" in
        "cloud")
            info "Configurando MeiliSearch Cloud..."
            # Secrets già configurati manualmente
            ;;
        "vm")
            info "Configurando MeiliSearch VM..."
            ./meilisearch-setup.sh setup_meilisearch_vm
            ;;
        "database")
            info "Configurando Database Full-Text..."
            ./meilisearch-setup.sh setup_database_fulltext
            ;;
        *)
            error "Opzione non valida: $option"
            exit 1
            ;;
    esac
}

# Aggiungi al menu principale
case "${1:-help}" in
    # ...existing cases...
    "setup-search")
        setup_meilisearch "${2:-cloud}"
        ;;
    # ...existing help...
esac
EOF

    success "Script Cloud Run aggiornato"
}

# Confronto costi
show_cost_comparison() {
    info "💰 Confronto costi MeiliSearch:"
    echo
    echo "┌─────────────────────┬──────────────┬─────────────────┐"
    echo "│ Soluzione           │ Costo/mese   │ Gestione        │"
    echo "├─────────────────────┼──────────────┼─────────────────┤"
    echo "│ MeiliSearch Cloud   │ €0-29        │ Zero            │"
    echo "│ VM e2-micro         │ €7-10        │ Media           │"
    echo "│ Database Full-Text  │ €0           │ Bassa           │"
    echo "│ Algolia             │ €0-50        │ Zero            │"
    echo "│ Elasticsearch       │ €50-100      │ Alta            │"
    echo "└─────────────────────┴──────────────┴─────────────────┘"
    echo
    echo "🎯 Raccomandazione: MeiliSearch Cloud per semplicità"
}

# Menu principale
case "${1:-help}" in
    "cloud")
        setup_meilisearch_cloud
        ;;
    "vm")
        create_meilisearch_startup_script
        setup_meilisearch_vm
        ;;
    "alternatives")
        setup_alternatives
        ;;
    "database")
        setup_database_fulltext
        ;;
    "update-deploy")
        update_cloud_run_deploy
        ;;
    "costs")
        show_cost_comparison
        ;;
    "help"|*)
        echo -e "${BLUE}🔍 MeiliSearch Setup per Cloud Run${NC}"
        echo
        echo "Utilizzo: $0 <comando>"
        echo
        echo "Comandi:"
        echo "  cloud        - Setup MeiliSearch Cloud (raccomandato)"
        echo "  vm           - Setup MeiliSearch su VM e2-micro"
        echo "  alternatives - Mostra alternative (Algolia, etc.)"
        echo "  database     - Setup Database Full-Text Search"
        echo "  update-deploy- Aggiorna script deploy"
        echo "  costs        - Confronto costi"
        echo
        echo "Esempi:"
        echo "  $0 cloud     # Setup MeiliSearch Cloud"
        echo "  $0 vm        # Setup VM dedicata"
        echo "  $0 costs     # Mostra confronto costi"
        ;;
esac
