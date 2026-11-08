# 05 · Plan de despliegue — TriTransportes

Plan para llevar TriTransportes a producción con contenedores. La pauta no exige desplegar en la nube; sí exige un plan realista y un entorno que se ejecute en contenedores (`compose.yaml`, ver [README](../README.md)). Los comandos de este documento son los mismos que ejecuta el pipeline (`.github/workflows/ci.yml`).

Fecha: 8 de noviembre de 2026 · Versión 1.0

---

## 1. Entornos

| Entorno | Dónde | Datos | Diferencias de configuración |
|---|---|---|---|
| **Desarrollo** | PC del integrante con Docker Desktop: `docker compose up` | Sintéticos (`seed_masivo.php`) | `APP_ENV=development`; `WEB_PORT=8080`; contraseñas de ejemplo permitidas; sin HTTPS |
| **Pruebas** | (a) GitHub Actions, en cada *push*: base temporal que se descarta. (b) Servidor de demostración `trivoxa.cl/TriTransportes` (Hostinger, PHP 8.3 + MariaDB 11.8, sin Docker) | Sintéticos | `APP_ENV=testing` en CI; secretos generados al azar en cada ejecución; IA simulada en CI y sin clave en la demostración |
| **Producción** | Servidor virtual con Docker (sección 2) | Reales de la empresa | `APP_ENV=production`; HTTPS obligatorio; secretos únicos; respaldo diario con copia externa; clave de IA vigente; ningún puerto de base de datos publicado |

Los tres entornos usan las mismas imágenes: lo único que cambia es el `.env`.

## 2. Infraestructura propuesta

| Recurso | Propuesta | Justificación |
|---|---|---|
| Servidor | VPS Linux (Ubuntu 24.04 LTS) de 2 vCPU, 4 GB de RAM y 40–80 GB de SSD; por ejemplo, Hetzner Cloud CX22/CX23 o equivalente | Los 5 servicios usan menos de 1 GB en reposo; 15.000 viajes al año ocupan menos de 50 MB; el resto es margen para respaldos e imágenes |
| Red | Firewall del proveedor + `ufw`: solo 22 (SSH con llave, sin contraseña), 80 y 443 | La base y PHP-FPM viven en la red interna `backend` de Docker y nunca se publican |
| Proxy TLS | Caddy 2 en el host (o un contenedor adicional) delante de `web:8080` | Certificado de Let's Encrypt automático y renovación sin intervención |
| Dominio | `tritransportes.cl` (NIC Chile) con registros A/AAAA al VPS | Dominio propio de la empresa |
| HTTPS | Let's Encrypt; redirección 80 → 443; HSTS de 1 año | Ya activo en el servidor de demostración |

```
Internet ──443──► Caddy (TLS) ──► web:8080 (nginx) ──► api:9000 (PHP-FPM) ──► db:3306 (MariaDB)
                                                         └──► api.groq.com (red egress)
```

## 3. Pasos de despliegue

**Primera instalación** (una vez):

```bash
# 1. Servidor
adduser deploy && usermod -aG docker deploy           # Docker Engine + plugin compose instalados
ufw allow OpenSSH && ufw allow 80,443/tcp && ufw enable

# 2. Código y configuración
sudo -u deploy git clone https://github.com/<organizacion>/tritransportes.git /opt/tritransportes
cd /opt/tritransportes
cp .env.example .env && chmod 600 .env                 # valores de producción (sección 4) y SEED_DEMO=0

# 3. Arranque: migrate crea el esquema y termina; luego api y web
docker compose pull                                    # imágenes publicadas por el pipeline (GHCR)
docker compose up -d
docker compose ps                                      # db, api y web "healthy"; migrate "exited (0)"

# 4. Verificación
curl -fsS https://tritransportes.cl/TriTransportes/api/v1/health    # {"status":"ok","db":"ok",...}
```

**Cada versión nueva** (después de que el pipeline publique las imágenes):

```bash
cd /opt/tritransportes
git pull origin main                                   # compose.yaml y scripts de la versión
docker compose exec -e BACKUP_UNA_VEZ=1 backup sh /backup.sh   # respaldo previo (ver sección 6)
export IMAGE_PREFIX=ghcr.io/<organizacion>/tritransportes IMAGE_TAG=<sha del commit>
docker compose pull && docker compose up -d            # migrate aplica las migraciones nuevas antes de que arranque api
curl -fsS https://tritransportes.cl/TriTransportes/api/v1/health
```

## 4. Secretos y variables

| Variable | Desarrollo | Pruebas (CI) | Producción |
|---|---|---|---|
| `DB_PASS`, `DB_ROOT_PASSWORD` | Ejemplo del `.env.example` | `openssl rand` en cada ejecución, enmascarado | `openssl rand -hex 24`, solo en `/opt/tritransportes/.env` (permisos 600) |
| `JWT_SECRET` | Ejemplo | Aleatorio por ejecución | 64 caracteres aleatorios; rotarlo cierra todas las sesiones |
| `GROQ_API_KEY` | Vacía (IA responde 503) | Sin clave: se usa el doble `FakeIaClient` | Clave propia del entorno, distinta de la de desarrollo |
| `DEMO_PASSWORD` / `SEED_DEMO` | Ejemplo / `1` | Aleatoria / `1` | Sin uso / `0`: en producción no se generan datos sintéticos ni cuentas demo |

Reglas:
- `.env` nunca entra al repositorio (`.gitignore`) ni a las imágenes (`.dockerignore`).
- En GitHub Actions no se guardan secretos de producción; el despliegue se hace en el servidor.
- **Rotación:** cada 6 meses, al salir un integrante o ante cualquier sospecha de exposición. La clave de Groq del sistema base que quedó expuesta durante el análisis se revoca antes de la entrega.

## 5. Actualización y reversión

- **Estrategia:** cada *push* a `main` que pasa el pipeline publica las imágenes `api` y `web` con dos etiquetas: el *sha* del commit (inmutable) y `latest`. Producción siempre se despliega por *sha*, nunca por `latest`.
- **Migraciones:** cada migración de Phinx tiene `up()` y `down()`; `migrate` aplica solo las pendientes. Las migraciones que borran datos se hacen en dos versiones (primero dejar de usar, después borrar).
- **Reversión de la aplicación:** `IMAGE_TAG=<sha anterior> docker compose up -d` (menos de un minuto).
- **Reversión del esquema**, si la versión trajo migraciones:

```bash
docker compose run --rm migrate vendor/bin/phinx rollback -c phinx.php -t <versión anterior>
IMAGE_TAG=<sha anterior> docker compose up -d
```

- **Reversión probada:**
  - 8 de noviembre de 2026, servidor de demostración: `phinx rollback -t 20261108000000` revirtió las 6 migraciones y `phinx migrate` las volvió a aplicar sin errores (`docs/evidencias/hito2/phinx-status.txt`).
  - El pipeline repite esa prueba en cada ejecución (trabajo `pruebas`).
- **Último recurso:** restaurar el respaldo previo a la actualización (sección 6).

## 6. Respaldo y recuperación

| Aspecto | Valor |
|---|---|
| Qué | Base de datos completa (`mariadb-dump --single-transaction --routines --triggers`) y volumen `uploads` |
| Frecuencia | Diario (servicio `backup`) y antes de cada actualización |
| Retención | 14 días en el volumen `backups` + copia diaria fuera del servidor (almacenamiento de objetos del proveedor, con `rclone`) |
| RPO (pérdida máxima) | 24 horas |
| RTO (tiempo máximo de restauración) | **15 minutos** (RNF07) |

Restauración:

```bash
docker compose stop api web
gunzip < backups/tritransportes_AAAAMMDD_HHMM.sql.gz | docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" tritransportes
docker compose start api web && curl -fsS https://tritransportes.cl/TriTransportes/api/v1/health
```

**Prueba de restauración:** el pipeline ejecuta en cada *push* el procedimiento completo (trabajo `contenedores`):
1. Respalda la base sembrada.
2. La restaura en un contenedor MariaDB vacío.
3. Compara los conteos de viajes, cargas, guías y mantenciones.
4. Mide la duración.

El resultado queda en el registro de la ejecución. Antes de abrir el repositorio en GitHub, el procedimiento se validó en el servidor de demostración con el respaldo `pre_hito2_20261108_210817.sql.gz` (1,2 MB comprimido).

## 7. Monitoreo y logs

| Qué se mide | Cómo | Alerta |
|---|---|---|
| Disponibilidad | Monitor externo (UptimeRobot o similar, plan gratuito) a `/api/v1/health` cada 5 minutos | Correo al equipo si falla 2 veces seguidas |
| Salud de contenedores | `healthcheck` de `db`, `api` y `web`; `restart: unless-stopped` | `docker compose ps` muestra *unhealthy*; el monitor externo detecta la caída |
| Errores 5xx | Cada 500 queda en `logs/api.log` con `request_id`, que el usuario ve en pantalla | Revisión diaria; más de 10 errores por hora → correo (script con `cron`) |
| Tiempos de respuesta | Registro de acceso de nginx (`docker compose logs web`) | p95 > 500 ms en listados (RNF01) |
| Fallas de la IA | Códigos 502, 503 y 504 en `logs/api.log` | Informativa: el sistema sigue funcionando sin IA |
| Respaldos | Registro del servicio `backup` (`respaldo OK` o `ERROR`) | Ausencia del archivo del día → correo |
| Auditoría de negocio | Tabla `auditoria` (cambios y exportaciones con usuario, IP y fecha) | Consulta del administrador |

Dónde se ven: `docker compose logs -f <servicio>`, `logs/api.log` (volumen `logs`) y el panel del monitor externo.

## 8. Seguridad en producción

- **Cabeceras:** CSP, HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y `Permissions-Policy` en nginx (`docker/nginx.conf`), iguales a las del servidor de demostración.
- **CORS:** la API y el front end comparten el mismo origen, así que no se habilita CORS. Cualquier otro origen queda bloqueado por el navegador.
- **Endurecimiento:**
  - PHP-FPM corre como `www-data` y no como root; `expose_php=Off` y `display_errors=Off`.
  - nginx responde 404 a `vendor/`, `src/`, `tests/`, `database/`, `config/` y a los archivos ocultos.
  - La base no tiene puerto publicado, y la red `backend` es interna (sin salida a internet).
  - SSH solo con llave; actualizaciones de seguridad automáticas en el host (`unattended-upgrades`).
- **Aplicación:**
  - JWT de 60 minutos y límite de tasa (5 logins y 120 solicitudes por minuto).
  - Validación en 3 niveles, auditoría de cambios y exportaciones, y mitigaciones del OWASP API Top 10 (informe, sección 6.2).
- **Actualización de imágenes:**
  - Las versiones base están fijadas (`php:8.3.14-fpm-alpine3.20`, `nginx:1.27.3-alpine`, `mariadb:11.8.2`).
  - Se revisan una vez al mes y se suben de *patch* con una PR, que el pipeline prueba completo antes de publicar.
  - `composer audit` revisa las dependencias de PHP.

## 9. Costos y riesgos

Costos mensuales estimados (referencias de noviembre de 2026; precios sin impuestos, a confirmar al contratar):

| Ítem | Costo estimado | Fuente |
|---|---|---|
| VPS 2 vCPU, 4 GB (Hetzner CX22/CX23) | 4,4–4,6 €/mes (≈ USD 5) | Comparativas de precios de Hetzner 2026; el plan CX22 fue reemplazado por CX23, verificar en hetzner.com/cloud |
| Copia externa de respaldos (≤ 10 GB) | ≈ USD 1/mes | Almacenamiento de objetos del mismo proveedor |
| Dominio `.cl` | ≈ $9.940 + IVA al año (≈ USD 1/mes) | Referencia no oficial; tarifa vigente en nic.cl |
| Certificado HTTPS, CI/CD (GitHub Actions), registro de imágenes (GHCR), monitor externo | $0 | Planes gratuitos |
| IA (Groq, Llama 3.3 70B) | $0 en el plan gratuito; con un plan pagado, centavos de dólar por resumen | Uso esperado: menos de 100 resúmenes al mes |
| **Total** | **≈ USD 7–8 al mes** | |

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Exposición de secretos | Media | Alto | `.env` fuera del repositorio y de las imágenes; enmascarado en CI; rotación |
| Migración que daña datos | Baja | Alto | Respaldo previo; `down()` probado en CI; despliegue por *sha* con reversión en un minuto |
| Caída del proveedor de IA o cambio en su plan gratuito | Media | Bajo | Timeout de 20 s, errores 502/503/504 controlados; el resto del sistema sigue funcionando; el proveedor se cambia implementando `IaClient` |
| Pérdida del servidor | Baja | Alto | Copia externa diaria; reinstalación con la sección 3 en menos de 1 hora |
| Disco lleno (respaldos o registros) | Baja | Medio | Retención de 14 días; rotación de registros de Docker (`max-size`) |
| Aumento de precios del proveedor | Media | Bajo | Todo corre en contenedores estándar: se migra a otro proveedor con los mismos comandos |

## 10. Lista de verificación previa a producción

- [ ] Pipeline en verde para el commit que se va a desplegar (pruebas, contenedores y publicación).
- [ ] Migraciones probadas con `rollback` en CI y restauración del respaldo dentro de 15 minutos.
- [ ] `.env` de producción completo, con permisos 600, sin valores de ejemplo y con claves nuevas (incluida la de Groq).
- [ ] Cuentas de demostración no creadas o desactivadas; contraseña nueva para el administrador.
- [ ] HTTPS activo, redirección 80 → 443 y cabeceras verificadas (`curl -I`).
- [ ] Puerto de la base de datos sin publicar (`docker compose ps` no muestra 3306).
- [ ] Respaldo diario funcionando y primera copia externa verificada.
- [ ] Monitor externo configurado sobre `/api/v1/health` con alerta por correo.
- [ ] Firewall con solo 22, 80 y 443, y SSH solo con llave.
- [ ] Prueba de humo: login, listar viajes, programar y completar un viaje, exportar un reporte.
