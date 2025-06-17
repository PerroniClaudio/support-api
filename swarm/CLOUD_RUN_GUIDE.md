# 🌊 Google Cloud Run - Setup Guide

## 📊 Confronto e2-medium vs Cloud Run

| Aspetto             | **Compute Engine e2-medium** | **Google Cloud Run**      |
| ------------------- | ---------------------------- | ------------------------- |
| **Costo Base**      | €30/mese fisso               | €0 senza traffico         |
| **Costo Tipico**    | €30/mese                     | €5-15/mese                |
| **RAM Disponibile** | 4GB fissi                    | 1-8GB per container       |
| **Scalabilità**     | Manuale (1 istanza)          | 0-1000 istanze automatico |
| **SSL**             | Configurazione manuale       | Automatico                |
| **Gestione**        | Server management            | Zero management           |
| **Cold Start**      | N/A                          | 1-3 secondi               |
| **Concorrenza**     | Limitata                     | 1000 req/istanza          |

## 🚀 Vantaggi Cloud Run per Spreetzitt

### ✅ **Costi Ottimizzati**

```bash
# Tier GRATUITO (sempre disponibile):
- 2 milioni request/mese
- 180.000 vCPU-secondi/mese
- 360.000 GiB-secondi memoria/mese

# Tipico costo mensile progetto medio:
- Backend: €3-8/mese
- Frontend: €1-3/mese
- Database: €7/mese (db-f1-micro)
- Redis: €25/mese (Memorystore basic)
# TOTALE: €36-43/mese (ma scala in base al traffico!)
```

### ✅ **Perfetto per il tuo Stack**

- **Laravel Backend**: Ottimo per Cloud Run (stateless)
- **React Frontend**: Supporto nativo SPA
- **Database esterno**: Già configurato su Cloud SQL
- **Redis**: Disponibile via Memorystore

### ✅ **Deploy Semplificato**

```bash
# Deploy in un comando:
gcloud run deploy spreetzitt-backend \
  --source . \
  --region europe-west1 \
  --allow-unauthenticated
```

## 🛠️ Configurazione Raccomandata

### **Backend Laravel**

```yaml
# Risorse:
memory: 1Gi
cpu: 1 vCPU
concurrency: 80 requests/istanza
timeout: 300s
min-instances: 0 # Scale to zero
max-instances: 100 # Scale up per traffico alto
```

### **Frontend React**

```yaml
# Risorse:
memory: 512Mi
cpu: 1 vCPU
concurrency: 1000 requests/istanza
timeout: 60s
min-instances: 0
max-instances: 50
```

## 🔧 Configurazioni Avanzate

### **Custom Domains**

```bash
# Collega i tuoi domini
gcloud run domain-mappings create \
  --service spreetzitt-backend \
  --domain api.yourdomain.com

gcloud run domain-mappings create \
  --service spreetzitt-frontend \
  --domain app.yourdomain.com
```

### **Environment Variables**

```bash
# Setta variabili ambiente
gcloud run services update spreetzitt-backend \
  --set-env-vars="APP_ENV=production,APP_DEBUG=false" \
  --set-secrets="APP_KEY=app-key:latest"
```

### **Traffic Splitting** (per A/B testing)

```bash
# Gradual rollout
gcloud run services update-traffic spreetzitt-backend \
  --to-revisions=spreetzitt-backend-v2=20,spreetzitt-backend-v1=80
```

## 📈 Performance e Ottimizzazioni

### **Cold Start Optimization**

```dockerfile
# Multi-stage build per immagini più leggere
FROM php:8.2-fpm-alpine as builder
# ... build steps ...

FROM php:8.2-fpm-alpine as runtime
COPY --from=builder /app /app
# Immagine finale: ~100MB vs ~500MB
```

### **Connection Pooling**

```php
// Laravel - ottimizza connessioni DB
'connections' => [
    'mysql' => [
        'options' => [
            PDO::ATTR_PERSISTENT => true,
        ],
    ],
],
```

### **Caching Strategy**

```bash
# Redis per sessioni + cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## 🎯 **Quando Usare Cloud Run vs e2-medium**

### **✅ Usa Cloud Run se:**

- Traffico variabile/basso
- Vuoi zero management
- Budget ottimizzato
- Scaling automatico necessario
- SSL automatico importante

### **❌ Usa e2-medium se:**

- Traffico sempre alto (>80% utilizzo 24/7)
- Controllo completo del server necessario
- Applicazioni stateful
- Long-running processes continui

## 🚀 Migration Plan

### **Fase 1: Test Environment**

```bash
# Deploy su Cloud Run ambiente test
./cloud-run-deploy.sh setup
./cloud-run-deploy.sh deploy test
```

### **Fase 2: A/B Testing**

```bash
# 10% traffico Cloud Run, 90% e2-medium
# Misura performance e costi
```

### **Fase 3: Full Migration**

```bash
# Sposta completamente a Cloud Run
# Dismetti e2-medium
```

## 📊 Monitoring e Logs

### **Cloud Monitoring** (integrato)

- Request latency
- Error rates
- Instance count
- Memory/CPU usage

### **Structured Logging**

```php
// Laravel - log strutturati per Cloud Logging
Log::info('User action', [
    'user_id' => $user->id,
    'action' => 'login',
    'ip' => $request->ip()
]);
```

## 💡 **Raccomandazione Finale**

Per il tuo progetto Spreetzitt, **Cloud Run è la scelta migliore** perché:

1. **Costi**: Probabilmente spenderai 50-70% in meno
2. **Scalabilità**: Gestisce automaticamente picchi di traffico
3. **Semplicità**: Deploy con un comando, zero configurazione server
4. **Reliability**: 99.95% SLA di Google
5. **Global**: CDN e edge locations automatici

Vuoi che ti aiuti a configurare la migrazione da e2-medium a Cloud Run? 🚀

## 🔍 **MeiliSearch su Cloud Run - Soluzioni**

### **Problema**: MeiliSearch è stateful, Cloud Run è stateless

### **1. 🎯 SOLUZIONE CONSIGLIATA: MeiliSearch Cloud**

_(Hosted service ufficiale)_

```bash
# Costi MeiliSearch Cloud:
- Tier gratuito: 100k documenti, 10k ricerche/mese
- Tier Starter: €29/mese - 1M documenti, 100k ricerche/mese
- Tier Pro: €99/mese - 10M documenti, 1M ricerche/mese

# Configurazione Laravel:
MEILISEARCH_HOST=https://your-project.meilisearch.io
MEILISEARCH_KEY=your_private_key
```

**✅ Vantaggi:**

- Zero configurazione
- Backup automatici
- Scaling gestito
- SLA 99.9%
- Costi prevedibili

### **2. 🛠️ Compute Engine dedicata per MeiliSearch**

_(Architettura ibrida)_

```bash
# Crea VM micro per MeiliSearch
gcloud compute instances create meilisearch-vm \
  --machine-type=e2-micro \
  --image-family=ubuntu-2004-lts \
  --image-project=ubuntu-os-cloud \
  --disk-size=20GB \
  --zone=europe-west1-b

# Costi: ~€7-10/mese
```

**Architettura:**

```
Cloud Run (Backend + Frontend)
       ↓
   e2-micro VM (MeiliSearch)
       ↓
   Cloud SQL (Database)
```

### **3. 🐳 Docker Swarm con MeiliSearch Cloud**

_(Ibrido ottimizzato)_

```yaml
# docker-compose.hybrid.yml
version: "3.8"
services:
  backend:
    environment:
      # MeiliSearch Cloud
      - MEILISEARCH_HOST=https://your-project.meilisearch.io
      - MEILISEARCH_KEY=${MEILISEARCH_CLOUD_KEY}
  # Niente MeiliSearch locale
```

### **4. 🔧 Elasticsearch/Algolia Alternative**

```bash
# Opzione A: Elasticsearch Service
- €50-100/mese per cluster gestito
- Più potente ma complesso

# Opzione B: Algolia
- €0-50/mese based on operations
- Ottimo per search, ma meno flessibile
```

## 🎯 **Raccomandazione Finale MeiliSearch**

**Per il tuo progetto consiglio MeiliSearch Cloud:**

1. **Tier gratuito** per iniziare (100k documenti)
2. **€29/mese** quando cresci (vs €30/mese e2-medium completa)
3. **Zero management** - come Cloud Run
4. **Performance ottimali** - edge locations globali

## 💡 **SOLUZIONE DEFINITIVA: Scout Database Driver**

### **🎯 La Scelta più Intelligente**

**"Sfanculiamo MeiliSearch e usiamo Laravel Scout con driver database!"**

```bash
# Configurazione semplicissima:
SCOUT_DRIVER=database

# E basta. Fine. 🎉
```

**✅ Vantaggi:**

- **€0 costi extra** - usa il database che hai già
- **Zero configurazione** - funziona out-of-the-box
- **Zero gestione** - niente servizi aggiuntivi
- **Zero problemi** - MySQL full-text search integrato

**❌ Svantaggi:**

- Performance meno ottimali su dataset enormi (>100k record)
- Funzionalità di ricerca meno avanzate vs MeiliSearch

### **🚀 Implementazione**

```bash
# Script automatico per la migrazione
./migrate-to-database-search.sh migrate

# Quello che fa:
# 1. Configura Scout per usare database driver
# 2. Crea migration per full-text indexes
# 3. Aggiorna modelli Laravel
# 4. Rimuove MeiliSearch da Docker/Cloud Run
# 5. Pulisce configurazioni
```

### **📊 Confronto Finale**

| Soluzione                          | **Costi/mese** | **Gestione** | **Performance** |
| ---------------------------------- | -------------- | ------------ | --------------- |
| **e2-medium + MeiliSearch**        | €30            | Media        | Ottima          |
| **Cloud Run + MeiliSearch Cloud**  | €8-35          | Zero         | Ottima          |
| **Cloud Run + Database Search**    | €8-15          | Zero         | Buona           |
| **Docker Swarm + Database Search** | €30            | Bassa        | Buona           |

### **🎯 Raccomandazione DEFINITIVA:**

**Cloud Run + Scout Database Driver = €8-15/mese**

- Costi minimi
- Zero gestione
- Performance sufficienti per la maggior parte dei casi
- Scalabilità automatica
- Semplicità totale

# 🌊 Google Cloud Run - Setup Guide

## 📊 Confronto e2-medium vs Cloud Run

| Aspetto             | **Compute Engine e2-medium** | **Google Cloud Run**      |
| ------------------- | ---------------------------- | ------------------------- |
| **Costo Base**      | €30/mese fisso               | €0 senza traffico         |
| **Costo Tipico**    | €30/mese                     | €5-15/mese                |
| **RAM Disponibile** | 4GB fissi                    | 1-8GB per container       |
| **Scalabilità**     | Manuale (1 istanza)          | 0-1000 istanze automatico |
| **SSL**             | Configurazione manuale       | Automatico                |
| **Gestione**        | Server management            | Zero management           |
| **Cold Start**      | N/A                          | 1-3 secondi               |
| **Concorrenza**     | Limitata                     | 1000 req/istanza          |

## 🚀 Vantaggi Cloud Run per Spreetzitt

### ✅ **Costi Ottimizzati**

```bash
# Tier GRATUITO (sempre disponibile):
- 2 milioni request/mese
- 180.000 vCPU-secondi/mese
- 360.000 GiB-secondi memoria/mese

# Tipico costo mensile progetto medio:
- Backend: €3-8/mese
- Frontend: €1-3/mese
- Database: €7/mese (db-f1-micro)
- Redis: €25/mese (Memorystore basic)
# TOTALE: €36-43/mese (ma scala in base al traffico!)
```

### ✅ **Perfetto per il tuo Stack**

- **Laravel Backend**: Ottimo per Cloud Run (stateless)
- **React Frontend**: Supporto nativo SPA
- **Database esterno**: Già configurato su Cloud SQL
- **Redis**: Disponibile via Memorystore

### ✅ **Deploy Semplificato**

```bash
# Deploy in un comando:
gcloud run deploy spreetzitt-backend \
  --source . \
  --region europe-west1 \
  --allow-unauthenticated
```

## 🛠️ Configurazione Raccomandata

### **Backend Laravel**

```yaml
# Risorse:
memory: 1Gi
cpu: 1 vCPU
concurrency: 80 requests/istanza
timeout: 300s
min-instances: 0 # Scale to zero
max-instances: 100 # Scale up per traffico alto
```

### **Frontend React**

```yaml
# Risorse:
memory: 512Mi
cpu: 1 vCPU
concurrency: 1000 requests/istanza
timeout: 60s
min-instances: 0
max-instances: 50
```

## 🔧 Configurazioni Avanzate

### **Custom Domains**

```bash
# Collega i tuoi domini
gcloud run domain-mappings create \
  --service spreetzitt-backend \
  --domain api.yourdomain.com

gcloud run domain-mappings create \
  --service spreetzitt-frontend \
  --domain app.yourdomain.com
```

### **Environment Variables**

```bash
# Setta variabili ambiente
gcloud run services update spreetzitt-backend \
  --set-env-vars="APP_ENV=production,APP_DEBUG=false" \
  --set-secrets="APP_KEY=app-key:latest"
```

### **Traffic Splitting** (per A/B testing)

```bash
# Gradual rollout
gcloud run services update-traffic spreetzitt-backend \
  --to-revisions=spreetzitt-backend-v2=20,spreetzitt-backend-v1=80
```

## 📈 Performance e Ottimizzazioni

### **Cold Start Optimization**

```dockerfile
# Multi-stage build per immagini più leggere
FROM php:8.2-fpm-alpine as builder
# ... build steps ...

FROM php:8.2-fpm-alpine as runtime
COPY --from=builder /app /app
# Immagine finale: ~100MB vs ~500MB
```

### **Connection Pooling**

```php
// Laravel - ottimizza connessioni DB
'connections' => [
    'mysql' => [
        'options' => [
            PDO::ATTR_PERSISTENT => true,
        ],
    ],
],
```

### **Caching Strategy**

```bash
# Redis per sessioni + cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## 🎯 **Quando Usare Cloud Run vs e2-medium**

### **✅ Usa Cloud Run se:**

- Traffico variabile/basso
- Vuoi zero management
- Budget ottimizzato
- Scaling automatico necessario
- SSL automatico importante

### **❌ Usa e2-medium se:**

- Traffico sempre alto (>80% utilizzo 24/7)
- Controllo completo del server necessario
- Applicazioni stateful
- Long-running processes continui

## 🚀 Migration Plan

### **Fase 1: Test Environment**

```bash
# Deploy su Cloud Run ambiente test
./cloud-run-deploy.sh setup
./cloud-run-deploy.sh deploy test
```

### **Fase 2: A/B Testing**

```bash
# 10% traffico Cloud Run, 90% e2-medium
# Misura performance e costi
```

### **Fase 3: Full Migration**

```bash
# Sposta completamente a Cloud Run
# Dismetti e2-medium
```

## 📊 Monitoring e Logs

### **Cloud Monitoring** (integrato)

- Request latency
- Error rates
- Instance count
- Memory/CPU usage

### **Structured Logging**

```php
// Laravel - log strutturati per Cloud Logging
Log::info('User action', [
    'user_id' => $user->id,
    'action' => 'login',
    'ip' => $request->ip()
]);
```

## 💡 **Raccomandazione Finale**

Per il tuo progetto Spreetzitt, **Cloud Run è la scelta migliore** perché:

1. **Costi**: Probabilmente spenderai 50-70% in meno
2. **Scalabilità**: Gestisce automaticamente picchi di traffico
3. **Semplicità**: Deploy con un comando, zero configurazione server
4. **Reliability**: 99.95% SLA di Google
5. **Global**: CDN e edge locations automatici

Vuoi che ti aiuti a configurare la migrazione da e2-medium a Cloud Run? 🚀

## 🔍 **MeiliSearch su Cloud Run - Soluzioni**

### **Problema**: MeiliSearch è stateful, Cloud Run è stateless

### **1. 🎯 SOLUZIONE CONSIGLIATA: MeiliSearch Cloud**

_(Hosted service ufficiale)_

```bash
# Costi MeiliSearch Cloud:
- Tier gratuito: 100k documenti, 10k ricerche/mese
- Tier Starter: €29/mese - 1M documenti, 100k ricerche/mese
- Tier Pro: €99/mese - 10M documenti, 1M ricerche/mese

# Configurazione Laravel:
MEILISEARCH_HOST=https://your-project.meilisearch.io
MEILISEARCH_KEY=your_private_key
```

**✅ Vantaggi:**

- Zero configurazione
- Backup automatici
- Scaling gestito
- SLA 99.9%
- Costi prevedibili

### **2. 🛠️ Compute Engine dedicata per MeiliSearch**

_(Architettura ibrida)_

```bash
# Crea VM micro per MeiliSearch
gcloud compute instances create meilisearch-vm \
  --machine-type=e2-micro \
  --image-family=ubuntu-2004-lts \
  --image-project=ubuntu-os-cloud \
  --disk-size=20GB \
  --zone=europe-west1-b

# Costi: ~€7-10/mese
```

**Architettura:**

```
Cloud Run (Backend + Frontend)
       ↓
   e2-micro VM (MeiliSearch)
       ↓
   Cloud SQL (Database)
```

### **3. 🐳 Docker Swarm con MeiliSearch Cloud**

_(Ibrido ottimizzato)_

```yaml
# docker-compose.hybrid.yml
version: "3.8"
services:
  backend:
    environment:
      # MeiliSearch Cloud
      - MEILISEARCH_HOST=https://your-project.meilisearch.io
      - MEILISEARCH_KEY=${MEILISEARCH_CLOUD_KEY}
  # Niente MeiliSearch locale
```

### **4. 🔧 Elasticsearch/Algolia Alternative**

```bash
# Opzione A: Elasticsearch Service
- €50-100/mese per cluster gestito
- Più potente ma complesso

# Opzione B: Algolia
- €0-50/mese based on operations
- Ottimo per search, ma meno flessibile
```

## 🎯 **Raccomandazione Finale MeiliSearch**

**Per il tuo progetto consiglio MeiliSearch Cloud:**

1. **Tier gratuito** per iniziare (100k documenti)
2. **€29/mese** quando cresci (vs €30/mese e2-medium completa)
3. **Zero management** - come Cloud Run
4. **Performance ottimali** - edge locations globali

## 💡 **SOLUZIONE DEFINITIVA: Scout Database Driver**

### **🎯 La Scelta più Intelligente**

**"Sfanculiamo MeiliSearch e usiamo Laravel Scout con driver database!"**

```bash
# Configurazione semplicissima:
SCOUT_DRIVER=database

# E basta. Fine. 🎉
```

**✅ Vantaggi:**

- **€0 costi extra** - usa il database che hai già
- **Zero configurazione** - funziona out-of-the-box
- **Zero gestione** - niente servizi aggiuntivi
- **Zero problemi** - MySQL full-text search integrato

**❌ Svantaggi:**

- Performance meno ottimali su dataset enormi (>100k record)
- Funzionalità di ricerca meno avanzate vs MeiliSearch

### **🚀 Implementazione**

```bash
# Script automatico per la migrazione
./migrate-to-database-search.sh migrate

# Quello che fa:
# 1. Configura Scout per usare database driver
# 2. Crea migration per full-text indexes
# 3. Aggiorna modelli Laravel
# 4. Rimuove MeiliSearch da Docker/Cloud Run
# 5. Pulisce configurazioni
```

### **📊 Confronto Finale**

| Soluzione                          | **Costi/mese** | **Gestione** | **Performance** |
| ---------------------------------- | -------------- | ------------ | --------------- |
| **e2-medium + MeiliSearch**        | €30            | Media        | Ottima          |
| **Cloud Run + MeiliSearch Cloud**  | €8-35          | Zero         | Ottima          |
| **Cloud Run + Database Search**    | €8-15          | Zero         | Buona           |
| **Docker Swarm + Database Search** | €30            | Bassa        | Buona           |

### **🎯 Raccomandazione DEFINITIVA:**

**Cloud Run + Scout Database Driver = €8-15/mese**

- Costi minimi
- Zero gestione
- Performance sufficienti per la maggior parte dei casi
- Scalabilità automatica
- Semplicità totale

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
./cloud-run-dns-setup.sh setup

# Quello che fa:
# 1. Mostra URL Cloud Run attuali
# 2. Configura domini personalizzati
# 3. Mostra record DNS da configurare
# 4. Crea Google Cloud Secrets dal tuo .env.prod
# 5. Configura environment variables
# 6. Testa la configurazione
```

### **📋 Checklist Configurazione**

**Prima del deploy:**

```bash
# 1. Crea .env.prod con le tue variabili
APP_KEY=base64:your-key
DB_HOST=your-gcp-sql-ip
DB_PASSWORD=your-password
DB_DATABASE=spreetzitt_prod
# ... altre variabili

# 2. Modifica domini nello script
FRONTEND_DOMAIN="app.tuodominio.com"
BACKEND_DOMAIN="api.tuodominio.com"

# 3. Verifica domini in Search Console
```

**Dopo il deploy:**

```bash
# 4. Esegui setup DNS
./cloud-run-dns-setup.sh setup

# 5. Configura record DNS nel tuo provider
# 6. Attendi propagazione DNS (5-60 min)
# 7. Testa configurazione
./cloud-run-dns-setup.sh test
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
