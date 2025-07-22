#!/bin/bash

# Script para ejecutar tests con la configuración correcta
cd "$(dirname "$0")"

# Asegurar que usamos SQLite para tests
export DB_CONNECTION=sqlite
export DB_DATABASE=:memory:
export APP_ENV=testing

# Solo limpiar config
php artisan config:clear

# Ejecutar tests
if [ $# -eq 0 ]; then
    echo "Ejecutando todos los tests..."
    php artisan test
else
    echo "Ejecutando tests con filtro: $1"
    php artisan test --filter="$1"
fi
