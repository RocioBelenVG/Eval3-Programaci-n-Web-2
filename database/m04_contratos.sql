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
