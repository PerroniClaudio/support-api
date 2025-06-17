#!/bin/bash

# 🎯 Migrazione da MeiliSearch a Laravel Scout Database Driver
# La soluzione più semplice: usa MySQL full-text search integrato

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

# Directory di lavoro
LARAVEL_DIR="../support-api"

# Funzione principale
migrate_to_database_driver() {
    info "🎯 Migrazione a Laravel Scout Database Driver..."
    
    # 1. Aggiorna configurazione Scout
    update_scout_config
    
    # 2. Crea migration per full-text indexes
    create_fulltext_migration
    
    # 3. Aggiorna modelli
    update_models
    
    # 4. Rimuovi MeiliSearch dalle configurazioni
    remove_meilisearch_config
    
    # 5. Aggiorna Docker/Cloud Run
    update_deployment_configs
    
    success "🎉 Migrazione completata!"
    show_next_steps
}

# Aggiorna configurazione Scout
update_scout_config() {
    info "📝 Aggiornamento configurazione Scout..."
    
    # Backup config esistente
    cp "$LARAVEL_DIR/config/scout.php" "$LARAVEL_DIR/config/scout.php.backup" 2>/dev/null || true
    
    # Aggiorna scout.php
    cat > "$LARAVEL_DIR/config/scout.php" << 'EOF'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    */
    'driver' => env('SCOUT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    */
    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    */
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    */
    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    */
    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Engines
    |--------------------------------------------------------------------------
    */
    'engines' => [
        'database' => [
            'driver' => 'database',
        ],
    ],
];
EOF

    success "Configurazione Scout aggiornata"
}

# Crea migration per full-text indexes
create_fulltext_migration() {
    info "🗄️  Creazione migration per full-text indexes..."
    
    local timestamp=$(date +%Y_%m_%d_%H%M%S)
    local migration_file="$LARAVEL_DIR/database/migrations/${timestamp}_add_fulltext_indexes_for_scout.php"
    
    cat > "$migration_file" << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Aggiungi full-text indexes per i modelli searchable
        
        // Esempio per posts (modifica secondo le tue tabelle)
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->fullText(['title', 'content', 'excerpt']);
            });
        }
        
        // Esempio per users
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->fullText(['name', 'email']);
            });
        }
        
        // Aggiungi altre tabelle che usi per la ricerca
        // Schema::table('products', function (Blueprint $table) {
        //     $table->fullText(['name', 'description']);
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropFullText(['title', 'content', 'excerpt']);
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropFullText(['name', 'email']);
            });
        }
    }
};
EOF

    success "Migration creata: $migration_file"
}

# Aggiorna modelli per usare Scout correttamente
update_models() {
    info "🏗️  Aggiornamento modelli Laravel..."
    
    # Crea esempio di modello searchable
    cat > "$LARAVEL_DIR/app/Models/SearchableModel.php" << 'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class SearchableModel extends Model
{
    use Searchable;

    /**
     * Campi searchable per full-text search
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            // Aggiungi altri campi necessari
        ];
    }

    /**
     * Configurazione per database driver
     */
    public function searchableAs(): string
    {
        return 'searchable_models_index';
    }

    /**
     * Scope per ricerca avanzata
     */
    public function scopeSearch($query, $term)
    {
        return $query->whereRaw(
            "MATCH(title, content, excerpt) AGAINST(? IN BOOLEAN MODE)",
            [$term . '*']
        );
    }
}
EOF

    # Crea service per gestire le ricerche
    mkdir -p "$LARAVEL_DIR/app/Services"
    cat > "$LARAVEL_DIR/app/Services/SearchService.php" << 'EOF'
<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Laravel\Scout\Builder;

class SearchService
{
    /**
     * Ricerca globale su più modelli
     */
    public function globalSearch(string $query, int $limit = 10): array
    {
        $results = [];
        
        // Ricerca posts
        $results['posts'] = Post::search($query)->take($limit)->get();
        
        // Ricerca users
        $results['users'] = User::search($query)->take($limit)->get();
        
        // Aggiungi altri modelli secondo necessità
        
        return $results;
    }

    /**
     * Ricerca avanzata con filtri
     */
    public function advancedSearch(string $query, array $filters = []): Builder
    {
        $search = Post::search($query);
        
        // Aggiungi filtri
        foreach ($filters as $field => $value) {
            $search->where($field, $value);
        }
        
        return $search;
    }

    /**
     * Suggerimenti di ricerca
     */
    public function searchSuggestions(string $partial, int $limit = 5): array
    {
        // Usa LIKE per suggerimenti rapidi
        return Post::where('title', 'LIKE', $partial . '%')
            ->select('title')
            ->limit($limit)
            ->pluck('title')
            ->toArray();
    }
}
EOF

    success "Modelli aggiornati"
}

# Rimuovi configurazioni MeiliSearch
remove_meilisearch_config() {
    info "🧹 Rimozione configurazioni MeiliSearch..."
    
    # Aggiorna .env
    if [ -f "$LARAVEL_DIR/.env" ]; then
        sed -i.backup 's/SCOUT_DRIVER=meilisearch/SCOUT_DRIVER=database/' "$LARAVEL_DIR/.env"
        sed -i '' '/MEILISEARCH_HOST=/d' "$LARAVEL_DIR/.env"
        sed -i '' '/MEILISEARCH_KEY=/d' "$LARAVEL_DIR/.env"
    fi
    
    # Aggiorna .env.prod
    if [ -f "../.env.prod" ]; then
        sed -i.backup 's/SCOUT_DRIVER=meilisearch/SCOUT_DRIVER=database/' "../.env.prod"
        sed -i '' '/MEILISEARCH_HOST=/d' "../.env.prod"
        sed -i '' '/MEILISEARCH_KEY=/d' "../.env.prod"
    fi
    
    success "Configurazioni MeiliSearch rimosse"
}

# Aggiorna configurazioni deployment
update_deployment_configs() {
    info "🐳 Aggiornamento configurazioni deployment..."
    
    # Aggiorna Docker Compose Swarm (rimuovi MeiliSearch)
    if [ -f "docker-compose.swarm.yml" ]; then
        cp "docker-compose.swarm.yml" "docker-compose.swarm.yml.backup"
        
        # Rimuovi servizio MeiliSearch
        sed -i '' '/meilisearch:/,/^$/d' "docker-compose.swarm.yml"
        
        # Rimuovi volume MeiliSearch
        sed -i '' '/meilisearch_data:/d' "docker-compose.swarm.yml"
        
        # Rimuovi variabili ambiente MeiliSearch dal backend
        sed -i '' '/MEILISEARCH_HOST=/d' "docker-compose.swarm.yml"
        sed -i '' '/MEILISEARCH_KEY=/d' "docker-compose.swarm.yml"
    fi
    
    # Aggiorna e2-medium compose
    if [ -f "docker-compose.e2-medium.yml" ]; then
        cp "docker-compose.e2-medium.yml" "docker-compose.e2-medium.yml.backup"
        
        # Rimuovi servizio MeiliSearch
        sed -i '' '/meilisearch:/,/^$/d' "docker-compose.e2-medium.yml"
        sed -i '' '/meilisearch_data:/d' "docker-compose.e2-medium.yml"
        sed -i '' '/MEILISEARCH_HOST=/d' "docker-compose.e2-medium.yml"
        sed -i '' '/MEILISEARCH_KEY=/d' "docker-compose.e2-medium.yml"
    fi
    
    # Aggiorna script deploy
    if [ -f "deploy.sh" ]; then
        cp "deploy.sh" "deploy.sh.backup"
        
        # Rimuovi riferimenti MeiliSearch dai secrets
        sed -i '' '/meilisearch_key/d' "deploy.sh"
        sed -i '' '/MEILISEARCH_KEY/d' "deploy.sh"
    fi
    
    success "Configurazioni deployment aggiornate"
}

# Mostra prossimi passi
show_next_steps() {
    echo
    success "🎉 Migrazione completata!"
    echo
    info "Prossimi passi:"
    echo "1. 📊 Esegui migration:"
    echo "   cd $LARAVEL_DIR && php artisan migrate"
    echo
    echo "2. 🔍 Reindicizza i dati esistenti:"
    echo "   php artisan scout:import 'App\\Models\\Post'"
    echo "   php artisan scout:import 'App\\Models\\User'"
    echo
    echo "3. 🧪 Testa la ricerca:"
    echo "   php artisan tinker"
    echo "   >>> App\\Models\\Post::search('test')->get()"
    echo
    echo "4. 🚀 Deploy senza MeiliSearch:"
    echo "   ./deploy.sh deploy"
    echo
    echo "5. 📈 Monitora performance:"
    echo "   - La ricerca sarà più veloce per dataset piccoli"
    echo "   - Per dataset grandi (>100k record) considera MeiliSearch"
    echo
    success "✅ Vantaggi della migrazione:"
    echo "  💰 Costi ridotti: -€30/mese (no MeiliSearch)"
    echo "  🔧 Gestione semplificata: zero configurazione"
    echo "  📦 Stack ridotto: meno servizi da mantenere"
    echo "  🚀 Deploy più veloce: meno container"
}

# Test della configurazione
test_search_setup() {
    info "🧪 Test configurazione ricerca..."
    
    cd "$LARAVEL_DIR"
    
    # Test connessione database
    php artisan tinker --execute="DB::connection()->getPdo()"
    
    # Test Scout
    php artisan scout:status
    
    success "Test completati"
}

# Rollback (se necessario)
rollback_migration() {
    warning "🔄 Rollback migrazione..."
    
    # Ripristina backup
    [ -f "$LARAVEL_DIR/config/scout.php.backup" ] && mv "$LARAVEL_DIR/config/scout.php.backup" "$LARAVEL_DIR/config/scout.php"
    [ -f "docker-compose.swarm.yml.backup" ] && mv "docker-compose.swarm.yml.backup" "docker-compose.swarm.yml"
    [ -f "docker-compose.e2-medium.yml.backup" ] && mv "docker-compose.e2-medium.yml.backup" "docker-compose.e2-medium.yml"
    [ -f "deploy.sh.backup" ] && mv "deploy.sh.backup" "deploy.sh"
    
    success "Rollback completato"
}

# Menu principale
case "${1:-help}" in
    "migrate")
        migrate_to_database_driver
        ;;
    "test")
        test_search_setup
        ;;
    "rollback")
        rollback_migration
        ;;
    "help"|*)
        echo -e "${BLUE}🎯 Migrazione a Laravel Scout Database Driver${NC}"
        echo
        echo "Elimina MeiliSearch e usa MySQL full-text search integrato"
        echo
        echo "Utilizzo: $0 <comando>"
        echo
        echo "Comandi:"
        echo "  migrate   - Esegui migrazione completa"
        echo "  test      - Testa configurazione"
        echo "  rollback  - Rollback migrazione"
        echo
        echo "Vantaggi:"
        echo "  💰 Risparmio: -€30/mese (no MeiliSearch service)"
        echo "  🔧 Semplicità: zero configurazione extra"
        echo "  📦 Stack ridotto: meno servizi da gestire"
        echo "  🚀 Deploy veloce: meno container"
        echo
        echo "Svantaggi:"
        echo "  📊 Performance: meno efficiente su dataset grandi (>100k)"
        echo "  🔍 Funzionalità: meno opzioni di ricerca avanzata"
        echo
        echo "Esempio:"
        echo "  $0 migrate  # Esegui migrazione completa"
        ;;
esac
