#!/bin/sh
# Servicio auxiliar "migrate": deja la base lista y termina (se ejecuta en cada docker compose up).
#   1. Aplica las migraciones pendientes (en una base vacía, la migración base crea el esquema).
#   2. Solo si SEED_DEMO=1 (por defecto): con menos de 500 viajes genera 12 meses de datos sintéticos
#      y crea o actualiza las cuentas de demostración por rol (contraseña desde DEMO_PASSWORD).
#      En producción se usa SEED_DEMO=0: solo migraciones.
set -eu
cd /var/www/html/TriTransportes

echo "→ Migraciones"
vendor/bin/phinx migrate -c phinx.php

if [ "${SEED_DEMO:-1}" != "1" ]; then
  echo "✓ Base lista (SEED_DEMO=0: sin datos ni cuentas de demostración)"
  exit 0
fi

VIAJES=$(php -r 'define("ROOT", getcwd()); require "config/config.php"; require "config/database.php"; echo DB::count("SELECT COUNT(*) FROM viajes");')
echo "→ Viajes existentes: $VIAJES"
if [ "$VIAJES" -lt 500 ]; then
  echo "→ Generando datos sintéticos (12 meses)"
  php database/seed_masivo.php --purgar
fi

echo "→ Cuentas de demostración por rol"
vendor/bin/phinx seed:run -c phinx.php -s UsuariosDemoSeeder
echo "✓ Base lista"
