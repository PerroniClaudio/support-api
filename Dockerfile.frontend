# Dockerfile per applicazione React con Vite (Pre-produzione)
# Mantiene Vite attivo per l'ambiente di pre-produzione

FROM node:18-alpine

# Imposta la directory di lavoro
WORKDIR /app

# Copia i file di configurazione delle dipendenze
COPY frontend/package.json frontend/pnpm-lock.yaml ./

# Installa pnpm globalmente
RUN npm install -g pnpm

# Installa le dipendenze
RUN pnpm install --frozen-lockfile

# Copia il codice sorgente
COPY frontend/ .

# Esponi la porta di Vite (5173)
EXPOSE 5173

# Avvia Vite in modalità preview/development
CMD ["pnpm", "run", "dev", "--host", "0.0.0.0"]