-- ============================================================
-- TriTransportes — SEED DEMO PARTE 3
-- Datos operacionales de todos los módulos
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- M03 COMBUSTIBLE — cargas_combustible
-- fuente 1=Estanque Propio,2=Bencinera; estanques e1:1,2 e2:3; prov e1:1,2 e2:3
-- ============================================================
INSERT INTO cargas_combustible
(empresa_id, obra_id, contrato_id, fecha, tipo_unidad, vehiculo_id, equipo_id, conductor_id, operador_id, litros, precio_litro, tipo_combustible, horometro, odometro, fuente_id, estanque_id, proveedor_id, observacion, creado_por) VALUES
(1, 1, 1, CURDATE(),                         'equipo',   NULL, 1, NULL, 2, 180.0, 980, 'Petróleo', 8420.5, NULL, 1, 2, 1, 'Carga matinal excavadora', 1),
(1, 1, 1, CURDATE(),                         'vehiculo', 1, NULL, 1, NULL, 220.0, 980, 'Petróleo', NULL, 145200, 1, 2, 1, 'Camión tolva faena minera', 1),
(1, 2, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY),'equipo',  NULL, 4, NULL, NULL, 150.0, 975, 'Petróleo', 9870.0, NULL, 1, 1, 1, 'Motoniveladora pavimentación', 1),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY),'vehiculo', 2, NULL, 1, NULL, 240.0, 970, 'Petróleo', NULL, 198400, 2, NULL, 2, 'Carga en bencinera externa', 1),
(1, 3, NULL,DATE_SUB(CURDATE(), INTERVAL 5 DAY),'equipo', NULL, 5, NULL, NULL, 200.0, 965, 'Petróleo', 7640.0, NULL, 1, 1, 1, 'Bulldozer loteo', 1),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY),'equipo',  NULL, 3, NULL, 1, 165.0, 960, 'Petróleo', 6310.0, NULL, 1, 2, 1, 'Excavadora Komatsu', 1),
(1, 2, 2, DATE_SUB(CURDATE(), INTERVAL 12 DAY),'vehiculo',3, NULL, 3, NULL, 210.0, 955, 'Petróleo', NULL, 89300, 1, 1, 1, 'Scania pavimentación', 1),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 18 DAY),'equipo', NULL, 2, NULL, 2, 190.0, 950, 'Petróleo', 11250.0, NULL, 1, 2, 1, 'Cargador frontal', 1),
-- Empresa 2
(2, 4, 3, CURDATE(),                          'equipo', NULL, 6, NULL, 3, 175.0, 985, 'Petróleo', 4120.0, NULL, 1, 3, 3, 'Excavadora Volvo fundaciones', 4),
(2, 5, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY),'vehiculo',6, NULL, 4, NULL, 230.0, 980, 'Petróleo', NULL, 132000, 1, 3, 3, 'Camión ruta H-30', 4),
(2, 4, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY),'equipo', NULL, 7, NULL, NULL, 160.0, 978, 'Petróleo', 5560.0, NULL, 1, 3, 3, 'Cargador Hitachi', 4),
(2, 5, 4, DATE_SUB(CURDATE(), INTERVAL 10 DAY),'equipo',NULL, 8, NULL, NULL, 140.0, 975, 'Petróleo', 2980.0, NULL, 1, 3, 3, 'Retroexcavadora', 4);

-- ============================================================
-- M04 MAQUINARIA — mantenciones + repuestos + detenciones
-- ============================================================
INSERT INTO mantenciones (id, empresa_id, equipo_id, tipo, fecha, horometro, descripcion, tecnico, costo_total, estado, observaciones, creado_por) VALUES
(1, 1, 1, 'preventiva', DATE_SUB(CURDATE(), INTERVAL 15 DAY), 8400.0, 'Cambio de aceite y filtros 8400 hrs', 'Andrés Salinas', 480000, 'completada', 'Realizada en taller', 1),
(2, 1, 2, 'correctiva', DATE_SUB(CURDATE(), INTERVAL 3 DAY),  11240.0,'Reparación sistema hidráulico', 'Andrés Salinas', 1250000, 'en_proceso', 'Espera repuesto bomba', 1),
(3, 1, 4, 'preventiva', CURDATE(),                            9870.0, 'Mantención programada 250 hrs', 'Taller externo', 320000, 'pendiente', 'Agendada', 1),
(4, 2, 6, 'preventiva', DATE_SUB(CURDATE(), INTERVAL 7 DAY),  4100.0, 'Mantención 500 hrs', 'Víctor Riquelme', 410000, 'completada', NULL, 4),
(5, 2, 8, 'correctiva', DATE_SUB(CURDATE(), INTERVAL 2 DAY),  2970.0, 'Cambio neumático trasero', 'Taller Machalí', 280000, 'en_proceso', NULL, 4);

INSERT INTO mantencion_repuestos (mantencion_id, descripcion, cantidad, costo_unitario) VALUES
(1, 'Aceite motor 15W40 (tambor)', 1, 180000),
(1, 'Filtro de aceite', 2, 25000),
(1, 'Filtro de combustible', 2, 35000),
(2, 'Manguera hidráulica alta presión', 3, 85000),
(2, 'Bomba hidráulica (reacondicionada)', 1, 950000),
(4, 'Kit filtros 500 hrs', 1, 220000),
(5, 'Neumático 23.5-25', 1, 280000);

INSERT INTO detenciones_equipo (empresa_id, equipo_id, fecha_inicio, fecha_fin, motivo, tipo, creado_por) VALUES
(1, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL, 'Reparación hidráulica en curso', 'mantencion', 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Mantención preventiva programada', 'mantencion', 1),
(2, 8, DATE_SUB(CURDATE(), INTERVAL 2 DAY), NULL, 'Cambio de neumático', 'falla', 4);

-- ============================================================
-- M02 FAENA — partes_diarios + detalle
-- supervisor_id = trabajador_id
-- ============================================================
INSERT INTO partes_diarios (id, empresa_id, obra_id, contrato_id, fecha, supervisor_id, clima, estado, observaciones, lat, lng, creado_por) VALUES
(1, 1, 1, 1, CURDATE(),                          2, 'Despejado', 'borrador', 'Avance normal de movimiento de tierra sector norte.', -34.0850, -70.3580, 1),
(2, 1, 2, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 8, 'Nublado',   'cerrado',  'Pavimentación tramo 2 completada.', -33.7330, -70.7420, 1),
(3, 1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 2, 'Soleado',   'cerrado',  'Conformación de subrasante.', -34.0850, -70.3580, 1),
(4, 2, 4, 3, CURDATE(),                          12, 'Despejado', 'borrador', 'Excavación masiva nivel -8m.', -34.1700, -70.7400, 4),
(5, 2, 5, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY),12, 'Lluvia leve','cerrado', 'Recapado suspendido parcialmente por lluvia.', -34.2000, -70.8000, 4);

INSERT INTO parte_actividades (parte_id, descripcion, unidad, cantidad) VALUES
(1, 'Excavación material común', 'm3', 1850),
(1, 'Carguío a camiones', 'm3', 1850),
(2, 'Colocación de mezcla asfáltica', 'ton', 320),
(2, 'Compactación', 'm2', 4200),
(3, 'Conformación subrasante', 'm2', 3800),
(4, 'Excavación masiva', 'm3', 2100),
(5, 'Recapado asfáltico', 'ton', 180);

INSERT INTO parte_personal (parte_id, trabajador_id, horas) VALUES
(1, 2, 9.0), (1, 4, 9.0), (1, 5, 9.0), (1, 3, 8.5),
(2, 8, 9.0), (2, 7, 9.0),
(3, 2, 8.0), (3, 5, 8.0),
(4, 12, 9.0), (4, 10, 9.0),
(5, 12, 6.0), (5, 11, 6.0);

INSERT INTO parte_equipos (parte_id, equipo_id, horas_trabajo, horas_detenciones) VALUES
(1, 1, 8.5, 0.5), (1, 2, 9.0, 0.0),
(2, 4, 9.0, 0.0),
(3, 3, 8.0, 0.0),
(4, 6, 9.0, 0.0), (4, 8, 7.0, 1.0),
(5, 7, 5.5, 0.5);

-- ============================================================
-- M01 TRANSPORTE — viajes + guias_despacho
-- conductores e1:1(t3),2(t7),3(t8) e2:4(t11),5(t13); materiales 1-9
-- ============================================================
INSERT INTO viajes (id, empresa_id, obra_id, contrato_id, vehiculo_id, conductor_id, fecha, hora_salida, hora_llegada, origen, destino, km_inicio, km_fin, material_id, cantidad, unidad, estado, observaciones) VALUES
(1, 1, 1, 1, 1, 1, CURDATE(),                          '08:00','10:30','Faena El Teniente','Botadero Norte', 145200, 145260, 3, 16, 'm3', 'completado', 'Material de excavación'),
(2, 1, 1, 1, 2, 1, CURDATE(),                          '08:15','10:45','Faena El Teniente','Botadero Norte', 198400, 198470, 3, 16, 'm3', 'completado', NULL),
(3, 1, 2, 2, 3, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY),'09:00','11:00','Planta Asfalto','Av. Central Buin', 89300, 89340, 7, 28, 'ton', 'completado', 'Mezcla asfáltica caliente'),
(4, 1, 1, 1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY),'14:00',NULL,'Faena El Teniente','Botadero Norte', 145100, 145160, 3, 16, 'm3', 'en_ruta', NULL),
(5, 1, 2, 2, 2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY),'08:30','10:00','Cantera Cerro Negro','Av. Central Buin', 197900, 197960, 5, 18, 'm3', 'completado', 'Base granular'),
(6, 2, 4, 3, 6, 4, CURDATE(),                          '08:00','09:30','Faena Cordillera','Botadero Sur', 132000, 132045, 1, 14, 'm3', 'programado', NULL),
(7, 2, 5, 4, 7, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY),'10:00','12:30','Planta Asfalto Andes','Ruta H-30', 154300, 154390, 7, 26, 'ton', 'completado', NULL);

INSERT INTO guias_despacho (empresa_id, viaje_id, obra_id, numero_guia, fecha, vehiculo_id, conductor_id, material_id, cantidad, unidad, origen, destino, receptor, rut_receptor, observaciones, creado_por) VALUES
(1, 1, 1, 'GD-001234', CURDATE(),                          1, 1, 3, 16, 'm3', 'Faena El Teniente', 'Botadero Norte', 'Minera El Teniente', '90.123.456-7', NULL, 1),
(1, 3, 2, 'GD-001235', DATE_SUB(CURDATE(), INTERVAL 1 DAY),3, 3, 7, 28, 'ton','Planta Asfalto', 'Av. Central Buin', 'Municipalidad de Buin', '69.070.100-4', 'Recepción conforme', 1),
(1, 5, 2, 'GD-001236', DATE_SUB(CURDATE(), INTERVAL 4 DAY),2, 1, 5, 18, 'm3', 'Cantera Cerro Negro', 'Av. Central Buin', 'Municipalidad de Buin', '69.070.100-4', NULL, 1),
(2, 7, 5, 'AND-GD-0078', DATE_SUB(CURDATE(), INTERVAL 2 DAY),7, 5, 7, 26, 'ton','Planta Asfalto Andes', 'Ruta H-30', 'Vialidad O''Higgins', '61.202.000-0', NULL, 4);

-- ============================================================
-- M05 ÁRIDOS — produccion + stock + despachos
-- ============================================================
INSERT INTO produccion_aridos (empresa_id, obra_id, material_id, clasificacion_id, fecha, cantidad, unidad, equipo_id, operador_id, turno, observaciones, creado_por) VALUES
(1, NULL, 3, 1, CURDATE(),                          420, 'ton', 2, 2, 'mañana', 'Producción grava 3/4', 1),
(1, NULL, 4, 2, CURDATE(),                          380, 'ton', 2, 2, 'tarde',  'Producción gravilla 1/2', 1),
(1, NULL, 1, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 250, 'ton', 3, 1, 'mañana', 'Arena fina lavada', 1),
(1, NULL, 7, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 600, 'ton', 2, 2, 'mañana', 'Base granular CBR 80', 1),
(2, NULL, 3, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 300, 'ton', 7, 3, 'mañana', 'Grava para fundaciones', 4),
(2, NULL, 5, 6, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 180, 'ton', 7, 3, 'tarde',  'Piedra bolón', 4);

INSERT INTO stock_aridos (empresa_id, obra_id, material_id, clasificacion_id, cantidad, unidad) VALUES
(1, NULL, 3, 1, 1240, 'ton'),
(1, NULL, 4, 2,  860, 'ton'),
(1, NULL, 1, 3,  430, 'ton'),
(1, NULL, 7, 5, 1500, 'ton'),
(2, NULL, 3, 1,  520, 'ton'),
(2, NULL, 5, 6,  300, 'ton');

INSERT INTO despachos_aridos (empresa_id, obra_id, cliente_id, material_id, clasificacion_id, fecha, guia_numero, vehiculo_id, conductor_id, cantidad, unidad, precio_unitario, destino, receptor, rut_receptor, observaciones, creado_por) VALUES
(1, 2, 3, 3, 1, CURDATE(),                          'DA-00501', 1, 1, 16, 'ton', 9500, 'Obra Av. Central Buin', 'Constructora del Maipo', '76.345.678-9', NULL, 1),
(1, NULL, 3, 4, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY),'DA-00502', 2, 1, 18, 'ton', 9800, 'Bodega Cliente', 'Constructora del Maipo', '76.345.678-9', 'Despacho directo', 1),
(1, NULL, 2, 7, 5, DATE_SUB(CURDATE(), INTERVAL 6 DAY),'DA-00503', 3, 3, 22, 'ton', 8200, 'Acopio Municipal', 'Municipalidad de Buin', '69.070.100-4', NULL, 1),
(2, 5, 5, 3, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'AND-DA-021', 6, 4, 14, 'ton', 9600, 'Ruta H-30', 'Vialidad O''Higgins', '61.202.000-0', NULL, 4);

-- ============================================================
-- M07 DOCUMENTOS
-- tipos_documento 1-11; estados_documento 1=Vigente,2=Por Vencer,3=Vencido
-- ============================================================
INSERT INTO documentos (empresa_id, tipo_id, estado_id, obra_id, entidad_tipo, entidad_id, titulo, numero, fecha_emision, fecha_vencimiento, observaciones, creado_por) VALUES
(1, 2, 1, NULL, 'conductor', 1, 'Licencia de Conducir - Manuel Tapia', 'LC-145678', DATE_SUB(CURDATE(), INTERVAL 365 DAY), DATE_ADD(CURDATE(), INTERVAL 200 DAY), NULL, 1),
(1, 2, 2, NULL, 'conductor', 2, 'Licencia de Conducir - Felipe Morales', 'LC-189012', DATE_SUB(CURDATE(), INTERVAL 340 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'Próxima a vencer', 1),
(1, 4, 1, NULL, 'vehiculo', 1, 'Seguro Obligatorio - JKLM12', 'SOAP-2026-001', DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_ADD(CURDATE(), INTERVAL 305 DAY), NULL, 1),
(1, 5, 3, NULL, 'vehiculo', 2, 'Revisión Técnica - JKLM34', 'RT-2025-882', DATE_SUB(CURDATE(), INTERVAL 400 DAY), DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'VENCIDA - renovar urgente', 1),
(1, 9, 1, 1, 'general', 1, 'Permiso de Obra Camino Minero', 'PO-2026-014', DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_ADD(CURDATE(), INTERVAL 245 DAY), NULL, 1),
(1, 7, 1, NULL, 'general', NULL, 'Contrato Comercial CT-2026-001', 'CT-2026-001', DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_ADD(CURDATE(), INTERVAL 90 DAY), NULL, 1),
(2, 2, 2, NULL, 'conductor', 5, 'Licencia de Conducir - Mauricio Pino', 'LC-146667', DATE_SUB(CURDATE(), INTERVAL 347 DAY), DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'Por vencer', 4),
(2, 5, 1, NULL, 'vehiculo', 6, 'Revisión Técnica - PQRS12', 'RT-2026-501', DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 335 DAY), NULL, 4),
(2, 9, 1, 4, 'general', 4, 'Permiso Edificación Cordillera', 'PE-2026-220', DATE_SUB(CURDATE(), INTERVAL 90 DAY), DATE_ADD(CURDATE(), INTERVAL 275 DAY), NULL, 4);

-- ============================================================
-- M08 SEGURIDAD — checklists + items + incidentes
-- tipos_checklist 1-5
-- ============================================================
INSERT INTO checklists_registros (id, empresa_id, tipo_id, obra_id, equipo_id, vehiculo_id, trabajador_id, fecha, turno, resultado, observaciones, creado_por) VALUES
(1, 1, 1, 1, 1, NULL, 5, CURDATE(),                          'mañana', 'aprobado',  'Equipo en óptimas condiciones', 1),
(2, 1, 4, 2, NULL, 1, 3, CURDATE(),                          'mañana', 'observado', 'Luz de freno trasera con falla menor', 1),
(3, 1, 1, 1, 3, NULL, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY),'tarde',  'aprobado',  NULL, 1),
(4, 2, 1, 4, 6, NULL, 10,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'mañana', 'aprobado',  NULL, 4),
(5, 2, 4, 5, NULL, 6, 13,DATE_SUB(CURDATE(), INTERVAL 3 DAY),'mañana', 'rechazado', 'Neumático en mal estado, fuera de servicio', 4);

INSERT INTO checklist_items_resp (registro_id, item, cumple, observacion) VALUES
(1, 'Nivel de aceite', 1, NULL),
(1, 'Sistema hidráulico', 1, NULL),
(1, 'Luces y alarmas', 1, NULL),
(1, 'Extintor vigente', 1, NULL),
(2, 'Frenos', 1, NULL),
(2, 'Luces traseras', 0, 'Luz de freno derecha no enciende'),
(2, 'Cinturón de seguridad', 1, NULL),
(3, 'Nivel de combustible', 1, NULL),
(3, 'Estado de orugas', 1, NULL),
(4, 'Nivel de aceite', 1, NULL),
(4, 'Sistema hidráulico', 1, NULL),
(5, 'Neumáticos', 0, 'Neumático trasero izquierdo liso'),
(5, 'Frenos', 1, NULL);

INSERT INTO incidentes (empresa_id, obra_id, fecha, hora, tipo, gravedad, descripcion, lugar, trabajador_id, dias_perdidos, acciones, estado, creado_por) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:30', 'cuasi_accidente', 'leve', 'Trabajador estuvo cerca de zona de carguío sin señalización adecuada.', 'Frente de excavación', 4, 0, 'Se reforzó señalización y charla de seguridad.', 'cerrado', 1),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 12 DAY),'15:45', 'incidente', 'moderado', 'Derrame menor de combustible durante carga.', 'Zona de abastecimiento', NULL, 0, 'Contención con material absorbente. Capacitación a operadores.', 'investigando', 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 25 DAY),'08:20', 'accidente', 'leve', 'Golpe en mano al manipular herramienta.', 'Taller de faena', 6, 2, 'Atención en mutualidad. Reposo 2 días.', 'cerrado', 1),
(2, 4, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '11:00', 'cuasi_accidente', 'leve', 'Caída de material desde altura sin afectar personal.', 'Excavación nivel -8m', NULL, 0, 'Instalación de malla de contención.', 'cerrado', 4),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 9 DAY), '16:10', 'incidente', 'moderado', 'Volcamiento parcial de carga en transporte.', 'Ruta H-30 km 4', 13, 1, 'Revisión de amarre de carga. Charla preventiva.', 'investigando', 4);

SET FOREIGN_KEY_CHECKS = 1;
