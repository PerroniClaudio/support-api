# 🚀 Google Cloud Run - Tutorial Deploy Completo

## 📋 Prerequisiti

**Prima di iniziare, assicurati di avere:**

1. **Google Cloud Project** attivo con fatturazione abilitata
2. **gcloud CLI** installato e configurato
3. **Docker** installato sul tuo sistema
4. **Domini** verificati in Google Search Console (per SSL personalizzato)

```bash
# Installa gcloud CLI (macOS)
brew install google-cloud-sdk

# Configura autenticazione
gcloud auth login
gcloud config set project IL-TUO-PROJECT-ID
```

## 🎯 Perché Cloud Run?

**Vantaggi rispetto a Compute Engine e2-medium:**

| Aspetto         | e2-medium              | Cloud Run                  |
| --------------- | ---------------------- | -------------------------- |
| **Costo**       | €30/mese fisso         | €5-15/mese (pay-per-use)   |
| **Gestione**    | Server management      | Zero management            |
| **Scalabilità** | Manuale                | 0-1000 istanze automatiche |
| **SSL**         | Configurazione manuale | Automatico                 |

## 🛠️ Step 1: Preparazione Progetti

### Setup Automatico

**Usa lo script di setup per configurare tutto automaticamente:**

```bash
# Setup iniziale - configura progetto GCP, verifica prerequisiti
./setup.sh
```

Lo script di setup:

- Verifica prerequisiti (gcloud, Docker)
- Configura progetto Google Cloud
- Abilita APIs necessarie
- Crea file di configurazione base

### Backend Laravel

**Il Dockerfile è già ottimizzato in `docker/backend.dockerfile`:**

- Multi-stage build per immagini leggere
- Nginx + PHP-FPM con Supervisor
- Configurazioni ottimizzate per Cloud Run
- Health checks integrati

### Frontend React

**Il Dockerfile è già ottimizzato in `docker/frontend.dockerfile`:**

- Build Vite ottimizzato
- Nginx configurato per SPA routing
- Compressione gzip abilitata
- Security headers configurati

## 🔧 Step 2: Configurazione Environment Variables

### Configurazione Automatica

**Usa il template e lo script di gestione secrets:**

```bash
# 1. Copia e modifica il template
cp config/.env.template config/.env.prod
# Modifica config/.env.prod con i tuoi valori

# 2. Crea automaticamente i Google Cloud Secrets
./scripts/create-secrets.sh
```

### Gestione Secrets Manuale

Se preferisci creare i secrets manualmente:

```bash
# Crea secrets per variabili sensibili
echo "your-app-key" | gcloud secrets create app-key --data-file=-
echo "your-db-password" | gcloud secrets create db-password --data-file=-
echo "your-jwt-secret" | gcloud secrets create jwt-secret --data-file=-
```

### CI/CD Automatico

**Il file `config/cloudbuild.yaml` è già configurato per:**

- Build automatico di backend e frontend
- Push delle immagini a Google Container Registry
- Deploy automatico su Cloud Run
- Health checks post-deploy

## 🚀 Step 3: Deploy Backend

### Deploy Automatico

**Usa lo script di deploy per il backend:**

```bash
# Deploy completo (backend + frontend)
./deploy.sh

# Oppure solo backend
./scripts/deploy-backend.sh
```

### Deploy Manuale

Se preferisci deployare manualmente:

```bash
# Deploy backend Laravel
gcloud run deploy spreetzitt-backend \
  --source ../support-api \
  --region europe-west1 \
  --memory 1Gi \
  --cpu 1 \
  --concurrency 80 \
  --timeout 300 \
  --min-instances 0 \
  --max-instances 10 \
  --set-env-vars="APP_ENV=production,SCOUT_DRIVER=database" \
  --set-secrets="APP_KEY=app-key:latest,DB_PASSWORD=db-password:latest" \
  --allow-unauthenticated
```

## 🌐 Step 4: Deploy Frontend

### Deploy Automatico

**Usa lo script di deploy per il frontend:**

```bash
# Deploy completo (se non già fatto)
./deploy.sh

# Oppure solo frontend
./scripts/deploy-frontend.sh
```

Lo script configura automaticamente `VITE_API_URL` con l'URL del backend deployato.

### Deploy Manuale

Se preferisci deployare manualmente:

```bash
# Deploy frontend React
gcloud run deploy spreetzitt-frontend \
  --source ../frontend \
  --region europe-west1 \
  --memory 512Mi \
  --cpu 1 \
  --concurrency 1000 \
  --timeout 60 \
  --min-instances 0 \
  --max-instances 5 \
  --allow-unauthenticated
```

## 🔗 Step 5: Domini Personalizzati

### Setup Automatico Domini

**Usa lo script di setup DNS:**

```bash
# Setup completo domini e SSL
./scripts/dns-setup.sh
```

Lo script:

- Mostra gli URL attuali dei servizi
- Configura i domain mappings
- Mostra i record DNS da configurare
- Verifica lo stato SSL
- Testa i domini

### Verifica Domini

```bash
# 1. Vai su Google Search Console
# https://search.google.com/search-console
# 2. Aggiungi e verifica i tuoi domini:
#    - api.tuodominio.com
#    - app.tuodominio.com
```

### Configurazione Manuale

Se preferisci configurare manualmente:

```bash
# Backend API
gcloud run domain-mappings create \
  --service spreetzitt-backend \
  --domain api.tuodominio.com \
  --region europe-west1

# Frontend App
gcloud run domain-mappings create \
  --service spreetzitt-frontend \
  --domain app.tuodominio.com \
  --region europe-west1
```

### Configura DNS

```bash
# Google ti fornirà i record DNS da aggiungere al tuo provider:

# Per api.tuodominio.com:
# Tipo: CNAME
# Nome: api
# Valore: ghs.googlehosted.com

# Per app.tuodominio.com:
# Tipo: CNAME
# Nome: app
# Valore: ghs.googlehosted.com
```

## 📊 Step 6: Monitoring e Ottimizzazioni

### Configurazione Logs

```bash
# Visualizza logs in tempo reale
gcloud run logs tail spreetzitt-backend --region europe-west1

# Logs strutturati Laravel
Log::info('API Request', [
    'endpoint' => $request->path(),
    'method' => $request->method(),
    'user_id' => auth()->id(),
    'ip' => $request->ip()
]);
```

### Health Checks

```php
// routes/web.php - endpoint health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected'
    ]);
});
```

## 🔄 Step 7: CI/CD Automatico

### Configurazione CI/CD

**Il file `config/cloudbuild.yaml` è già configurato per:**

- Build automatico immagini Docker
- Deploy automatico su Cloud Run
- Health checks post-deploy
- Gestione versioni con tags

### GitHub Actions

Per integrare con GitHub Actions, crea `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Cloud Run

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - id: "auth"
        uses: "google-github-actions/auth@v1"
        with:
          credentials_json: "${{ secrets.GCP_SA_KEY }}"

      - name: "Set up Cloud SDK"
        uses: "google-github-actions/setup-gcloud@v1"

      - name: "Build and Deploy"
        run: |
          gcloud builds submit --config cloud-run/config/cloudbuild.yaml
```

### Trigger Cloud Build

```bash
# Configura trigger automatico da GitHub
gcloud builds triggers create github \
  --repo-name=spreetzitt \
  --repo-owner=YOUR_GITHUB_USERNAME \
  --branch-pattern="^main$" \
  --build-config=cloud-run/config/cloudbuild.yaml \
  --name=spreetzitt-deploy
```

## 💡 Step 8: Ottimizzazioni Avanzate

### Database Connection Pooling

```php
// config/database.php
'connections' => [
    'mysql' => [
        'options' => [
            PDO::ATTR_PERSISTENT => true,
        ],
        'pool' => [
            'max_connections' => 10,
            'max_idle_time' => 60,
        ],
    ],
],
```

### Caching Redis

```bash
# Configura Redis per sessioni e cache
gcloud redis instances create spreetzitt-redis \
  --size=1 \
  --region=europe-west1 \
  --tier=basic

# Nel .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## 🎯 Raccomandazione: Soluzione Search

**Usa Laravel Scout con Database Driver:**

```bash
# Configurazione più semplice ed economica
SCOUT_DRIVER=database

# Vantaggi:
# - €0 costi extra
# - Zero configurazione
# - Integrazione nativa MySQL full-text search
# - Performance adeguate per <100k records
```

## ✅ Checklist Deploy Finale

**Prima del go-live:**

- [ ] Backend funzionante su Cloud Run URL temporaneo
- [ ] Frontend funzionante su Cloud Run URL temporaneo
- [ ] Database Cloud SQL connesso e funzionante
- [ ] Domini verificati in Search Console
- [ ] Record DNS configurati
- [ ] SSL certificates attivi (automatico)
- [ ] Environment variables e secrets configurati
- [ ] Health checks funzionanti
- [ ] Logs strutturati attivi

**Risultato finale:**

- **Frontend**: `https://app.tuodominio.com`
- **Backend**: `https://api.tuodominio.com`
- **Costi**: €8-15/mese (vs €30/mese e2-medium)
- **Gestione**: Zero server management
- **Scalabilità**: Automatica da 0 a 1000 istanze

# 🌐 **Cloud Run: Domini e Variabili d'Ambiente**

### **📍 Come Funzionano gli URL di Cloud Run**

Cloud Run **NON** ti espone un IP fisso, ma un **URL HTTPS** dinamico:

```bash
# Esempio URL Cloud Run (cambiano ad ogni deploy!)
Backend:  https://spreetzitt-backend-abc123-ew.a.run.app
Frontend: https://spreetzitt-frontend-xyz789-ew.a.run.app

# ⚠️ PROBLEMA: Questi URL cambiano ad ogni deploy!
```

### **🔗 Soluzione: Domini Personalizzati**

**Step 1: Verifica domini**

```bash
# Google Search Console
https://search.google.com/search-console
# Verifica i tuoi domini: api.tuodominio.com, app.tuodominio.com
```

**Step 2: Mappa domini a Cloud Run**

```bash
# Backend
gcloud run domain-mappings create \
  --service=spreetzitt-backend \
  --domain=api.tuodominio.com

# Frontend
gcloud run domain-mappings create \
  --service=spreetzitt-frontend \
  --domain=app.tuodominio.com
```

**Step 3: Configura DNS**

```bash
# Google ti fornisce record DNS da aggiungere:
# Tipo: CNAME
# Nome: api.tuodominio.com
# Valore: ghs.googlehosted.com

# Tipo: A
# Nome: app.tuodominio.com
# Valore: 216.239.32.21 (esempio)
```

### **🔐 Gestione Variabili d'Ambiente (.env)**

**Problema**: Cloud Run non usa file `.env` direttamente

**Soluzione**: Google Cloud Secrets + Environment Variables

#### **📋 Variabili NON Sensibili** (env vars)

```bash
# Configurazione diretta
gcloud run services update spreetzitt-backend \
  --set-env-vars="APP_ENV=production,DB_HOST=1.2.3.4,SCOUT_DRIVER=database"
```

#### **🔐 Variabili Sensibili** (secrets)

```bash
# 1. Crea secret
echo "your-secret-key" | gcloud secrets create app-key --data-file=-

# 2. Usa nel servizio
gcloud run services update spreetzitt-backend \
  --set-secrets="APP_KEY=app-key:latest"
```

### **🛠️ Script Automatico**

```bash
# Setup completo automatico
./scripts/dns-setup.sh

# Quello che fa:
# 1. Mostra URL Cloud Run attuali
# 2. Configura domini personalizzati
# 3. Mostra record DNS da configurare
# 4. Crea Google Cloud Secrets dal tuo config/.env.prod
# 5. Configura environment variables
# 6. Testa la configurazione
```

### **📋 Checklist Configurazione**

**Prima del deploy:**

```bash
# 1. Crea config/.env.prod con le tue variabili
APP_KEY=base64:your-key
DB_HOST=your-gcp-sql-ip
DB_PASSWORD=your-password
DB_DATABASE=spreetzitt_prod
# ... altre variabili

# 2. Modifica domini nel file config/.env.prod
FRONTEND_DOMAIN="app.tuodominio.com"
BACKEND_DOMAIN="api.tuodominio.com"

# 3. Verifica domini in Search Console
```

**Dopo il deploy:**

```bash
# 4. Esegui setup DNS
./scripts/dns-setup.sh

# 5. Configura record DNS nel tuo provider
# 6. Attendi propagazione DNS (5-60 min)
# 7. Testa configurazione
./scripts/dns-setup.sh test
```

### **🎯 Risultato Finale**

```bash
# URLs finali stabili:
Frontend: https://app.tuodominio.com
Backend:  https://api.tuodominio.com

# SSL automatico ✅
# Scaling automatico ✅
# Costi ottimizzati ✅
# Zero gestione server ✅
```

## 🎯 Quick Start

**Usa gli script automatici per un deploy rapido:**

```bash
# 1. Setup iniziale (una volta sola)
./setup.sh

# 2. Configura variabili ambiente
cp config/.env.template config/.env.prod
# Modifica config/.env.prod con i tuoi valori

# 3. Deploy completo
./deploy.sh

# 4. Setup domini (opzionale)
./scripts/dns-setup.sh
```

**Scripts disponibili:**

- `./setup.sh` - Setup iniziale completo
- `./deploy.sh` - Deploy backend + frontend
- `./scripts/deploy-backend.sh` - Deploy solo backend
- `./scripts/deploy-frontend.sh` - Deploy solo frontend
- `./scripts/create-secrets.sh` - Gestione Google Cloud Secrets
- `./scripts/dns-setup.sh` - Setup domini personalizzati
- `./scripts/cleanup.sh` - Pulizia risorse

Per maggiori dettagli sugli script, vedi: `README.md`

## 🔧 Gestione e Monitoraggio

### Comandi Utili per il Monitoraggio

```bash
# Visualizza logs backend
./scripts/deploy-backend.sh --logs

# Visualizza logs frontend
./scripts/deploy-frontend.sh --logs

# Informazioni servizi
./scripts/deploy-backend.sh --info
./scripts/deploy-frontend.sh --info

# Test health checks
./scripts/deploy-backend.sh --test
./scripts/deploy-frontend.sh --test

# Stato domini e SSL
./scripts/dns-setup.sh status
```

### Gestione Secrets

```bash
# Lista secrets esistenti
./scripts/create-secrets.sh list

# Crea/aggiorna tutti i secrets
./scripts/create-secrets.sh create

# Test accesso secrets
./scripts/create-secrets.sh test
```

### Pulizia Risorse

```bash
# Lista tutte le risorse
./scripts/cleanup.sh --list

# Rimuovi solo servizi
./scripts/cleanup.sh --services

# Rimuovi tutto
./scripts/cleanup.sh --all
```
