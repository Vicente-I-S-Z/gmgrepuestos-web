-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 07: COLUMNA pedidos.stock_descontado_en (F2-3, E4-H3, RF-033)
-- ---------------------------------------------------------------------
--  Al pasar un pedido a "aprobado" en el panel se descuenta el stock de
--  sus productos UNA sola vez. Esta columna guarda cuándo se descontó:
--  si ya tiene fecha, volver a aprobar el pedido no descuenta de nuevo.
--
--  Los pedidos que ya estaban aprobados antes de este cambio se marcan
--  como descontados (con su fecha), para que moverlos a otro estado y
--  volver a aprobarlos no reste stock que nunca se les reservó.
--
--  Requisito: haber ejecutado 01_estructura.sql.
--  HAY QUE EJECUTARLO EN PRODUCCIÓN antes de subir la Fase 2: sin esta
--  columna el panel no puede aprobar pedidos (muestra un error y no cambia
--  nada). Es seguro repetirlo. Requiere MariaDB (Hostinger y XAMPP).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS stock_descontado_en DATETIME NULL DEFAULT NULL
    COMMENT 'Cuándo se descontó el stock al aprobar (NULL: aún no)' AFTER pago_estado;

UPDATE pedidos SET stock_descontado_en = fecha
 WHERE estado = 'aprobado' AND stock_descontado_en IS NULL;

-- Fin del script 07
