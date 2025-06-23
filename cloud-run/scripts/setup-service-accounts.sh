#!/bin/bash

# 🔐 Spreetzitt - Setup Service Accounts
# Script per configurare automaticamente i service accounts necessari per Cloud Run e CI/CD

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔐 Spreetzitt - Setup Service Accounts${NC}"
echo "========================================"

# Ottieni project ID
PROJECT_ID=$(gcloud config get-value project)
if [ -z "$PROJECT_ID" ]; then
    echo -e "${RED}❌ Errore: Nessun progetto Google Cloud configurato${NC}"
    exit 1
fi

echo -e "${BLUE}ℹ️  Progetto: $PROJECT_ID${NC}"

# =============================================================================
# SERVICE ACCOUNT PER CLOUD RUN BACKEND
# =============================================================================
echo -e "${YELLOW}📦 Configurazione service account backend...${NC}"

BACKEND_SA="spreetzitt-backend"
BACKEND_SA_EMAIL="$BACKEND_SA@$PROJECT_ID.iam.gserviceaccount.com"

# Verifica se il service account esiste già
if gcloud iam service-accounts describe $BACKEND_SA_EMAIL --quiet 2>/dev/null; then
    echo -e "${GREEN}✅ Service account backend già esistente${NC}"
else
    echo -e "${YELLOW}ℹ️  Creazione service account backend...${NC}"
    gcloud iam service-accounts create $BACKEND_SA \
        --description="Service account for Spreetzitt backend on Cloud Run" \
        --display-name="Spreetzitt Backend"
    echo -e "${GREEN}✅ Service account backend creato${NC}"
fi

# Assegna permessi per Secret Manager
echo -e "${YELLOW}ℹ️  Assegnazione permessi Secret Manager...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$BACKEND_SA_EMAIL" \
    --role="roles/secretmanager.secretAccessor" \
    --quiet

# Assegna permessi per Cloud SQL (se necessario)
echo -e "${YELLOW}ℹ️  Assegnazione permessi Cloud SQL...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$BACKEND_SA_EMAIL" \
    --role="roles/cloudsql.client" \
    --quiet

echo -e "${GREEN}✅ Service account backend configurato${NC}"

# =============================================================================
# SERVICE ACCOUNT PER CLOUD RUN FRONTEND
# =============================================================================
echo -e "${YELLOW}🌐 Configurazione service account frontend...${NC}"

FRONTEND_SA="spreetzitt-frontend"
FRONTEND_SA_EMAIL="$FRONTEND_SA@$PROJECT_ID.iam.gserviceaccount.com"

# Verifica se il service account esiste già
if gcloud iam service-accounts describe $FRONTEND_SA_EMAIL --quiet 2>/dev/null; then
    echo -e "${GREEN}✅ Service account frontend già esistente${NC}"
else
    echo -e "${YELLOW}ℹ️  Creazione service account frontend...${NC}"
    gcloud iam service-accounts create $FRONTEND_SA \
        --description="Service account for Spreetzitt frontend on Cloud Run" \
        --display-name="Spreetzitt Frontend"
    echo -e "${GREEN}✅ Service account frontend creato${NC}"
fi

echo -e "${GREEN}✅ Service account frontend configurato${NC}"

# =============================================================================
# SERVICE ACCOUNT PER CI/CD (Cloud Build)
# =============================================================================
echo -e "${YELLOW}🚀 Configurazione service account CI/CD...${NC}"

CICD_SA="spreetzitt-cicd"
CICD_SA_EMAIL="$CICD_SA@$PROJECT_ID.iam.gserviceaccount.com"

# Verifica se il service account esiste già
if gcloud iam service-accounts describe $CICD_SA_EMAIL --quiet 2>/dev/null; then
    echo -e "${GREEN}✅ Service account CI/CD già esistente${NC}"
else
    echo -e "${YELLOW}ℹ️  Creazione service account CI/CD...${NC}"
    gcloud iam service-accounts create $CICD_SA \
        --description="Service account for Spreetzitt CI/CD pipeline" \
        --display-name="Spreetzitt CI/CD"
    echo -e "${GREEN}✅ Service account CI/CD creato${NC}"
fi

# Assegna permessi per Cloud Build
echo -e "${YELLOW}ℹ️  Assegnazione permessi Cloud Build...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$CICD_SA_EMAIL" \
    --role="roles/cloudbuild.builds.builder" \
    --quiet

# Assegna permessi per Cloud Run Admin
echo -e "${YELLOW}ℹ️  Assegnazione permessi Cloud Run...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$CICD_SA_EMAIL" \
    --role="roles/run.admin" \
    --quiet

# Assegna permessi per Container Registry
echo -e "${YELLOW}ℹ️  Assegnazione permessi Container Registry...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$CICD_SA_EMAIL" \
    --role="roles/storage.admin" \
    --quiet

# Assegna permessi per accedere ai secrets durante il build
echo -e "${YELLOW}ℹ️  Assegnazione permessi Secret Manager per CI/CD...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$CICD_SA_EMAIL" \
    --role="roles/secretmanager.secretAccessor" \
    --quiet

# Assegna permessi per impersonare altri service accounts
echo -e "${YELLOW}ℹ️  Assegnazione permessi Service Account User...${NC}"
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$CICD_SA_EMAIL" \
    --role="roles/iam.serviceAccountUser" \
    --quiet

echo -e "${GREEN}✅ Service account CI/CD configurato${NC}"

# =============================================================================
# SUMMARY
# =============================================================================
echo ""
echo -e "${GREEN}🎉 Setup completato!${NC}"
echo "=============================="
echo -e "${BLUE}Service Accounts creati:${NC}"
echo "📦 Backend:  $BACKEND_SA_EMAIL"
echo "🌐 Frontend: $FRONTEND_SA_EMAIL"
echo "🚀 CI/CD:    $CICD_SA_EMAIL"
echo ""
echo -e "${YELLOW}⚠️  Ricorda di aggiornare i file di configurazione:${NC}"
echo "   - cloudbuild.yaml: Usa service account CI/CD"
echo "   - service.yaml: Usa service accounts specifici per backend/frontend"
echo ""
echo -e "${GREEN}✅ Ora puoi procedere con il deploy!${NC}"
