-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 09: CUENTAS, ROLES, RESTABLECER CLAVE Y BANNERS
--  (F3-2 E1-H5 RF-005; F4-10 E1-H6, E1-H7, E6-H11)
-- ---------------------------------------------------------------------
--  1. administradores.email y administradores.rol ('admin' o 'vendedor').
--     Los administradores que ya existen quedan como 'admin' y sin correo:
--     el correo se agrega en el panel > Usuarios (sin correo no pueden
--     restablecer su contraseña).
--  2. restablecer_clave: enlaces de un solo uso para cambiar la contraseña
--     (guarda solo el hash SHA-256 del enlace, vence a los 30 minutos).
--  3. banners: avisos que se muestran arriba de la tienda (panel > Banners).
--
--  Requisito: haber ejecutado 01_estructura.sql.
--  HAY QUE EJECUTARLO EN PRODUCCIÓN junto con la subida que trae el panel
--  de Usuarios y Banners. Es seguro repetirlo. Requiere MariaDB 10.0.2 o
--  superior (Hostinger y XAMPP) por "ADD COLUMN IF NOT EXISTS".
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE administradores
  ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL DEFAULT NULL COMMENT 'Correo para restablecer la contraseña' AFTER nombre,
  ADD COLUMN IF NOT EXISTS rol ENUM('admin','vendedor') NOT NULL DEFAULT 'admin' COMMENT 'admin: todo el panel; vendedor: productos, pedidos y avisos' AFTER email;

CREATE TABLE IF NOT EXISTS restablecer_clave (
  id_restablecer  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_admin        INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL COMMENT 'SHA-256 del enlace enviado (el enlace no se guarda)',
  expira_en       DATETIME      NOT NULL,
  usado_en        DATETIME      NULL DEFAULT NULL,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_restablecer),
  UNIQUE KEY uq_restablecer_token (token_hash),
  KEY idx_restablecer_admin (id_admin),
  CONSTRAINT fk_restablecer_admin FOREIGN KEY (id_admin)
    REFERENCES administradores (id_admin) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
  id_banner     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  mensaje       VARCHAR(200)  NOT NULL,
  enlace        VARCHAR(255)  NULL DEFAULT NULL COMMENT 'Dirección del propio sitio (opcional)',
  activo        TINYINT(1)    NOT NULL DEFAULT 1,
  vigente_hasta DATE          NULL DEFAULT NULL COMMENT 'Vacío: sin fecha de término',
  creado_en     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_banner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
