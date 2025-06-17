# Spreetzitt Development Makefile
# Comandi semplificati per gestire l'ambiente di sviluppo e produzione

.PHONY: help up down build restart logs frontend-logs backend-logs nginx-logs redis-logs meilisearch-logs meilisearch-ui status clean prod-up prod-down prod-build prod-logs prod-status prod-deploy prod-rollback prod-backup security-scan ssl-renew

# Configurazione
COMPOSE_FILE := docker-compose.dev.yml
COMPOSE_FILE_PROD := docker-compose.prod.yml
ENV_FILE := .env
ENV_FILE_PROD := .env.prod

# Colori per output colorato
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[0;33m
BLUE := \033[0;34m
MAGENTA := \033[0;35m
CYAN := \033[0;36m
NC := \033[0m # No Color

## 🚀 COMANDI PRINCIPALI

help: ## 📖 Mostra questo messaggio di aiuto
	@echo "$(CYAN)=== Spreetzitt Development Environment ===$(NC)"
	@echo ""
	@echo "$(GREEN)Comandi disponibili:$(NC)"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ""
	@echo "$(BLUE)Esempi d'uso:$(NC)"
	@echo "  make up          # Avvia tutti i servizi"
	@echo "  make logs        # Visualizza tutti i log"
	@echo "  make meilisearch-ui # Apre Meilisearch UI nel browser"
	@echo ""

up: ## 🔥 Avvia tutti i servizi (con build)
	@echo "$(GREEN)🚀 Avvio dell'ambiente di sviluppo...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) --env-file $(ENV_FILE) up -d --build
	@echo "$(GREEN)✅ Servizi avviati con successo!$(NC)"
	@echo "$(CYAN)Frontend:$(NC) http://localhost:5173"
	@echo "$(CYAN)Backend:$(NC) http://localhost (HTTPS disponibile)"
	@echo "$(CYAN)Meilisearch UI:$(NC) http://localhost:7700"
	@echo "$(CYAN)Redis:$(NC) localhost:6379"

down: ## 🛑 Ferma tutti i servizi
	@echo "$(YELLOW)🛑 Fermando tutti i servizi...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) down
	@echo "$(GREEN)✅ Servizi fermati$(NC)"

build: ## 🔨 Ricostruisce tutte le immagini
	@echo "$(BLUE)🔨 Ricostruzione delle immagini...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) build --no-cache
	@echo "$(GREEN)✅ Immagini ricostruite$(NC)"

rebuild: down build up ## 🔄 Ferma, ricostruisce e riavvia tutto

restart: ## 🔄 Riavvia tutti i servizi
	@echo "$(YELLOW)🔄 Riavvio dei servizi...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) restart
	@echo "$(GREEN)✅ Servizi riavviati$(NC)"

## 📊 MONITORING E LOG

status: ## 📊 Mostra lo stato di tutti i container
	@echo "$(CYAN)📊 Stato dei container:$(NC)"
	@docker-compose -f $(COMPOSE_FILE) ps

logs: ## 📜 Visualizza tutti i log
	@echo "$(CYAN)📜 Log di tutti i servizi:$(NC)"
	@docker-compose -f $(COMPOSE_FILE) logs -f

frontend-logs: ## 🎨 Log del frontend (React/Vite)
	@echo "$(CYAN)🎨 Log del frontend:$(NC)"
	@docker logs -f frontend

backend-logs: ## ⚙️ Log del backend (PHP/Laravel)
	@echo "$(CYAN)⚙️ Log del backend:$(NC)"
	@docker logs -f backend

nginx-logs: ## 🌐 Log di Nginx
	@echo "$(CYAN)🌐 Log di Nginx:$(NC)"
	@docker logs -f nginx

redis-logs: ## 🔴 Log di Redis
	@echo "$(CYAN)🔴 Log di Redis:$(NC)"
	@docker logs -f redis

meilisearch-logs: ## 🔍 Log di Meilisearch
	@echo "$(CYAN)🔍 Log di Meilisearch:$(NC)"
	@docker logs -f meilisearch

## 🛠️ UTILITÀ

meilisearch-ui: ## 🔍 Apre l'interfaccia web di Meilisearch
	@echo "$(MAGENTA)🔍 Apertura Meilisearch UI...$(NC)"
	@echo "$(CYAN)URL:$(NC) http://localhost:7700"
	@open http://localhost:7700

shell-backend: ## 💻 Accedi alla shell del backend
	@echo "$(BLUE)💻 Accesso alla shell del backend...$(NC)"
	@docker exec -it backend bash

shell-frontend: ## 💻 Accedi alla shell del frontend
	@echo "$(BLUE)💻 Accesso alla shell del frontend...$(NC)"
	@docker exec -it frontend sh

shell-nginx: ## 💻 Accedi alla shell di Nginx
	@echo "$(BLUE)💻 Accesso alla shell di Nginx...$(NC)"
	@docker exec -it nginx sh

redis-cli: ## 🔴 Accedi a Redis CLI
	@echo "$(RED)🔴 Accesso a Redis CLI...$(NC)"
	@docker exec -it redis redis-cli

## 🧹 PULIZIA

clean: ## 🧹 Rimuove container, volumi e immagini non utilizzate
	@echo "$(YELLOW)🧹 Pulizia dell'ambiente...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) down -v --remove-orphans
	@docker system prune -f
	@echo "$(GREEN)✅ Pulizia completata$(NC)"

clean-all: ## 💥 Pulizia completa (ATTENZIONE: rimuove anche i volumi!)
	@echo "$(RED)💥 ATTENZIONE: Questa operazione rimuoverà TUTTI i dati!$(NC)"
	@echo "$(RED)Premi CTRL+C per annullare, ENTER per continuare...$(NC)"
	@read
	@docker-compose -f $(COMPOSE_FILE) down -v --remove-orphans
	@docker system prune -af --volumes
	@echo "$(GREEN)✅ Pulizia completa eseguita$(NC)"

## 🔧 SVILUPPO

npm-install: ## 📦 Installa le dipendenze npm del frontend
	@echo "$(BLUE)📦 Installazione dipendenze frontend...$(NC)"
	@docker exec -it frontend npm install

composer-install: ## 📦 Installa le dipendenze Composer del backend
	@echo "$(BLUE)📦 Installazione dipendenze backend...$(NC)"
	@docker exec -it backend composer install

artisan: ## 🎨 Esegui comando Artisan (es: make artisan CMD="migrate")
	@echo "$(MAGENTA)🎨 Esecuzione comando Artisan: $(CMD)$(NC)"
	@docker exec -it backend php artisan $(CMD)

## 🚀 COMANDI DI PRODUZIONE

prod-up: ## 🔥 Avvia ambiente di produzione
	@echo "$(GREEN)🚀 Avvio dell'ambiente di produzione...$(NC)"
	@if [ ! -f $(ENV_FILE_PROD) ]; then \
		echo "$(RED)❌ File $(ENV_FILE_PROD) non trovato!$(NC)"; \
		echo "$(YELLOW)💡 Copia $(ENV_FILE_PROD).example in $(ENV_FILE_PROD) e configura le variabili$(NC)"; \
		exit 1; \
	fi
	@docker-compose -f $(COMPOSE_FILE_PROD) --env-file $(ENV_FILE_PROD) up -d --build
	@echo "$(GREEN)✅ Ambiente di produzione avviato!$(NC)"

prod-down: ## 🛑 Ferma ambiente di produzione
	@echo "$(YELLOW)🛑 Fermando ambiente di produzione...$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) down
	@echo "$(GREEN)✅ Ambiente di produzione fermato$(NC)"

prod-build: ## 🔨 Build immagini produzione
	@echo "$(BLUE)🔨 Build delle immagini di produzione...$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) build --no-cache
	@echo "$(GREEN)✅ Immagini di produzione costruite$(NC)"

prod-logs: ## 📜 Log ambiente produzione
	@echo "$(CYAN)📜 Log dell'ambiente di produzione:$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) logs -f

prod-status: ## 📊 Stato ambiente produzione
	@echo "$(CYAN)📊 Stato dell'ambiente di produzione:$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) ps

prod-deploy: ## 🚀 Deploy completo in produzione
	@echo "$(MAGENTA)🚀 Deploy in produzione...$(NC)"
	@if [ ! -f $(ENV_FILE_PROD) ]; then \
		echo "$(RED)❌ File $(ENV_FILE_PROD) non trovato!$(NC)"; \
		exit 1; \
	fi
	@echo "$(BLUE)1. Stopping existing services...$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) --env-file $(ENV_FILE_PROD) down
	@echo "$(BLUE)2. Building new images...$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) --env-file $(ENV_FILE_PROD) build --no-cache
	@echo "$(BLUE)3. Starting services...$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) --env-file $(ENV_FILE_PROD) up -d
	@echo "$(BLUE)4. Running migrations...$(NC)"
	@sleep 30
	@docker exec spreetzitt-backend-prod php artisan migrate --force
	@docker exec spreetzitt-backend-prod php artisan config:cache
	@docker exec spreetzitt-backend-prod php artisan route:cache
	@docker exec spreetzitt-backend-prod php artisan view:cache
	@echo "$(GREEN)✅ Deploy completato!$(NC)"

prod-rollback: ## 🔄 Rollback rapido
	@echo "$(YELLOW)🔄 Rollback in corso...$(NC)"
	@docker-compose -f $(COMPOSE_FILE_PROD) --env-file $(ENV_FILE_PROD) down
	@docker-compose -f $(COMPOSE_FILE_PROD) --env-file $(ENV_FILE_PROD) up -d
	@echo "$(GREEN)✅ Rollback completato$(NC)"

prod-backup: ## 💾 Backup dati produzione
	@echo "$(BLUE)💾 Backup dei dati di produzione...$(NC)"
	@mkdir -p ./backups/$(shell date +%Y%m%d_%H%M%S)
	@echo "$(YELLOW)⚠️  Database esterno: esegui backup manualmente$(NC)"
	@echo "$(CYAN)💡 Esempio: mysqldump -h \$$DB_HOST -u \$$DB_USERNAME -p \$$DB_DATABASE > ./backups/\$(shell date +%Y%m%d_%H%M%S)/database.sql$(NC)"
	@docker cp spreetzitt-redis-prod:/data ./backups/$(shell date +%Y%m%d_%H%M%S)/redis_data
	@docker cp spreetzitt-meilisearch-prod:/meili_data ./backups/$(shell date +%Y%m%d_%H%M%S)/meilisearch_data
	@echo "$(GREEN)✅ Backup container completato in ./backups/$(shell date +%Y%m%d_%H%M%S)$(NC)"

## 🔒 SICUREZZA

security-scan: ## 🔒 Scansione sicurezza container
	@echo "$(MAGENTA)🔒 Scansione sicurezza...$(NC)"
	@docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:latest image spreetzitt-backend-prod
	@docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:latest image spreetzitt-frontend-prod

ssl-renew: ## 🔐 Rinnova certificati SSL
	@echo "$(CYAN)🔐 Rinnovo certificati SSL...$(NC)"
	@echo "$(YELLOW)Implementa qui il tuo sistema di rinnovo SSL (es: certbot)$(NC)"

test-db: ## 🔍 Testa connessione database esterno
	@echo "$(CYAN)🔍 Test connessione database esterno...$(NC)"
	@./scripts/test-database-connection.sh

# Target di default
.DEFAULT_GOAL := help
