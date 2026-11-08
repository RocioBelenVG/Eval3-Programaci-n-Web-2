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
