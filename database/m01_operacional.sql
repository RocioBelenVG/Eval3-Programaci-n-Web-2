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
