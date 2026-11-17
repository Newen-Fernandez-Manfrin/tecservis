-- =====================================================================
--  TEC SERVIS - Base de datos (script único, incluye el módulo de
--  reparaciones ya integrado: no hace falta ningún script aparte).
--
--  Importar desde phpMyAdmin o con:  mysql -u root -p < sql/tecservis.sql
--
--  Acceso de administrador:
--     Usuario:    tecservisadmin
--     Contraseña: tecservis2026
-- =====================================================================

DROP DATABASE IF EXISTS tecservis;
CREATE DATABASE tecservis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tecservis;

-- ---------------------------------------------------------------- roles
CREATE TABLE roles (
  id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ------------------------------------------------------------- usuarios
-- El acceso al sistema es por USUARIO y CONTRASEÑA.
-- El email es opcional y sólo sirve como dato de contacto.
CREATE TABLE usuarios (
  id_usuario     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario        VARCHAR(40)  NOT NULL UNIQUE,
  nombre         VARCHAR(80)  NOT NULL,
  apellido       VARCHAR(80)  NOT NULL,
  email          VARCHAR(120) NULL,
  telefono       VARCHAR(40)  NULL,
  password_hash  VARCHAR(255) NOT NULL,
  id_rol         INT UNSIGNED NOT NULL DEFAULT 1,
  estado         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  ultimo_acceso  DATETIME NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_usuarios_estado (estado)
) ENGINE=InnoDB;

-- --------------------------------------------------------------- rubros
CREATE TABLE rubros (
  id_rubro    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  estado      ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

-- --------------------------------------------------------------- marcas
CREATE TABLE marcas (
  id_marca    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  estado      ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

-- ------------------------------------------------------------ productos
CREATE TABLE productos (
  id_producto         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(120) NOT NULL,
  descripcion         TEXT NULL,
  id_rubro            INT UNSIGNED NOT NULL,
  id_marca            INT UNSIGNED NOT NULL,
  precio              DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock               INT UNSIGNED NOT NULL DEFAULT 0,
  imagen              VARCHAR(255) NULL,
  estado              ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_alta          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_rubro FOREIGN KEY (id_rubro) REFERENCES rubros(id_rubro)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_productos_marca FOREIGN KEY (id_marca) REFERENCES marcas(id_marca)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_productos_rubro (id_rubro),
  INDEX idx_productos_marca (id_marca),
  INDEX idx_productos_estado (estado)
) ENGINE=InnoDB;

-- -------------------------------------------------------------- pedidos
-- Los pedidos se guardan en la base: no dependen de ningún servicio externo.
CREATE TABLE pedidos (
  id_pedido       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_producto     INT UNSIGNED NULL,
  id_usuario      INT UNSIGNED NULL,
  producto        VARCHAR(120) NOT NULL,
  cantidad        INT UNSIGNED NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(12,2) NOT NULL,
  total           DECIMAL(12,2) NOT NULL,
  cliente         VARCHAR(120) NOT NULL,
  telefono        VARCHAR(40)  NOT NULL,
  email           VARCHAR(120) NULL,
  direccion       VARCHAR(255) NOT NULL,
  alias_pagador   VARCHAR(80)  NULL,
  comprobante     VARCHAR(80)  NULL,
  observaciones   VARCHAR(255) NULL,
  estado          ENUM('pendiente','confirmado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pedidos_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_pedidos_estado (estado),
  INDEX idx_pedidos_fecha (fecha)
) ENGINE=InnoDB;

-- --------------------------------------------- solicitudes de reparación
CREATE TABLE solicitudes_reparacion (
  id_solicitud       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario         INT UNSIGNED NULL,
  cliente            VARCHAR(120) NOT NULL,
  email              VARCHAR(120) NOT NULL,
  telefono           VARCHAR(40) NOT NULL,
  tipo_dispositivo   VARCHAR(50) NOT NULL,
  marca_modelo       VARCHAR(160) NOT NULL,
  descripcion        TEXT NOT NULL,
  imagen             VARCHAR(255) NULL,
  estado             ENUM('pendiente','en_revision','presupuestado','reparado','cancelado')
                     NOT NULL DEFAULT 'pendiente',
  presupuesto        DECIMAL(12,2) NULL,
  detalle_presupuesto TEXT NULL,
  fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reparaciones_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_reparaciones_estado (estado),
  INDEX idx_reparaciones_fecha (fecha)
) ENGINE=InnoDB;

-- Si ya tenías la base importada con una versión anterior no hace falta hacer nada:
-- conexion.php (asegurar_esquema) crea o completa esta tabla y `notificaciones` solo.

-- ------------------------------------------------------- notificaciones
-- Aviso que ve el administrador en el panel cuando entra un pedido nuevo.
CREATE TABLE notificaciones (
  id_notificacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario      INT UNSIGNED NULL,
  id_pedido       INT UNSIGNED NULL,
  id_solicitud_reparacion INT UNSIGNED NULL,
  titulo          VARCHAR(120) NOT NULL,
  mensaje         TEXT NOT NULL,
  leida           TINYINT(1) NOT NULL DEFAULT 0,
  fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_notificaciones_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_notificaciones_reparacion FOREIGN KEY (id_solicitud_reparacion)
    REFERENCES solicitudes_reparacion(id_solicitud)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_notificaciones_leida (leida)
) ENGINE=InnoDB;

-- ==================================================== DATOS INICIALES ==

INSERT INTO roles (id_rol, nombre) VALUES
  (1, 'Usuario'),
  (2, 'Super Usuario');

-- Usuario: tecservisadmin  /  Contraseña: tecservis2026
INSERT INTO usuarios (usuario, nombre, apellido, email, password_hash, id_rol) VALUES
  ('tecservisadmin', 'Administrador', 'TEC SERVIS', NULL,
   '$2y$10$m6IV3QuOEtx7CJ6HHUVveOcKuE66Mj9T6w7GR0sqVrTHOmmVlSu0O', 2);

INSERT INTO rubros (nombre, descripcion) VALUES
  ('Placas de video',     'Placas de video para gaming y trabajo'),
  ('Memorias RAM',        'Memorias DDR4 y DDR5'),
  ('Motherboards',        'Placas madre para PC de escritorio'),
  ('Gabinetes',           'Gabinetes para armado de PC'),
  ('Servicios técnicos',  'Reparación y mantenimiento'),
  ('Celulares',           'Reparación y accesorios');

INSERT INTO marcas (nombre, descripcion) VALUES
  ('NVIDIA',   'Placas de video GeForce'),
  ('AMD',      'Placas de video Radeon'),
  ('Asus',     'Componentes Asus'),
  ('Gigabyte', 'Componentes Gigabyte'),
  ('MSI',      'Componentes MSI'),
  ('Genérica', 'Productos varios');

INSERT INTO productos (nombre, descripcion, id_rubro, id_marca, precio, stock, imagen) VALUES
  ('GTX 960',             'Placa de video para gaming de entrada, ideal para equipos de uso general.', 1, 1, 120000, 10, 'placas de video/gtx 960.jpg'),
  ('RTX 4060',            'Placa de video GeForce RTX 4060, excelente relación precio-rendimiento.',   1, 1, 650000,  8, 'placas de video/rtx 4060.jpg'),
  ('RTX 5060 Ti',         'Placa de video GeForce RTX 5060 Ti para gaming en alta calidad.',           1, 1, 850000,  5, 'placas de video/rtx 5060ti.jpg'),
  ('RX 6400',             'Placa de video AMD Radeon RX 6400, compacta y de bajo consumo.',            1, 2, 220000,  7, 'placas de video/rx 6400.jpg'),
  ('Memoria DDR4 16 GB',  'Memoria RAM DDR4 de 16 GB para actualizar tu equipo.',                      2, 6,  85000, 15, 'RAM/ddr4 16 gb.jpg'),
  ('Memoria DDR4 4 GB',   'Memoria RAM DDR4 de 4 GB para equipos de uso básico.',                      2, 6,  25000, 20, 'RAM/ram ddr4 4gb.jpg'),
  ('Memoria GMOG DDR4 16 GB', 'Memoria RAM GMOG DDR4 de 16 GB con disipador.',                         2, 6,  90000, 12, 'RAM/ram ddr4 gmog 16gb.webp'),
  ('Memoria DDR5 16 GB',  'Memoria RAM DDR5 de 16 GB de última generación.',                           2, 6, 120000, 10, 'RAM/RAM ddr5 16 gb.jpg'),
  ('Memoria DDR5 32 GB',  'Memoria RAM DDR5 de 32 GB para edición y multitarea exigente.',             2, 6, 220000,  6, 'RAM/ram ddr5 32 gb.jpeg'),
  ('Asus Prime B840',     'Motherboard Asus Prime B840 para procesadores AMD.',                        3, 3, 180000,  6, 'mothers/asus prime b840.jpg'),
  ('Gigabyte Z790 D',     'Motherboard Gigabyte Z790 D para procesadores Intel de alta gama.',         3, 4, 350000,  4, 'mothers/gigabyte z790d.png'),
  ('Gigabyte H610 DDR5',  'Motherboard Gigabyte H610 con soporte DDR5.',                               3, 4, 140000,  7, 'mothers/gygabyte h610 ddr5.jpg'),
  ('MSI PRO H610',        'Motherboard MSI PRO H610, estable y económica.',                            3, 5, 145000,  7, 'mothers/msi pro h610.jpg'),
  ('Gabinete Ecovision',  'Gabinete para PC con buena ventilación.',                                   4, 6,  75000,  9, 'gabinetes/gabinete ecovision.jpg'),
  ('Gabinete Mid Tower',  'Gabinete Mid Tower con espacio para placas de gran tamaño.',                4, 6,  95000,  8, 'gabinetes/gabinete mid tower.webp'),
  ('Gabinete Noxi Kuma',  'Gabinete gaming Noxi Kuma con panel lateral e iluminación.',                4, 6, 115000,  5, 'gabinetes/gabinete noxi gaming kuma.webp');

-- ============================================================= VISTAS ==

CREATE OR REPLACE VIEW vista_productos AS
SELECT p.id_producto,
       p.nombre AS producto,
       p.descripcion,
       p.id_rubro,
       p.id_marca,
       r.nombre AS rubro,
       m.nombre AS marca,
       p.precio,
       p.stock,
       p.imagen,
       p.estado,
       p.fecha_alta
FROM productos p
INNER JOIN rubros r ON p.id_rubro = r.id_rubro
INNER JOIN marcas m ON p.id_marca = m.id_marca;
