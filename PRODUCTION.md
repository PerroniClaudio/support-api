# 🚀 Guida Deploy Produzione - Spreetzitt

## 📋 Prerequisiti

### **Sistema Operativo**

- Linux (Ubuntu 20.04+ raccomandato) o macOS
- Docker 20.10+ installato
- Docker Compose 2.0+ installato

### **Certificati SSL**

- Certificato SSL valido in `./sslcert/certificate.crt`
- Chiave privata in `./sslcert/private.key`

### **Domini DNS**

- `api.ifortech.com` → IP del server (backend API)
- `frontend.ifortech.com` → IP del server (frontend React)

---

## 🔧 Configurazione Iniziale

### **1. Variabili d'Ambiente**

Crea il file `.env.prod` nella root del progetto:

```bash
# Database
DB_CONNECTION=mysql
DB_HOST=database_host
DB_PORT=3306
DB_DATABASE=spreetzitt_prod
DB_USERNAME=spreetzitt_user
DB_PASSWORD=your_secure_password

# Laravel App
APP_KEY=base64:your_generated_app_key
APP_ENV=production
APP_DEBUG=false

# Redis
REDIS_PASSWORD=your_redis_password

# MeiliSearch
MEILISEARCH_KEY=your_meilisearch_master_key

# Email
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_password
```

### **2. Certificati SSL**

```bash
# Copia i tuoi certificati SSL
cp your_certificate.crt ./sslcert/certificate.crt
cp your_private.key ./sslcert/private.key

# Imposta i permessi corretti
chmod 644 ./sslcert/certificate.crt
chmod 600 ./sslcert/private.key
```

### **3. Configurazione Frontend**

Modifica il file `./frontend/.env.local` per produzione:

```bash
# 365 auth (Production)
VITE_MICROSOFT_TENANT_ID=e0afdb25-123e-41b8-b985-4aab3fc0e719
VITE_MICROSOFT_CLIENT_ID=eea21952-f77c-4996-af44-37b3c80b0cfa
VITE_REDIRECT_URI=https://frontend.ifortech.com/support/admin

# Server URLs (Production)
VITE_SERVER_URL=https://frontend.ifortech.com
VITE_API_BASE_URL=https://api.ifortech.com

# Google API
VITE_GOOGLE_API_KEY=
VITE_OTP_VALIDATION_DURATION=6000000
```

---

## 🚀 Deploy

### **1. Test della Configurazione Nginx**

```bash
# Test sintassi configurazione nginx
docker run --rm -v $(pwd)/nginx/default.prod.conf:/etc/nginx/conf.d/default.conf:ro nginx:alpine nginx -t
```

### **2. Build e Avvio dei Servizi**

```bash
# Fermata eventuali servizi attivi
docker-compose -f docker-compose.prod.yml down

# Build delle immagini (forza rebuild)
docker-compose -f docker-compose.prod.yml build --no-cache

# Avvio dei servizi in background
docker-compose -f docker-compose.prod.yml up -d
```

### **3. Verifica Deploy**

```bash
# Controlla lo stato dei container
docker-compose -f docker-compose.prod.yml ps

# Verifica i log
docker-compose -f docker-compose.prod.yml logs -f

# Test health check
docker-compose -f docker-compose.prod.yml exec frontend wget --spider http://localhost:3000
docker-compose -f docker-compose.prod.yml exec backend php artisan inspire
```

---

## 🔍 Test e Verifica

### **1. Test Nginx e SSL**

```bash
# Test configurazione SSL
curl -I https://api.ifortech.com
curl -I https://frontend.ifortech.com

# Test headers di sicurezza
curl -I https://frontend.ifortech.com | grep -E "(X-Frame-Options|X-Content-Type-Options|Strict-Transport-Security)"
```

### **2. Test Applicazioni**

- **Frontend**: Apri https://frontend.ifortech.com
- **API**: Test endpoint https://api.ifortech.com/api/health
- **Admin**: https://frontend.ifortech.com/support/admin

### **3. Test Performance**

```bash
# Test compressione gzip
curl -H "Accept-Encoding: gzip" -I https://frontend.ifortech.com

# Test cache headers
curl -I https://frontend.ifortech.com/assets/app.js
```

---

## 📊 Monitoraggio

### **1. Log dei Container**

```bash
# Log di tutti i servizi
docker-compose -f docker-compose.prod.yml logs -f

# Log specifici per servizio
docker-compose -f docker-compose.prod.yml logs -f nginx
docker-compose -f docker-compose.prod.yml logs -f frontend
docker-compose -f docker-compose.prod.yml logs -f backend
```

### **2. Metriche di Performance**

```bash
# Utilizzo risorse dei container
docker stats

# Spazio disco
docker system df
```

### **3. Health Check**

```bash
# Script per controllo automatico
#!/bin/bash
echo "=== Health Check Spreetzitt Production ==="
echo "Frontend: $(curl -s -o /dev/null -w "%{http_code}" https://frontend.ifortech.com)"
echo "API: $(curl -s -o /dev/null -w "%{http_code}" https://api.ifortech.com)"
echo "Container Status:"
docker-compose -f docker-compose.prod.yml ps --format "table {{.Name}}\t{{.Status}}"
```

---

## 🔧 Manutenzione

### **1. Aggiornamenti**

```bash
# Backup before update
docker-compose -f docker-compose.prod.yml exec backend php artisan backup:run

# Pull delle modifiche
git pull origin main

# Rebuild e restart
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d
```

### **2. Backup**

```bash
# Backup database
docker-compose -f docker-compose.prod.yml exec backend php artisan backup:run

# Backup volumi Docker
docker run --rm -v spreetzitt_storage_data:/data -v $(pwd)/backups:/backup alpine tar czf /backup/storage-backup-$(date +%Y%m%d).tar.gz -C /data .
```

### **3. Risoluzione Problemi**

```bash
# Restart singolo servizio
docker-compose -f docker-compose.prod.yml restart frontend

# Ricostruzione completa
docker-compose -f docker-compose.prod.yml down -v
docker-compose -f docker-compose.prod.yml up -d --build

# Accesso shell container
docker-compose -f docker-compose.prod.yml exec frontend sh
docker-compose -f docker-compose.prod.yml exec backend bash
```

---

## 🔒 Sicurezza

### **1. Configurazioni di Sicurezza Implementate**

- ✅ SSL/TLS con cipher suite moderne
- ✅ Headers di sicurezza (HSTS, CSP, X-Frame-Options)
- ✅ Rate limiting e timeout configurati
- ✅ File sensibili bloccati
- ✅ Container con privilegi limitati

### **2. Controlli Periodici**

```bash
# Test certificato SSL
echo | openssl s_client -servername frontend.ifortech.com -connect frontend.ifortech.com:443 2>/dev/null | openssl x509 -noout -dates

# Scan vulnerabilità container
docker scan spreetzitt-frontend-prod
docker scan spreetzitt-backend-prod
```

---

## 🆘 Troubleshooting

### **Problemi Comuni**

**1. Frontend non risponde**

```bash
docker-compose -f docker-compose.prod.yml logs frontend
# Controlla se serve è attivo sulla porta 3000
```

**2. Errori SSL**

```bash
# Verifica certificati
openssl x509 -in ./sslcert/certificate.crt -text -noout
# Controlla permessi
ls -la ./sslcert/
```

**3. Database connection failed**

```bash
# Test connessione database
docker-compose -f docker-compose.prod.yml exec backend php artisan migrate:status
```

---

## 📞 Contatti di Emergenza

- **Sviluppatore**: [inserire contatto]
- **System Admin**: [inserire contatto]
- **Documentazione**: https://github.com/your-repo/spreetzitt

---

**⚠️ IMPORTANTE**: Prima di ogni deploy in produzione, testare sempre in ambiente di staging!
