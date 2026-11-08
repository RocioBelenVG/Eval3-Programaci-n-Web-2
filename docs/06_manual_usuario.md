# 06 · Manual de usuario por rol — TriTransportes

Aplicación: **https://trivoxa.cl/TriTransportes/app/** (o http://localhost:8080 con `docker compose up`).
Funciona en celular, tablet y computador, y se puede usar completa con el teclado y con lector de pantalla.

## Para todos los roles

1. **Ingresar:** escriba su correo y su contraseña y pulse *Ingresar*. Si un dato está mal, el error aparece bajo el campo y en un resumen al inicio del formulario.
2. **Menú:** en el computador está a la izquierda. En el celular se abre con el botón **☰ Menú** y se cierra con *Esc*. Cada persona ve solo las opciones de su rol.
3. **Sesión:** dura 60 minutos. Al vencer, el sistema pide ingresar otra vez. *Cerrar sesión* está al pie del menú.
4. **Listados:** busque escribiendo en *Buscar*, filtre por estado o fechas, ordene pulsando el encabezado de una columna y cambie de página al pie. *Limpiar filtros* vuelve al listado completo.
5. **Si algo falla:** los mensajes dicen qué pasó y qué hacer. Un error inesperado muestra un código (por ejemplo `9f2c1a7e`); entrégueselo al administrador.

## Conductor

| Quiero… | Cómo |
|---|---|
| Ver mis viajes | **Mis viajes**. Solo aparecen los viajes asignados a usted. |
| Iniciar un viaje | Abra el viaje (su número) → **Iniciar viaje** → *Iniciar viaje*. Queda registrada la hora de salida. |
| Terminar un viaje | Abra el viaje en ruta → **Completar viaje** → escriba el **kilometraje final** (no puede ser menor que el inicial) → *Completar viaje*. |
| Emitir la guía de despacho | En un viaje completado → **Emitir guía de despacho** → número de guía, receptor y su RUT (sin puntos, con guion). |
| Registrar combustible | **Combustible** → **Registrar carga** → elija *Vehículo* o *Equipo*, la unidad, la fecha, los litros (máximo 600 en un camión y 1.500 en un equipo), el precio y el odómetro, que no puede ser menor que el último registrado. |

El conductor no puede cancelar viajes: si un viaje no se hará, avise al supervisor.

## Supervisor

Todo lo del conductor, para cualquier viaje, y además:

| Quiero… | Cómo |
|---|---|
| Programar un viaje | **Programar viaje** → vehículo, conductor (con licencia vigente en la fecha), fecha, origen, destino y, si corresponde, obra, carga y km inicial. Si deja vacío el km inicial, se usa el odómetro del vehículo. |
| Modificar un viaje | Ficha del viaje → **Modificar**. Solo se puede mientras el viaje está *Programado*. |
| Cancelar un viaje | Ficha → **Cancelar viaje** → escriba el motivo. Queda en las observaciones y en la auditoría. |
| Registrar una mantención | **Mantenciones** → **Registrar mantención** → equipo, tipo, fecha y costo. Luego **Iniciar** y **Completar**. |
| Revisar el rendimiento | **Flota** → la patente del vehículo: km, litros, km/L y costo de cada mes. |
| Ver indicadores y reportes | **Dashboard** y **Reportes** (excepto el *Resumen gerencial*, que es de la gerencia). |

## Gerencia

| Quiero… | Cómo |
|---|---|
| Ver cómo va la operación | **Dashboard**: 6 indicadores con su variación frente al período anterior (dice en palabras si mejora o empeora), una lectura del período y 4 gráficos. Cambie las fechas, la obra o el vehículo y pulse *Aplicar*. |
| Ver los números de un gráfico | Bajo cada gráfico, **Ver tabla de datos**. |
| Pedir un resumen con IA | En el Dashboard, **Resumen con IA de este período**, o en el menú **Resumen con IA** para elegir fechas y enfoque. Junto al resumen se muestran las cifras usadas, para verificarlas. Si el servicio de IA no está disponible, el resto del sistema sigue funcionando. |
| Sacar un reporte | **Reportes** → elija *Detalle de viajes*, *Resumen gerencial mensual* o *Combustible por vehículo*, ajuste los filtros y pulse **Generar**. |
| Exportar | **Excel**, **PDF** o **CSV**: se descarga con los filtros aplicados y la exportación queda registrada. El CSV usa punto y coma (;) y coma decimal, para abrirlo directo en Excel. |
| Imprimir | **Imprimir**: sale el reporte con su título, filtros y fecha, sin menús ni botones. |

La gerencia solo consulta: no programa viajes ni registra cargas.

## Administrador

Tiene todo lo de los demás roles y además puede **eliminar** viajes que todavía están programados (un viaje iniciado se cancela, no se elimina). La gestión de usuarios y la consulta de la auditoría se hacen por ahora desde el sistema clásico (https://trivoxa.cl/TriTransportes/).

## Preguntas frecuentes

- **"No se puede pasar de 'completado' a 'en_ruta'"**: un viaje completado o cancelado ya no cambia de estado. Si hubo un error, el supervisor debe programar uno nuevo.
- **"La licencia de … vence el …"**: elija otro conductor o actualice la licencia en el sistema clásico.
- **"Demasiadas solicitudes"**: espere el tiempo indicado. Es una protección contra abusos.
