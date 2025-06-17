# 🔄 Laravel Jobs con Google Cloud Run

Guida completa per eseguire Laravel Queue Workers su Google Cloud Run.

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

#### 1. Crea Job Service

```bash
# Deploy job worker
gcloud run jobs create spreetzitt-worker \
  --image=gcr.io/PROJECT_ID/spreetzitt-backend:latest \
  --region=europe-west1 \
  --memory=1Gi \
  --cpu=1 \
  --max-retries=3 \
  --parallelism=1 \
  --task-count=1 \
  --command="php" \
  --args="artisan,queue:work,--stop-when-empty" \
  --set-env-vars="APP_ENV=production" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest"
```

#### 2. Triggering Jobs

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

**Opzione B: Cloud Scheduler (CRON)**

```bash
# Crea scheduler per processare queue ogni 5 minuti
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

#### 1. Dockerfile per Worker

```dockerfile
# Dockerfile.worker
FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev && \
    docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application
COPY . /app
WORKDIR /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port for health checks
EXPOSE 8080

# Worker command
CMD ["php", "artisan", "queue:work", "--timeout=300", "--memory=256", "--tries=3"]
```

#### 2. Deploy Worker Service

```bash
# Build e deploy worker
gcloud run deploy spreetzitt-worker \
  --source . \
  --region=europe-west1 \
  --memory=1Gi \
  --cpu=1 \
  --min-instances=1 \
  --max-instances=10 \
  --concurrency=1 \
  --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest" \
  --no-allow-unauthenticated \
  --port=8080
```

#### 3. Health Check per Worker

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

### Setup Consigliato

```bash
# 1. Worker service per job critici (email, notifiche)
gcloud run deploy spreetzitt-worker-critical \
  --source . \
  --min-instances=1 \
  --max-instances=5 \
  --memory=512Mi \
  --set-env-vars="QUEUE_CONNECTION=database,QUEUE_QUEUE=critical"

# 2. Cloud Run Jobs per job pesanti (report, analytics)
gcloud run jobs create spreetzitt-worker-reports \
  --image=gcr.io/PROJECT_ID/spreetzitt-backend:latest \
  --memory=2Gi \
  --cpu=2 \
  --args="artisan,queue:work,--queue=reports,--stop-when-empty"
```

### Queue Configuration

```php
// Dispatch job critici
SendWelcomeEmail::dispatch($user)->onQueue('critical');

// Dispatch job pesanti
GeneratePdfReport::dispatch($reportData)->onQueue('reports');
```

### Cron per Reports

```bash
# Scheduler per processare report ogni ora
gcloud scheduler jobs create http process-reports \
  --schedule="0 * * * *" \
  --uri="https://api.tuodominio.com/api/process-reports" \
  --http-method=POST
```

---

## 🚀 Script Deploy Completo

```bash
#!/bin/bash
# deploy-workers.sh

PROJECT_ID="your-project-id"
REGION="europe-west1"
IMAGE="gcr.io/$PROJECT_ID/spreetzitt-backend:latest"

echo "🔄 Deploying Laravel Workers to Cloud Run..."

# 1. Worker service per job critici
echo "📧 Deploying critical worker..."
gcloud run deploy spreetzitt-worker-critical \
  --image=$IMAGE \
  --region=$REGION \
  --memory=512Mi \
  --cpu=0.5 \
  --min-instances=1 \
  --max-instances=5 \
  --concurrency=1 \
  --command="php" \
  --args="artisan,queue:work,--queue=critical,--timeout=120" \
  --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest" \
  --no-allow-unauthenticated

# 2. Job per report pesanti
echo "📊 Deploying reports job..."
gcloud run jobs create spreetzitt-worker-reports \
  --image=$IMAGE \
  --region=$REGION \
  --memory=2Gi \
  --cpu=2 \
  --max-retries=2 \
  --parallelism=1 \
  --task-count=1 \
  --command="php" \
  --args="artisan,queue:work,--queue=reports,--stop-when-empty,--timeout=600" \
  --set-env-vars="APP_ENV=production,QUEUE_CONNECTION=database" \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest"

# 3. Scheduler per report
echo "⏰ Setting up scheduler..."
gcloud scheduler jobs create http process-reports \
  --location=$REGION \
  --schedule="0 */2 * * *" \
  --uri="https://europe-west1-run.googleapis.com/apis/run.googleapis.com/v1/namespaces/$PROJECT_ID/jobs/spreetzitt-worker-reports:run" \
  --http-method=POST \
  --oauth-service-account-email="$PROJECT_ID@appspot.gserviceaccount.com"

echo "✅ Workers deployed successfully!"
echo "📧 Critical worker: Always running (emails, notifications)"
echo "📊 Reports job: Triggered every 2 hours"
```

---

## 🧪 Test e Monitoring

### Test Jobs

```bash
# Test dispatch job
php artisan tinker
>>> SendWelcomeEmail::dispatch(User::first())

# Verifica queue
php artisan queue:monitor database

# Log Cloud Run
gcloud run services logs read spreetzitt-worker-critical --region=europe-west1
```

### Monitoring

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
