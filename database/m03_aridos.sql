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
