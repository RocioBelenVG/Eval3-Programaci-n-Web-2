# Primera ejecución del pipeline CI/CD en GitHub Actions

- Ejecución: https://github.com/RocioBelenVG/Eval3-Programaci-n-Web-2/actions/runs/42544454504
- Commit: `d9d2e80` (rama main), evento `push`
- Inicio: 2026-11-09T01:13:57Z · Última actualización: 2026-11-09T01:17:56Z (UTC)
- Resultado: **success**

## Pruebas (PHPUnit): success (39 s)

| Paso | Resultado | Duración |
|---|---|---|
| Set up job | success | 1 s |
| Initialize containers | success | 18 s |
| Run actions/checkout@v4 | success | 1 s |
| Run shivammathur/setup-php@v2 | success | 3 s |
| Dependencias | success | 2 s |
| Archivo .env de prueba (secretos generados y enmascarados) | success | 0 s |
| Migraciones y datos sintéticos | success | 7 s |
| Reversibilidad de las migraciones | success | 0 s |
| PHPUnit | success | 3 s |
| Reporte de pruebas | success | 1 s |
| Post Run actions/checkout@v4 | success | 0 s |
| Stop containers | success | 0 s |
| Complete job | success | 0 s |

## Contenedores (build + compose + humo): success (110 s)

| Paso | Resultado | Duración |
|---|---|---|
| Set up job | success | 0 s |
| Run actions/checkout@v4 | success | 1 s |
| .env para compose (secretos generados y enmascarados) | success | 0 s |
| Construir imágenes (incluye la etapa de pruebas del Dockerfile) | success | 54 s |
| docker compose up | success | 30 s |
| Prueba de humo por HTTP | success | 0 s |
| Prueba de respaldo y restauración (RNF07, menos de 15 minutos) | success | 9 s |
| Reversión de migraciones dentro de los contenedores | success | 2 s |
| Registros si algo falló | skipped | 0 s |
| Bajar el entorno | success | 11 s |
| Post Run actions/checkout@v4 | success | 0 s |
| Complete job | success | 0 s |

## Publicar imágenes (GHCR): success (83 s)

| Paso | Resultado | Duración |
|---|---|---|
| Set up job | success | 2 s |
| Run actions/checkout@v4 | success | 2 s |
| Run docker/setup-buildx-action@v3 | success | 5 s |
| Run docker/login-action@v3 | success | 0 s |
| Nombre de las imágenes en minúsculas | success | 0 s |
| Run docker/build-push-action@v6 | success | 58 s |
| Run docker/build-push-action@v6 | success | 7 s |
| Post Run docker/build-push-action@v6 | success | 2 s |
| Post Run docker/build-push-action@v6 | success | 1 s |
| Post Run docker/login-action@v3 | success | 0 s |
| Post Run docker/setup-buildx-action@v3 | success | 3 s |
| Post Run actions/checkout@v4 | success | 1 s |
| Complete job | success | 0 s |

Artefactos de la ejecución: `phpunit-junit` (reporte JUnit) y los registros de construcción de las imágenes. Datos obtenidos de la API pública de GitHub (`/actions/runs/{id}/jobs`).
