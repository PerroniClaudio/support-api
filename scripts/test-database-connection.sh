#!/bin/bash

# Spreetzitt Database Connection Test Script
# Test della connessione al database esterno

echo "🔍 Testing external database connection..."

# Load environment variables
if [ -f ../cloud-run/config/.env.prod ]; then
    export $(cat ../cloud-run/config/.env.prod | grep -v '#' | awk '/=/ {print $1}')
else
    echo "❌ .env.prod file not found!"
    exit 1
fi

# Test database connection using Laravel artisan
echo "📡 Testing database connection..."
docker exec spreetzitt-backend-prod php artisan tinker --execute="
try {
    \$pdo = DB::connection()->getPdo();
    echo '✅ Database connection successful!' . PHP_EOL;
    echo 'Database: ' . \$pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
    echo 'Version: ' . \$pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ Database connection failed: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    echo "✅ All database tests passed!"
else
    echo "❌ Database tests failed!"
    exit 1
fi
