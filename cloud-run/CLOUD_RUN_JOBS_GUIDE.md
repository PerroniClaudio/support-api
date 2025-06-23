# 🔄 Laravel Jobs con Google Cloud Run

Guida completa per eseguire Laravel Queue Workers su Google Cloud Run utilizzando gli script automatizzati.

## 🚀 Quick Start

**Usa gli script automatici per configurare i workers:**

```bash
# 1. Setup workers automatico
./scripts/deploy-workers.sh

# 2. Monitora workers
./scripts/monitor-workers.sh

# 3. Test job dispatch
./scripts/test-jobs.sh
```

## 🎯 Opzioni Disponibili

### 1. 🚀 **Cloud Run Jobs** (Raccomandato)

- Servizio dedicato per task batch
- Scaling automatico 0-N
- Costi pay-per-execution
- Perfetto per job sporadici

### 2. ⚡ **Cloud Run Service Worker**

- Container sempre attivo per queue worker
- Scaling automatico con min-instances
- Migliore per job frequenti

---

## 🚀 Opzione 1: Cloud Run Jobs

### Vantaggi

- ✅ **Costi ottimali**: Paghi solo quando esegue
- ✅ **Scaling perfetto**: 0 costi quando non ci sono job
- ✅ **Gestione automatica**: Google gestisce scheduling
- ✅ **Affidabilità**: Retry automatici

### Setup Cloud Run Jobs

#### 1. Setup Automatico

**Usa lo script di deploy per workers:**

```bash
# Deploy completo workers (service + jobs)
./scripts/deploy-workers.sh

# Deploy solo job worker
./scripts/deploy-workers.sh --jobs-only

# Deploy solo service worker
./scripts/deploy-workers.sh --service-only
```

#### 2. Configurazione Manuale (Opzionale)

Se preferisci configurare manualmente (gli script automatici sono raccomandati):

```bash
# Deploy job worker manuale (configurazione da config/.env.prod)
gcloud run jobs create spreetzitt-worker \
  --image=gcr.io/$PROJECT_ID/spreetzitt-backend:latest \
  --region=europe-west1 \
  --memory=1Gi \
  --cpu=1 \
  --max-retries=3 \
  --parallelism=1 \
  --task-count=1 \
  --command="php" \
  --args="artisan,queue:work,--stop-when-empty" \
  --set-env-vars="APP_ENV=production" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest"
```

#### 3. Triggering Automatico

**Lo script configura automaticamente:**

- Cloud Scheduler per processare queue periodicamente
- API endpoints per trigger manuali
- Monitoring e health checks

#### 4. Configurazione Manuale (Opzionale)

**Opzione A: Via API dal backend**

```php
// Nel tuo controller Laravel
use Google\Cloud\Run\V2\JobsClient;

public function processQueue()
{
    $client = new JobsClient();
    $jobName = 'projects/PROJECT_ID/locations/europe-west1/jobs/spreetzitt-worker';

    $execution = $client->runJob($jobName);
    return response()->json(['execution_id' => $execution->getName()]);
}
```

**Opzione B: Cloud Scheduler (CRON) - Configurato automaticamente**

```bash
# ✅ Configurato automaticamente dallo script deploy-workers.sh
# Processa queue ogni 5 minuti

# Setup manuale (opzionale se gli script non funzionano):
gcloud scheduler jobs create http spreetzitt-queue-processor \
  --location=europe-west1 \
  --schedule="*/5 * * * *" \
  --uri="https://api.tuodominio.com/process-queue" \
  --http-method=POST \
  --headers="Content-Type=application/json"
```

#### 3. Script di Deploy

```bash
#!/bin/bash
# deploy-jobs.sh

PROJECT_ID="your-project-id"
REGION="europe-west1"
IMAGE="gcr.io/$PROJECT_ID/spreetzitt-backend:latest"

# Deploy job worker
gcloud run jobs create spreetzitt-worker \
  --image=$IMAGE \
  --region=$REGION \
  --memory=1Gi \
  --cpu=1 \
  --max-retries=3 \
  --parallelism=1 \
  --task-count=1 \
  --command="php" \
  --args="artisan,queue:work,--stop-when-empty,--timeout=300" \
  --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest"

echo "✅ Cloud Run Job deployed"
```

---

## ⚡ Opzione 2: Cloud Run Service Worker

### Vantaggi

- ✅ **Latenza bassa**: Worker sempre attivo
- ✅ **Processing continuo**: Ideale per code con traffic costante
- ✅ **Scaling reattivo**: Più worker automaticamente

### Setup Service Worker

#### 1. Deploy Automatico

**Usa lo script per deployare il service worker:**

```bash
# Deploy service worker per job continui
./scripts/deploy-workers.sh --service-only

# Monitor worker
./scripts/monitor-workers.sh --service
```

#### 2. Deploy Manuale (Opzionale)

**⚠️ Usa invece lo script automatico: `./scripts/deploy-workers.sh --service-only`**

Se preferisci deployare manualmente:

```bash
# ⚠️ Deploy manuale (usa invece ./scripts/deploy-workers.sh --service-only)
# Build e deploy worker (configurazione da config/.env.prod)
gcloud run deploy spreetzitt-worker \
  --source ../support-api \
  --region=europe-west1 \
  --memory=1Gi \
  --cpu=1 \
  --min-instances=1 \
  --max-instances=10 \
  --concurrency=1 \
  --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest" \
  --no-allow-unauthenticated \
  --port=8080
```

#### 3. Dockerfile per Worker

**I Dockerfile sono già configurati in `docker/backend.dockerfile`:**

```dockerfile
# Il Dockerfile include già la configurazione per worker
# Vedi docker/backend.dockerfile nella cartella cloud-run
# Include supporto per supervisord e queue workers
```

#### 4. Health Check per Worker

**L'health check è già configurato nel backend Laravel:**

```php
// routes/web.php - Health check per worker
Route::get('/worker-health', function () {
    // Verifica connessione database
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'healthy',
            'worker' => 'active',
            'timestamp' => now()
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ], 500);
    }
});
```

---

## 🔧 Configurazione Laravel

### 1. Database Queue Driver

Per semplicità, usa il database driver:

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],
```

### 2. Job Configuration

```php
// app/Jobs/ExampleJob.php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minuti
    public $tries = 3;     // 3 tentativi
    public $retryAfter = 60; // Retry dopo 1 minuto

    public function __construct(
        public User $user
    ) {
        // Imposta queue specifica per email
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        // Logica job
        Mail::to($this->user->email)->send(new WelcomeMail($this->user));

        Log::info('Welcome email sent', ['user_id' => $this->user->id]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Welcome email failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}
```

### 3. Migration per Jobs Table

```bash
# Crea migration
php artisan queue:table
php artisan migrate
```

---

## 📊 Confronto Soluzioni

| Aspetto         | **Cloud Run Jobs** | **Cloud Run Service**    |
| --------------- | ------------------ | ------------------------ |
| **Costi**       | €0 quando idle     | ~€5-15/mese min-instance |
| **Latenza**     | 10-30s cold start  | <1s sempre attivo        |
| **Scaling**     | 0-1000 executions  | 1-1000 instances         |
| **Complessità** | Media              | Bassa                    |
| **Use Case**    | Job sporadici      | Code continue            |

---

## 🎯 Raccomandazione per Spreetzitt

Guardando i tuoi job, consiglio **approccio ibrido**:

### Setup Consigliato con Scripts Automatici

**✅ Usa lo script automatico per setup ibrido (raccomandato):**

```bash
# Setup completo workers (critical + reports)
./scripts/deploy-workers.sh --hybrid

# Monitor entrambi i workers
./scripts/monitor-workers.sh --all

# Test workers
./scripts/test-jobs.sh --all
```

### Configurazione Manuale (Opzionale)

**Se preferisci configurare manualmente (non raccomandato):**

```bash
# 1. Worker service per job critici (email, notifiche)
gcloud run deploy spreetzitt-worker-critical \
  --source ../support-api \
  --region=europe-west1 \
  --min-instances=1 \
  --max-instances=5 \
  --memory=512Mi \
  --set-env-vars="QUEUE_CONNECTION=database,QUEUE_QUEUE=critical" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest"

# 2. Cloud Run Jobs per job pesanti (report, analytics)
gcloud run jobs create spreetzitt-worker-reports \
  --image=gcr.io/$PROJECT_ID/spreetzitt-backend:latest \
  --region=europe-west1 \
  --memory=2Gi \
  --cpu=2 \
  --args="artisan,queue:work,--queue=reports,--stop-when-empty" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest"
```

### Queue Configuration

```php
// Dispatch job critici
SendWelcomeEmail::dispatch($user)->onQueue('critical');

// Dispatch job pesanti
GeneratePdfReport::dispatch($reportData)->onQueue('reports');
```

### Cron per Reports

**✅ Configurato automaticamente dallo script `deploy-workers.sh --hybrid`**

Setup manuale opzionale:

```bash
# ⚠️ Setup manuale scheduler (gli script lo fanno automaticamente)
# Scheduler per processare report ogni ora
gcloud scheduler jobs create http process-reports \
  --location=europe-west1 \
  --schedule="0 * * * *" \
  --uri="https://api.tuodominio.com/api/process-reports" \
  --http-method=POST
```

---

## 🚀 Script Deploy Completo

**Gli script automatici sono già configurati:**

```bash
# Deploy completo workers
./scripts/deploy-workers.sh

# Le opzioni disponibili:
./scripts/deploy-workers.sh --help

# Opzioni:
#   --jobs-only     Deploy solo Cloud Run Jobs
#   --service-only  Deploy solo Cloud Run Service
#   --hybrid        Deploy setup ibrido (raccomandato)
#   --critical      Deploy solo worker critici
#   --reports       Deploy solo worker report
```

### Script Personalizzabile

**Il file `scripts/deploy-workers.sh` include:**

- Configurazione automatica da `config/.env.prod`
- Setup worker service per job critici
- Setup Cloud Run Jobs per report pesanti
- Configurazione Cloud Scheduler
- Health checks e monitoring

### Configurazione Manual (Opzionale)

Se preferisci configurare manualmente:

---

## 🧪 Test e Monitoring

### Test Automatico Jobs

```bash
# Test completo workers
./scripts/test-jobs.sh

# Test specifici
./scripts/test-jobs.sh --critical
./scripts/test-jobs.sh --reports
./scripts/test-jobs.sh --dispatch
```

### Monitoring con Scripts

```bash
# Monitor tutti i workers
./scripts/monitor-workers.sh

# Monitor specifici
./scripts/monitor-workers.sh --service
./scripts/monitor-workers.sh --jobs
./scripts/monitor-workers.sh --logs
```

### Test Manuali

Se preferisci testare manualmente:

```bash
# Test dispatch job (comando da eseguire nel backend)
php artisan tinker
>>> SendWelcomeEmail::dispatch(User::first())

# Verifica queue
php artisan queue:monitor database

# Log Cloud Run (sostituito da ./scripts/monitor-workers.sh --logs)
gcloud run services logs read spreetzitt-worker-critical --region=europe-west1
```

### Monitoring Manuale

Se preferisci monitorare manualmente:

```bash
# Status worker service
gcloud run services describe spreetzitt-worker-critical --region=europe-west1

# Executions job
gcloud run jobs executions list --job=spreetzitt-worker-reports --region=europe-west1

# Scheduler status
gcloud scheduler jobs list --location=europe-west1
```

---

**🎉 Risultato**: Laravel queue workers completamente gestiti su Cloud Run con scaling automatico e costi ottimizzati!
