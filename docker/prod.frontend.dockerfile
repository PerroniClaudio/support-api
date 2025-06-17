# Multi-stage Dockerfile per applicazione React con Vite (Produzione)
# Stage 1: Build dell'applicazione
FROM node:18-alpine AS builder

# Imposta la directory di lavoro
WORKDIR /app

# Copia i file di configurazione delle dipendenze
COPY frontend/package.json frontend/pnpm-lock.yaml ./

# Installa pnpm globalmente
RUN npm install -g pnpm

# Installa le dipendenze
RUN pnpm install --frozen-lockfile

# Copia il codice sorgente e i file di configurazione
COPY frontend/ .

# Copia il file delle variabili d'ambiente
COPY frontend/.env.local .env.local

# Build dell'applicazione per la produzione
RUN pnpm run build

# Stage 2: Server per servire l'applicazione
FROM node:18-alpine

# Installa serve globalmente per servire i file statici
RUN npm install -g serve

# Copia i file buildati dal primo stage
COPY --from=builder /app/dist /app

# Imposta la directory di lavoro
WORKDIR /app

# Esponi la porta 3000 (porta di default di serve)
EXPOSE 3000

# Avvia serve per servire i file statici
CMD ["serve", "-s", ".", "-l", "3000"]