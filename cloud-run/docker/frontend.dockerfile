# 🐳 Spreetzitt Frontend - Production Dockerfile
# Multi-stage build per React/Vite ottimizzato

# =============================================================================
# BUILDER STAGE - Build React app con Vite
# =============================================================================
FROM node:18-alpine AS builder

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
FROM nginx:alpine AS runtime

# Installa wget per test di connectivity
RUN apk add --no-cache wget

# Rimuovi configurazione nginx default
RUN rm /etc/nginx/conf.d/default.conf

# Copia build da builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Verifica che i file siano stati copiati correttamente
RUN ls -la /usr/share/nginx/html && \
    test -f /usr/share/nginx/html/index.html || (echo "ERROR: index.html non trovato!" && exit 1)

# Copia configurazione nginx ottimizzata per SPA
COPY nginx/nginx-frontend.conf /etc/nginx/conf.d/default.conf

# Copia configurazione nginx principale
COPY nginx/nginx-main.conf /etc/nginx/nginx.conf

# Crea directory per logs
RUN mkdir -p /var/log/nginx

# Imposta permessi
RUN chown -R nginx:nginx /usr/share/nginx/html \
    && chmod -R 755 /usr/share/nginx/html

# Esponi porta 8080 (richiesto da Cloud Run)
EXPOSE 8080

# Test configurazione nginx
RUN nginx -t

# Avvia nginx
CMD ["nginx", "-g", "daemon off;"]

# Labels per metadata
LABEL maintainer="spreetzitt-team"
LABEL version="1.0"
LABEL description="Spreetzitt React Frontend for Google Cloud Run"
