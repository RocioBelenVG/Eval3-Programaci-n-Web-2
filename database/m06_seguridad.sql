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
