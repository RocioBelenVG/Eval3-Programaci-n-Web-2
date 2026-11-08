# TriTransportes

Sistema web de gestión de **flota, transporte y combustible** para una empresa de transporte de carga (ficticia: TRITRANSPORTES SpA).
Es el proyecto integrador de la **Evaluación 3 de Lenguaje de Programación Web II**.

> Estado: **Hito 4 — análisis, reportes y plan de despliegue**. Dashboard con 6 indicadores y 4 gráficos accesibles, 3 reportes exportables a Excel, PDF y CSV con vista de impresión, y [plan de despliegue](docs/05_plan_despliegue.md). Las 83 pruebas automatizadas pasan; Lighthouse da accesibilidad 100 en todas las pantallas (evidencia de los hitos [2](docs/evidencias/hito2/), [3](docs/evidencias/hito3/) y [4](docs/evidencias/hito4/)).

## Documentación

| Documento | Contenido |
|---|---|
| Informe técnico (PDF) | `Informe_Tecnico_TriTransportes.pdf`, en la carpeta de entrega: las 11 secciones de la pauta, matriz de cumplimiento y rúbrica autoevaluada |
| [Evidencia del Hito 4](docs/evidencias/hito4/) | Lighthouse, axe, tiempos del dashboard (RNF02), archivos exportados de los 3 reportes y protocolos de NVDA y del ensayo |
| [06 · Manual de usuario](docs/06_manual_usuario.md) | Uso breve por rol: Conductor, Supervisor, Gerencia y Administrador |
| [Diagramas exportados](docs/diagramas/) | Capas, componentes, entidad–relación, estados del viaje y despliegue en SVG (fuentes Mermaid en los documentos 02 y 03) |
| [05 · Plan de despliegue](docs/05_plan_despliegue.md) | Los 10 apartados de la pauta: entornos, infraestructura, pasos, secretos, reversión, respaldo, monitoreo, seguridad, costos y lista de verificación |
| [Evidencia del Hito 3](docs/evidencias/hito3/) | Auditoría axe-core de la interfaz, pruebas de IA y verificación de contenedores |
| [Evidencia del Hito 2](docs/evidencias/hito2/) | Salida de PHPUnit (testdox y JUnit), estado de las migraciones, verificación de la base y prueba de humo de la API |
| [01 · Requerimientos](docs/01_requerimientos.md) | Dominio, roles, requerimientos funcionales y no funcionales, historias de usuario, casos de uso y plan de trabajo |
| [02 · Modelo de datos](docs/02_modelo_datos.md) | Diagrama entidad–relación, diccionario de datos, normalización y respaldo |
| [03 · Arquitectura](docs/03_arquitectura.md) | Capas, componentes, despliegue, OWASP, decisiones (ADR) y SOLID |
| [Contrato OpenAPI](docs/openapi.yaml) | Contrato de la API REST v1 |
| [Plan de adaptación](docs/PLAN_ADAPTACION.md) | Hitos y alcance |

## Stack

PHP 8.3 · MariaDB 11.8 · Bootstrap 5.3 (personalizado) · Chart.js · FPDF · Groq (Llama 3.3) · Docker · GitHub Actions

## Ejecución

### Local con Docker (un solo comando)
```bash
cp .env.example .env      # cambie las contraseñas y JWT_SECRET; GROQ_API_KEY es opcional
docker compose up
```
Abra http://localhost:8080 (redirige a la aplicación en `/TriTransportes/app/`). La primera vez, el servicio `migrate` crea el esquema, genera 12 meses de datos sintéticos (unos minutos) y crea las cuentas de demostración.

| Servicio | Imagen | Función |
|---|---|---|
| `web` | `nginx:1.27.3-alpine` (etapa `web`) | Front end y estáticos; deriva PHP a `api`. Único puerto publicado: 8080 |
| `api` | `php:8.3.14-fpm-alpine3.20` (etapa `api`) | API REST y sistema clásico, usuario `www-data` |
| `db` | `mariadb:11.8.2` | Base de datos en la red interna `backend`, volumen `db-data` |
| `migrate` | etapa `api` | Auxiliar: migraciones, datos sintéticos y cuentas demo; termina al acabar |
| `backup` | `mariadb:11.8.2` | Auxiliar: `mariadb-dump` diario comprimido en el volumen `backups`, 14 días de retención |

Redes: `frontend` (solo `web`), `backend` interna sin salida a internet (`db`, `api`, auxiliares) y `egress` (solo `api`, para la IA). Todos los servicios de larga vida tienen *health check* y `restart: unless-stopped`.

### Entorno de demostración
https://trivoxa.cl/TriTransportes (Apache + PHP + MariaDB en Hostinger, configurado mediante `.env`). La aplicación nueva está en https://trivoxa.cl/TriTransportes/app/ y el contrato en https://trivoxa.cl/TriTransportes/api/docs/.

### Instalación manual (hosting PHP)
```bash
cp .env.example .env                      # completar base de datos, JWT_SECRET y DEMO_PASSWORD
composer install
vendor/bin/phinx migrate -c phinx.php     # crea el esquema (base vacía) y aplica las migraciones
php database/seed_masivo.php --purgar     # 12 meses de datos sintéticos
vendor/bin/phinx seed:run -c phinx.php    # cuentas de demostración por rol
```

## Interfaz web

Aplicación de una página en `app/` (HTML, CSS y JavaScript nativo, sin frameworks) sobre el sistema de diseño `assets/css/tt-design.css`. Consume solo la API REST con JWT.

- Menú y acciones según el rol; vistas de 403 y 404; estados de carga, vacíos y de error.
- Listado de viajes con búsqueda, filtros, orden por columna y paginación; ficha con la máquina de estados; formularios con validación inmediata y resumen de errores enlazado a cada campo.
- Responsive de 360 a 1440 px sin desplazamiento horizontal; tablas apiladas en celular; menú colapsable que se cierra con Esc.
- Accesibilidad WCAG 2.2 AA: enlace para saltar al contenido, foco visible y gestionado en cada cambio de vista, anuncios con `aria-live` y modales con `<dialog>`. axe-core no encuentra infracciones en ninguna vista ([evidencia](docs/evidencias/hito3/)).

## Dashboard y reportes

- **Dashboard** (`#/dashboard`, Administrador, Supervisor y Gerencia): 6 indicadores con fórmula, numerador, denominador y variación frente al período anterior; lectura del período en texto; 4 gráficos (viajes por mes, costo por viaje, rendimiento por camión y viajes por estado), cada uno con descripción y tabla de datos; filtros por fecha, obra y vehículo. Endpoints `GET /dashboard/kpis` y `GET /dashboard/series`.
- **Reportes** (`#/reportes`): *Detalle de viajes* (operativo), *Resumen gerencial mensual* (con gráfico y totales; Administrador y Gerencia) y *Combustible por vehículo* (parametrizable por fechas).
- **Exportación** (`GET /reportes/{reporte}/export?formato=xlsx|pdf|csv`), con los filtros aplicados:
  - Excel: encabezados, fechas y números con formato, y fila de totales con fórmulas.
  - PDF: título, fecha de emisión, filtros, numeración y texto seleccionable.
  - CSV: UTF-8 con BOM, separador `;` y coma decimal.
  - Cada exportación queda en `auditoria` con usuario, formato y filtros.
- **Impresión:** botón *Imprimir* con `@media print`, que deja fuera menús y botones.

## Integración de IA

`POST /api/v1/ia/resumen` genera un resumen gerencial del período con Groq (Llama 3.3 70B).
- La clave se lee solo de `GROQ_API_KEY` (variable de entorno). Si falta, el endpoint responde 503 y el resto del sistema sigue funcionando.
- Entrada validada (fechas, período de 366 días como máximo y enfoque) y límite de 10 solicitudes por minuto por usuario.
- Tiempo máximo de espera `AI_TIMEOUT` (20 s). Errores del proveedor: 504 por tiempo, 502 por error o respuesta inválida y 503 por límite, siempre con el esquema de error estándar.
- Al modelo solo le llegan totales del período y del anterior, nunca datos personales. Su respuesta se valida y se muestra como texto.
- Pruebas con el servicio simulado (`tests/Support/FakeIaClient.php`, `tests/Integration/IaTest.php`).

## CI/CD

`.github/workflows/ci.yml` corre en cada *push* y *pull request*:
1. **Pruebas:** PHPUnit (unitarias y de integración) contra MariaDB 11.8, con migraciones, datos sintéticos y la prueba de `rollback`.
2. **Contenedores:** construye las imágenes (la etapa `test` del Dockerfile vuelve a correr las pruebas unitarias), ejecuta `docker compose up` y hace una prueba de humo por HTTP.
3. **Publicación:** solo en `main` y solo si las etapas anteriores pasan, sube las imágenes `api` y `web` a GitHub Container Registry.

Los secretos de prueba se generan en cada ejecución y se enmascaran en los registros.

## API REST

- Base: `https://trivoxa.cl/TriTransportes/api/v1` · contrato en [docs/openapi.yaml](docs/openapi.yaml).
- `POST /auth/login` entrega un JWT (HS256, 1 hora). El resto de las rutas exige `Authorization: Bearer <token>`.
- Roles: **Administrador**, **Supervisor**, **Conductor** (solo ve y opera sus viajes) y **Gerencia** (solo lectura).
- Errores con un esquema único `{ "error": { "code", "message", "details", "request_id" } }` y códigos 400, 401, 403, 404, 405, 409, 422, 429 y 500.
- Límite de tasa: 5 intentos de login por minuto (IP + correo) y 120 solicitudes por minuto por usuario.
- Prueba de humo: `DEMO_PASSWORD=... bash tools/smoke_api.sh`.

| Capa | Carpeta | Responsabilidad |
|---|---|---|
| Entrada | `api/v1/index.php`, `src/Kernel.php`, `src/Http/` | Ruta, JWT, límite de tasa, rol y formato de error |
| Controladores | `src/Controller/` | Validan la entrada y responden JSON |
| Servicios | `src/Service/` | Reglas de negocio, transacciones y auditoría |
| Dominio | `src/Domain/` | Roles y máquinas de estado (viaje y mantención) |
| Repositorios | `src/Repository/` | SQL con sentencias preparadas, siempre filtrado por empresa |
| Composición | `src/App.php`, `src/Container.php` | Inyección de dependencias |

## Base de datos y migraciones

`database/migrations/` contiene 7 migraciones Phinx con `up()` y `down()`. Revertirlas todas y volver a aplicarlas se probó en el servidor el 8 de noviembre de 2026.

| Migración | Cambio |
|---|---|
| `20261108000000_base` | Esquema heredado (solo se ejecuta en una base vacía) |
| `20261108000100_checks_viajes` | CHECK de kilometraje, horas y cantidad |
| `20261108000200_checks_combustible` | CHECK de litros (600 L camión / 1.500 L equipo), precio y horómetro; triggers de exclusividad vehículo/equipo |
| `20261108000300_checks_flota` | CHECK de año y patente chilena; costo de mantención no negativo |
| `20261108000400_indices` | Índices `(empresa_id, estado, fecha)` y `(vehiculo_id, fecha)`; número de guía único por empresa |
| `20261108000500_vistas_reportes` | Vistas `v_viajes_detalle`, `v_rendimiento_mensual` y `v_kpi_mensual` |
| `20261108000600_rate_limits` | Tabla del límite de tasa |

## Pruebas

```bash
vendor/bin/phpunit --testdox                 # 63 pruebas: 32 unitarias y 31 de integración
vendor/bin/phpunit --testsuite Unit          # sin base de datos
```

Las pruebas de integración recorren la API completa contra MariaDB. Cada caso corre dentro de una transacción que se revierte al terminar, así que no dejan datos.

## Datos de prueba

Todos los datos son **sintéticos**: los genera `database/seed_masivo.php` con una semilla fija y simulan 12 meses de operación (más de 14.000 viajes). No contienen datos personales reales. El seeder `UsuariosDemoSeeder` crea una cuenta por rol (`supervisor@`, `conductor@` y `gerencia@tritransportes.cl`; el administrador es `admin@tritransportes.cl`). La contraseña sale de `DEMO_PASSWORD` en el `.env` del servidor y no se versiona.

## Seguridad

- Los secretos (base de datos, JWT, API de IA) se leen **solo** desde variables de entorno o desde `.env`, que **no** se versiona.
- `.htaccess` bloquea el acceso web a `.env`, `.git`, `config/`, `vendor/`, `src/`, `tests/`, `database/` y los scripts SQL (responden 403).
- Contraseñas con bcrypt, JWT con firma verificada en tiempo constante y rechazo de `alg: none`, SQL siempre preparado y orden de columnas en lista blanca.
- Toda creación, modificación, cambio de estado y eliminación queda en `auditoria` con los datos antes y después.

## Uso de herramientas de IA

| Herramienta | Propósito |
|---|---|
| Claude (Anthropic) | Análisis de la pauta, adaptación del sistema base, redacción de la documentación técnica y apoyo en la programación. Todo el código y los documentos son revisados por el equipo, que puede explicarlos y modificarlos en vivo. |
| Groq · Llama 3.3 70B | Servicio de IA integrado al sistema (resumen gerencial y asistente). |

## Equipo

| Integrante | Rol en el proyecto |
|---|---|
| _(completar)_ | |
