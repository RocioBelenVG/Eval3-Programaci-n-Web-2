# Prueba con teclado y lector de pantalla (NVDA) — protocolo y registro

**Debe ejecutarla una persona del equipo**: la pauta pide una prueba manual con lector de pantalla, que no se puede automatizar.
Duración estimada: 30 minutos. Completar las columnas *Resultado* y *NVDA anuncia* y copiar la tabla al informe (sección 7.5).

- Fecha: ____ · Persona: ____
- Navegador y versión: Firefox ____ (o Chrome ____) · NVDA versión: ____ (descarga gratuita en nvaccess.org)
- URL: https://trivoxa.cl/TriTransportes/app/ · cuentas: `supervisor@`, `conductor@` y `gerencia@tritransportes.cl` (la contraseña está en `DEMO_PASSWORD` del `.env` del servidor)
- Teclas útiles de NVDA: `Insert+Espacio` (modo foco/exploración), `H` (siguiente encabezado), `D` (siguiente región), `T` (siguiente tabla), `Ctrl+Alt+flechas` (moverse por las celdas).

| # | Acción (solo teclado: Tab, Shift+Tab, Enter, Espacio, Esc, flechas) | Resultado esperado | Resultado | NVDA anuncia |
|---|---|---|---|---|
| 1 | Abrir la URL y pulsar Tab | El primer foco es "Saltar al contenido" y es visible | | |
| 2 | Iniciar sesión con la contraseña incorrecta | Se anuncia el resumen de errores; el campo queda asociado a su error | | |
| 3 | Iniciar sesión como Supervisor | Se anuncia "Viajes" (título de la vista) y "Bienvenido…" | | |
| 4 | Menú → "Programar viaje" y enviar el formulario vacío | El foco va al resumen; cada enlace lleva a su campo; cada campo lee su error | | |
| 5 | Completar y guardar | Se anuncia "Viaje #N programado" y el título de la ficha | | |
| 6 | "Iniciar viaje" (modal): Tab dentro del modal y luego Esc | El foco no sale del modal; Esc lo cierra y vuelve al botón | | |
| 7 | "Completar viaje" con un km menor al inicial y luego uno válido | Se anuncia el error del km; después, el estado "Completado" | | |
| 8 | Listado de viajes: ordenar por Fecha y cambiar de página | Se anuncia el orden (aria-sort) y "N viajes encontrados" | | |
| 9 | Cerrar sesión e ingresar como Gerencia → Dashboard | Se leen los 6 indicadores con su variación en palabras (mejora o empeora) | | |
| 10 | En un gráfico: "Ver tabla de datos" | Se lee el título, la descripción y la tabla con sus encabezados | | |
| 11 | Reportes → Resumen gerencial → Excel | El botón anuncia ocupado y luego "Descargado …" | | |
| 12 | Zoom del navegador al 200 % en el dashboard | No se pierde contenido ni aparece desplazamiento horizontal | | |

Observaciones y hallazgos (con su corrección): ____
