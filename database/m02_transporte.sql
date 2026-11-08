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
