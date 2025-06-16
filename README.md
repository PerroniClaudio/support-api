# 🖥️ Spreetzitt Server

Backend e configurazione dell'infrastruttura per Spreetzitt, che include API Laravel, servizi Docker e configurazioni per l'ambiente di sviluppo.

## 📋 Panoramica

Il server è composto da:

- **Backend API**: Laravel (PHP) in `/support-api/`
- **Frontend**: React con Vite in `/frontend/`
- **Web Server**: Nginx come reverse proxy
- **Search**: Meilisearch per ricerca avanzata
- **Cache**: Redis per performance
- **Containerizzazione**: Docker Compose per orchestrazione

## 🚀 Quick Start

### Prerequisiti

- Docker e Docker Compose
- Make (consigliato per i comandi semplificati)

### Avvio Rapido

```bash
# Con Make (metodo consigliato)
make up

# Senza Make
docker-compose -f docker-compose.dev.yml --env-file .env up -d --build
```

### Verifica che tutto funzioni

```bash
make status  # Controlla lo stato dei container
make logs    # Visualizza tutti i log
```

## 🔧 Comandi Make Disponibili

### 🚀 Comandi Principali

| Comando        | Descrizione                         |
| -------------- | ----------------------------------- |
| `make up`      | Avvia tutti i servizi con build     |
| `make down`    | Ferma tutti i servizi               |
| `make restart` | Riavvia tutti i servizi             |
| `make rebuild` | Ferma, ricostruisce e riavvia tutto |
| `make build`   | Ricostruisce tutte le immagini      |

### 📊 Monitoring e Log

| Comando                 | Descrizione                           |
| ----------------------- | ------------------------------------- |
| `make status`           | Mostra lo stato di tutti i container  |
| `make logs`             | Visualizza tutti i log in tempo reale |
| `make frontend-logs`    | Solo log del frontend React           |
| `make backend-logs`     | Solo log del backend Laravel          |
| `make nginx-logs`       | Solo log di Nginx                     |
| `make redis-logs`       | Solo log di Redis                     |
| `make meilisearch-logs` | Solo log di Meilisearch               |

### 🛠️ Utilità di Sviluppo

| Comando               | Descrizione                              |
| --------------------- | ---------------------------------------- |
| `make meilisearch-ui` | Apre Meilisearch UI nel browser          |
| `make shell-backend`  | Accedi alla shell del container backend  |
| `make shell-frontend` | Accedi alla shell del container frontend |
| `make shell-nginx`    | Accedi alla shell del container Nginx    |
| `make redis-cli`      | Accedi alla CLI di Redis                 |

### 📦 Gestione Dipendenze

| Comando                      | Descrizione                              |
| ---------------------------- | ---------------------------------------- |
| `make npm-install`           | Installa dipendenze npm del frontend     |
| `make composer-install`      | Installa dipendenze Composer del backend |
| `make artisan CMD="comando"` | Esegui comandi Artisan Laravel           |

### 🧹 Pulizia

| Comando          | Descrizione                                   |
| ---------------- | --------------------------------------------- |
| `make clean`     | Rimuove container e immagini non utilizzate   |
| `make clean-all` | Pulizia completa (⚠️ rimuove anche i volumi!) |

## 🌐 Servizi e Porte

| Servizio    | URL/Porta             | Descrizione               |
| ----------- | --------------------- | ------------------------- |
| Frontend    | http://localhost:5173 | Applicazione React        |
| Backend     | http://localhost      | API Laravel tramite Nginx |
| Meilisearch | http://localhost:7700 | Search engine e UI        |
| Redis       | localhost:6379        | Cache e sessioni          |
| Nginx       | localhost:80/443      | Web server e proxy        |

## 📁 Struttura delle Directory

```
server/
├── docker-compose.dev.yml    # Configurazione sviluppo
├── docker-compose.prod.yml   # Configurazione produzione
├── Dockerfile                # Immagine backend
├── Dockerfile.frontend       # Immagine frontend
├── Makefile                  # Comandi semplificati
├── .env                      # Variabili d'ambiente
├── frontend/                 # Applicazione React
├── support-api/              # API Laravel
├── nginx/                    # Configurazione Nginx
├── php/                      # Configurazione PHP
├── redis/                    # Dati Redis persistenti
├── script/                   # Script di utilità
└── sslcert/                  # Certificati SSL
```

## 🔧 Configurazione

### File .env

Crea e configura il file `.env` con le tue impostazioni:

```bash
# Copia il template se esiste
cp .env.example .env

# Oppure crea manualmente con le variabili necessarie
```

Variabili importanti:

- `MEILISEARCH_KEY`: Chiave master per Meilisearch
- `APP_ENV`: Ambiente (development/production)
- Configurazioni database, cache, ecc.

### SSL/HTTPS

I certificati SSL sono in `/sslcert/`. Per development puoi usare certificati self-signed:

```bash
# Genera certificati per development (se necessario)
make shell-nginx
# Dentro il container nginx
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/nginx/ssl/private.key \
  -out /etc/nginx/ssl/certificate.crt
```

## 🚀 Sviluppo

### Workflow Tipico

1. **Avvia l'ambiente**:

   ```bash
   make up
   ```

2. **Sviluppa il frontend** (React):

   - Modifica i file in `frontend/src/`
   - Hot reload automatico su http://localhost:5173

3. **Sviluppa il backend** (Laravel):

   - Modifica i file in `support-api/`
   - Ricaricamento automatico delle modifiche PHP

4. **Controlla i log**:

   ```bash
   make logs                # Tutti insieme
   make frontend-logs       # Solo frontend
   make backend-logs        # Solo backend
   ```

5. **Accedi ai container** se necessario:
   ```bash
   make shell-backend       # Per Laravel/PHP
   make shell-frontend      # Per React/Node
   ```

### Eseguire Comandi Laravel

```bash
# Migrazioni database
make artisan CMD="migrate"

# Seeder
make artisan CMD="db:seed"

# Cache clear
make artisan CMD="cache:clear"

# Generare chiave app
make artisan CMD="key:generate"
```

### Debugging

```bash
# Controlla stato container
make status

# Log in tempo reale
make logs

# Log specifici
make backend-logs
make nginx-logs

# Accedi al container per debug
make shell-backend
```

## 🔍 Meilisearch

### Configurazione

Meilisearch è configurato per:

- **Porta**: 7700
- **Master Key**: Definita in `.env` come `MEILISEARCH_KEY`
- **Dati persistenti**: Volume Docker `meilisearch_data`

### Uso

```bash
# Apri l'interfaccia web
make meilisearch-ui

# Controlla i log
make meilisearch-logs

# Accedi ai dati (tramite API o UI web)
curl -H "Authorization: Bearer YOUR_MASTER_KEY" \
  "http://localhost:7700/indexes"
```

## 💾 Redis

### Configurazione

Redis è configurato per:

- **Porta**: 6379
- **Dati persistenti**: Volume Docker `redis_data`

### Uso

```bash
# Accedi alla CLI
make redis-cli

# Una volta dentro Redis CLI
redis> keys *
redis> get chiave_specifica
redis> info
```

## 🐛 Troubleshooting

### Problemi Comuni

**Container non si avviano:**

```bash
make logs  # Controlla gli errori
make down
make up
```

**Errori di build:**

```bash
make build  # Ricostruisci tutto
# oppure
make rebuild  # Ferma, ricostruisci e riavvia
```

**Problemi di rete/porte:**

```bash
# Controlla chi usa le porte
lsof -i :80 -i :443 -i :5173 -i :7700

# Ferma tutto e riavvia
make down
make up
```

**Cache/Volume corrotti:**

```bash
make clean     # Pulizia normale
# oppure per reset completo
make clean-all  # ⚠️ Rimuove anche i dati!
```

**Permessi file:**

```bash
# Su macOS/Linux
sudo chown -R $USER:$USER .
```

### Log Dettagliati

```bash
# Tutti i log
docker-compose -f docker-compose.dev.yml logs --tail=100

# Log specifico container
docker logs backend --tail=50 -f
docker logs frontend --tail=50 -f
docker logs nginx --tail=50 -f
```

## 🔄 Aggiornamenti

### Aggiornare le dipendenze

```bash
# Frontend (npm)
make shell-frontend
npm update

# Backend (composer)
make shell-backend
composer update
```

### Ricostruire dopo modifiche ai Dockerfile

```bash
make rebuild
```

## 📊 Performance e Monitoring

### Monitoring Container

```bash
# Uso risorse
docker stats

# Spazio occupato
docker system df

# Log delle performance
make logs | grep -E "(error|warning|slow)"
```

### Ottimizzazione

- **Laravel**: Cache delle configurazioni, route, view
- **Redis**: Usa come cache per sessioni e query
- **Nginx**: Proxy caching configurato
- **Meilisearch**: Indicizzazione ottimizzata

## 🚀 Deploy Produzione

Per la produzione, usa il compose dedicato:

```bash
docker-compose -f docker-compose.prod.yml up -d --build
```

Assicurati di:

- Configurare correttamente le variabili d'ambiente
- Usare certificati SSL validi
- Configurare backup per Redis e Meilisearch
- Impostare log rotation

---

**Per supporto, consulta i log con `make logs` o apri un issue!** 🚀
