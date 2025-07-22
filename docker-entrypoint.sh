#!/bin/bash

# Esperar a que MySQL esté disponible
echo "Esperando a que MySQL esté disponible..."
while ! php artisan migrate:status 2>/dev/null; do
    echo "MySQL no está listo aún, esperando..."
    sleep 2
done

echo "MySQL está disponible. Ejecutando migraciones..."

# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders si es necesario
if [ ! -f "/var/www/html/storage/.seeded" ]; then
    echo "Ejecutando seeders..."
    php artisan db:seed --force
    touch /var/www/html/storage/.seeded
fi

echo "Inicialización completada."

# Generar clave de aplicación si no existe
if [ ! -f "/var/www/html/.env" ] || ! grep -q "APP_KEY=" /var/www/html/.env || [ -z "$(grep "APP_KEY=" /var/www/html/.env | cut -d'=' -f2)" ]; then
    echo "Generando clave de aplicación..."
    php artisan key:generate --force
fi

# Limpiar caché
php artisan config:cache
php artisan route:cache

echo "Iniciando Supervisor (incluye queue workers y PHP-FPM)..."

# Iniciar Supervisor que manejará tanto PHP-FPM como los queue workers
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
