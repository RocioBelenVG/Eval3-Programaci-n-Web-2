# TriTransportes

Sistema web de gestión de **flota, transporte y combustible** para una empresa de transporte de carga (ficticia: TRITRANSPORTES SpA).
Es el proyecto integrador de la **Evaluación 3 de Lenguaje de Programación Web II**.

> Estado: **Hito 1 — propuesta y diseño**. El sistema base funciona en el entorno de demostración. La API REST con JWT, la arquitectura en capas, Docker y CI/CD se incorporan en los hitos 2 y 3 (ver [plan de adaptación](docs/PLAN_ADAPTACION.md)).

## Documentación

| Documento | Contenido |
|---|---|
| [Informe técnico](https://claude.ai/artifact/SASKfHSE1JgLPWnPj7p1JS) | Informe con las 11 secciones de la pauta, indicadores reales, matriz de cumplimiento y rúbrica autoevaluada (se exporta a PDF para la entrega) |
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
1. Copiar `.env.example` a `.env` y completar los datos de la base de datos.
2. Importar `database/INSTALL.sql` y luego `database/m07_firma_gps.sql`.
3. Generar los datos sintéticos (12 meses): `php database/seed_masivo.php --purgar`.

## Datos de prueba

Todos los datos son **sintéticos**: los genera `database/seed_masivo.php` con una semilla fija y simulan 12 meses de operación (más de 14.000 viajes). No contienen datos personales reales.

## Seguridad

- Los secretos (base de datos, JWT, API de IA) se leen **solo** desde variables de entorno o desde `.env`, que **no** se versiona.
- `.htaccess` bloquea el acceso web a `.env`, `.git`, `config/` y los scripts SQL.

## Uso de herramientas de IA

| Herramienta | Propósito |
|---|---|
| Claude (Anthropic) | Análisis de la pauta, adaptación del sistema base, redacción de la documentación técnica y apoyo en la programación. Todo el código y los documentos son revisados por el equipo, que puede explicarlos y modificarlos en vivo. |
| Groq · Llama 3.3 70B | Servicio de IA integrado al sistema (resumen gerencial y asistente). |

## Equipo

| Integrante | Rol en el proyecto |
|---|---|
| _(completar)_ | |
