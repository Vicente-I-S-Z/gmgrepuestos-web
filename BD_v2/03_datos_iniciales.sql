-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 03 de 3: DATOS INICIALES DEL SISTEMA
-- ---------------------------------------------------------------------
--  Copia desde BD_actual lo que el sitio necesita para seguir operando:
--  las 3 cuentas de administrador (mismas contraseñas, porque se copia
--  el hash bcrypt tal cual) y el cupón BIENVENIDO10.
--  NO se migran pedidos, clientes ni chats: eran pruebas del equipo y
--  apuntan a productos que ya no existen en el catálogo nuevo.
--  Requisito: haber ejecutado 01_estructura.sql.
--  Seguridad: este archivo contiene hashes de contraseñas. No lo
--  publiques ni lo subas a un repositorio público.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

INSERT INTO administradores (usuario, nombre, password_hash) VALUES
  ('rodolfo', 'Rodolfo', '$2y$12$/ijJKFlK6qfhhdHubso4ZeLH5ZtpjWAlVY4U7HgQGtjS.L37ej7y.'),
  ('vicente', 'Vicente', '$2y$12$VU1XVfr9HTznLd/f2XGNQ.olJSvhx7Z02EYwWKtvs697Ru2a068tS'),
  ('martin',  'Martin',  '$2y$12$nTbXRQ9QVZjeHauDtogjHesWKENE6HSrn5mM8rs61ox7.AbtqEOsi');

INSERT INTO codigos_descuento (codigo, tipo, valor, activo) VALUES
  ('BIENVENIDO10', 'porcentaje', 10.00, 1);

COMMIT;

-- Fin del script 03
