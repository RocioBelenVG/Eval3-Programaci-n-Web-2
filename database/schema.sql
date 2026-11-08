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
