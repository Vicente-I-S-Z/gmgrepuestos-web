-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 08: TABLA busquedas_sin_resultado (F3-10, E2-H10, RF-017)
-- ---------------------------------------------------------------------
--  Cada búsqueda de la tienda que no encuentra nada suma 1 a su término
--  (con el modelo y la categoría elegidos), para que GMG vea qué piden los
--  clientes y no tiene. No guarda IP ni datos personales.
--
--  Requisito: haber ejecutado 01_estructura.sql.
--  HAY QUE EJECUTARLO EN PRODUCCIÓN al subir la Fase 3. Es seguro
--  repetirlo (no borra datos). Sin esta tabla la tienda funciona igual,
--  solo no registra los términos (queda un aviso en el log).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS busquedas_sin_resultado (
  id_busqueda  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  termino      VARCHAR(100)  NOT NULL COMMENT 'Texto buscado, en minúsculas',
  modelo       VARCHAR(50)   NOT NULL DEFAULT '' COMMENT 'Modelo elegido en el filtro (vacío si ninguno)',
  categoria    VARCHAR(80)   NOT NULL DEFAULT '' COMMENT 'Categoría elegida en el filtro (vacía si ninguna)',
  veces        INT UNSIGNED  NOT NULL DEFAULT 1,
  primera_vez  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultima_vez   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_busqueda),
  UNIQUE KEY uq_busqueda (termino, modelo, categoria),
  KEY idx_busqueda_ultima (ultima_vez)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
