# Spreetzitt Development Makefile
# Comandi semplificati per gestire l'ambiente di sviluppo

.PHONY: help up down build restart logs frontend-logs backend-logs nginx-logs redis-logs meilisearch-logs meilisearch-ui status clean

# Configurazione
COMPOSE_FILE := docker-compose.dev.yml
ENV_FILE := .env

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

# Target di default
.DEFAULT_GOAL := help
