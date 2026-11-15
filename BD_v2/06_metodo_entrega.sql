-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 06: COLUMNA pedidos.metodo_entrega
-- ---------------------------------------------------------------------
--  Producción tiene esta columna (la agregó un script del Sprint 4 que no
--  quedó guardado en BD_v2) y una base creada con 01 a 05 no la tenía:
--  el panel de pedidos dejaba "Unknown column 'metodo_entrega'" en el log.
--  Definición y posición copiadas de la estructura de producción
--  (exportada desde phpMyAdmin el 15-11-2026). Con este script, una base
--  nueva queda igual a producción.
--
--  GMG no hace envíos: la entrega se coordina por WhatsApp (E3-H4).
--
--  Requisito: haber ejecutado 01_estructura.sql.
--  En producción no hace falta (la columna ya existe), pero es seguro:
--  IF NOT EXISTS no cambia nada si ya está. Requiere MariaDB (Hostinger y XAMPP).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS metodo_entrega VARCHAR(60) NOT NULL DEFAULT 'Coordinación por WhatsApp'
    COMMENT 'Cómo se entrega el pedido' AFTER metodo_pago;

-- Fin del script 06
