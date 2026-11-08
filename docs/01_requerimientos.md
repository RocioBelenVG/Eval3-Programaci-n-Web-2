# 01 · Propuesta y requerimientos — TriTransportes

## 1. Dominio

**Contexto.** TRITRANSPORTES SpA (empresa ficticia) es una empresa chilena de transporte de carga para obras de construcción. Opera una flota de camiones y maquinaria, con conductores que hacen varios viajes diarios entre plantas, canteras y obras de clientes.

**Problema.** La programación de viajes, las guías de despacho, las cargas de combustible y las mantenciones se registran en planillas y por WhatsApp. Por eso no se sabe a tiempo cuánto cuesta cada viaje, qué camión consume de más ni cuánta flota está disponible.

**Objetivo.** Construir un sistema web que registre de punta a punta la operación de transporte (programar, ejecutar y cerrar viajes, cargar combustible y mantener la flota), con trazabilidad por usuario e indicadores para la gerencia.

**Alcance (MVP).**

| Incluido | Fuera de alcance |
|---|---|
| Viajes con flujo de estados y guías de despacho | Facturación electrónica (SII) |
| Cargas de combustible y rendimiento | Áridos, contratos, documentos, seguridad, faena* |
| Flota: vehículos, equipos y mantenciones | Firma y GPS en terreno, modo offline* |
| Maestros: clientes, obras, conductores | App móvil nativa |
| Usuarios, roles, permisos y auditoría | Multiempresa visible al usuario final |
| Dashboard, reportes (XLSX/PDF/CSV) e IA | |

\* Existen en el sistema base, pero no forman parte del alcance evaluado y se ocultan del menú.

## 2. Roles de usuario

| Rol | Descripción | Puede |
|---|---|---|
| **Administrador** | Responsable del sistema | Gestionar usuarios, roles y maestros; ver la auditoría; todo lo demás |
| **Supervisor** | Jefe de operaciones | Programar, cancelar y cerrar viajes; registrar mantenciones; emitir guías; ver reportes y exportarlos |
| **Conductor** | Ejecuta los viajes | Ver solo **sus** viajes; iniciar y finalizar un viaje; registrar cargas de combustible de su vehículo |
| **Gerencia** | Toma decisiones | Solo lectura: dashboard, reportes gerenciales, resumen con IA y exportación |

## 3. Requerimientos funcionales

Prioridad MoSCoW: **M** = Must, **S** = Should, **C** = Could.

| ID | Requerimiento | Rol | Prioridad |
|---|---|---|---|
| RF01 | Iniciar sesión con correo y contraseña y obtener un token JWT; cerrar sesión | Todos | M |
| RF02 | Programar un viaje (vehículo, conductor, obra, origen, destino, fecha y carga) | Supervisor | M |
| RF03 | Cambiar el estado de un viaje (`programado → en_ruta → completado`, o `cancelado`), rechazando transiciones inválidas | Supervisor, Conductor | M |
| RF04 | Listar viajes con búsqueda, filtros (fecha, estado, vehículo, conductor, obra), orden y paginación | Todos* | M |
| RF05 | Emitir una guía de despacho asociada a un viaje completado | Supervisor | M |
| RF06 | Registrar una carga de combustible (litros, precio, odómetro u horómetro) | Conductor, Supervisor | M |
| RF07 | Calcular el rendimiento (km/L o L/h) por vehículo o equipo | Supervisor, Gerencia | M |
| RF08 | Registrar una mantención y gestionar su estado (`pendiente → en_proceso → completada`) | Supervisor | M |
| RF09 | Mantener maestros: vehículos, equipos, conductores, clientes y obras | Administrador | M |
| RF10 | Gestionar usuarios y asignarles un rol | Administrador | M |
| RF11 | Registrar en auditoría quién creó o modificó qué y cuándo, con valores antes y después | Sistema | M |
| RF12 | Dashboard con 6 indicadores y 4 o más gráficos filtrables por fecha, obra y vehículo | Gerencia, Supervisor | M |
| RF13 | Reporte operativo: detalle de viajes filtrado | Supervisor, Gerencia | M |
| RF14 | Reporte gerencial: resumen mensual con totales y gráficos | Gerencia | M |
| RF15 | Reporte parametrizable por rango de fechas: consumo y costo de combustible por vehículo | Supervisor, Gerencia | M |
| RF16 | Exportar cada reporte a XLSX, PDF y CSV con los filtros aplicados, registrando quién exportó | Según permiso | M |
| RF17 | Vista de impresión limpia de cada reporte | Según permiso | M |
| RF18 | Resumen gerencial generado por IA a partir de los indicadores del período | Gerencia, Supervisor | M |
| RF19 | Detectar y explicar con IA cargas de combustible anómalas | Supervisor | S |
| RF20 | Ver la ficha de un vehículo: viajes, consumo y mantenciones | Supervisor, Gerencia | S |
| RF21 | Alertas de mantención próxima según odómetro u horómetro | Supervisor | C |

\* El Conductor ve solo sus propios viajes.

## 4. Requerimientos no funcionales

| ID | Atributo | Requerimiento verificable | Cómo se verifica |
|---|---|---|---|
| RNF01 | Rendimiento | Los listados paginados responden en < 500 ms (p95) con 15.000 viajes | Medición con `curl -w` y log de tiempos |
| RNF02 | Rendimiento | El dashboard carga en < 2 s con 12 meses de datos | Lighthouse y pestaña Network |
| RNF03 | Seguridad | Todo endpoint, salvo `/auth/login`, exige un JWT válido; los tokens expiran en 60 min | Pruebas de integración (401) |
| RNF04 | Seguridad | Mitigar 5 o más riesgos del OWASP API Top 10 (ver arquitectura) | Pruebas y revisión |
| RNF05 | Seguridad | Ningún secreto en el repositorio; configuración por variables de entorno | Revisión de `git log` y `.env.example` |
| RNF06 | Disponibilidad | El sistema arranca con `docker compose up` con health checks y política de reinicio | Demostración |
| RNF07 | Disponibilidad | Respaldo diario de la base de datos y restauración probada en < 15 min | Registro de la prueba |
| RNF08 | Accesibilidad | WCAG 2.2 AA; Lighthouse ≥ 90 en accesibilidad y sin errores críticos de axe | Informe de auditoría |
| RNF09 | Usabilidad | Responsive de 360 a 1440 px sin scroll horizontal; controles táctiles ≥ 44×44 px | Capturas en 3 anchos |
| RNF10 | Mantenibilidad | Arquitectura en capas; controladores sin SQL; servicios con pruebas unitarias | Revisión de código y PHPUnit |
| RNF11 | Integridad | Validación en front end, API y base de datos (`CHECK`, `UNIQUE`, `NOT NULL`, FK) | Pruebas con datos inválidos |
| RNF12 | Resiliencia | El servicio de IA tiene un timeout de 20 s y sus fallas se devuelven con el formato de error estándar | Prueba con servicio simulado |

## 5. Historias de usuario

### Supervisor
**HU01 — Programar viaje (RF02).** Como supervisor, quiero programar un viaje para planificar la operación del día.
- *Dado* un vehículo activo y un conductor con licencia vigente, *cuando* guardo el viaje, *entonces* queda en estado `programado` y aparece en el listado.
- *Si* el conductor tiene la licencia vencida, *entonces* el sistema rechaza el viaje con un mensaje claro (422).
- *Si* el kilometraje final es menor que el inicial, *entonces* se rechaza en el formulario, en la API y en la base de datos.

**HU02 — Cancelar viaje (RF03).** Como supervisor, quiero cancelar un viaje programado o en ruta, indicando el motivo.
- *Si* el viaje está `completado`, *entonces* la cancelación responde 409 con el mensaje "Transición no permitida".
- La cancelación queda en la auditoría con usuario, fecha y estado anterior.

**HU03 — Registrar mantención (RF08).** Como supervisor, quiero registrar mantenciones y avanzar su estado para conocer la disponibilidad de la flota.
- Una mantención `completada` no puede volver a `pendiente`.
- El costo total debe ser ≥ 0.

### Conductor
**HU04 — Mis viajes (RF04).** Como conductor, quiero ver solo mis viajes del día para saber qué debo hacer.
- Si intento abrir el viaje de otro conductor por su id, la API responde 404 (protección BOLA).

**HU05 — Iniciar y finalizar viaje (RF03).** Como conductor, quiero marcar el inicio y el término de mi viaje.
- Al finalizar debo informar el kilometraje final, que debe ser mayor o igual al inicial.

**HU06 — Cargar combustible (RF06).** Como conductor, quiero registrar una carga desde el celular.
- Los litros deben ser > 0 y ≤ capacidad máxima (600 L), y el odómetro no puede ser menor que el último registrado.
- El formulario muestra los errores junto a cada campo y los anuncia al lector de pantalla.

### Gerencia
**HU07 — Dashboard (RF12).** Como gerente, quiero ver los indicadores del mes con filtros para decidir dónde reducir costos.
- Cada gráfico tiene título, descripción textual y botón "Ver tabla de datos".
- Al cambiar el filtro de fecha, todos los gráficos se actualizan.

**HU08 — Resumen con IA (RF18).** Como gerente, quiero un resumen en lenguaje natural de los indicadores del período.
- Si el servicio de IA no responde en 20 s, veo un mensaje "Servicio de IA no disponible" sin que se caiga la página.

**HU09 — Exportar reporte (RF16).** Como gerente, quiero exportar el reporte mensual a Excel y PDF.
- El PDF incluye título, fecha de emisión, filtros usados y numeración de páginas.
- La exportación queda registrada en la auditoría.

### Administrador
**HU10 — Gestionar usuarios (RF10).** Como administrador, quiero crear usuarios y asignarles un rol.
- El correo es único y la contraseña exige al menos 8 caracteres con letras y números.
- Un usuario sin el permiso correspondiente que entra a una URL de administración ve la página 403.

**HU11 — Auditoría (RF11).** Como administrador, quiero consultar quién modificó un viaje y qué cambió.

## 6. Casos de uso completos (6 o más)

1. Programar, ejecutar y cerrar un viaje (HU01, HU05).
2. Cancelar un viaje (HU02).
3. Emitir una guía de despacho de un viaje completado (RF05).
4. Registrar una carga de combustible y ver el rendimiento (HU06, RF07).
5. Registrar y cerrar una mantención (HU03).
6. Consultar el dashboard y exportar reportes (HU07, HU09).
7. Generar un resumen con IA (HU08).
8. Administrar usuarios y revisar la auditoría (HU10, HU11).

## 7. Plan de trabajo

| Integrante | Responsabilidad principal | Apoyo |
|---|---|---|
| _(nombre 1)_ | Backend: API, JWT, servicios y pruebas | Base de datos |
| _(nombre 2)_ | Base de datos: migraciones, vistas, seeds y respaldo | Reportes |
| _(nombre 3)_ | Front end: sistema de diseño, accesibilidad y responsive | Dashboard |
| _(nombre 4)_ | BI y DevOps: indicadores, dashboard, Docker y CI/CD | IA |

**Herramientas:** Git y GitHub (rama por integrante y pull requests), Hostinger como entorno de demostración, Docker para desarrollo y entrega, Mermaid para los diagramas y PHPUnit para las pruebas.

**Organización del repositorio:**
- `src/`: capas Domain, Repository, Service y Http.
- `api/`: punto de entrada de la API.
- `modules/`: vistas.
- `database/`: migraciones y seeds.
- `docs/`: documentación y diagramas.
- `tests/`: pruebas automatizadas.
