# 🚀 Spreetzitt - Guida Deploy Produzione

## 📋 Checklist Pre-Deploy

### 1. Preparazione Environment

- [ ] Copiare `.env.prod.example` in `.env.prod`
- [ ] Configurare tutte le variabili d'ambiente
- [ ] Generare `APP_KEY` sicura: `php artisan key:generate --show`
- [ ] Configurare certificati SSL in `./sslcert/`
- [ ] Verificare configurazione database

### 2. Sicurezza

- [ ] Passwords sicure per tutti i servizi
- [ ] Firewall configurato (solo porte 80, 443, 22)
- [ ] Fail2ban attivo
- [ ] Backup automatici configurati
- [ ] Monitoring attivo

### 3. Performance

- [ ] Ottimizzazione database
- [ ] Cache Redis configurata
- [ ] CDN configurato (se necessario)
- [ ] Logs rotazione attiva

## 🔧 Configurazione Servizi

### Database Esterno

```bash
# Configurazione database esterno:
- Host: DB_HOST (tuo server database)
- Porta: DB_PORT (solitamente 3306 per MySQL)
- Database: DB_DATABASE
- Utente: DB_USERNAME
- Password: DB_PASSWORD

# Assicurati che il database esterno sia:
- Ottimizzato per produzione
- Con backup automatici
- Accessibile dai container Docker
- Con firewall configurato correttamente
```

### Redis

```bash
# Configurazione sicura:
- Password protetto
- Persistence attiva (AOF)
- Memory optimization
```

### Nginx

```bash
# Configurazioni di sicurezza:
- Rate limiting
- SSL/TLS ottimizzato
- Security headers
- Gzip compression
```

## 🚀 Deploy

### Deploy Iniziale

```bash
# 1. Preparare environment
cp .env.prod.example .env.prod
# Modificare .env.prod con i valori reali

# 2. Deploy
make prod-deploy
```

### Deploy Aggiornamenti

```bash
# Deploy veloce
make prod-up

# Deploy completo con build
make prod-deploy
```

### Rollback

```bash
make prod-rollback
```

## 📊 Monitoring

### Health Checks

Tutti i servizi hanno health checks configurati:

- Backend: `php artisan inspire`
- Frontend: HTTP check
- Database: `mysqladmin ping`
- Redis: `redis-cli ping`
- Meilisearch: HTTP health endpoint

### Logs

```bash
# Tutti i logs
make prod-logs

# Logs specifici
docker logs spreetzitt-backend-prod
docker logs spreetzitt-nginx-prod
```

### Metriche

I logs sono configurati con rotazione automatica:

- Max size: 10MB
- Max files: 3
- Driver: json-file

## 💾 Backup

### Backup Automatico

```bash
make prod-backup
```

### Backup Database Esterno

```bash
# Backup del database esterno (esegui dal server dove gira il DB o da remoto)
mysqldump -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE > backup_$(date +%Y%m%d).sql

# Backup con compressione
mysqldump -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Restore Database Esterno

```bash
# Restore del database esterno
mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE < backup_file.sql

# Restore da file compresso
gunzip < backup_file.sql.gz | mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE
```

## 🔒 Sicurezza

### Scansione Vulnerabilità

```bash
make security-scan
```

### Aggiornamenti Sicurezza

```bash
# Aggiorna immagini base
make prod-build
make prod-deploy
```

### SSL/TLS

- Certificati in `./sslcert/`
- Rinnovo automatico configurare con certbot
- Grade A+ SSL Labs

## 🛠️ Troubleshooting

### Container Non Si Avvia

```bash
# Check status
make prod-status

# Check logs
make prod-logs

# Check health
docker ps --filter "health=unhealthy"
```

### Performance Issues

```bash
# Check resource usage
docker stats

# Check database
docker exec spreetzitt-db-prod mysqladmin processlist -u root -p

# Check Redis
docker exec spreetzitt-redis-prod redis-cli info memory
```

### Database Issues

```bash
# Testa connessione al database esterno
docker exec spreetzitt-backend-prod php artisan tinker --execute="DB::connection()->getPdo();"

# Run migrations
docker exec spreetzitt-backend-prod php artisan migrate --force

# Clear cache
docker exec spreetzitt-backend-prod php artisan cache:clear

# Test database connection
docker exec spreetzitt-backend-prod php artisan migrate:status
```

## 📈 Ottimizzazioni

### Performance Database Esterno

⚠️ **Importante**: Dato che usi un database esterno, assicurati che sia ottimizzato:

```bash
# Configurazioni MySQL consigliate per produzione:
- innodb_buffer_pool_size = 70-80% della RAM disponibile
- max_connections = secondo il carico previsto
- query_cache_type = 1
- query_cache_size = 64M-256M
- slow_query_log = ON
- long_query_time = 2

# Monitoraggio performance
- Attiva slow query log
- Monitora connessioni attive
- Configura backup automatici
- Imposta alert per spazio disco
```

### Performance Redis

- Memory policy: allkeys-lru
- Maxmemory configurato
- Persistence ottimizzata

### Performance Applicazione

- OPcache attivo
- Config cached
- Routes cached
- Views cached

## 🔄 Manutenzione

### Aggiornamenti Regolari

```bash
# 1. Backup
make prod-backup

# 2. Deploy
make prod-deploy

# 3. Verifica
make prod-status
```

### Pulizia Logs

```bash
# Docker cleanup
docker system prune -f

# Application logs cleanup
docker exec spreetzitt-backend-prod php artisan log:clear
```

### Database Maintenance (Esterno)

```bash
# Ottimizzazione tabelle database esterno
mysqlcheck -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD --optimize $DB_DATABASE

# Analisi tabelle
mysqlcheck -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD --analyze $DB_DATABASE

# Riparazione tabelle (se necessario)
mysqlcheck -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD --repair $DB_DATABASE

# Check integrità
mysqlcheck -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD --check $DB_DATABASE
```

## 🚨 Emergenze

### Downtime Completo

1. Check server resources: `htop`, `df -h`
2. Check Docker: `docker system df`
3. Check logs: `make prod-logs`
4. Restart services: `make prod-deploy`

### Database Corrotta

1. Stop application: `make prod-down`
2. Restore backup: `[restore commands]`
3. Start application: `make prod-up`

### SSL Scaduto

1. Rinnova certificati
2. Restart nginx: `docker restart spreetzitt-nginx-prod`

## 📞 Supporto

Per problemi gravi:

1. Backup immediato: `make prod-backup`
2. Raccogliere logs: `make prod-logs > emergency.log`
3. Documentare il problema
4. Procedere con rollback se necessario
