#!/bin/sh
# Servicio auxiliar "backup": respaldo comprimido al iniciar y luego cada BACKUP_INTERVAL segundos
# (por defecto, diario), con retención de BACKUP_RETENTION_DAYS días (por defecto, 14).
# Respaldo puntual:  docker compose exec -e BACKUP_UNA_VEZ=1 backup sh /backup.sh
# Restaurar:  gunzip < /backups/tritransportes_AAAAMMDD_HHMM.sql.gz | mariadb -h db -u root -p tritransportes
set -eu
INTERVALO="${BACKUP_INTERVAL:-86400}"
RETENCION="${BACKUP_RETENTION_DAYS:-14}"
while true; do
  ARCHIVO="/backups/tritransportes_$(date +%Y%m%d_%H%M).sql.gz"
  if mariadb-dump --single-transaction --routines --triggers -h db -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" | gzip > "$ARCHIVO.tmp"; then
    mv "$ARCHIVO.tmp" "$ARCHIVO"
    echo "$(date -Iseconds) respaldo OK: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"
  else
    rm -f "$ARCHIVO.tmp"
    echo "$(date -Iseconds) ERROR: falló el respaldo" >&2
  fi
  find /backups -name 'tritransportes_*.sql.gz' -mtime +"$RETENCION" -delete
  [ "${BACKUP_UNA_VEZ:-0}" = "1" ] && exit 0   # respaldo puntual (antes de actualizar o en CI)
  sleep "$INTERVALO"
done
