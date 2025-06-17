# 🚀 Spreetzitt Backend - Production Setup

## 📦 Dockerfile di Produzione

Il `prod.backend.dockerfile` è ottimizzato per:

- **Multi-stage build** per immagini più leggere
- **Sicurezza avanzata** con utente non-root
- **Performance** con OPcache e ottimizzazioni PHP
- **Monitoring** con health checks integrati

## 🔧 File di Configurazione

### PHP Ottimizzato (`php/php.prod.ini`)

- OPcache abilitato per performance
- Limiti di memoria ottimizzati (256MB)
- Sicurezza avanzata (expose_php = Off)
- Gestione errori per produzione

### PHP-FPM (`php/php-fpm.prod.conf`)

- Process management statico per performance prevedibili
- 20 processi worker massimi
- Logging avanzato con slow query detection
- Security hardening

### Supervisord (`php/supervisord.prod.conf`)

- Gestione processi Laravel (queue, scheduler)
- Logging strutturato con rotazione automatica
- Health monitoring integrato
- Processo di pulizia log automatico

## 🚀 Build e Deploy

### Build Locale

```bash
# Build dell'immagine
docker build -f docker/prod.backend.dockerfile -t spreetzitt-backend:prod .

# Test dell'immagine
docker run --rm spreetzitt-backend:prod php --version
```

### Deploy Produzione

```bash
# Deploy completo
make prod-deploy

# Solo rebuild
make prod-build
```

## 🔍 Testing e Debugging

### Test Connessione Database

```bash
make test-db
```

### Health Check

```bash
# Verifica health del container
docker inspect --format='{{.State.Health.Status}}' spreetzitt-backend-prod

# Log health check
docker logs spreetzitt-backend-prod | grep health
```

### Performance Monitoring

```bash
# Status PHP-FPM
curl http://localhost/fpm-status

# Ping PHP-FPM
curl http://localhost/fpm-ping

# Process info
docker exec spreetzitt-backend-prod supervisorctl status
```

## 📊 Ottimizzazioni Implementate

### PHP Level

- **OPcache**: Cache bytecode per performance
- **Realpath Cache**: Cache filesystem paths
- **Memory Management**: Garbage collection ottimizzato

### Application Level

- **Config Cache**: Configurazione Laravel cachata
- **Route Cache**: Route precompilate
- **View Cache**: Template Blade precompilati
- **Event Cache**: Event listeners cachati

### Process Level

- **Static PM**: Processi PHP-FPM fissi per performance prevedibili
- **Queue Workers**: 2 worker Redis paralleli
- **Supervisor**: Gestione processi con auto-restart

## 🔒 Sicurezza

### Container Security

- **Non-root User**: Processi eseguiti come `appuser`
- **Read-only Filesystem**: Dove possibile
- **No New Privileges**: Prevenzione escalation
- **Minimal Dependencies**: Solo pacchetti necessari

### PHP Security

- **Error Hiding**: Errori non esposti in produzione
- **File Upload**: Controlli rigorosi
- **Session Security**: Cookie sicuri e HttpOnly

### Process Security

- **Resource Limits**: Memory e process limits
- **Environment Isolation**: Variabili ambiente controllate
- **Log Security**: Log separati per audit

## 🔧 Troubleshooting

### Container Non Si Avvia

```bash
# Check logs
docker logs spreetzitt-backend-prod

# Check configuration
docker exec spreetzitt-backend-prod php artisan config:show

# Test PHP
docker exec spreetzitt-backend-prod php -m
```

### Performance Issues

```bash
# Check OPcache status
docker exec spreetzitt-backend-prod php -r "print_r(opcache_get_status());"

# Check memory usage
docker exec spreetzitt-backend-prod php artisan about

# Monitor processes
docker exec spreetzitt-backend-prod supervisorctl status
```

### Database Issues

```bash
# Test connection
make test-db

# Run migrations
docker exec spreetzitt-backend-prod php artisan migrate --force

# Check queue
docker exec spreetzitt-backend-prod php artisan queue:work --once
```

## 📈 Monitoring

### Log Files

- `/var/log/supervisor/` - Supervisord logs
- `/var/www/html/storage/logs/` - Laravel logs
- `/var/log/fpm-*.log` - PHP-FPM logs

### Metrics

- Memory usage: `docker stats spreetzitt-backend-prod`
- Process status: `supervisorctl status`
- Application health: Health check endpoint

### Alerts

Configura alert per:

- Container restart frequenti
- High memory usage (>80%)
- Queue job failures
- Slow query detection (>2 secondi)
