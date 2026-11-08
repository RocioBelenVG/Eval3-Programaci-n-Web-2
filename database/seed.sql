-- ============================================================
-- TriTransportes — SEED v1.0
-- Datos iniciales obligatorios
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- EMPRESA BASE
-- ------------------------------------------------------------
INSERT INTO empresas (id, razon_social, rut, giro, ciudad, activo) VALUES
(1, 'TRITRANSPORTES SpA', '76.000.000-0', 'Áridos, Transporte y Construcción', 'Santiago', 1);

-- ------------------------------------------------------------
-- ROLES
-- ------------------------------------------------------------
INSERT INTO roles (id, nombre, descripcion) VALUES
(1,  'Administrador General', 'Acceso total al sistema'),
(2,  'Gerencia',              'Vista gerencial, solo lectura'),
(3,  'Operaciones',           'Gestión operacional completa'),
(4,  'Supervisor',            'Partes diarios y seguridad'),
(5,  'Prevencionista',        'Seguridad y documentación'),
(6,  'Jefe de Taller',        'Gestión de maquinaria completa'),
(7,  'Mecánico',              'Registro mantenciones'),
(8,  'Operador',              'Registro combustible y faena'),
(9,  'Conductor',             'Registro viajes y combustible'),
(10, 'Cliente',               'Consulta de avance de obras'),
(11, 'Auditor',               'Lectura de todos los módulos');

-- ------------------------------------------------------------
-- PERMISOS — Gerencia (solo ver)
-- ------------------------------------------------------------
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(2, 'dashboard',   'ver', 1),
(2, 'faena',       'ver', 1),
(2, 'combustible', 'ver', 1),
(2, 'maquinaria',  'ver', 1),
(2, 'transporte',  'ver', 1),
(2, 'aridos',      'ver', 1),
(2, 'contratos',   'ver', 1),
(2, 'documentos',  'ver', 1),
(2, 'seguridad',   'ver', 1);

-- PERMISOS — Operaciones
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(3, 'dashboard',   'ver',     1),
(3, 'faena',       'ver',     1), (3, 'faena',       'crear',   1), (3, 'faena',       'editar', 1),
(3, 'combustible', 'ver',     1), (3, 'combustible', 'crear',   1), (3, 'combustible', 'editar', 1),
(3, 'maquinaria',  'ver',     1), (3, 'maquinaria',  'crear',   1), (3, 'maquinaria',  'editar', 1),
(3, 'transporte',  'ver',     1), (3, 'transporte',  'crear',   1), (3, 'transporte',  'editar', 1),
(3, 'aridos',      'ver',     1), (3, 'aridos',      'crear',   1), (3, 'aridos',      'editar', 1),
(3, 'contratos',   'ver',     1),
(3, 'documentos',  'ver',     1),
(3, 'seguridad',   'ver',     1);

-- PERMISOS — Supervisor
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(4, 'dashboard',   'ver',   1),
(4, 'faena',       'ver',   1), (4, 'faena',   'crear',  1), (4, 'faena', 'editar', 1),
(4, 'combustible', 'ver',   1),
(4, 'maquinaria',  'ver',   1),
(4, 'transporte',  'ver',   1),
(4, 'seguridad',   'ver',   1), (4, 'seguridad', 'crear', 1), (4, 'seguridad', 'editar', 1);

-- PERMISOS — Prevencionista
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(5, 'dashboard',  'ver',   1),
(5, 'seguridad',  'ver',   1), (5, 'seguridad',  'crear', 1), (5, 'seguridad', 'editar', 1),
(5, 'documentos', 'ver',   1), (5, 'documentos', 'crear', 1), (5, 'documentos', 'editar', 1);

-- PERMISOS — Jefe de Taller
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(6, 'dashboard',  'ver',   1),
(6, 'maquinaria', 'ver',   1), (6, 'maquinaria', 'crear', 1), (6, 'maquinaria', 'editar', 1),
(6, 'combustible','ver',   1);

-- PERMISOS — Mecánico
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(7, 'maquinaria', 'ver', 1), (7, 'maquinaria', 'crear', 1),
(7, 'combustible','ver', 1);

-- PERMISOS — Operador
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(8, 'dashboard',   'ver',   1),
(8, 'faena',       'ver',   1),
(8, 'combustible', 'ver',   1), (8, 'combustible', 'crear', 1);

-- PERMISOS — Conductor
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(9, 'transporte',  'ver',   1), (9, 'transporte', 'crear', 1),
(9, 'combustible', 'ver',   1), (9, 'combustible', 'crear', 1);

-- PERMISOS — Cliente
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(10, 'dashboard', 'ver', 1),
(10, 'faena',     'ver', 1),
(10, 'contratos', 'ver', 1);

-- PERMISOS — Auditor (todo en lectura)
INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES
(11, 'dashboard',   'ver', 1),
(11, 'faena',       'ver', 1),
(11, 'combustible', 'ver', 1),
(11, 'maquinaria',  'ver', 1),
(11, 'transporte',  'ver', 1),
(11, 'aridos',      'ver', 1),
(11, 'contratos',   'ver', 1),
(11, 'documentos',  'ver', 1),
(11, 'seguridad',   'ver', 1);

-- ------------------------------------------------------------
-- USUARIO ADMINISTRADOR
-- Contraseña inicial: 98697356
-- Hash bcrypt (cost 12) de la contraseña '98697356'
-- ------------------------------------------------------------
INSERT INTO usuarios (id, empresa_id, email, nombre, password_hash, rol_id, activo) VALUES
(1, 1, 'admin@tritransportes.cl', 'Administrador del Sistema',
 '$2y$12$g4s79YrwBPwIlFuz0LLEzudf2xKxcRk.kgTk9KPPMKJgAptxvnSKe', 1, 1);

-- ------------------------------------------------------------
-- CARGOS
-- ------------------------------------------------------------
INSERT INTO cargos (nombre, area) VALUES
('Gerente General',       'Dirección'),
('Administrador',         'Administración'),
('Jefe de Obras',         'Operaciones'),
('Supervisor de Faena',   'Operaciones'),
('Operador de Maquinaria','Operaciones'),
('Conductor',             'Transporte'),
('Mecánico',              'Taller'),
('Prevencionista',        'Seguridad'),
('Bodeguero',             'Logística'),
('Administrativo',        'Administración'),
('Auxiliar de Faena',     'Operaciones');

-- ------------------------------------------------------------
-- CATEGORÍAS VEHÍCULO
-- ------------------------------------------------------------
INSERT INTO categorias_vehiculo (nombre, tipo) VALUES
('Camión Tolva',          'transporte'),
('Camión Mixer',          'transporte'),
('Camión Pluma',          'transporte'),
('Camioneta',             'liviano'),
('Furgón',                'liviano'),
('Bus',                   'liviano'),
('Retroexcavadora',       'maquinaria'),
('Motoniveladora',        'maquinaria');

-- ------------------------------------------------------------
-- MARCAS EQUIPO
-- ------------------------------------------------------------
INSERT INTO marcas_equipo (nombre) VALUES
('Caterpillar'),('Komatsu'),('Volvo'),('Liebherr'),
('Terex'),('Doosan'),('Case'),('JCB'),('Hitachi'),('John Deere');

-- ------------------------------------------------------------
-- CATEGORÍAS EQUIPO
-- ------------------------------------------------------------
INSERT INTO categorias_equipo (nombre, tipo) VALUES
('Excavadora',          'movimiento_tierra'),
('Bulldozer',           'movimiento_tierra'),
('Motoniveladora',      'vial'),
('Retroexcavadora',     'movimiento_tierra'),
('Cargador Frontal',    'movimiento_tierra'),
('Compactador',         'vial'),
('Grúa Horquilla',      'izaje'),
('Rodillo Compactador', 'vial'),
('Camión Grúa',         'izaje'),
('Perforadora',         'minería');

-- ------------------------------------------------------------
-- FUENTES COMBUSTIBLE
-- ------------------------------------------------------------
INSERT INTO fuentes_combustible (nombre, tipo) VALUES
('Estanque Propio',     'estanque_propio'),
('Bencinera Externa',   'bencinera'),
('Camión Estanque',     'camion_estanque'),
('Bidón',               'otro');

-- ------------------------------------------------------------
-- TIPOS CHECKLIST
-- ------------------------------------------------------------
INSERT INTO tipos_checklist (nombre, aplica_a) VALUES
('Pre-operacional Diario', 'equipo'),
('Inspección Semanal',     'equipo'),
('Revisión Mensual',       'equipo'),
('Pre-operacional Vehículo','vehiculo'),
('Checklist Seguridad',    'faena');

-- ------------------------------------------------------------
-- TIPOS INSPECCIÓN
-- ------------------------------------------------------------
INSERT INTO tipos_inspeccion (nombre, periodicidad) VALUES
('Inspección Mecánica',        'Mensual'),
('Inspección Eléctrica',       'Trimestral'),
('Inspección Estructural',     'Semestral'),
('Inspección de Seguridad',    'Mensual'),
('Inspección Ambiental',       'Trimestral'),
('Revisión Técnica Vehicular', 'Anual');

-- ------------------------------------------------------------
-- TIPOS DOCUMENTO
-- ------------------------------------------------------------
INSERT INTO tipos_documento (nombre, aplica_a, requiere_vencimiento) VALUES
('Contrato de Trabajo',         'trabajador',  0),
('Licencia de Conducir',        'conductor',   1),
('Certificación de Operador',   'operador',    1),
('Seguro de Vehículo',          'vehiculo',    1),
('Revisión Técnica',            'vehiculo',    1),
('Permiso de Circulación',      'vehiculo',    1),
('Contrato Comercial',          'contrato',    1),
('Certificado de Calidad',      'equipo',      1),
('Permiso de Obra',             'obra',        1),
('Seguro de Responsabilidad',   'empresa',     1),
('Patente Comercial',           'empresa',     1);

-- ------------------------------------------------------------
-- ESTADOS DOCUMENTO
-- ------------------------------------------------------------
INSERT INTO estados_documento (nombre, color, alerta_dias) VALUES
('Vigente',        '#28a745', 30),
('Por Vencer',     '#ffc107', 15),
('Vencido',        '#dc3545',  0),
('En Renovación',  '#17a2b8', 30);

-- ------------------------------------------------------------
-- ESTADOS CONTRATO
-- ------------------------------------------------------------
INSERT INTO estados_contrato (nombre, color) VALUES
('En Preparación', '#6c757d'),
('Vigente',        '#28a745'),
('En Ejecución',   '#007bff'),
('Paralizado',     '#ffc107'),
('Finalizado',     '#17a2b8'),
('Resciliado',     '#dc3545');

-- ------------------------------------------------------------
-- MATERIALES ÁRIDOS
-- ------------------------------------------------------------
INSERT INTO materiales (nombre, unidad_medida) VALUES
('Arena Fina',     'm3'),
('Arena Gruesa',   'm3'),
('Grava',          'm3'),
('Gravilla',       'm3'),
('Piedra',         'm3'),
('Roca',           'm3'),
('Base Granular',  'm3'),
('Sub-Base',       'm3'),
('Tierra de Relleno', 'm3');
