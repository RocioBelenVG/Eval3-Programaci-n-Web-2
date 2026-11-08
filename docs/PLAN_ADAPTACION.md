# TriTransportes — Plan de adaptación para la Evaluación 3 (LPW II)

TriTransportes parte de un sistema base propio de gestión operacional, recortado al dominio de **flota, transporte y combustible** y refactorizado para cumplir la rúbrica de la Evaluación 3. Este origen se declara al profesor en el Hito 1.

## 1. Alcance acotado (producto mínimo viable)

| Se mantiene | Se congela/retira del alcance evaluado |
|---|---|
| Transporte (viajes, guías de despacho) | Áridos, contratos, documentos, seguridad, faena |
| Combustible (cargas, rendimiento) | Firma y GPS en terreno, PWA offline |
| Maquinaria/flota (vehículos, equipos, mantenciones, horómetros) | Multiempresa visible (queda 1 empresa ficticia) |
| Administración (usuarios, roles, auditoría) | |

**Entidades principales** (6 o más): `vehiculos`, `conductores`, `viajes`, `guias_despacho`, `cargas_combustible`, `mantenciones`, `clientes`, `obras`, `usuarios`, `roles`, `auditoria`.

**Roles evaluados** (3 o más, con permisos distintos):
- **Administrador:** usuarios, maestros y auditoría.
- **Supervisor de operaciones:** programa y aprueba viajes, registra mantenciones y ve reportes.
- **Conductor:** ve sus viajes, los inicia y cierra, y registra cargas de combustible.
- **Gerencia:** solo lectura, con dashboard y reportes gerenciales.

**Flujo con estados** (máquina de estados con transiciones válidas):
`programado → en_ruta → completado` y `programado|en_ruta → cancelado`. Una transición inválida devuelve **409 Conflict**.
Mantenciones: `pendiente → en_proceso → completada`.

## 2. Arquitectura objetivo

```
Presentación (HTML server-side + JS fetch)   →  /modules, /assets
API REST JSON + JWT                          →  /api/v1  (front controller + router)
Servicios de negocio                         →  /src/Service   (reglas, estados)
Acceso a datos (Repository + PDO)            →  /src/Repository
Dominio                                      →  /src/Domain    (entidades, enums de estado)
```

- **Patrones:** Repository (obligatorio), State para los estados del viaje, y un contenedor simple de inyección de dependencias.
- **SOLID:** los servicios dependen de interfaces de repositorio, y los controladores no tienen SQL.
- **Errores:** formato estándar `{ "error": { "code", "message", "details" } }` con un manejador central.
- **Seguridad (OWASP API Top 10):** BOLA (alcance por dueño y empresa), autenticación rota (JWT con expiración), exposición de datos (serializadores), límite de tasa, BFLA (permisos por rol), mala configuración (cabeceras, CORS) e inyección (PDO preparado).

## 3. Stack y herramientas

| Necesidad | Herramienta |
|---|---|
| Dependencias | Composer |
| JWT | firebase/php-jwt |
| Migraciones | Phinx |
| Pruebas | PHPUnit (unitarias de servicios e integración de endpoints) |
| Contrato | OpenAPI 3 (`docs/openapi.yaml`) + Swagger UI en `/api/docs` |
| Gráficos | Chart.js |
| Exportación | Exporter XLSX/CSV propio + FPDF (ya existen) |
| IA | Groq (Llama 3.3), endpoint `POST /api/v1/ia/resumen-flota` |
| Contenedores | Dockerfile multi-stage (php-fpm-alpine + nginx) + compose (app, db, phpmyadmin) |
| CI/CD | GitHub Actions: build → test → publicar imagen solo si las pruebas pasan |

> El hosting (Hostinger) no ejecuta Docker: sirve como **entorno de demostración/staging**. La entrega exige además `docker compose up` local, que se agrega en el Hito 3.

## 4. Hitos

### Hito 1 — Semana 11: propuesta y diseño
- [ ] Presentar el dominio al profesor, declarando que se parte de un sistema base propio que se refactoriza.
- [ ] Requerimientos funcionales y no funcionales, y historias de usuario por rol.
- [ ] Diagrama entidad–relación y diccionario de datos del alcance recortado.
- [ ] Contrato OpenAPI (antes de implementar).
- [ ] Maquetas y guía de estilos.
- [ ] Repositorio en GitHub con los integrantes del equipo.

### Hito 2 — Semana 12: datos y backend
- [x] Rebranding de los datos a una empresa ficticia; el seed sintético conserva 12 meses de historia.
- [x] Migraciones Phinx con `CHECK`, `UNIQUE`, índices, triggers y vistas para reportes (7 migraciones, reversibles).
- [x] `/src` con Domain, Repository, Service, contenedor de inyección y API `/api/v1` con JWT.
- [x] Máquina de estados de viajes y mantenciones.
- [x] Pruebas PHPUnit (63) y límite de tasa. Evidencia en `docs/evidencias/hito2/`.

### Hito 3 — Semana 13: front end, contenedores e IA
- [x] Listados y formularios consumiendo la API (búsqueda, filtros, orden y paginación).
- [x] Sistema de diseño propio y accesibilidad WCAG 2.2 AA (foco visible, enlace para saltar al contenido, errores anunciados); axe-core sin infracciones.
- [x] Endpoint de IA con valor para el dominio y pruebas con mock.
- [x] Docker + compose + GitHub Actions (archivos listos; primera ejecución al publicar el repositorio en GitHub).

### Hito 4 — Semana 14: análisis, reportes y despliegue
- [x] 6 indicadores con fórmula y 4 o más gráficos con filtros y tabla accesible.
- [x] 3 reportes (operativo, gerencial y parametrizable) en XLSX/PDF/CSV y vista de impresión.
- [x] Plan de despliegue (10 apartados), Lighthouse y axe. Pendiente: prueba con NVDA (manual, `docs/evidencias/hito4/prueba-teclado-nvda.md`).
- [ ] Ensayo cronometrado (guion en `docs/evidencias/hito4/ensayo-presentacion.md`).

### Semana 15 — entrega y defensa

## 5. Indicadores propuestos

| Indicador | Fórmula | Pregunta de negocio |
|---|---|---|
| Rendimiento de combustible | km recorridos / litros cargados | ¿Qué vehículos consumen de más? |
| Costo de combustible por viaje | Σ costo combustible / n.º viajes | ¿Cuánto cuesta mover cada viaje? |
| Cumplimiento de viajes | completados / programados | ¿Se cumple la planificación? |
| Disponibilidad de flota | (horas totales − horas en mantención) / horas totales | ¿Cuánta flota está operativa? |
| MTBF | horas de operación / n.º fallas correctivas | ¿Cada cuánto falla un equipo? |
| Tasa de cancelación | cancelados / total de viajes | ¿Dónde se pierde capacidad? |

## 6. Reglas de trabajo
- El código vive en Git: el hosting es un repositorio con `receive.denyCurrentBranch=updateInstead`, y un `git push hosting main` actualiza el sitio.
- `.env` nunca se sube al repositorio. Toda clave que haya quedado expuesta se rota de inmediato.
- Cada integrante trabaja en su rama y hace sus propios commits.
- El uso de herramientas de IA se declara en el README.
