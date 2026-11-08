-- ============================================================
-- TriTransportes — Firma digital y georreferencia en los módulos de terreno
-- Agrega las columnas donde aún no existían. Es idempotente: puede ejecutarse
-- varias veces sin efecto adicional.
-- ============================================================

SET NAMES utf8mb4;

-- M03 Combustible: la carga ya tenía GPS; se suma la firma del responsable
ALTER TABLE cargas_combustible
    ADD COLUMN IF NOT EXISTS firma_b64 MEDIUMTEXT NULL AFTER foto_ruta;

-- M05 Transporte · Guías de despacho: firma del receptor + lugar de entrega
ALTER TABLE guias_despacho
    ADD COLUMN IF NOT EXISTS lat       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS lng       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS firma_b64 MEDIUMTEXT    NULL;

-- M06 Áridos · Despachos: firma de quien recibe el material
ALTER TABLE despachos_aridos
    ADD COLUMN IF NOT EXISTS lat       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS lng       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS firma_b64 MEDIUMTEXT    NULL;

-- M09 Seguridad · Incidentes: lugar exacto del evento + firma del reportante
ALTER TABLE incidentes
    ADD COLUMN IF NOT EXISTS lat       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS lng       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS firma_b64 MEDIUMTEXT    NULL;

-- M09 Seguridad · Checklists: ya tenía firma, se agrega la ubicación
ALTER TABLE checklists_registros
    ADD COLUMN IF NOT EXISTS lat DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS lng DECIMAL(10,7) NULL;

-- M04 Maquinaria · Mantenciones: firma del técnico y lugar de la intervención
ALTER TABLE mantenciones
    ADD COLUMN IF NOT EXISTS lat       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS lng       DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS firma_b64 MEDIUMTEXT    NULL;

-- M05 Transporte · Viajes: firma de conformidad al cierre del viaje
ALTER TABLE viajes
    ADD COLUMN IF NOT EXISTS firma_b64 MEDIUMTEXT NULL;
