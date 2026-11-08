# TriTransportes

Sistema web de gestión de **flota, transporte y combustible** para una empresa de transporte de carga (ficticia: TRITRANSPORTES SpA).
Es el proyecto integrador de la **Evaluación 3 de Lenguaje de Programación Web II**.

> Estado: **Hito 2 — datos y backend**. Migraciones versionadas con Phinx, API REST `/api/v1` con JWT y cuatro roles, máquinas de estado y 63 pruebas automatizadas que pasan ([evidencia](docs/evidencias/hito2/)). La interfaz conectada a la API, Docker y CI/CD se incorporan en el Hito 3 (ver [plan de adaptación](docs/PLAN_ADAPTACION.md)).

## Documentación

| Documento | Contenido |
|---|---|
| Informe técnico (PDF) | `Informe_Tecnico_TriTransportes.pdf`, en la carpeta de entrega: las 11 secciones de la pauta, matriz de cumplimiento y rúbrica autoevaluada |
| [Evidencia del Hito 2](docs/evidencias/hito2/) | Salida de PHPUnit (testdox y JUnit), estado de las migraciones, verificación de la base y prueba de humo de la API |
| [01 · Requerimientos](docs/01_requerimientos.md) | Dominio, roles, requerimientos funcionales y no funcionales, historias de usuario, casos de uso y plan de trabajo |
| [02 · Modelo de datos](docs/02_modelo_datos.md) | Diagrama entidad–relación, diccionario de datos, normalización y respaldo |
| [03 · Arquitectura](docs/03_arquitectura.md) | Capas, componentes, despliegue, OWASP, decisiones (ADR) y SOLID |
| [Contrato OpenAPI](docs/openapi.yaml) | Contrato de la API REST v1 |
| [Plan de adaptación](docs/PLAN_ADAPTACION.md) | Hitos y alcance |

## Stack

PHP 8.3 · MariaDB 11.8 · Bootstrap 5.3 (personalizado) · Chart.js · FPDF · Groq (Llama 3.3) · Docker · GitHub Actions

## Ejecución

### Entorno de demostración
https://trivoxa.cl/TriTransportes (Apache + PHP + MariaDB, configurado mediante `.env`).

### Local con Docker *(se incorpora en el Hito 3)*
```bash
cp .env.example .env      # completar los valores
docker compose up
```

### Instalación manual (hosting PHP)
```bash
cp .env.example .env                      # completar base de datos, JWT_SECRET y DEMO_PASSWORD
composer install
vendor/bin/phinx migrate -c phinx.php     # crea el esquema (base vacía) y aplica las migraciones
php database/seed_masivo.php --purgar     # 12 meses de datos sintéticos
vendor/bin/phinx seed:run -c phinx.php    # cuentas de demostración por rol
```

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
