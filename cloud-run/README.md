# 🚀 Spreetzitt Cloud Run Deploy

Questa cartella contiene tutti gli script e file necessari per deployare Spreetzitt su Google Cloud Run.

## 📁 Struttura

```
cloud-run/
├── README.md                    # Questa guida
├── deploy.sh                    # Script principale deploy
├── setup.sh                     # Setup iniziale ambiente
├── config/
│   ├── .env.template           # Template variabili ambiente
│   ├── cloudbuild.yaml         # Configurazione CI/CD
│   └── service.yaml            # Configurazione Cloud Run services
├── docker/
│   ├── backend.dockerfile      # Dockerfile Laravel
│   ├── frontend.dockerfile     # Dockerfile React
│   ├── nginx-backend.conf      # Config nginx backend
│   ├── nginx-frontend.conf     # Config nginx frontend
│   └── supervisord.conf        # Config supervisor
└── scripts/
    ├── create-secrets.sh       # Gestione Google Cloud Secrets
    ├── dns-setup.sh           # Setup domini personalizzati
    ├── deploy-backend.sh      # Deploy solo backend
    ├── deploy-frontend.sh     # Deploy solo frontend
    └── cleanup.sh             # Pulizia risorse
```

## 🚀 Quick Start

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

## 📋 Prerequisiti

1. **gcloud CLI** installato e configurato
2. **Docker** installato
3. **Google Cloud Project** con fatturazione attiva
4. **Domini** verificati in Google Search Console (opzionale)

## 🎯 Costi Stimati

- **Backend**: €3-8/mese
- **Frontend**: €1-3/mese
- **Database**: €7/mese (Cloud SQL)
- **Redis**: €25/mese (Memorystore) o €0 (Scout database)

## 🔧 Configurazioni

### Environment Variables

Le variabili d'ambiente sono gestite tramite:

- **Secrets**: per variabili sensibili (passwords, keys)
- **Env vars**: per configurazioni non sensibili

### Domini Personalizzati

Gli script supportano automaticamente:

- SSL certificates (automatico)
- Custom domains
- DNS configuration

## 📚 Guide Dettagliate

Per una guida completa, vedi: `../swarm/CLOUD_RUN_GUIDE.md`
