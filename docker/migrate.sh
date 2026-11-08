#!/bin/sh
# Servicio auxiliar "migrate": deja la base lista y termina (se ejecuta en cada docker compose up).
#   1. Aplica las migraciones pendientes (en una base vacía, la migración base crea el esquema).
#   2. Si hay menos de 500 viajes, genera los 12 meses de datos sintéticos.
#   3. Crea o actualiza las cuentas de demostración por rol (contraseña desde DEMO_PASSWORD).
set -eu
cd /var/www/html/TriTransportes

echo "→ Migraciones"
vendor/bin/phinx migrate -c phinx.php

VIAJES=$(php -r 'define("ROOT", getcwd()); require "config/config.php"; require "config/database.php"; echo DB::count("SELECT COUNT(*) FROM viajes");')
echo "→ Viajes existentes: $VIAJES"
if [ "$VIAJES" -lt 500 ]; then
  echo "→ Generando datos sintéticos (12 meses)"
  php database/seed_masivo.php --purgar
fi

echo "→ Cuentas de demostración por rol"
vendor/bin/phinx seed:run -c phinx.php -s UsuariosDemoSeeder
echo "✓ Base lista"
