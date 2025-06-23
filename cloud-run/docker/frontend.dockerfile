# 🐳 Spreetzitt Frontend - Production Dockerfile
# Multi-stage build per React/Vite ottimizzato

# =============================================================================
# BUILDER STAGE - Build React app con Vite
# =============================================================================
FROM node:18-alpine as builder

# Installa pnpm globalmente
RUN npm install -g pnpm

# Imposta directory di lavoro
WORKDIR /app

# Copia package files
COPY package.json pnpm-lock.yaml ./

# Installa dipendenze
RUN pnpm install --frozen-lockfile

# Copia codice sorgente
COPY . .

# Build production
RUN pnpm build

# =============================================================================
# RUNTIME STAGE - Nginx ottimizzato per SPA
# =============================================================================
FROM nginx:alpine as runtime

# Rimuovi configurazione nginx default
RUN rm /etc/nginx/conf.d/default.conf

# Copia build da builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Copia configurazione nginx ottimizzata per SPA
COPY ../cloud-run/docker/nginx-frontend.conf /etc/nginx/conf.d/default.conf

# Copia configurazione nginx principale
COPY ../cloud-run/docker/nginx-main.conf /etc/nginx/nginx.conf

# Crea directory per logs
RUN mkdir -p /var/log/nginx

# Imposta permessi
RUN chown -R nginx:nginx /usr/share/nginx/html \
    && chmod -R 755 /usr/share/nginx/html

# Esponi porta 8080 (richiesto da Cloud Run)
EXPOSE 8080

# Configura nginx per Cloud Run
RUN sed -i 's/listen 80;/listen 8080;/' /etc/nginx/conf.d/default.conf

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Avvia nginx
CMD ["nginx", "-g", "daemon off;"]

# Labels per metadata
LABEL maintainer="spreetzitt-team"
LABEL version="1.0"
LABEL description="Spreetzitt React Frontend for Google Cloud Run"
