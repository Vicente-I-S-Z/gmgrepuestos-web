-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 04: FOTOS DE LOS MODELOS QUE FALTABAN
-- ---------------------------------------------------------------------
--  Para bases que ya están cargadas: asigna la foto de portada a Fronx,
--  Ciaz, APV, Carry, Vitara, Grand Vitara y Grand Nomade, sin volver a
--  importar el catálogo. Las fotos están en la carpeta img/.
--  Si la base se crea de cero con 02_datos_catalogo.sql, este archivo
--  no hace falta (ese script ya trae las fotos).
--  Para cambiar una foto más adelante, se puede hacer desde el panel
--  (Catálogo) o con un UPDATE como estos.
-- =====================================================================

UPDATE modelos SET imagen_url = 'img/fronx.jpg'        WHERE slug = 'fronx';
UPDATE modelos SET imagen_url = 'img/ciaz.jpg'         WHERE slug = 'ciaz';
UPDATE modelos SET imagen_url = 'img/apv.jpg'          WHERE slug = 'apv';
UPDATE modelos SET imagen_url = 'img/carry.jpg'        WHERE slug = 'carry';
UPDATE modelos SET imagen_url = 'img/vitara.jpg'       WHERE slug = 'vitara';
UPDATE modelos SET imagen_url = 'img/grand-vitara.jpg' WHERE slug = 'grand-vitara';
UPDATE modelos SET imagen_url = 'img/grand-nomade.jpg' WHERE slug = 'grand-nomade';
