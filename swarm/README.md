# 🐳 Docker Swarm Setup - Spreetzitt

Configurazione per deploy Docker Swarm ottimizzata per il progetto Spreetzitt.

## 📋 Struttura File

```
swarm/
├── docker-compose.swarm.yml  # Configurazione Docker Swarm stack
├── deploy.sh                 # Script di deploy e gestione
├── .github-workflows-cicd.yml # GitHub Actions CI/CD
├── README.md                 # Questa documentazione
└── scripts/                  # Script di utilità
```

## 🚀 Setup Iniziale

### 1. Inizializza Docker Swarm

```bash
# Sul server di produzione
docker swarm init

# Se hai più nodi (opzionale)
# docker swarm join --token <token> <manager-ip>:2377
```

### 2. Crea directory di progetto

```bash
# Sul server
sudo mkdir -p /opt/spreetzitt
cd /opt/spreetzitt

# Clona repository
sudo git clone <your-repo-url> .
sudo chown -R $USER:$USER /opt/spreetzitt
```

### 3. Configura variabili d'ambiente

```bash
# Copia file di configurazione
cp .env.example .env.prod

# Modifica con i tuoi valori
nano .env.prod
```

Variabili essenziali:

```bash
# Laravel App
APP_KEY=base64:your_generated_app_key
APP_ENV=production
APP_DEBUG=false

# Database Google Cloud
DB_CONNECTION=mysql
DB_HOST=your-gcp-database-ip
DB_PORT=3306
DB_DATABASE=spreetzitt_prod
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Redis (interno al stack)
REDIS_PASSWORD=your_redis_password

# MeiliSearch
MEILISEARCH_KEY=your_meilisearch_master_key

# Email
MAIL_MAILER=smtp
MAIL_HOST=your.smtp.host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_password
```

### 4. Setup GitHub Actions

#### Configura i secrets nel repository GitHub:

**Repository Settings > Secrets and variables > Actions**

```bash
# Accesso SSH
SSH_PRIVATE_KEY=your_private_key
SSH_USER=your_username
SERVER_HOST=your.server.ip

# Database Google Cloud (per test)
DB_CONNECTION=mysql
DB_HOST=your-gcp-database-ip
DB_PORT=3306
DB_DATABASE=spreetzitt_test
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Domini
FRONTEND_DOMAIN=frontend.yourdomain.com
API_DOMAIN=api.yourdomain.com
```

#### Sposta il workflow file:

```bash
# Dal tuo repository locale
mkdir -p .github/workflows
cp server/swarm/.github-workflows-cicd.yml .github/workflows/cicd.yml
```

## 🎯 Utilizzo

### Deploy Manuale

```bash
cd /opt/spreetzitt/server/swarm

# Deploy versione latest
./deploy.sh deploy

# Deploy versione specifica
./deploy.sh deploy v1.2.3

# Visualizza stato
./deploy.sh status

# Visualizza log
./deploy.sh logs

# Segui log di un servizio specifico
./deploy.sh logs backend true
```

### Deploy Automatico

Il deploy automatico avviene tramite GitHub Actions:

1. **Push su `main`** → Deploy automatico in produzione
2. **Push su `develop`** → Build e test (no deploy)
3. **Tag `v*`** → Deploy con versione specifica
4. **Pull Request** → Solo test

### Gestione dello Stack

```bash
# Scala un servizio
./deploy.sh scale frontend 3

# Rollback alla versione precedente
./deploy.sh rollback

# Pulizia risorse
./deploy.sh cleanup

# Rimozione completa stack
./deploy.sh remove
```

## 📊 Monitoraggio

### Comandi utili

```bash
# Stato servizi
docker stack services spreetzitt

# Processi in esecuzione
docker stack ps spreetzitt

# Log di un servizio
docker service logs spreetzitt_backend

# Metriche risorse
docker stats
```

### Health Checks

Tutti i servizi hanno health check configurati:

- **Nginx**: verifica endpoint `/health`
- **Backend**: esegue `php artisan inspire`
- **Frontend**: verifica porta 3000
- **Redis**: ping Redis
- **MeiliSearch**: verifica endpoint `/health`

## 🔧 Configurazioni Avanzate

### SSL/TLS

I certificati SSL vanno posizionati in:

```
/opt/spreetzitt/sslcert/
├── certificate.crt
└── private.key
```

### Scaling Automatico

Per abilitare auto-scaling basato su CPU:

```bash
# Installa Docker Swarm autoscaler (opzionale)
docker service create \
  --name autoscaler \
  --mount type=bind,source=/var/run/docker.sock,target=/var/run/docker.sock \
  --constraint 'node.role == manager' \
  --mode global \
  your-autoscaler-image
```

### Backup Automatico

I backup delle configurazioni vengono salvati automaticamente in:

```
/opt/spreetzitt/backups/
```

## 🚨 Troubleshooting

### Servizio non parte

```bash
# Verifica log del servizio
docker service logs spreetzitt_<service_name>

# Verifica configurazione
docker stack config spreetzitt

# Riavvia servizio
docker service update --force spreetzitt_<service_name>
```

### Rollback d'emergenza

```bash
# Rollback immediato
./deploy.sh rollback

# Verifica stato dopo rollback
./deploy.sh status
```

### Problemi di rete

```bash
# Verifica rete overlay
docker network ls
docker network inspect spreetzitt_app-network

# Ricrea rete se necessario
docker network rm spreetzitt_app-network
docker stack deploy --compose-file docker-compose.swarm.yml spreetzitt
```

## 📈 Ottimizzazione Performance

### Risorse Limitate

Le risorse sono pre-configurate per un server medio:

- **Backend**: 1GB RAM, 0.5 CPU
- **Frontend**: 512MB RAM, 0.3 CPU
- **Redis**: 256MB RAM, 0.2 CPU
- **MeiliSearch**: 512MB RAM, 0.3 CPU

### Modifica risorse

Edita `docker-compose.swarm.yml`:

```yaml
deploy:
  resources:
    limits:
      memory: 2G
      cpus: "1.0"
    reservations:
      memory: 1G
      cpus: "0.5"
```

## 🔐 Sicurezza

- **Secrets**: Gestiti tramite Docker Secrets
- **Network**: Overlay criptata
- **Containers**: Non-privileged, read-only dove possibile
- **SSL**: Certificati gestiti tramite volumi read-only

## 📞 Supporto

Per problemi o miglioramenti, apri una issue nel repository o contatta il team di sviluppo.
