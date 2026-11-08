-- ============================================================
-- TriTransportes — SEED DEMO (MULTIEMPRESA)
-- Datos de demostración para 2 empresas, todos los módulos.
-- Fechas relativas a CURDATE() para mostrar actividad viva.
-- Importar DESPUÉS de schema.sql + módulos + seed.sql
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- CATÁLOGOS GLOBALES COMPLEMENTARIOS
-- ============================================================

-- Modelos de equipo (dependen de marcas_equipo 1=CAT,2=Komatsu,3=Volvo,4=Liebherr,9=Hitachi,10=John Deere)
INSERT INTO modelos_equipo (id, marca_id, nombre) VALUES
(1, 1, '320D'),
(2, 1, '966H'),
(3, 1, '140K'),
(4, 2, 'PC200-8'),
(5, 2, 'D65PX'),
(6, 3, 'A40G'),
(7, 3, 'EC480D'),
(8, 9, 'ZX350'),
(9, 10, '710L'),
(10, 4, 'R954C');

-- Clasificaciones de árido (dependen de materiales 1=Arena Fina,2=Arena Gruesa,3=Grava,4=Gravilla,5=Piedra,7=Base Granular)
INSERT INTO clasificaciones_arido (id, material_id, nombre, tamano) VALUES
(1, 3, 'Grava 3/4"',        '3/4"'),
(2, 4, 'Gravilla 1/2"',     '1/2"'),
(3, 1, 'Arena Fina Lavada', '0-5 mm'),
(4, 2, 'Arena Gruesa',      '0-10 mm'),
(5, 7, 'Base CBR 80',       '0-40 mm'),
(6, 5, 'Piedra Bolón',      '4-8"');

-- ============================================================
-- EMPRESAS
-- ============================================================
UPDATE empresas SET
    razon_social = 'TRITRANSPORTES SpA',
    giro = 'Áridos, Transporte y Movimiento de Tierra',
    direccion = 'Av. Industrial 1234',
    ciudad = 'Santiago',
    telefono = '+56 2 2345 6789',
    email = 'contacto@tritransportes.cl'
WHERE id = 1;

INSERT INTO empresas (id, razon_social, rut, giro, direccion, ciudad, telefono, email, activo) VALUES
(2, 'TRANSPORTES CORDILLERA LTDA.', '77.111.222-3', 'Construcción y Obras Civiles', 'Camino El Roble 4500', 'Rancagua', '+56 72 221 3344', 'contacto@cordillera.cl', 1);

-- ============================================================
-- SUCURSALES
-- ============================================================
INSERT INTO sucursales (id, empresa_id, nombre, direccion, ciudad, telefono) VALUES
(1, 1, 'Casa Matriz Santiago',  'Av. Industrial 1234',   'Santiago',   '+56 2 2345 6789'),
(2, 1, 'Planta Áridos Maipo',   'Ruta G-46 Km 12',       'Buin',       '+56 2 2345 6790'),
(3, 2, 'Casa Matriz Rancagua',  'Camino El Roble 4500',  'Rancagua',   '+56 72 221 3344'),
(4, 2, 'Taller Machalí',        'Av. San Juan 890',      'Machalí',    '+56 72 221 3345');

-- ============================================================
-- CLIENTES + CONTACTOS
-- ============================================================
INSERT INTO clientes (id, empresa_id, razon_social, rut, tipo, giro, direccion, ciudad, telefono, email) VALUES
(1, 1, 'Minera El Teniente S.A.',     '90.123.456-7', 'empresa', 'Minería del cobre',      'El Teniente s/n',   'Rancagua', '+56 72 245 0000', 'compras@elteniente.cl'),
(2, 1, 'Municipalidad de Buin',        '69.070.100-4', 'empresa', 'Administración pública', 'Plaza de Armas 1',  'Buin',     '+56 2 2821 0000', 'obras@muibuin.cl'),
(3, 1, 'Constructora del Maipo Ltda.', '76.345.678-9', 'empresa', 'Construcción',           'Av. Maipú 2200',    'Santiago', '+56 2 2555 1212', 'contacto@cmaipo.cl'),
(4, 2, 'Inmobiliaria Cordillera S.A.', '96.555.444-3', 'empresa', 'Desarrollo inmobiliario','Av. Libertador 50', 'Rancagua', '+56 72 223 8800', 'proyectos@cordillera.cl'),
(5, 2, 'Vialidad Región O''Higgins',   '61.202.000-0', 'empresa', 'Obras públicas',         'Cuevas 456',        'Rancagua', '+56 72 220 1100', 'contacto@vialidad6.cl');

INSERT INTO contactos (cliente_id, nombre, cargo, email, telefono) VALUES
(1, 'Carlos Pérez',   'Jefe de Adquisiciones', 'cperez@elteniente.cl', '+56 9 8111 2233'),
(1, 'Ana Rojas',      'Supervisora Contratos', 'arojas@elteniente.cl', '+56 9 8111 2244'),
(2, 'Luis Fuentes',   'Director de Obras',     'lfuentes@muibuin.cl',  '+56 9 8222 3344'),
(3, 'María Soto',     'Gerente de Proyectos',  'msoto@cmaipo.cl',      '+56 9 8333 4455'),
(4, 'Pedro Lagos',    'Jefe de Proyecto',      'plagos@cordillera.cl', '+56 9 8444 5566'),
(5, 'Jorge Núñez',    'Inspector Fiscal',      'jnunez@vialidad6.cl',  '+56 9 8555 6677');

-- ============================================================
-- OBRAS + CENTROS DE COSTO
-- ============================================================
INSERT INTO obras (id, empresa_id, nombre, codigo, cliente_id, descripcion, fecha_inicio, fecha_termino, estado, lat, lng) VALUES
(1, 1, 'Camino Minero El Teniente',        'OBR-001', 1, 'Construcción y mantención de camino minero 12 km', DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'activa', -34.0850, -70.3580),
(2, 1, 'Pavimentación Av. Central Buin',   'OBR-002', 2, 'Pavimentación asfáltica 3,2 km y obras de aguas lluvia', DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'activa', -33.7330, -70.7420),
(3, 1, 'Movimiento de Tierra Loteo Maipo', 'OBR-003', 3, 'Excavación y relleno para loteo habitacional', DATE_SUB(CURDATE(), INTERVAL 200 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'finalizada', -33.6100, -70.7600),
(4, 2, 'Edificio Cordillera Etapa 1',      'AND-001', 4, 'Excavación masiva y fundaciones edificio 18 pisos', DATE_SUB(CURDATE(), INTERVAL 90 DAY), DATE_ADD(CURDATE(), INTERVAL 150 DAY), 'activa', -34.1700, -70.7400),
(5, 2, 'Ruta H-30 Mejoramiento',           'AND-002', 5, 'Mejoramiento de carpeta y bermas 8 km', DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_ADD(CURDATE(), INTERVAL 100 DAY), 'activa', -34.2000, -70.8000);

INSERT INTO centros_costo (obra_id, codigo, descripcion) VALUES
(1, 'CC-001-MT',  'Movimiento de tierra'),
(1, 'CC-001-TR',  'Transporte de material'),
(2, 'CC-002-PAV', 'Pavimentación'),
(4, 'CC-AND1-EXC','Excavación masiva'),
(5, 'CC-AND2-CAR','Carpeta asfáltica');

-- ============================================================
-- CONTRATOS + ITEMS + AVANCES
-- estados_contrato: 2=Vigente, 3=En Ejecución
-- ============================================================
INSERT INTO contratos (id, empresa_id, obra_id, cliente_id, estado_id, numero, descripcion, tipo, monto_contrato, fecha_inicio, fecha_termino) VALUES
(1, 1, 1, 1, 3, 'CT-2026-001', 'Servicio de movimiento de tierra y transporte camino minero', 'Suma Alzada',  850000000, DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
(2, 1, 2, 2, 3, 'CT-2026-002', 'Pavimentación asfáltica Av. Central',                          'Precios Unitarios', 420000000, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_ADD(CURDATE(), INTERVAL 120 DAY)),
(3, 2, 4, 4, 3, 'AND-CT-001',  'Excavación masiva y fundaciones',                              'Suma Alzada',  610000000, DATE_SUB(CURDATE(), INTERVAL 90 DAY), DATE_ADD(CURDATE(), INTERVAL 150 DAY)),
(4, 2, 5, 5, 2, 'AND-CT-002',  'Mejoramiento Ruta H-30',                                       'Precios Unitarios', 380000000, DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_ADD(CURDATE(), INTERVAL 100 DAY));

INSERT INTO items_contrato (id, contrato_id, codigo, descripcion, unidad, cantidad_total, precio_unitario) VALUES
(1, 1, 'IT-01', 'Excavación en material común',     'm3',  120000, 3500),
(2, 1, 'IT-02', 'Transporte de material a botadero', 'm3',  120000, 2800),
(3, 1, 'IT-03', 'Conformación de subrasante',        'm2',   48000, 1200),
(4, 2, 'IT-01', 'Suministro y colocación asfalto',   'ton',   9500, 38000),
(5, 2, 'IT-02', 'Base estabilizada',                 'm3',   12000, 14500),
(6, 3, 'IT-01', 'Excavación masiva',                 'm3',   85000, 4200),
(7, 4, 'IT-01', 'Recapado asfáltico',                'ton',   6200, 41000);

INSERT INTO avances_contrato (contrato_id, item_id, fecha, cantidad, monto, observacion, creado_por) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 42000,  147000000, 'Avance acumulado mes 1', 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  28000,   98000000, 'Avance mes 2', 1),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  60000,  168000000, 'Transporte a botadero', 1),
(2, 4, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 2300,    87400000, 'Primer tramo asfaltado', 1),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  31000,  130200000, 'Excavación sector norte', 1),
(4, 7, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  1800,    73800000, 'Recapado km 0-3', 1);

SET FOREIGN_KEY_CHECKS = 1;
