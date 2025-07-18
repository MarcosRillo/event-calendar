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

echo "Inicialización completada. Iniciando PHP-FPM..."

# Iniciar PHP-FPM
exec php-fpm
