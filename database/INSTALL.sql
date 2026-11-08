-- ============================================================
-- TriTransportes — INSTALADOR COMPLETO (esquema + datos demo)
-- Importar este único archivo en phpMyAdmin del hosting.
-- Crea todas las tablas y carga datos de demostración
-- para 2 empresas en todos los módulos.
-- ============================================================

-- ===== schema.sql =====
-- ============================================================
-- TriTransportes — SCHEMA COMPLETO v1.0
-- Fase 0: Tablas maestras + usuarios + auditoría
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- EMPRESAS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS empresas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(150) NOT NULL,
    rut         VARCHAR(20)  NOT NULL,
    giro        VARCHAR(150),
    direccion   VARCHAR(200),
    ciudad      VARCHAR(100),
    telefono    VARCHAR(30),
    email       VARCHAR(120),
    logo        VARCHAR(255),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rut (rut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ROLES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion VARCHAR(255),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PERMISOS (matriz rol × módulo × acción)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permisos (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id   INT UNSIGNED NOT NULL,
    modulo   VARCHAR(50)  NOT NULL,
    accion   VARCHAR(20)  NOT NULL,
    permitido TINYINT(1)  NOT NULL DEFAULT 0,
    UNIQUE KEY uq_perm (rol_id, modulo, accion),
    CONSTRAINT fk_perm_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SUCURSALES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sucursales (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    direccion   VARCHAR(200),
    ciudad      VARCHAR(100),
    telefono    VARCHAR(30),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_suc_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CARGOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cargos (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    area   VARCHAR(100),
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TRABAJADORES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trabajadores (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    rut         VARCHAR(20)  NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    apellido    VARCHAR(100) NOT NULL,
    cargo_id    INT UNSIGNED,
    telefono    VARCHAR(30),
    email       VARCHAR(120),
    fecha_ingreso DATE,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rut (empresa_id, rut),
    CONSTRAINT fk_trab_emp  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_trab_cargo FOREIGN KEY (cargo_id)  REFERENCES cargos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CONDUCTORES AUTORIZADOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conductores (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trabajador_id   INT UNSIGNED NOT NULL,
    licencia_tipo   VARCHAR(10),
    licencia_numero VARCHAR(30),
    licencia_vence  DATE,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trab (trabajador_id),
    CONSTRAINT fk_cond_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- OPERADORES AUTORIZADOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS operadores (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trabajador_id   INT UNSIGNED NOT NULL,
    certificacion   VARCHAR(150),
    certif_numero   VARCHAR(50),
    certif_vence    DATE,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trab (trabajador_id),
    CONSTRAINT fk_oper_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CLIENTES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    razon_social VARCHAR(150) NOT NULL,
    rut         VARCHAR(20),
    tipo        ENUM('empresa','persona_natural') NOT NULL DEFAULT 'empresa',
    giro        VARCHAR(150),
    direccion   VARCHAR(200),
    ciudad      VARCHAR(100),
    telefono    VARCHAR(30),
    email       VARCHAR(120),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cli_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CONTACTOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contactos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    nombre     VARCHAR(100) NOT NULL,
    cargo      VARCHAR(100),
    email      VARCHAR(120),
    telefono   VARCHAR(30),
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_cont_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ESTADOS CONTRATO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estados_contrato (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80)  NOT NULL,
    color  VARCHAR(20)  NOT NULL DEFAULT '#6c757d',
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- OBRAS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS obras (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    nombre      VARCHAR(150) NOT NULL,
    codigo      VARCHAR(30),
    cliente_id  INT UNSIGNED,
    descripcion TEXT,
    fecha_inicio DATE,
    fecha_termino DATE,
    estado      ENUM('activa','pausada','finalizada') NOT NULL DEFAULT 'activa',
    lat         DECIMAL(10,7),
    lng         DECIMAL(10,7),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_obra_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_obra_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CENTROS DE COSTO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS centros_costo (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id     INT UNSIGNED NOT NULL,
    codigo      VARCHAR(30)  NOT NULL,
    descripcion VARCHAR(200),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_cc_obra FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CONTRATOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contratos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    obra_id         INT UNSIGNED,
    cliente_id      INT UNSIGNED,
    estado_id       INT UNSIGNED,
    numero          VARCHAR(50),
    descripcion     VARCHAR(255),
    tipo            VARCHAR(80),
    monto_contrato  DECIMAL(15,2) DEFAULT 0,
    fecha_inicio    DATE,
    fecha_termino   DATE,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cont_emp   FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_cont_obra  FOREIGN KEY (obra_id)    REFERENCES obras(id)    ON DELETE SET NULL,
    CONSTRAINT fk_contratos_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_cont_est   FOREIGN KEY (estado_id)  REFERENCES estados_contrato(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CATEGORÍAS VEHÍCULO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias_vehiculo (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    tipo   ENUM('transporte','maquinaria','liviano') NOT NULL DEFAULT 'transporte',
    activo TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- VEHÍCULOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vehiculos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT UNSIGNED NOT NULL,
    patente      VARCHAR(10)  NOT NULL,
    marca        VARCHAR(60),
    modelo       VARCHAR(80),
    anio         SMALLINT UNSIGNED,
    categoria_id INT UNSIGNED,
    color        VARCHAR(40),
    odometro     INT UNSIGNED DEFAULT 0,
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patente (empresa_id, patente),
    CONSTRAINT fk_veh_emp  FOREIGN KEY (empresa_id)   REFERENCES empresas(id),
    CONSTRAINT fk_veh_cat  FOREIGN KEY (categoria_id) REFERENCES categorias_vehiculo(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- MARCAS EQUIPO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS marcas_equipo (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    activo TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- MODELOS EQUIPO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS modelos_equipo (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marca_id INT UNSIGNED NOT NULL,
    nombre   VARCHAR(100) NOT NULL,
    activo   TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_mod_marca FOREIGN KEY (marca_id) REFERENCES marcas_equipo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CATEGORÍAS EQUIPO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias_equipo (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    tipo   VARCHAR(60),
    activo TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- EQUIPOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    codigo          VARCHAR(30)  NOT NULL,
    nombre          VARCHAR(120) NOT NULL,
    marca_id        INT UNSIGNED,
    modelo_id       INT UNSIGNED,
    categoria_id    INT UNSIGNED,
    anio            SMALLINT UNSIGNED,
    numero_serie    VARCHAR(60),
    horometro       DECIMAL(10,1) DEFAULT 0,
    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_codigo (empresa_id, codigo),
    CONSTRAINT fk_eq_emp   FOREIGN KEY (empresa_id)   REFERENCES empresas(id),
    CONSTRAINT fk_eq_marca FOREIGN KEY (marca_id)     REFERENCES marcas_equipo(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_mod   FOREIGN KEY (modelo_id)    REFERENCES modelos_equipo(id) ON DELETE SET NULL,
    CONSTRAINT fk_eq_cat   FOREIGN KEY (categoria_id) REFERENCES categorias_equipo(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FUENTES COMBUSTIBLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fuentes_combustible (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    tipo   ENUM('estanque_propio','bencinera','camion_estanque','otro') NOT NULL DEFAULT 'otro',
    activo TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PROVEEDORES COMBUSTIBLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS proveedores_combustible (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    nombre      VARCHAR(120) NOT NULL,
    rut         VARCHAR(20),
    telefono    VARCHAR(30),
    email       VARCHAR(120),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prov_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ESTANQUES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estanques (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    capacidad   DECIMAL(10,2) DEFAULT 0,
    tipo_combustible VARCHAR(30) DEFAULT 'Petróleo',
    ubicacion   VARCHAR(200),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_est_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- POZOS ÁRIDOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pozos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    nombre      VARCHAR(120) NOT NULL,
    codigo      VARCHAR(30),
    ubicacion   VARCHAR(200),
    lat         DECIMAL(10,7),
    lng         DECIMAL(10,7),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pozo_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- MATERIALES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS materiales (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    unidad_medida  VARCHAR(20)  NOT NULL DEFAULT 'm3',
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CLASIFICACIONES ÁRIDO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clasificaciones_arido (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_id INT UNSIGNED NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    tamano      VARCHAR(30),
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_clas_mat FOREIGN KEY (material_id) REFERENCES materiales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TIPOS CHECKLIST
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_checklist (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(100) NOT NULL,
    aplica_a  VARCHAR(60) DEFAULT 'equipo',
    activo    TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TIPOS INSPECCIÓN
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_inspeccion (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL,
    periodicidad VARCHAR(30),
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TIPOS DOCUMENTO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_documento (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre                VARCHAR(100) NOT NULL,
    aplica_a              VARCHAR(60),
    requiere_vencimiento  TINYINT(1)  NOT NULL DEFAULT 0,
    activo                TINYINT(1)  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ESTADOS DOCUMENTO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estados_documento (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(80)  NOT NULL,
    color       VARCHAR(20)  NOT NULL DEFAULT '#6c757d',
    alerta_dias INT UNSIGNED DEFAULT 30,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- USUARIOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT UNSIGNED NOT NULL,
    trabajador_id INT UNSIGNED,
    email         VARCHAR(120) NOT NULL,
    nombre        VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol_id        INT UNSIGNED NOT NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    CONSTRAINT fk_usr_emp  FOREIGN KEY (empresa_id)    REFERENCES empresas(id),
    CONSTRAINT fk_usr_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id) ON DELETE SET NULL,
    CONSTRAINT fk_usr_rol  FOREIGN KEY (rol_id)        REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- AUDITORÍA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT UNSIGNED,
    usuario_id   INT UNSIGNED,
    modulo       VARCHAR(50)  NOT NULL,
    accion       VARCHAR(50)  NOT NULL,
    entidad_tipo VARCHAR(50),
    entidad_id   INT UNSIGNED,
    datos_antes  JSON,
    datos_despues JSON,
    ip           VARCHAR(45),
    fecha        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aud_fecha  (fecha),
    INDEX idx_aud_usr    (usuario_id),
    INDEX idx_aud_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== m02_transporte.sql =====
-- ============================================================
-- TriTransportes — TABLAS MÓDULO M01 TRANSPORTE
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS viajes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    obra_id         INT UNSIGNED,
    contrato_id     INT UNSIGNED,
    vehiculo_id     INT UNSIGNED NOT NULL,
    conductor_id    INT UNSIGNED NOT NULL,
    fecha           DATE NOT NULL,
    hora_salida     TIME,
    hora_llegada    TIME,
    origen          VARCHAR(200),
    destino         VARCHAR(200),
    km_inicio       INT UNSIGNED DEFAULT 0,
    km_fin          INT UNSIGNED DEFAULT 0,
    material_id     INT UNSIGNED,
    cantidad        DECIMAL(10,2),
    unidad          VARCHAR(30),
    estado          ENUM('programado','en_ruta','completado','cancelado') NOT NULL DEFAULT 'programado',
    observaciones   TEXT,
    lat_origen      DECIMAL(10,7),
    lng_origen      DECIMAL(10,7),
    lat_destino     DECIMAL(10,7),
    lng_destino     DECIMAL(10,7),
    creado_por      INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_viaj_emp  FOREIGN KEY (empresa_id)  REFERENCES empresas(id),
    CONSTRAINT fk_viaj_obra FOREIGN KEY (obra_id)     REFERENCES obras(id)       ON DELETE SET NULL,
    CONSTRAINT fk_viaj_veh  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
    CONSTRAINT fk_viaj_cond FOREIGN KEY (conductor_id)REFERENCES conductores(id),
    CONSTRAINT fk_viaj_mat  FOREIGN KEY (material_id) REFERENCES materiales(id)  ON DELETE SET NULL,
    CONSTRAINT fk_viaj_usr  FOREIGN KEY (creado_por)  REFERENCES usuarios(id)    ON DELETE SET NULL,
    INDEX idx_viaj_emp_f  (empresa_id, fecha),
    INDEX idx_viaj_veh    (vehiculo_id),
    INDEX idx_viaj_cond   (conductor_id),
    INDEX idx_viaj_obra   (obra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guias_despacho (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    viaje_id        INT UNSIGNED,
    obra_id         INT UNSIGNED,
    numero_guia     VARCHAR(50) NOT NULL,
    fecha           DATE NOT NULL,
    vehiculo_id     INT UNSIGNED,
    conductor_id    INT UNSIGNED,
    material_id     INT UNSIGNED,
    cantidad        DECIMAL(10,2),
    unidad          VARCHAR(30),
    origen          VARCHAR(200),
    destino         VARCHAR(200),
    receptor        VARCHAR(150),
    rut_receptor    VARCHAR(12),
    observaciones   TEXT,
    foto_ruta       VARCHAR(255),
    creado_por      INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_guia_emp  FOREIGN KEY (empresa_id)  REFERENCES empresas(id),
    CONSTRAINT fk_guia_viaj FOREIGN KEY (viaje_id)    REFERENCES viajes(id)      ON DELETE SET NULL,
    CONSTRAINT fk_guia_obra FOREIGN KEY (obra_id)     REFERENCES obras(id)       ON DELETE SET NULL,
    CONSTRAINT fk_guia_veh  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)   ON DELETE SET NULL,
    CONSTRAINT fk_guia_cond FOREIGN KEY (conductor_id)REFERENCES conductores(id) ON DELETE SET NULL,
    CONSTRAINT fk_guia_mat  FOREIGN KEY (material_id) REFERENCES materiales(id)  ON DELETE SET NULL,
    CONSTRAINT fk_guia_usr  FOREIGN KEY (creado_por)  REFERENCES usuarios(id)    ON DELETE SET NULL,
    INDEX idx_guia_emp_f  (empresa_id, fecha),
    INDEX idx_guia_num    (numero_guia),
    INDEX idx_guia_obra   (obra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== m01_operacional.sql =====
-- ============================================================
-- TriTransportes — TABLAS OPERACIONALES FASE 1
-- M02 Faena | M03 Combustible | M04 Maquinaria
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- M03 GESTIÓN DE COMBUSTIBLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cargas_combustible (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id       INT UNSIGNED NOT NULL,
    obra_id          INT UNSIGNED,
    contrato_id      INT UNSIGNED,
    fecha            DATE NOT NULL,
    tipo_unidad      ENUM('vehiculo','equipo') NOT NULL DEFAULT 'vehiculo',
    vehiculo_id      INT UNSIGNED,
    equipo_id        INT UNSIGNED,
    conductor_id     INT UNSIGNED,
    operador_id      INT UNSIGNED,
    litros           DECIMAL(10,2) NOT NULL,
    precio_litro     DECIMAL(8,2),
    tipo_combustible VARCHAR(30) DEFAULT 'Petróleo',
    horometro        DECIMAL(10,1),
    odometro         INT UNSIGNED,
    fuente_id        INT UNSIGNED,
    estanque_id      INT UNSIGNED,
    proveedor_id     INT UNSIGNED,
    lat              DECIMAL(10,7),
    lng              DECIMAL(10,7),
    foto_ruta        VARCHAR(255),
    observacion      TEXT,
    creado_por       INT UNSIGNED,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_comb_emp    FOREIGN KEY (empresa_id)   REFERENCES empresas(id),
    CONSTRAINT fk_comb_obra   FOREIGN KEY (obra_id)      REFERENCES obras(id)    ON DELETE SET NULL,
    CONSTRAINT fk_comb_veh    FOREIGN KEY (vehiculo_id)  REFERENCES vehiculos(id) ON DELETE SET NULL,
    CONSTRAINT fk_comb_eq     FOREIGN KEY (equipo_id)    REFERENCES equipos(id)  ON DELETE SET NULL,
    CONSTRAINT fk_comb_fuente FOREIGN KEY (fuente_id)    REFERENCES fuentes_combustible(id) ON DELETE SET NULL,
    CONSTRAINT fk_comb_est    FOREIGN KEY (estanque_id)  REFERENCES estanques(id) ON DELETE SET NULL,
    CONSTRAINT fk_comb_prov   FOREIGN KEY (proveedor_id) REFERENCES proveedores_combustible(id) ON DELETE SET NULL,
    CONSTRAINT fk_comb_usr    FOREIGN KEY (creado_por)   REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_cc_fecha    (fecha),
    INDEX idx_cc_emp_f    (empresa_id, fecha),
    INDEX idx_cc_veh      (vehiculo_id),
    INDEX idx_cc_eq       (equipo_id),
    INDEX idx_cc_obra     (obra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- M04 GESTIÓN DE MAQUINARIA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mantenciones (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT UNSIGNED NOT NULL,
    equipo_id     INT UNSIGNED NOT NULL,
    tipo          ENUM('preventiva','correctiva') NOT NULL DEFAULT 'preventiva',
    fecha         DATE NOT NULL,
    horometro     DECIMAL(10,1),
    descripcion   TEXT,
    tecnico       VARCHAR(100),
    costo_total   DECIMAL(12,2) DEFAULT 0,
    estado        ENUM('pendiente','en_proceso','completada') NOT NULL DEFAULT 'pendiente',
    observaciones TEXT,
    creado_por    INT UNSIGNED,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mant_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_mant_eq  FOREIGN KEY (equipo_id)  REFERENCES equipos(id),
    CONSTRAINT fk_mant_usr FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_mant_eq_f (equipo_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mantencion_repuestos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mantencion_id  INT UNSIGNED NOT NULL,
    descripcion    VARCHAR(200) NOT NULL,
    cantidad       DECIMAL(8,2) NOT NULL DEFAULT 1,
    costo_unitario DECIMAL(10,2) DEFAULT 0,
    CONSTRAINT fk_rep_mant FOREIGN KEY (mantencion_id) REFERENCES mantenciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detenciones_equipo (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT UNSIGNED NOT NULL,
    equipo_id    INT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin    DATETIME,
    motivo       VARCHAR(255),
    tipo         ENUM('mantencion','falla','accidente','otro') DEFAULT 'falla',
    creado_por   INT UNSIGNED,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_det_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_det_eq  FOREIGN KEY (equipo_id)  REFERENCES equipos(id),
    INDEX idx_det_eq (equipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- M02 REPORTES DIARIOS DE FAENA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partes_diarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT UNSIGNED NOT NULL,
    obra_id       INT UNSIGNED,
    contrato_id   INT UNSIGNED,
    fecha         DATE NOT NULL,
    supervisor_id INT UNSIGNED,
    clima         VARCHAR(50),
    estado        ENUM('borrador','cerrado') NOT NULL DEFAULT 'borrador',
    observaciones TEXT,
    firma_b64     MEDIUMTEXT,
    lat           DECIMAL(10,7),
    lng           DECIMAL(10,7),
    creado_por    INT UNSIGNED,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pd_emp  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_pd_obra FOREIGN KEY (obra_id)    REFERENCES obras(id)    ON DELETE SET NULL,
    CONSTRAINT fk_pd_usr  FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_pd_emp_f    (empresa_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parte_actividades (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parte_id    INT UNSIGNED NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    unidad      VARCHAR(30),
    cantidad    DECIMAL(10,2),
    CONSTRAINT fk_pact_p FOREIGN KEY (parte_id) REFERENCES partes_diarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parte_personal (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parte_id      INT UNSIGNED NOT NULL,
    trabajador_id INT UNSIGNED NOT NULL,
    horas         DECIMAL(5,1),
    CONSTRAINT fk_pper_p    FOREIGN KEY (parte_id)      REFERENCES partes_diarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pper_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parte_equipos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parte_id          INT UNSIGNED NOT NULL,
    equipo_id         INT UNSIGNED NOT NULL,
    horas_trabajo     DECIMAL(5,1) DEFAULT 0,
    horas_detenciones DECIMAL(5,1) DEFAULT 0,
    CONSTRAINT fk_peq_p  FOREIGN KEY (parte_id) REFERENCES partes_diarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_peq_eq FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parte_fotos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parte_id    INT UNSIGNED NOT NULL,
    ruta_foto   VARCHAR(255) NOT NULL,
    descripcion VARCHAR(200),
    CONSTRAINT fk_pfoto_p FOREIGN KEY (parte_id) REFERENCES partes_diarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== m03_aridos.sql =====
-- ============================================================
-- TriTransportes — TABLAS MÓDULO M05 ÁRIDOS
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Producción en planta/cantera
CREATE TABLE IF NOT EXISTS produccion_aridos (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id            INT UNSIGNED NOT NULL,
    obra_id               INT UNSIGNED,
    material_id           INT UNSIGNED NOT NULL,
    clasificacion_id      INT UNSIGNED,
    fecha                 DATE NOT NULL,
    cantidad              DECIMAL(12,2) NOT NULL,
    unidad                VARCHAR(20)   NOT NULL DEFAULT 'ton',
    equipo_id             INT UNSIGNED,
    operador_id           INT UNSIGNED,
    turno                 ENUM('mañana','tarde','noche') DEFAULT 'mañana',
    observaciones         TEXT,
    creado_por            INT UNSIGNED,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prod_emp   FOREIGN KEY (empresa_id)       REFERENCES empresas(id),
    CONSTRAINT fk_prod_obra  FOREIGN KEY (obra_id)          REFERENCES obras(id)          ON DELETE SET NULL,
    CONSTRAINT fk_prod_mat   FOREIGN KEY (material_id)      REFERENCES materiales(id),
    CONSTRAINT fk_prod_clas  FOREIGN KEY (clasificacion_id) REFERENCES clasificaciones_arido(id) ON DELETE SET NULL,
    CONSTRAINT fk_prod_eq    FOREIGN KEY (equipo_id)        REFERENCES equipos(id)        ON DELETE SET NULL,
    CONSTRAINT fk_prod_op    FOREIGN KEY (operador_id)      REFERENCES operadores(id)     ON DELETE SET NULL,
    CONSTRAINT fk_prod_usr   FOREIGN KEY (creado_por)       REFERENCES usuarios(id)       ON DELETE SET NULL,
    INDEX idx_prod_emp_f (empresa_id, fecha),
    INDEX idx_prod_mat   (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock actual por material/obra (tabla de saldo)
CREATE TABLE IF NOT EXISTS stock_aridos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id       INT UNSIGNED NOT NULL,
    obra_id          INT UNSIGNED,
    material_id      INT UNSIGNED NOT NULL,
    clasificacion_id INT UNSIGNED,
    cantidad         DECIMAL(14,2) NOT NULL DEFAULT 0,
    unidad           VARCHAR(20)   NOT NULL DEFAULT 'ton',
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_emp  FOREIGN KEY (empresa_id)       REFERENCES empresas(id),
    CONSTRAINT fk_stock_obra FOREIGN KEY (obra_id)          REFERENCES obras(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_mat  FOREIGN KEY (material_id)      REFERENCES materiales(id),
    CONSTRAINT fk_stock_clas FOREIGN KEY (clasificacion_id) REFERENCES clasificaciones_arido(id) ON DELETE SET NULL,
    UNIQUE KEY uq_stock (empresa_id, obra_id, material_id, clasificacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Despachos de áridos a clientes/obras
CREATE TABLE IF NOT EXISTS despachos_aridos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id       INT UNSIGNED NOT NULL,
    obra_id          INT UNSIGNED,
    cliente_id       INT UNSIGNED,
    material_id      INT UNSIGNED NOT NULL,
    clasificacion_id INT UNSIGNED,
    fecha            DATE NOT NULL,
    guia_numero      VARCHAR(50),
    vehiculo_id      INT UNSIGNED,
    conductor_id     INT UNSIGNED,
    cantidad         DECIMAL(12,2) NOT NULL,
    unidad           VARCHAR(20)   NOT NULL DEFAULT 'ton',
    precio_unitario  DECIMAL(10,2) DEFAULT 0,
    destino          VARCHAR(200),
    receptor         VARCHAR(150),
    rut_receptor     VARCHAR(12),
    observaciones    TEXT,
    foto_ruta        VARCHAR(255),
    creado_por       INT UNSIGNED,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_desp_emp   FOREIGN KEY (empresa_id)       REFERENCES empresas(id),
    CONSTRAINT fk_desp_obra  FOREIGN KEY (obra_id)          REFERENCES obras(id)          ON DELETE SET NULL,
    CONSTRAINT fk_desp_cli   FOREIGN KEY (cliente_id)       REFERENCES clientes(id)       ON DELETE SET NULL,
    CONSTRAINT fk_desp_mat   FOREIGN KEY (material_id)      REFERENCES materiales(id),
    CONSTRAINT fk_desp_clas  FOREIGN KEY (clasificacion_id) REFERENCES clasificaciones_arido(id) ON DELETE SET NULL,
    CONSTRAINT fk_desp_veh   FOREIGN KEY (vehiculo_id)      REFERENCES vehiculos(id)      ON DELETE SET NULL,
    CONSTRAINT fk_desp_cond  FOREIGN KEY (conductor_id)     REFERENCES conductores(id)    ON DELETE SET NULL,
    CONSTRAINT fk_desp_usr   FOREIGN KEY (creado_por)       REFERENCES usuarios(id)       ON DELETE SET NULL,
    INDEX idx_desp_emp_f (empresa_id, fecha),
    INDEX idx_desp_mat   (material_id),
    INDEX idx_desp_obra  (obra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== m04_contratos.sql =====
-- ============================================================
-- TriTransportes — TABLAS MÓDULO M06 CONTRATOS
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Ítems del contrato (partidas presupuestadas)
CREATE TABLE IF NOT EXISTS items_contrato (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrato_id   INT UNSIGNED NOT NULL,
    codigo        VARCHAR(30),
    descripcion   VARCHAR(255) NOT NULL,
    unidad        VARCHAR(30),
    cantidad_total DECIMAL(12,2) DEFAULT 0,
    precio_unitario DECIMAL(12,2) DEFAULT 0,
    monto_total   DECIMAL(15,2) GENERATED ALWAYS AS (cantidad_total * precio_unitario) STORED,
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_item_cont FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Avances periódicos por ítem
CREATE TABLE IF NOT EXISTS avances_contrato (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED,
    fecha       DATE NOT NULL,
    cantidad    DECIMAL(12,2) NOT NULL DEFAULT 0,
    monto       DECIMAL(15,2) NOT NULL DEFAULT 0,
    observacion VARCHAR(255),
    creado_por  INT UNSIGNED,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_av_cont FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    CONSTRAINT fk_av_item FOREIGN KEY (item_id)     REFERENCES items_contrato(id) ON DELETE SET NULL,
    CONSTRAINT fk_av_usr  FOREIGN KEY (creado_por)  REFERENCES usuarios(id)   ON DELETE SET NULL,
    INDEX idx_av_cont_f (contrato_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== m05_documentos.sql =====
-- ============================================================
-- TriTransportes — TABLAS MÓDULO M07 DOCUMENTOS
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS documentos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    tipo_id         INT UNSIGNED,
    estado_id       INT UNSIGNED,
    obra_id         INT UNSIGNED,
    contrato_id     INT UNSIGNED,
    entidad_tipo    ENUM('trabajador','conductor','operador','vehiculo','equipo','empresa','general') DEFAULT 'general',
    entidad_id      INT UNSIGNED,
    titulo          VARCHAR(255) NOT NULL,
    numero          VARCHAR(100),
    fecha_emision   DATE,
    fecha_vencimiento DATE,
    ruta_archivo    VARCHAR(300),
    nombre_archivo  VARCHAR(255),
    observaciones   TEXT,
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    creado_por      INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_emp  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_doc_tipo FOREIGN KEY (tipo_id)    REFERENCES tipos_documento(id)   ON DELETE SET NULL,
    CONSTRAINT fk_doc_est  FOREIGN KEY (estado_id)  REFERENCES estados_documento(id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_obra FOREIGN KEY (obra_id)    REFERENCES obras(id)             ON DELETE SET NULL,
    CONSTRAINT fk_doc_usr  FOREIGN KEY (creado_por) REFERENCES usuarios(id)          ON DELETE SET NULL,
    INDEX idx_doc_emp   (empresa_id),
    INDEX idx_doc_venc  (fecha_vencimiento),
    INDEX idx_doc_ent   (entidad_tipo, entidad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== m06_seguridad.sql =====
-- ============================================================
-- TriTransportes — TABLAS MÓDULO M08 SEGURIDAD
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Registros de checklists completados
CREATE TABLE IF NOT EXISTS checklists_registros (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id     INT UNSIGNED NOT NULL,
    tipo_id        INT UNSIGNED,
    obra_id        INT UNSIGNED,
    equipo_id      INT UNSIGNED,
    vehiculo_id    INT UNSIGNED,
    trabajador_id  INT UNSIGNED,
    fecha          DATE NOT NULL,
    turno          ENUM('mañana','tarde','noche') DEFAULT 'mañana',
    resultado      ENUM('aprobado','rechazado','observado') NOT NULL DEFAULT 'aprobado',
    observaciones  TEXT,
    firma_b64      MEDIUMTEXT,
    creado_por     INT UNSIGNED,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chk_emp  FOREIGN KEY (empresa_id)    REFERENCES empresas(id),
    CONSTRAINT fk_chk_tipo FOREIGN KEY (tipo_id)       REFERENCES tipos_checklist(id) ON DELETE SET NULL,
    CONSTRAINT fk_chk_obra FOREIGN KEY (obra_id)       REFERENCES obras(id)           ON DELETE SET NULL,
    CONSTRAINT fk_chk_eq   FOREIGN KEY (equipo_id)     REFERENCES equipos(id)         ON DELETE SET NULL,
    CONSTRAINT fk_chk_veh  FOREIGN KEY (vehiculo_id)   REFERENCES vehiculos(id)       ON DELETE SET NULL,
    CONSTRAINT fk_chk_usr  FOREIGN KEY (creado_por)    REFERENCES usuarios(id)        ON DELETE SET NULL,
    INDEX idx_chk_emp_f (empresa_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ítems de cada checklist registrado
CREATE TABLE IF NOT EXISTS checklist_items_resp (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registro_id  INT UNSIGNED NOT NULL,
    item         VARCHAR(255) NOT NULL,
    cumple       TINYINT(1)   NOT NULL DEFAULT 1,
    observacion  VARCHAR(255),
    CONSTRAINT fk_chkr_reg FOREIGN KEY (registro_id) REFERENCES checklists_registros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incidentes / accidentes
CREATE TABLE IF NOT EXISTS incidentes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    obra_id         INT UNSIGNED,
    fecha           DATE NOT NULL,
    hora            TIME,
    tipo            ENUM('accidente','incidente','cuasi_accidente','enfermedad','otro') NOT NULL DEFAULT 'incidente',
    gravedad        ENUM('leve','moderado','grave','fatal') NOT NULL DEFAULT 'leve',
    descripcion     TEXT NOT NULL,
    lugar           VARCHAR(200),
    trabajador_id   INT UNSIGNED,
    dias_perdidos   INT UNSIGNED DEFAULT 0,
    acciones        TEXT,
    estado          ENUM('reportado','investigando','cerrado') NOT NULL DEFAULT 'reportado',
    foto_ruta       VARCHAR(255),
    creado_por      INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inc_emp  FOREIGN KEY (empresa_id)   REFERENCES empresas(id),
    CONSTRAINT fk_inc_obra FOREIGN KEY (obra_id)      REFERENCES obras(id)        ON DELETE SET NULL,
    CONSTRAINT fk_inc_trab FOREIGN KEY (trabajador_id)REFERENCES trabajadores(id) ON DELETE SET NULL,
    CONSTRAINT fk_inc_usr  FOREIGN KEY (creado_por)   REFERENCES usuarios(id)     ON DELETE SET NULL,
    INDEX idx_inc_emp_f (empresa_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== seed.sql =====
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

-- ===== seed_demo.sql =====
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

-- ===== seed_demo2.sql =====
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

-- ===== seed_demo3.sql =====
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

-- ════════════════════════════════════════════════════════════════
-- Anti fuerza-bruta: registro de intentos de inicio de sesión
-- ════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS login_intentos (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    email  VARCHAR(190) NOT NULL,
    ip     VARCHAR(45)  NOT NULL,
    exito  TINYINT(1)   NOT NULL DEFAULT 0,
    fecha  DATETIME     NOT NULL,
    INDEX idx_email_fecha (email, fecha),
    INDEX idx_ip_fecha (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
