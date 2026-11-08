# 04 · Guía de estilos y maquetas — TriTransportes

- **Sistema de diseño:** [`assets/css/tt-design.css`](../assets/css/tt-design.css). Contiene los tokens y los componentes, y es mobile first.
- **Maquetas navegables:** [`docs/maquetas/index.html`](maquetas/index.html). En el entorno de demostración están en `https://trivoxa.cl/TriTransportes/docs/maquetas/`.

El sistema base usaba Bootstrap 5 casi por defecto, con una paleta navy + ámbar. Esta guía **conserva esa identidad**, pero la vuelve **accesible (WCAG 2.2 AA)** y propia: tokens con nombre, componentes con todos sus estados y reglas de uso.

## 1. Hallazgos de contraste en el sistema base

| Uso actual | Colores | Contraste | WCAG AA (4,5:1) | Corrección |
|---|---|---|---|---|
| Botón "Ingresar" | blanco sobre `#f59e0b` | **2,15:1** | ❌ | Texto navy sobre ámbar: **8,31:1** |
| Texto secundario sobre fondo | `#64748b` sobre `#f1f5f9` | 4,34:1 | ❌ | `#475569`: **6,92:1** |
| Texto atenuado | `#94a3b8` sobre blanco | 2,56:1 | ❌ | Ya no se usa para texto |
| Ámbar como texto o enlace | `#f59e0b` sobre blanco | 2,15:1 | ❌ | `#b45309`: **5,02:1** |

Los valores se calcularon con la fórmula de luminancia relativa de WCAG 2.x.

## 2. Paleta

| Token | Hex | Uso | Contraste de referencia |
|---|---|---|---|
| `--tt-navy-900` | `#0f172a` | Texto principal, botón primario, barra lateral | 17,85:1 sobre blanco |
| `--tt-navy-700` | `#334155` | Hover del primario, encabezados de tabla | 10,35:1 |
| `--tt-amber-500` | `#f59e0b` | **Solo rellenos** con texto navy, o detalles sobre navy | 8,31:1 con navy |
| `--tt-amber-700` | `#b45309` | Ámbar usado como texto sobre blanco | 5,02:1 |
| `--tt-slate-100` | `#f1f5f9` | Fondo de página | — |
| `--tt-slate-500` | `#64748b` | Borde de inputs (componente: ≥ 3:1) | 4,76:1 |
| `--tt-slate-600` | `#475569` | Texto secundario | 7,58:1 |
| `--tt-focus` | `#2563eb` | Anillo de foco (3 px) | 5,17:1 |
| `--tt-success` | `#15803d` | Éxito, viaje completado | 5,02:1 |
| `--tt-danger` | `#b91c1c` | Error, acción destructiva | 6,47:1 |
| `--tt-info` | `#1d4ed8` | Información, enlaces, viaje programado | 6,70:1 |
| `--tt-warning` | `#b45309` | Advertencia, viaje en ruta | 5,02:1 |

**Regla 1.4.1:** el color nunca es la única señal. Cada estado lleva además un **ícono y un texto** (por ejemplo, "✓ Completado"), y el estado cancelado va además tachado.

## 3. Tipografía

- **Familia:** pila del sistema (`Segoe UI`, `system-ui`, Roboto…). Se ve nativa en cada dispositivo y no descarga fuentes, lo que favorece el rendimiento (RNF02).
- **Escala:** en `rem`, para que respete el zoom y el tamaño de letra del usuario.

| Token | Tamaño | Uso |
|---|---|---|
| `--tt-fs-xs` | 13 px | Encabezados de tabla, insignias |
| `--tt-fs-sm` | 14 px | Texto secundario, ayudas, celdas |
| `--tt-fs-md` | 16 px | Cuerpo e inputs (mínimo en formularios, evita el zoom automático en iOS) |
| `--tt-fs-lg` | 18 px | Títulos de tarjeta |
| `--tt-fs-xl` | 22 px | `h2` |
| `--tt-fs-2xl` | 28 px | `h1` (título de página) |
| `--tt-fs-3xl` | 36 px | Valor de un KPI |

- **Interlineado:** 1,5 en el cuerpo y 1,25 en los títulos.
- **Pesos:** 400 / 600 / 700.
- **Números:** `tabular-nums`, alineados a la derecha.

## 4. Espaciado, forma y elevación

- **Espaciado (base 4 px):** `--tt-space-1` 4 · `2` 8 · `3` 12 · `4` 16 · `5` 24 · `6` 32 · `7` 48.
- **Radios:** 6 (inputs), 10 (botones), 16 (tarjetas) y `999px` (insignias).
- **Sombras:** 3 niveles. `--tt-shadow-1` para tarjetas, `--tt-shadow-2` para elementos flotantes y `--tt-shadow-3` para modales.
- **Objetivo táctil:** **44 × 44 px** en botones, enlaces del menú e inputs. WCAG 2.2 exige un mínimo de 24 × 24 px.

## 5. Puntos de quiebre (mobile first)

| Rango | Nombre | Cambios |
|---|---|---|
| 360–767 px | Celular | Una columna, menú colapsable con botón, tablas como **tarjetas apiladas** |
| 768–1023 px | Tablet | Grillas de 2 columnas, filtros en 2 columnas, formularios de 2 columnas |
| ≥ 1024 px | Escritorio | Barra lateral fija de 16 rem, KPIs en 3 columnas, filtros en una fila |
| ≥ 1280 px | Escritorio amplio | KPIs en 6 columnas |

No hay desplazamiento horizontal de la página. Las tablas anchas se desplazan dentro de su contenedor (`.tt-table-wrap`) o se apilan en el celular (`.tt-table--stack`).

## 6. Componentes y estados

Cada componente interactivo define sus estados **normal, hover, foco, activo, deshabilitado y error**. Todos están en la sección "Componentes" de las maquetas.

| Componente | Clase | Variantes y estados |
|---|---|---|
| Botón | `.tt-btn` | `--primary`, `--accent`, `--secondary`, `--danger`, `--ghost`, `--sm`, `--block`; `:hover`, `:focus-visible`, `:active`, `:disabled`, `[aria-busy]` (cargando) |
| Campo | `.tt-field` > `.tt-label` + `.tt-input`/`.tt-select` + `.tt-hint` + `.tt-error` | `:hover`, `:focus-visible`, `:disabled`, `[aria-invalid="true"]` |
| Resumen de errores | `.tt-error-summary` | Lista de enlaces a cada campo con error; recibe el foco al enviar |
| Insignia de estado | `.tt-badge--{estado}` | programado, en_ruta, completado, cancelado (ícono + texto) |
| Tarjeta / KPI | `.tt-card`, `.tt-kpi` | `--accent`, variación `--up`/`--down` con flecha |
| Alerta | `.tt-alert--{tipo}` | success, danger, info, warning; con `role="status"` o `role="alert"` |
| Tabla | `.tt-table` (+ `--stack`) | `caption`, `th scope`, orden con `aria-sort`, números a la derecha |
| Filtros / paginación | `.tt-filters`, `.tt-pagination` | Página actual con `aria-current="page"` |
| Estado vacío | `.tt-empty` | Ícono, mensaje y acción sugerida |
| Carga | `.tt-skeleton`, `[aria-busy]` | Animación desactivada con `prefers-reduced-motion` |
| Modal | `<dialog class="tt-modal">` | Foco atrapado de forma nativa, cierre con Esc y retorno del foco al disparador |
| Navegación | `.tt-sidebar` + `.tt-nav` | Ítem actual con `aria-current="page"`; botón de menú con `aria-expanded` |

## 7. Patrones de accesibilidad obligatorios

1. `<html lang="es">` y un `<title>` descriptivo por página ("Viajes — TriTransportes").
2. Regiones: `header`, `nav` (con `aria-label`), `main id="contenido"` y `footer`. Enlace **"Saltar al contenido"** como primer elemento enfocable.
3. Encabezados jerárquicos: un solo `h1` por página, sin saltar niveles.
4. Cada campo con `<label for>`. Los errores se escriben en texto, se vinculan con `aria-describedby`, se marca `aria-invalid="true"` y se anuncian con `role="alert"`.
5. Gráficos: título visible, `figcaption` con la conclusión en texto y botón **"Ver tabla de datos"** (`<details>`) con la tabla equivalente.
6. Foco siempre visible: contorno de 3 px `--tt-focus` (ámbar sobre fondos oscuros). Nunca `outline: none` sin reemplazo.
7. `prefers-reduced-motion` desactiva animaciones y transiciones.
8. Zoom de 200 %: unidades `rem` y `%`, sin alturas fijas en contenedores de texto.
9. Impresión: `@media print` oculta el menú, la barra superior, los filtros y los botones, y muestra el encabezado con los filtros aplicados (`.tt-print-only`).

## 8. Maquetas por rol

Las maquetas están en `docs/maquetas/index.html`. Se construyeron con el mismo `tt-design.css` que usará la aplicación, así que la maqueta **es** la guía visual implementada.

| Rol | Pantallas maquetadas |
|---|---|
| Todos | Inicio de sesión (con error), página 403 sin permisos, página 404 |
| Supervisor | Listado de viajes con búsqueda, filtros, orden y paginación; formulario "Programar viaje" con validación |
| Conductor | "Mis viajes de hoy" en celular, iniciar o finalizar viaje, registrar carga de combustible con error, estado vacío |
| Gerencia | Dashboard con 6 KPIs, 4 gráficos (cada uno con su tabla de datos), resumen con IA y reporte imprimible |
| Administrador | Gestión de usuarios con roles y registro de auditoría |
