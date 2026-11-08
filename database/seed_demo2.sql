-- ============================================================
-- TriTransportes — SEED DEMO PARTE 2
-- Personal, flota, infraestructura y usuarios
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TRABAJADORES  (cargos 1-11 del seed base)
-- ============================================================
INSERT INTO trabajadores (id, empresa_id, rut, nombre, apellido, cargo_id, telefono, email, fecha_ingreso) VALUES
-- Empresa 1 (TRITRANSPORTES)
(1, 1, '12.345.678-9', 'Roberto',  'Muñoz',     3, '+56 9 9111 0001', 'rmunoz@tritransportes.cl',   DATE_SUB(CURDATE(), INTERVAL 800 DAY)),
(2, 1, '13.456.789-0', 'Sergio',   'Castro',    4, '+56 9 9111 0002', 'scastro@tritransportes.cl',  DATE_SUB(CURDATE(), INTERVAL 600 DAY)),
(3, 1, '14.567.890-1', 'Manuel',   'Tapia',     5, '+56 9 9111 0003', 'mtapia@tritransportes.cl',   DATE_SUB(CURDATE(), INTERVAL 500 DAY)),
(4, 1, '15.678.901-2', 'Jorge',    'Vega',      6, '+56 9 9111 0004', 'jvega@tritransportes.cl',    DATE_SUB(CURDATE(), INTERVAL 450 DAY)),
(5, 1, '16.789.012-3', 'Patricio', 'Díaz',      6, '+56 9 9111 0005', 'pdiaz@tritransportes.cl',    DATE_SUB(CURDATE(), INTERVAL 400 DAY)),
(6, 1, '17.890.123-4', 'Andrés',   'Salinas',   7, '+56 9 9111 0006', 'asalinas@tritransportes.cl', DATE_SUB(CURDATE(), INTERVAL 350 DAY)),
(7, 1, '18.901.234-5', 'Felipe',   'Morales',   8, '+56 9 9111 0007', 'fmorales@tritransportes.cl', DATE_SUB(CURDATE(), INTERVAL 300 DAY)),
(8, 1, '19.012.345-6', 'Cristián', 'Henríquez', 5, '+56 9 9111 0008', 'chenriquez@tritransportes.cl',DATE_SUB(CURDATE(), INTERVAL 250 DAY)),
-- Empresa 2 (ANDES)
(9,  2, '11.222.333-4', 'Daniel',  'Araya',     3, '+56 9 9222 0001', 'daraya@cordillera.cl',     DATE_SUB(CURDATE(), INTERVAL 700 DAY)),
(10, 2, '10.333.444-5', 'Víctor',  'Riquelme',  6, '+56 9 9222 0002', 'vriquelme@cordillera.cl',  DATE_SUB(CURDATE(), INTERVAL 520 DAY)),
(11, 2, '12.444.555-6', 'Esteban', 'Cáceres',   5, '+56 9 9222 0003', 'ecaceres@cordillera.cl',   DATE_SUB(CURDATE(), INTERVAL 410 DAY)),
(12, 2, '13.555.666-7', 'Rodrigo', 'Vergara',   4, '+56 9 9222 0004', 'rvergara@cordillera.cl',   DATE_SUB(CURDATE(), INTERVAL 360 DAY)),
(13, 2, '14.666.777-8', 'Mauricio','Pino',      8, '+56 9 9222 0005', 'mpino@cordillera.cl',      DATE_SUB(CURDATE(), INTERVAL 300 DAY));

-- ============================================================
-- CONDUCTORES  (referencian trabajador_id)
-- ============================================================
INSERT INTO conductores (id, trabajador_id, licencia_tipo, licencia_numero, licencia_vence) VALUES
(1, 3, 'A-4', 'LC-145678', DATE_ADD(CURDATE(), INTERVAL 200 DAY)),
(2, 7, 'A-5', 'LC-189012', DATE_ADD(CURDATE(), INTERVAL 25 DAY)),
(3, 8, 'A-4', 'LC-190123', DATE_ADD(CURDATE(), INTERVAL 320 DAY)),
(4, 11,'A-5', 'LC-124445', DATE_ADD(CURDATE(), INTERVAL 150 DAY)),
(5, 13,'A-4', 'LC-146667', DATE_ADD(CURDATE(), INTERVAL 18 DAY));

-- ============================================================
-- OPERADORES  (referencian trabajador_id)
-- ============================================================
INSERT INTO operadores (id, trabajador_id, certificacion, certif_numero, certif_vence) VALUES
(1, 4, 'Operador Retroexcavadora', 'CERT-4001', DATE_ADD(CURDATE(), INTERVAL 280 DAY)),
(2, 5, 'Operador Excavadora',      'CERT-5002', DATE_ADD(CURDATE(), INTERVAL 40 DAY)),
(3, 10,'Operador Cargador Frontal','CERT-1003', DATE_ADD(CURDATE(), INTERVAL 220 DAY));

-- ============================================================
-- VEHÍCULOS  (categorias_vehiculo 1-8)
-- ============================================================
INSERT INTO vehiculos (id, empresa_id, patente, marca, modelo, anio, categoria_id, color, odometro) VALUES
(1, 1, 'JKLM12', 'Mercedes-Benz', 'Actros 3344', 2021, 1, 'Blanco', 145200),
(2, 1, 'JKLM34', 'Volvo',         'FMX 460',     2020, 1, 'Amarillo',198400),
(3, 1, 'JKLM56', 'Scania',        'G450',        2022, 1, 'Rojo',     89300),
(4, 1, 'JKLM78', 'Toyota',        'Hilux',       2023, 4, 'Gris',     42100),
(5, 1, 'JKLM90', 'Ford',          'F-350',       2019, 3, 'Blanco',  176500),
(6, 2, 'PQRS12', 'Volvo',         'FH 540',      2021, 1, 'Azul',    132000),
(7, 2, 'PQRS34', 'Iveco',         'Trakker',     2020, 1, 'Blanco',  154300),
(8, 2, 'PQRS56', 'Nissan',        'Navara',      2022, 4, 'Negro',    51200);

-- ============================================================
-- EQUIPOS  (marcas 1-10, categorias 1-10, modelos 1-10)
-- ============================================================
INSERT INTO equipos (id, empresa_id, codigo, nombre, marca_id, modelo_id, categoria_id, anio, numero_serie, horometro) VALUES
(1, 1, 'EXC-01', 'Excavadora CAT 320D',        1, 1, 1, 2019, 'CAT320D-0091', 8420.5),
(2, 1, 'CAR-01', 'Cargador Frontal CAT 966H',  1, 2, 5, 2018, 'CAT966H-0042', 11250.0),
(3, 1, 'EXC-02', 'Excavadora Komatsu PC200',   2, 4, 1, 2020, 'KOM200-1187',  6310.0),
(4, 1, 'MOT-01', 'Motoniveladora CAT 140K',    1, 3, 3, 2017, 'CAT140K-0033', 9870.0),
(5, 1, 'BUL-01', 'Bulldozer Komatsu D65',      2, 5, 2, 2019, 'KOMD65-0078',  7640.0),
(6, 2, 'EXC-A1', 'Excavadora Volvo EC480',     3, 7, 1, 2021, 'VOLEC480-201', 4120.0),
(7, 2, 'CAR-A1', 'Cargador Hitachi ZX350',     9, 8, 5, 2020, 'HITZX350-118', 5560.0),
(8, 2, 'RET-A1', 'Retroexcavadora JD 710L',   10, 9, 4, 2022, 'JD710L-0455',  2980.0);

-- ============================================================
-- ESTANQUES Y PROVEEDORES DE COMBUSTIBLE
-- ============================================================
INSERT INTO estanques (id, empresa_id, nombre, capacidad, tipo_combustible, ubicacion) VALUES
(1, 1, 'Estanque Central Maipo', 20000, 'Petróleo', 'Planta Áridos Maipo'),
(2, 1, 'Estanque Faena Teniente', 10000, 'Petróleo', 'Camino Minero El Teniente'),
(3, 2, 'Estanque Taller Machalí', 15000, 'Petróleo', 'Taller Machalí');

INSERT INTO proveedores_combustible (id, empresa_id, nombre, rut, telefono, email) VALUES
(1, 1, 'Copec Distribución S.A.',  '99.500.000-1', '+56 2 2461 7000', 'ventas@copec.cl'),
(2, 1, 'Petrobras Chile',          '99.501.000-2', '+56 2 2460 8000', 'ventas@petrobras.cl'),
(3, 2, 'Shell Combustibles',       '99.502.000-3', '+56 2 2462 9000', 'ventas@shell.cl');

-- ============================================================
-- POZOS ÁRIDOS
-- ============================================================
INSERT INTO pozos (id, empresa_id, nombre, codigo, ubicacion, lat, lng) VALUES
(1, 1, 'Pozo Río Maipo',     'POZ-01', 'Ribera Río Maipo, Buin', -33.7000, -70.7300),
(2, 1, 'Cantera Cerro Negro','POZ-02', 'Ruta G-46 Km 18',        -33.6800, -70.7100),
(3, 2, 'Pozo Cachapoal',     'POZ-A1', 'Ribera Río Cachapoal',   -34.1800, -70.7600);

-- ============================================================
-- USUARIOS ADICIONALES
-- password de todos los usuarios demo: 98697356
-- hash bcrypt cost 12 de '98697356'
-- ============================================================
INSERT INTO usuarios (id, empresa_id, trabajador_id, email, nombre, password_hash, rol_id, activo) VALUES
(2, 1, 1, 'operaciones@tritransportes.cl', 'Roberto Muñoz (Operaciones)', '$2y$12$g4s79YrwBPwIlFuz0LLEzudf2xKxcRk.kgTk9KPPMKJgAptxvnSKe', 3, 1),
(3, 1, 8, 'prevencion@tritransportes.cl',  'Cristián Henríquez (Prevención)', '$2y$12$g4s79YrwBPwIlFuz0LLEzudf2xKxcRk.kgTk9KPPMKJgAptxvnSKe', 5, 1),
(4, 2, 9, 'operaciones@cordillera.cl',      'Daniel Araya (Operaciones)', '$2y$12$g4s79YrwBPwIlFuz0LLEzudf2xKxcRk.kgTk9KPPMKJgAptxvnSKe', 3, 1);

SET FOREIGN_KEY_CHECKS = 1;
