-- =====================================================================
--  GMG REPUESTOS · Base de datos relacional v2
--  Script 01 de 3: ESTRUCTURA (DDL)
-- ---------------------------------------------------------------------
--  Motor:        MariaDB 10.4+ o MySQL 8.0.16+ (InnoDB)
--  Charset:      utf8mb4 / utf8mb4_unicode_ci
--  Tablas:       17 (catálogo 8 · comercial 6 · chatbot 3) + 2 vistas
--  Uso:          phpMyAdmin > seleccionar la base NUEVA y VACÍA >
--                pestaña SQL > pegar todo > Continuar.
--  Importante:   este script NO borra nada. Si una tabla ya existe,
--                se detiene con error (protección contra pérdida de datos).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
--  NIVEL 0 · Tablas sin llaves foráneas hacia otras tablas
-- =====================================================================

-- ---------------------------------------------------------------------
-- marcas: fabricante del vehículo (hoy solo Suzuki, pero deja abierta
-- la puerta a otras marcas sin rediseñar).
-- ---------------------------------------------------------------------
CREATE TABLE marcas (
  id_marca      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(50)   NOT NULL,
  slug          VARCHAR(60)   NOT NULL,
  activo        TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id_marca),
  CONSTRAINT uq_marcas_nombre UNIQUE (nombre),
  CONSTRAINT uq_marcas_slug   UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Marcas de vehículos';

-- ---------------------------------------------------------------------
-- motores: un motor existe una sola vez y lo comparten varios modelos
-- (ej: K15B en Baleno, Ertiga, Carry y Jimny).
-- ---------------------------------------------------------------------
CREATE TABLE motores (
  id_motor           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  codigo             VARCHAR(20)        NOT NULL COMMENT 'Código de fábrica, ej: K12M',
  variante           VARCHAR(40)        NOT NULL DEFAULT '' COMMENT 'Distingue motores con el mismo código, ej: Boosterjet Turbo',
  cilindrada_cc      SMALLINT UNSIGNED  NOT NULL,
  cilindrada_litros  DECIMAL(2,1) GENERATED ALWAYS AS (ROUND(cilindrada_cc / 1000, 1)) VIRTUAL COMMENT 'Calculada, no se escribe',
  cilindros          TINYINT UNSIGNED   NOT NULL,
  valvulas           TINYINT UNSIGNED   NOT NULL,
  combustible        ENUM('Bencina','Diésel') NOT NULL DEFAULT 'Bencina',
  aspiracion         ENUM('Atmosférico','Turbo') NOT NULL DEFAULT 'Atmosférico',
  hibrido            TINYINT(1)         NOT NULL DEFAULT 0 COMMENT '1 = micro híbrido (Smart Hybrid)',
  tipo_distribucion  ENUM('Cadena','Correa') NOT NULL,
  PRIMARY KEY (id_motor),
  CONSTRAINT uq_motores_codigo_variante UNIQUE (codigo, variante),
  CONSTRAINT ck_motores_cilindrada CHECK (cilindrada_cc BETWEEN 500 AND 5000),
  CONSTRAINT ck_motores_cilindros  CHECK (cilindros BETWEEN 2 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Motores (catálogo maestro)';

-- ---------------------------------------------------------------------
-- categorias: árbol de categorías. id_categoria_padre permite
-- subcategorías (ej: Filtros > Filtros de aceite) sin otra tabla.
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
  id_categoria        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  id_categoria_padre  INT UNSIGNED      NULL COMMENT 'NULL = categoría principal',
  codigo              CHAR(3)           NOT NULL COMMENT 'Prefijo del SKU, ej: FRE',
  nombre              VARCHAR(60)       NOT NULL,
  slug                VARCHAR(70)       NOT NULL,
  descripcion         VARCHAR(255)      NULL,
  orden               TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (id_categoria),
  CONSTRAINT uq_categorias_codigo UNIQUE (codigo),
  CONSTRAINT uq_categorias_nombre UNIQUE (nombre),
  CONSTRAINT uq_categorias_slug   UNIQUE (slug),
  KEY idx_categorias_padre (id_categoria_padre),
  CONSTRAINT fk_categorias_padre FOREIGN KEY (id_categoria_padre)
    REFERENCES categorias (id_categoria) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías de repuestos (jerárquicas)';

-- ---------------------------------------------------------------------
-- clientes: un cliente se guarda una vez (antes se copiaba en cada pedido).
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
  id_cliente      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  rut             VARCHAR(12)   NOT NULL COMMENT 'Formato 12345678-9, sin puntos',
  nombre          VARCHAR(80)   NOT NULL,
  apellido        VARCHAR(80)   NOT NULL,
  email           VARCHAR(150)  NOT NULL,
  telefono        VARCHAR(20)   NOT NULL COMMENT 'Formato +569XXXXXXXX',
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cliente),
  CONSTRAINT uq_clientes_rut UNIQUE (rut),
  KEY idx_clientes_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes que han comprado';

-- ---------------------------------------------------------------------
-- codigos_descuento
-- ---------------------------------------------------------------------
CREATE TABLE codigos_descuento (
  id_codigo      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo         VARCHAR(30)   NOT NULL,
  tipo           ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
  valor          DECIMAL(10,2) NOT NULL,
  activo         TINYINT(1)    NOT NULL DEFAULT 1,
  vigente_hasta  DATE          NULL COMMENT 'NULL = sin fecha de término',
  creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_codigo),
  CONSTRAINT uq_codigos_codigo UNIQUE (codigo),
  CONSTRAINT ck_codigos_valor CHECK (valor > 0),
  CONSTRAINT ck_codigos_porcentaje CHECK (tipo <> 'porcentaje' OR valor <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cupones de descuento';

-- ---------------------------------------------------------------------
-- administradores
-- ---------------------------------------------------------------------
CREATE TABLE administradores (
  id_admin       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  usuario        VARCHAR(50)   NOT NULL,
  nombre         VARCHAR(100)  NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL COMMENT 'password_hash() de PHP (bcrypt)',
  activo         TINYINT(1)    NOT NULL DEFAULT 1,
  ultimo_acceso  DATETIME      NULL,
  creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_admin),
  CONSTRAINT uq_administradores_usuario UNIQUE (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del panel de administración';

-- ---------------------------------------------------------------------
-- conversaciones_chat
-- ---------------------------------------------------------------------
CREATE TABLE conversaciones_chat (
  id_conversacion    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  sesion_id          VARCHAR(64)   NOT NULL,
  creado_en          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_mensaje_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_conversacion),
  CONSTRAINT uq_conversaciones_sesion UNIQUE (sesion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sesiones del asistente virtual';

-- =====================================================================
--  NIVEL 1 · Dependen solo de tablas del nivel 0
-- =====================================================================

-- ---------------------------------------------------------------------
-- modelos
-- ---------------------------------------------------------------------
CREATE TABLE modelos (
  id_modelo        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  id_marca         INT UNSIGNED      NOT NULL,
  nombre           VARCHAR(50)       NOT NULL COMMENT 'Sin la marca, ej: Swift',
  slug             VARCHAR(60)       NOT NULL COMMENT 'Para URL, ej: grand-vitara',
  tipo_carroceria  ENUM('Hatchback','Sedán','Crossover','SUV','Todoterreno','Minivan','Furgón','Camioneta') NOT NULL,
  imagen_url       VARCHAR(255)      NULL,
  orden            TINYINT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Orden en la portada',
  activo           TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (id_modelo),
  CONSTRAINT uq_modelos_marca_nombre UNIQUE (id_marca, nombre),
  CONSTRAINT uq_modelos_slug UNIQUE (slug),
  CONSTRAINT fk_modelos_marca FOREIGN KEY (id_marca)
    REFERENCES marcas (id_marca) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Modelos de vehículo';

-- ---------------------------------------------------------------------
-- repuestos: el producto que se vende. Ya no guarda modelo, año ni motor:
-- eso vive en compatibilidades.
-- ---------------------------------------------------------------------
CREATE TABLE repuestos (
  id_repuesto       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  id_categoria      INT UNSIGNED       NOT NULL,
  sku               VARCHAR(30)        NOT NULL COMMENT 'Código interno CAT-GRUPO-TIPO, ej: FRE-SWI3-PDL (CAT = categorias.codigo)',
  nombre            VARCHAR(150)       NOT NULL,
  descripcion       TEXT               NULL,
  codigo_oem        VARCHAR(40)        NULL COMMENT 'Número de parte original Suzuki',
  marca_fabricante  VARCHAR(60)        NULL COMMENT 'Marca del repuesto, ej: Suzuki Genuine, NGK, Bosch',
  precio            DECIMAL(10,2)      NOT NULL DEFAULT 0.00 COMMENT 'CLP, IVA incluido',
  stock             INT                NOT NULL DEFAULT 0,
  stock_minimo      SMALLINT UNSIGNED  NOT NULL DEFAULT 3 COMMENT 'Bajo este número aparece en alertas del dashboard',
  activo            TINYINT(1)         NOT NULL DEFAULT 1 COMMENT '0 = oculto en la tienda',
  creado_en         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_repuesto),
  CONSTRAINT uq_repuestos_sku UNIQUE (sku),
  KEY idx_repuestos_categoria (id_categoria),
  KEY idx_repuestos_oem (codigo_oem),
  KEY idx_repuestos_activo_stock (activo, stock),
  FULLTEXT KEY ft_repuestos_busqueda (nombre, descripcion),
  CONSTRAINT ck_repuestos_precio CHECK (precio >= 0),
  CONSTRAINT ck_repuestos_stock  CHECK (stock >= 0),
  CONSTRAINT fk_repuestos_categoria FOREIGN KEY (id_categoria)
    REFERENCES categorias (id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Repuestos a la venta';

-- ---------------------------------------------------------------------
-- mensajes_chat
-- ---------------------------------------------------------------------
CREATE TABLE mensajes_chat (
  id_mensaje       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_conversacion  INT UNSIGNED  NOT NULL,
  rol              ENUM('user','bot') NOT NULL,
  contenido        TEXT          NOT NULL,
  feedback         ENUM('positivo','negativo') NULL,
  creado_en        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mensaje),
  KEY idx_mensajes_conversacion (id_conversacion, creado_en),
  CONSTRAINT fk_mensajes_conversacion FOREIGN KEY (id_conversacion)
    REFERENCES conversaciones_chat (id_conversacion) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mensajes de cada conversación';

-- =====================================================================
--  NIVEL 2
-- =====================================================================

-- ---------------------------------------------------------------------
-- vehiculos: versión concreta = modelo + motor + generación + años.
-- Resuelve la relación N:M entre modelos y motores.
-- ---------------------------------------------------------------------
CREATE TABLE vehiculos (
  id_vehiculo  INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  id_modelo    INT UNSIGNED       NOT NULL,
  id_motor     INT UNSIGNED       NOT NULL,
  generacion   VARCHAR(60)        NULL COMMENT 'Ej: 3ª gen.',
  anio_desde   SMALLINT UNSIGNED  NOT NULL,
  anio_hasta   SMALLINT UNSIGNED  NULL COMMENT 'NULL = sigue a la venta',
  activo       TINYINT(1)         NOT NULL DEFAULT 1,
  PRIMARY KEY (id_vehiculo),
  CONSTRAINT uq_vehiculos_modelo_motor_anio UNIQUE (id_modelo, id_motor, anio_desde),
  KEY idx_vehiculos_motor (id_motor),
  KEY idx_vehiculos_anios (anio_desde, anio_hasta),
  CONSTRAINT ck_vehiculos_anio_desde CHECK (anio_desde BETWEEN 1970 AND 2100),
  CONSTRAINT ck_vehiculos_rango CHECK (anio_hasta IS NULL OR anio_hasta >= anio_desde),
  CONSTRAINT fk_vehiculos_modelo FOREIGN KEY (id_modelo)
    REFERENCES modelos (id_modelo) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_vehiculos_motor FOREIGN KEY (id_motor)
    REFERENCES motores (id_motor) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Versiones de vehículo (modelo + motor + años)';

-- ---------------------------------------------------------------------
-- repuesto_imagenes: reemplaza imagen_url, imagen_url_2, imagen_url_3.
-- ---------------------------------------------------------------------
CREATE TABLE repuesto_imagenes (
  id_imagen    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  id_repuesto  INT UNSIGNED      NOT NULL,
  url          VARCHAR(255)      NOT NULL,
  texto_alt    VARCHAR(150)      NULL COMMENT 'Texto alternativo (accesibilidad y SEO)',
  orden        TINYINT UNSIGNED  NOT NULL DEFAULT 1 COMMENT '1 = imagen principal',
  PRIMARY KEY (id_imagen),
  CONSTRAINT uq_imagenes_repuesto_orden UNIQUE (id_repuesto, orden),
  CONSTRAINT fk_imagenes_repuesto FOREIGN KEY (id_repuesto)
    REFERENCES repuestos (id_repuesto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fotos de cada repuesto';

-- ---------------------------------------------------------------------
-- avisos_stock: "avísame cuando llegue".
-- ---------------------------------------------------------------------
CREATE TABLE avisos_stock (
  id_aviso     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_repuesto  INT UNSIGNED  NOT NULL,
  contacto     VARCHAR(150)  NOT NULL COMMENT 'Correo o teléfono',
  sesion_id    VARCHAR(64)   NULL,
  notificado   TINYINT(1)    NOT NULL DEFAULT 0,
  creado_en    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_aviso),
  KEY idx_avisos_repuesto (id_repuesto, notificado),
  CONSTRAINT fk_avisos_repuesto FOREIGN KEY (id_repuesto)
    REFERENCES repuestos (id_repuesto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Solicitudes de aviso de reposición';

-- ---------------------------------------------------------------------
-- pedidos
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
  id_pedido            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  id_cliente           INT UNSIGNED   NOT NULL,
  id_codigo_descuento  INT UNSIGNED   NULL,
  fecha                DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado               ENUM('pendiente','aprobado','rechazado','error') NOT NULL DEFAULT 'pendiente' COMMENT 'Revisión del administrador',
  pago_estado          ENUM('pendiente','recibido','rechazado') NOT NULL DEFAULT 'pendiente' COMMENT 'Resultado informado por la pasarela',
  metodo_pago          VARCHAR(30)    NOT NULL DEFAULT 'Mercado Pago',
  referencia_pago      VARCHAR(100)   NULL COMMENT 'payment_id de Mercado Pago',
  descuento_monto      DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Foto del descuento aplicado al momento del pago',
  total                DECIMAL(10,2)  NOT NULL COMMENT 'Desnormalización intencional: SUM(cantidad*precio_unitario) - descuento_monto al pagar. Es el monto cobrado por Mercado Pago y no debe cambiar',
  PRIMARY KEY (id_pedido),
  KEY idx_pedidos_cliente (id_cliente),
  KEY idx_pedidos_codigo (id_codigo_descuento),
  KEY idx_pedidos_fecha (fecha),
  KEY idx_pedidos_estados (estado, pago_estado),
  KEY idx_pedidos_referencia (referencia_pago),
  CONSTRAINT ck_pedidos_total CHECK (total >= 0),
  CONSTRAINT ck_pedidos_descuento CHECK (descuento_monto >= 0),
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (id_cliente)
    REFERENCES clientes (id_cliente) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pedidos_codigo FOREIGN KEY (id_codigo_descuento)
    REFERENCES codigos_descuento (id_codigo) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Órdenes de compra';

-- =====================================================================
--  NIVEL 3 · Tablas intermedias y de detalle
-- =====================================================================

-- ---------------------------------------------------------------------
-- compatibilidades: tabla intermedia N:M repuestos <-> vehiculos.
-- Un repuesto sirve para muchos vehículos y un vehículo usa muchos
-- repuestos.
-- ---------------------------------------------------------------------
CREATE TABLE compatibilidades (
  id_repuesto  INT UNSIGNED      NOT NULL,
  id_vehiculo  INT UNSIGNED      NOT NULL,
  cantidad     TINYINT UNSIGNED  NULL COMMENT 'Unidades que usa el vehículo, ej: 4 bujías',
  observacion  VARCHAR(150)      NULL COMMENT 'Restricción, ej: solo caja mecánica',
  PRIMARY KEY (id_repuesto, id_vehiculo),
  KEY idx_compat_vehiculo (id_vehiculo),
  CONSTRAINT fk_compat_repuesto FOREIGN KEY (id_repuesto)
    REFERENCES repuestos (id_repuesto) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_compat_vehiculo FOREIGN KEY (id_vehiculo)
    REFERENCES vehiculos (id_vehiculo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Qué repuesto sirve para qué vehículo';

-- ---------------------------------------------------------------------
-- pedido_detalle: líneas del pedido. nombre, sku y precio se guardan
-- como foto del momento de la compra (si el precio cambia mañana, el
-- pedido histórico no cambia).
-- ---------------------------------------------------------------------
CREATE TABLE pedido_detalle (
  id_detalle       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  id_pedido        INT UNSIGNED       NOT NULL,
  id_repuesto      INT UNSIGNED       NULL COMMENT 'NULL si el repuesto se eliminó después',
  sku              VARCHAR(30)        NOT NULL,
  nombre_repuesto  VARCHAR(150)       NOT NULL,
  cantidad         SMALLINT UNSIGNED  NOT NULL,
  precio_unitario  DECIMAL(10,2)      NOT NULL,
  PRIMARY KEY (id_detalle),
  CONSTRAINT uq_detalle_pedido_repuesto UNIQUE (id_pedido, id_repuesto),
  KEY idx_detalle_repuesto (id_repuesto),
  CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_detalle_precio CHECK (precio_unitario >= 0),
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedidos (id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detalle_repuesto FOREIGN KEY (id_repuesto)
    REFERENCES repuestos (id_repuesto) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de cada pedido';

-- ---------------------------------------------------------------------
-- mensaje_repuestos: reemplaza mensajes_chat.productos_json.
-- ---------------------------------------------------------------------
CREATE TABLE mensaje_repuestos (
  id_mensaje   INT UNSIGNED      NOT NULL,
  id_repuesto  INT UNSIGNED      NOT NULL,
  orden        TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  PRIMARY KEY (id_mensaje, id_repuesto),
  KEY idx_msgrep_repuesto (id_repuesto),
  CONSTRAINT fk_msgrep_mensaje FOREIGN KEY (id_mensaje)
    REFERENCES mensajes_chat (id_mensaje) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_msgrep_repuesto FOREIGN KEY (id_repuesto)
    REFERENCES repuestos (id_repuesto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Repuestos que el chatbot mostró en cada mensaje';

-- =====================================================================
--  VISTAS · Facilitan las consultas del sitio y del chatbot
-- =====================================================================

-- Vehículo con nombre legible, ej: "Swift 1.2 K12M (2017-2024)"
-- SQL SECURITY INVOKER: la vista se ejecuta con los permisos de quien la
-- consulta, así no falla al mover la base entre XAMPP y Hostinger.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW vw_vehiculos AS
SELECT
  v.id_vehiculo,
  ma.nombre                AS marca,
  mo.id_modelo,
  mo.nombre                AS modelo,
  mo.slug                  AS modelo_slug,
  mt.id_motor,
  mt.codigo                AS motor_codigo,
  mt.variante              AS motor_variante,
  mt.cilindrada_litros,
  v.generacion,
  v.anio_desde,
  v.anio_hasta,
  CONCAT(mo.nombre, ' ', mt.cilindrada_litros,
         IF(mt.aspiracion = 'Turbo', ' Turbo', ''), ' ', mt.codigo,
         ' (', v.anio_desde, '-', COALESCE(v.anio_hasta, 'hoy'), ')') AS descripcion,
  (v.activo AND mo.activo AND ma.activo) AS activo
FROM vehiculos v
JOIN modelos mo ON mo.id_modelo = v.id_modelo
JOIN marcas  ma ON ma.id_marca  = mo.id_marca
JOIN motores mt ON mt.id_motor  = v.id_motor;

-- Catálogo plano: una fila por repuesto y vehículo compatible.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW vw_catalogo AS
SELECT
  r.id_repuesto,
  r.sku,
  r.nombre,
  c.nombre        AS categoria,
  r.codigo_oem,
  r.precio,
  r.stock,
  r.activo,
  i.url           AS imagen_url,
  vv.id_vehiculo,
  vv.modelo,
  vv.modelo_slug,
  vv.motor_codigo,
  vv.cilindrada_litros,
  vv.anio_desde,
  vv.anio_hasta,
  vv.descripcion  AS vehiculo,
  vv.activo       AS vehiculo_activo,
  co.cantidad,
  co.observacion
FROM repuestos r
JOIN categorias        c  ON c.id_categoria = r.id_categoria
JOIN compatibilidades  co ON co.id_repuesto = r.id_repuesto
JOIN vw_vehiculos      vv ON vv.id_vehiculo = co.id_vehiculo
LEFT JOIN repuesto_imagenes i ON i.id_repuesto = r.id_repuesto AND i.orden = 1;

-- Fin del script 01
