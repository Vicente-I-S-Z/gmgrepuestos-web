-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 05: TABLAS DEL PANEL Y LÍMITES (INC-12)
-- ---------------------------------------------------------------------
--  Crea las 4 tablas que el código usa y que no estaban en 01:
--    base_conocimiento    respuestas del asistente (panel "Asistente", E5-H9)
--    intentos_pago        cada intento de pago de un pedido (E4-H4)
--    auditoria            quién cambió qué en el panel (E10-H7)
--    limites_solicitudes  contador por IP para limitar abusos (E10-H3, E10-H5)
--  Las 3 primeras son copia exacta de la estructura de producción
--  (exportada desde phpMyAdmin el 14-11-2026).
--
--  Requisito: haber ejecutado 01_estructura.sql (intentos_pago apunta a pedidos).
--  Es seguro ejecutarlo más de una vez y en producción: si una tabla ya
--  existe no la toca, y los textos del asistente solo se agregan si el tema
--  no existe (no reemplaza lo que se editó en el panel).
--  Uso: phpMyAdmin > seleccionar la base > pestaña SQL > pegar todo > Continuar.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- base_conocimiento: datos de la tienda que el asistente puede responder
-- y que muestran la página de Contacto y el pie de página.
-- Contenido vacío (NULL) = no configurado: el asistente no inventa y
-- ofrece WhatsApp.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS base_conocimiento (
  id_conocimiento  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  clave            VARCHAR(40)   NOT NULL COMMENT 'horario, direccion, telefono, email, despacho, medios_pago, garantia, devoluciones',
  titulo           VARCHAR(80)   NOT NULL,
  contenido        TEXT          NULL DEFAULT NULL COMMENT 'Vacío = no configurado',
  actualizado_en   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_conocimiento),
  UNIQUE KEY uq_conocimiento_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Respuestas configurables del asistente y datos de contacto';

-- ---------------------------------------------------------------------
-- intentos_pago: cada respuesta de la pasarela para un pedido, para
-- conciliar cobros. Solo la escribe backend/pagos.php.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS intentos_pago (
  id_intento           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  id_pedido            INT UNSIGNED    NOT NULL,
  pasarela             VARCHAR(30)     NOT NULL DEFAULT 'Mercado Pago',
  id_transaccion       VARCHAR(60)     NULL DEFAULT NULL COMMENT 'Identificador del pago en la pasarela (payment_id)',
  monto                DECIMAL(10,2)   NOT NULL COMMENT 'Monto informado por la pasarela, o el total del pedido si no lo informó',
  estado               VARCHAR(30)     NOT NULL COMMENT 'Estado tal como lo informa la pasarela (approved, pending, rejected...)',
  resultado            ENUM('recibido','pendiente','rechazado') NOT NULL COMMENT 'Estado traducido al del sitio',
  medio_pago           VARCHAR(40)     NULL DEFAULT NULL COMMENT 'credit_card, debit_card, account_money...',
  codigo_autorizacion  VARCHAR(40)     NULL DEFAULT NULL COMMENT 'Código de autorización de la tarjeta, si la pasarela lo entrega',
  verificado           TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = confirmado consultando directamente a la pasarela',
  fecha                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_intento),
  UNIQUE KEY uq_intentos_transaccion (pasarela, id_transaccion, estado),
  KEY idx_intentos_pedido (id_pedido, fecha),
  CONSTRAINT fk_intentos_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedidos (id_pedido) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cada intento de pago de un pedido';

-- ---------------------------------------------------------------------
-- auditoria: registro de cambios del panel. Solo se agregan filas (no hay
-- pantalla para editarlo ni borrarlo). id_admin sin llave foránea a
-- propósito: el registro se conserva aunque se elimine el administrador.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
  id_auditoria    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  fecha           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_admin        INT UNSIGNED     NULL DEFAULT NULL COMMENT 'Administrador que hizo el cambio',
  admin_nombre    VARCHAR(100)     NOT NULL COMMENT 'Nombre del administrador al momento del cambio',
  accion          VARCHAR(40)      NOT NULL COMMENT 'Ej: precio, stock, estado_pedido, codigo_creado',
  entidad         VARCHAR(30)      NOT NULL COMMENT 'repuesto, pedido, codigo_descuento, conocimiento, modelo, categoria...',
  id_entidad      INT UNSIGNED     NULL DEFAULT NULL,
  detalle         VARCHAR(255)     NOT NULL COMMENT 'Texto legible: qué cambió',
  valor_anterior  VARCHAR(255)     NULL DEFAULT NULL,
  valor_nuevo     VARCHAR(255)     NULL DEFAULT NULL,
  ip              VARCHAR(45)      NULL DEFAULT NULL,
  PRIMARY KEY (id_auditoria),
  KEY idx_auditoria_fecha (fecha),
  KEY idx_auditoria_entidad (entidad, id_entidad),
  KEY idx_auditoria_admin (id_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de auditoría del panel (solo se agregan filas)';

-- ---------------------------------------------------------------------
-- limites_solicitudes: cuántas veces una IP (guardada como hash, nunca la
-- IP) usó una acción en la ventana actual. La usa gmgLimiteSuperado()
-- (chat, buscador, códigos de descuento, avisos de stock y checkout).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS limites_solicitudes (
  clave           VARCHAR(100)      NOT NULL,
  ventana_inicio  INT UNSIGNED      NOT NULL,
  contador        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Contador por IP (hash) y acción para limitar abusos';

-- ---------------------------------------------------------------------
-- Temas del asistente. Las políticas son las mismas de producción.
-- Horario, dirección, teléfono y correo quedan sin configurar: se cargan
-- con los datos reales de GMG desde el panel (menú "Asistente").
-- INSERT IGNORE: si el tema ya existe, se deja como está.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO base_conocimiento (clave, titulo, contenido) VALUES
  ('horario',      'Horario de atención',    NULL),
  ('direccion',    'Dirección',              NULL),
  ('telefono',     'Teléfono',               NULL),
  ('email',        'Correo de contacto',     NULL),
  ('despacho',     'Despacho y entrega',     'GMG Repuestos no realiza envíos propios. Una vez realizada la compra, la entrega se coordina directamente con el cliente por WhatsApp. El pago en línea cubre solo los repuestos: si la entrega requiere un traslado, su costo se acuerda por WhatsApp antes de entregar.'),
  ('medios_pago',  'Medios de pago',         'El pago se hace en línea con Mercado Pago: tarjetas de crédito y débito, y los demás medios que Mercado Pago ofrezca al momento de pagar. Se puede pagar hasta en 6 cuotas, según la tarjeta.'),
  ('garantia',     'Garantía',               'Todos los productos tienen la garantía legal de la Ley 19.496 (Ley del Consumidor): si el repuesto llega con una falla, no corresponde a lo comprado o no sirve para el uso informado, el cliente tiene 6 meses desde que lo recibe para pedir su reparación, su cambio o la devolución del dinero.'),
  ('devoluciones', 'Cambios y devoluciones', 'Las compras en línea tienen derecho a retracto: se puede desistir de la compra, sin dar explicación, dentro de 10 días desde que se recibe el producto, siempre que no haya sido instalado ni usado y esté en su empaque original. Para pedir un cambio o una devolución hay que escribir por WhatsApp indicando el número de pedido.');

-- Fin del script 05
