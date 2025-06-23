# 🌐 Collegare Dominio Aruba a Google Cloud Run

Guida passo-passo per configurare il tuo dominio Aruba con Google Cloud Run.

## 📋 Prerequisiti

- ✅ Dominio registrato su Aruba
- ✅ Progetto Google Cloud configurato
- ✅ `gcloud` CLI installato e configurato
- ✅ Servizi Cloud Run deployati

## 🚀 Procedura Completa

### Step 1: Deploy su Cloud Run

Se non hai ancora deployato, esegui:

```bash
# Backend
gcloud run deploy spreetzitt-backend \
  --source . \
  --region europe-west8 \
  --allow-unauthenticated

# Frontend
gcloud run deploy spreetzitt-frontend \
  --source . \
  --region europe-west8 \
  --allow-unauthenticated
```

### Step 2: Verifica Domini in Google Search Console

1. Vai su [Google Search Console](https://search.google.com/search-console)
2. Clicca **"Aggiungi proprietà"**
3. Seleziona **"Prefisso URL"**
4. Inserisci il tuo dominio:
   - `https://api.tuodominio.com`
   - `https://app.tuodominio.com`
5. Segui la procedura di verifica (file HTML o record TXT)

> **💡 Tip**: La verifica tramite record TXT è più semplice se hai accesso al DNS.

### Step 3: Mappa Domini a Cloud Run

```bash
# Mappa backend
gcloud run domain-mappings create \
  --service=spreetzitt-backend \
  --domain=api.tuodominio.com \
  --region=europe-west8

# Mappa frontend
gcloud run domain-mappings create \
  --service=spreetzitt-frontend \
  --domain=app.tuodominio.com \
  --region=europe-west8
```

### Step 4: Ottieni Record DNS

Dopo la mappatura, Google ti fornirà i record DNS:

```bash
# Visualizza record DNS per backend
gcloud run domain-mappings describe api.tuodominio.com \
  --region=europe-west8 \
  --format="value(status.resourceRecords)"

# Visualizza record DNS per frontend
gcloud run domain-mappings describe app.tuodominio.com \
  --region=europe-west8 \
  --format="value(status.resourceRecords)"
```

**Esempio output**:

```
api.tuodominio.com A 216.239.32.21
_ghs-verification.api.tuodominio.com TXT ghs-verification=abc123xyz
```

## 🔧 Configurazione DNS su Aruba

### Accesso al Pannello

1. Vai su [admin.aruba.it](https://admin.aruba.it)
2. Effettua il login
3. **I tuoi servizi** → **Domini**
4. Clicca sul tuo dominio
5. **Gestione DNS**

### Aggiunta Record DNS

Per ogni record fornito da Google:

#### Record A (Indirizzo IP)

- **Tipo**: A
- **Nome/Host**: `api` (per api.tuodominio.com)
- **Valore**: `216.239.32.21` (IP fornito da Google)
- **TTL**: `3600` (1 ora)

#### Record TXT (Verifica)

- **Tipo**: TXT
- **Nome/Host**: `_ghs-verification.api`
- **Valore**: `ghs-verification=abc123xyz`
- **TTL**: `3600`

#### Record CNAME (Alternative)

Se Google fornisce un CNAME invece di un A:

- **Tipo**: CNAME
- **Nome/Host**: `api`
- **Valore**: `ghs.googlehosted.com`
- **TTL**: `3600`

### Esempio Configurazione Completa

Per dominio `miodominio.com` con sottodomini `api` e `app`:

| Tipo | Nome                   | Valore                  | TTL  |
| ---- | ---------------------- | ----------------------- | ---- |
| A    | api                    | 216.239.32.21           | 3600 |
| A    | app                    | 216.239.34.21           | 3600 |
| TXT  | \_ghs-verification.api | ghs-verification=xyz123 | 3600 |
| TXT  | \_ghs-verification.app | ghs-verification=abc456 | 3600 |

## ⏰ Propagazione DNS

### Tempi di Attesa

- **Aruba**: 15-30 minuti
- **Propagazione globale**: 2-48 ore (solitamente 2-6 ore)

### Verifica Propagazione

```bash
# Test risoluzione DNS
nslookup api.tuodominio.com
nslookup app.tuodominio.com

# Test dettagliato
dig api.tuodominio.com
dig app.tuodominio.com

# Tool online
# https://whatsmydns.net
```

## 🧪 Test Configurazione

### Test Base

```bash
# Test connessione HTTPS
curl -I https://api.tuodominio.com
curl -I https://app.tuodominio.com

# Test con endpoint specifico
curl https://api.tuodominio.com/health
```

### Test Completo

```bash
# Verifica SSL automatico
openssl s_client -connect api.tuodominio.com:443 -servername api.tuodominio.com

# Test performance
curl -w "@curl-format.txt" -o /dev/null -s https://api.tuodominio.com
```

## 🔐 Configurazione Variabili d'Ambiente

### Variabili Non Sensibili

```bash
gcloud run services update spreetzitt-backend \
  --set-env-vars="APP_ENV=production,DB_HOST=1.2.3.4,SCOUT_DRIVER=database" \
  --region=europe-west8
```

### Variabili Sensibili (Secrets)

```bash
# Crea secret
echo "your-app-key" | gcloud secrets create spreetzitt-app-key --data-file=-
echo "your-db-password" | gcloud secrets create spreetzitt-db-password --data-file=-

# Configura su Cloud Run
gcloud run services update spreetzitt-backend \
  --set-secrets="APP_KEY=spreetzitt-app-key:latest,DB_PASSWORD=spreetzitt-db-password:latest" \
  --region=europe-west8
```

### Migrazione da .env

Se hai un file `.env.prod`, puoi automatizzare:

```bash
# Script per creare secrets dal .env
while IFS='=' read -r key value; do
  if [[ $key == *"PASSWORD"* ]] || [[ $key == *"KEY"* ]] || [[ $key == *"SECRET"* ]]; then
    echo "$value" | gcloud secrets create "spreetzitt-${key,,}" --data-file=-
    echo "Secret creato: spreetzitt-${key,,}"
  fi
done < .env.prod
```

## 🎯 Risultato Finale

Una volta completata la configurazione:

```
✅ Frontend: https://app.tuodominio.com
✅ Backend:  https://api.tuodominio.com
✅ SSL automatico gestito da Google
✅ Scaling automatico 0-1000 istanze
✅ Costi pay-per-use
```

## 🐛 Troubleshooting

### Problemi Comuni

#### DNS non risolve

```bash
# Verifica record su Aruba
nslookup api.tuodominio.com

# Se ancora punta al vecchio IP, attendi propagazione
# Oppure verifica configurazione DNS su Aruba
```

#### SSL non funziona

```bash
# Google provisiona SSL automaticamente dopo 10-60 minuti
# Verifica stato certificato
gcloud run domain-mappings describe api.tuodominio.com \
  --region=europe-west8 \
  --format="value(status.conditions)"
```

#### Servizio non raggiungibile

```bash
# Verifica che il servizio sia up
gcloud run services list --region=europe-west8

# Test URL Cloud Run originale
curl https://spreetzitt-backend-xxx-ew.a.run.app
```

### Log e Debug

```bash
# Log del servizio
gcloud run services logs read spreetzitt-backend --region=europe-west8

# Stato mapping domini
gcloud run domain-mappings list --region=europe-west8

# Verifica configurazione servizio
gcloud run services describe spreetzitt-backend --region=europe-west8
```

## 💡 Best Practices

### Sicurezza

- ✅ Usa sempre HTTPS (automatico con Cloud Run)
- ✅ Configura secrets per dati sensibili
- ✅ Limita accesso con IAM quando necessario

### Performance

- ✅ Configura TTL DNS appropriato (3600s)
- ✅ Usa CDN per contenuti statici
- ✅ Monitora cold start times

### Costi

- ✅ Configura `min-instances=0` per scale-to-zero
- ✅ Monitora utilizzo con Cloud Monitoring
- ✅ Imposta budget alerts

## 📚 Risorse Utili

- [Google Cloud Run Documentation](https://cloud.google.com/run/docs)
- [Custom Domains Guide](https://cloud.google.com/run/docs/mapping-custom-domains)
- [Aruba DNS Help](https://help.aruba.it)
- [DNS Propagation Checker](https://whatsmydns.net)

---

**🎉 Fatto!** Il tuo dominio Aruba è ora collegato a Google Cloud Run con SSL automatico e scaling gestito!
