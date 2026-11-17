-- =====================================================================
-- Base de datos del sistema CINEMA  ("Tu cine en casa")
-- Motor: MariaDB / MySQL (probado el diseño para XAMPP: MariaDB 10.4, PHP 8.2)
-- Cómo usarla: abrir phpMyAdmin > pestaña "Importar" > elegir este archivo.
-- Crea la base `cinema` (borra y vuelve a crear las tablas si ya existían).
-- =====================================================================

DROP DATABASE IF EXISTS `cinema`;
CREATE DATABASE `cinema` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `cinema`;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- USUARIOS  (antes: usuarios.json y usuarios.php)
-- La contraseña NUNCA se guarda en texto: es el hash de password_hash().
-- ---------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario`    VARCHAR(20)  NOT NULL,
  `clave_hash` VARCHAR(255) NOT NULL,
  `rol`        ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_usuario` (`usuario`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SALAS  (antes: salas.php)
-- ---------------------------------------------------------------------
CREATE TABLE `salas` (
  `id`       VARCHAR(20)  NOT NULL,
  `nombre`   VARCHAR(50)  NOT NULL,
  `tipo`     ENUM('2D','3D','VIP') NOT NULL,
  `filas`    TINYINT UNSIGNED NOT NULL,
  `columnas` TINYINT UNSIGNED NOT NULL,
  `precio`   INT UNSIGNED NOT NULL COMMENT 'Valor de la entrada en pesos',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PELICULAS  (antes: peliculas.json)
-- `id` es el mismo identificador de texto que usa el sistema (ej: 'matrix').
-- ---------------------------------------------------------------------
CREATE TABLE `peliculas` (
  `id`      VARCHAR(80)  NOT NULL,
  `titulo`  VARCHAR(150) NOT NULL,
  `anio`    SMALLINT     DEFAULT NULL,
  `genero`  VARCHAR(50)  DEFAULT NULL,
  `imagen`  VARCHAR(255) DEFAULT NULL,
  `trailer` VARCHAR(30)  DEFAULT NULL COMMENT 'ID del video de YouTube',
  `sala_id` VARCHAR(20)  NOT NULL,
  `orden`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Orden en la cartelera',
  PRIMARY KEY (`id`),
  KEY `idx_peliculas_sala` (`sala_id`),
  CONSTRAINT `fk_peliculas_sala` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Horarios de función de cada película (ej: 15:00, 18:00, 21:00)
CREATE TABLE `pelicula_horarios` (
  `pelicula_id` VARCHAR(80) NOT NULL,
  `hora`        TIME        NOT NULL,
  PRIMARY KEY (`pelicula_id`, `hora`),
  CONSTRAINT `fk_horarios_pelicula` FOREIGN KEY (`pelicula_id`) REFERENCES `peliculas` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Días de la semana en que está en cartelera (0 = domingo ... 6 = sábado)
CREATE TABLE `pelicula_dias` (
  `pelicula_id` VARCHAR(80) NOT NULL,
  `dia`         TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`pelicula_id`, `dia`),
  CONSTRAINT `chk_dia` CHECK (`dia` BETWEEN 0 AND 6),
  CONSTRAINT `fk_dias_pelicula` FOREIGN KEY (`pelicula_id`) REFERENCES `peliculas` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- COMBOS DE CONFITERIA  (antes: combos.php)
-- ---------------------------------------------------------------------
CREATE TABLE `combos` (
  `id`          VARCHAR(40)  NOT NULL,
  `nombre`      VARCHAR(80)  NOT NULL,
  `descripcion` VARCHAR(150) DEFAULT NULL,
  `precio`      INT UNSIGNED NOT NULL,
  `emoji`       VARCHAR(20)  DEFAULT NULL,
  `orden`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CODIGOS DE DESCUENTO  (antes: descuentos.php)
-- tipo 'porcentaje': valor = %.   tipo 'monto': valor = pesos fijos.
-- un_uso = 1: cada usuario puede usarlo una sola vez.
-- ---------------------------------------------------------------------
CREATE TABLE `descuentos` (
  `codigo`      VARCHAR(30)  NOT NULL,
  `tipo`        ENUM('porcentaje','monto') NOT NULL,
  `valor`       INT UNSIGNED NOT NULL,
  `descripcion` VARCHAR(150) DEFAULT NULL,
  `vence`       DATE         DEFAULT NULL,
  `un_uso`      TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ENTRADAS (compras)  (antes: entradas.json)
-- ---------------------------------------------------------------------
CREATE TABLE `entradas` (
  `codigo`           CHAR(8)      NOT NULL COMMENT 'Código que se valida en la puerta',
  `usuario_id`       INT UNSIGNED NOT NULL,
  `pelicula_id`      VARCHAR(80)  DEFAULT NULL COMMENT 'NULL si la película se eliminó después',
  `titulo`           VARCHAR(150) NOT NULL COMMENT 'Título al momento de comprar',
  `sala_id`          VARCHAR(20)  NOT NULL,
  `fecha`            DATE         NOT NULL COMMENT 'Fecha de la función',
  `hora`             TIME         NOT NULL COMMENT 'Hora de la función',
  `precio_entrada`   INT UNSIGNED NOT NULL COMMENT 'Precio unitario al momento de comprar',
  `subtotal`         INT UNSIGNED NOT NULL,
  `codigo_descuento` VARCHAR(30)  DEFAULT NULL,
  `descuento`        INT UNSIGNED NOT NULL DEFAULT 0,
  `total`            INT UNSIGNED NOT NULL,
  `estado`           ENUM('activa','cancelada') NOT NULL DEFAULT 'activa',
  `comprado`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelada_en`     DATETIME     DEFAULT NULL,
  PRIMARY KEY (`codigo`),
  KEY `idx_entradas_usuario` (`usuario_id`),
  KEY `idx_entradas_funcion` (`pelicula_id`, `fecha`, `hora`),
  CONSTRAINT `fk_entradas_usuario`   FOREIGN KEY (`usuario_id`)       REFERENCES `usuarios` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_entradas_pelicula`  FOREIGN KEY (`pelicula_id`)      REFERENCES `peliculas` (`id`)   ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_entradas_sala`      FOREIGN KEY (`sala_id`)          REFERENCES `salas` (`id`)       ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_entradas_descuento` FOREIGN KEY (`codigo_descuento`) REFERENCES `descuentos` (`codigo`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Asientos de cada entrada (ej: B4, D8).
-- La clave única impide vender dos veces el mismo asiento de una función.
-- `activo` vale 1 mientras la entrada está vigente y pasa a NULL al cancelarla
-- (lo hace el trigger de más abajo), así el asiento vuelve a quedar libre.
CREATE TABLE `entrada_asientos` (
  `entrada_codigo` CHAR(8)     NOT NULL,
  `pelicula_id`    VARCHAR(80) NOT NULL,
  `fecha`          DATE        NOT NULL,
  `hora`           TIME        NOT NULL,
  `asiento`        VARCHAR(5)  NOT NULL,
  `activo`         TINYINT(1)  DEFAULT 1,
  PRIMARY KEY (`entrada_codigo`, `asiento`),
  UNIQUE KEY `uq_asiento_funcion` (`pelicula_id`, `fecha`, `hora`, `asiento`, `activo`),
  CONSTRAINT `fk_asientos_entrada` FOREIGN KEY (`entrada_codigo`) REFERENCES `entradas` (`codigo`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Combos comprados junto con la entrada (guarda el precio de ese momento)
CREATE TABLE `entrada_combos` (
  `entrada_codigo` CHAR(8)      NOT NULL,
  `combo_id`       VARCHAR(40)  NOT NULL,
  `cantidad`       TINYINT UNSIGNED NOT NULL,
  `precio`         INT UNSIGNED NOT NULL COMMENT 'Precio unitario al momento de comprar',
  PRIMARY KEY (`entrada_codigo`, `combo_id`),
  CONSTRAINT `fk_ecombos_entrada` FOREIGN KEY (`entrada_codigo`) REFERENCES `entradas` (`codigo`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ecombos_combo`   FOREIGN KEY (`combo_id`)       REFERENCES `combos` (`id`)       ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RESEÑAS  (antes: resenas.json)
-- Un usuario tiene una sola valoración por película (clave primaria doble).
-- ---------------------------------------------------------------------
CREATE TABLE `resenas` (
  `pelicula_id` VARCHAR(80)  NOT NULL,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `estrellas`   TINYINT UNSIGNED NOT NULL,
  `comentario`  VARCHAR(500) NOT NULL DEFAULT '',
  `fecha`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pelicula_id`, `usuario_id`),
  CONSTRAINT `chk_estrellas` CHECK (`estrellas` BETWEEN 1 AND 5),
  CONSTRAINT `fk_resenas_pelicula` FOREIGN KEY (`pelicula_id`) REFERENCES `peliculas` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_resenas_usuario`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios` (`id`)  ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TRIGGER: al cancelar una entrada se liberan sus asientos
-- ---------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER `trg_entrada_cancelada` AFTER UPDATE ON `entradas`
FOR EACH ROW
BEGIN
  IF NEW.estado = 'cancelada' AND OLD.estado <> 'cancelada' THEN
    UPDATE `entrada_asientos` SET `activo` = NULL WHERE `entrada_codigo` = NEW.codigo;
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- VISTAS
-- ---------------------------------------------------------------------
-- Promedio de estrellas y cantidad de reseñas por película
CREATE VIEW `v_promedio_resenas` AS
SELECT p.`id` AS pelicula_id, p.`titulo`,
       ROUND(AVG(r.`estrellas`), 1) AS promedio, COUNT(r.`usuario_id`) AS cantidad
FROM `peliculas` p
LEFT JOIN `resenas` r ON r.`pelicula_id` = p.`id`
GROUP BY p.`id`, p.`titulo`;

-- Ventas por película (solo entradas no canceladas)
CREATE VIEW `v_ventas_por_pelicula` AS
SELECT p.`id` AS pelicula_id, p.`titulo`,
       COUNT(e.`codigo`) AS entradas_vendidas,
       COALESCE(SUM(e.`total`), 0) AS recaudado
FROM `peliculas` p
LEFT JOIN `entradas` e ON e.`pelicula_id` = p.`id` AND e.`estado` = 'activa'
GROUP BY p.`id`, p.`titulo`;

-- =====================================================================
-- DATOS INICIALES (tomados del proyecto)
-- =====================================================================

-- Salas
INSERT INTO `salas` (`id`,`nombre`,`tipo`,`filas`,`columnas`,`precio`) VALUES
('sala1', 'Sala 1', '2D', 6, 8, 8000),
('sala2', 'Sala 2', '3D', 5, 10, 10000),
('sala3', 'Sala 3', 'VIP', 4, 6, 12000);

-- Usuarios. Contraseñas iniciales: admin = 1234, juan = 5678 (¡cambiar la del admin!).
-- 'chris' conserva el hash que ya tenía en usuarios.json.
INSERT INTO `usuarios` (`usuario`,`clave_hash`,`rol`,`creado`) VALUES
('admin', '$2y$12$pAnUigkqNcIyO2b3L4OUuOt9kQIPPtoena.Vvc11JtklGm5gdWDEq', 'admin', '2026-01-01 00:00:00'),
('juan', '$2y$12$b9z6.64rl291wZWjpGNqTe/Mm1NYplgNseLmQRmH2KV3F4rnoAhqi', 'usuario', '2026-01-01 00:00:00'),
('chris', '$2y$10$rkgQ43jy3y/GhzHr31gk1.SjgFobsQXVT/7IRoL41o1VS5OBISJ/u', 'usuario', '2026-10-09 23:53:55');

-- Combos de confitería
INSERT INTO `combos` (`id`,`nombre`,`descripcion`,`precio`,`emoji`,`orden`) VALUES
('pochoclo-chico', 'Pochoclo chico', 'Balde individual', 3500, '🍿', 1),
('pochoclo-grande', 'Pochoclo grande', 'Para compartir', 5500, '🍿', 2),
('gaseosa', 'Gaseosa', 'Vaso de 500 ml', 2500, '🥤', 3),
('nachos', 'Nachos', 'Con salsa cheddar', 4000, '🧀', 4),
('combo-pareja', 'Combo pareja', 'Pochoclo grande + 2 gaseosas', 9000, '💑', 5),
('combo-familiar', 'Combo familiar', '2 pochoclos grandes + 4 gaseosas', 16000, '👨‍👩‍👧‍👦', 6);

-- Códigos de descuento
INSERT INTO `descuentos` (`codigo`,`tipo`,`valor`,`descripcion`,`vence`,`un_uso`) VALUES
('ESTUDIANTE', 'porcentaje', 20, '20% de descuento aplicado', NULL, 0),
('BIENVENIDO', 'porcentaje', 15, '15% de bienvenida (un solo uso)', NULL, 1),
('PROMO2000', 'monto', 2000, '$2.000 de descuento aplicado', NULL, 0);

-- Películas
INSERT INTO `peliculas` (`id`,`titulo`,`anio`,`genero`,`imagen`,`trailer`,`sala_id`,`orden`) VALUES
('matrix', 'Matrix', 1999, 'Ciencia ficción', 'imagenes/matrix.jpg', NULL, 'sala1', 1),
('coco', 'Coco', 2017, 'Animación', 'imagenes/coco.jpg', NULL, 'sala1', 2),
('duna', 'Duna', 2021, 'Ciencia ficción', 'imagenes/duna.jpg', NULL, 'sala2', 3),
('interestelar', 'Interestelar', 2014, 'Ciencia ficción', 'imagenes/interestelar.jpg', NULL, 'sala3', 4),
('avengers-endgame', 'Avengers: Endgame', 2019, 'Acción', 'imagenes/avenger-endgame.jpg', NULL, 'sala2', 5),
('black-panther', 'Black Panther', 2018, 'Acción', 'imagenes/black-panther.jpg', NULL, 'sala1', 6),
('la-odisea', 'La Odisea', 2026, 'Aventura', 'imagenes/la-odisea.jpg', '8un_UztYsw0', 'sala3', 7),
('spider-man-brand-new-day', 'Spider-Man: Brand New Day', 2026, 'Acción', 'imagenes/spider-man-brand-new-day.jpg', 'Pw1X-57Ms-8', 'sala2', 8),
('toy-story-5', 'Toy Story 5', 2026, 'Animacion', 'imagenes/toy-story-5.jpg', NULL, 'sala1', 9),
('Capitan America', 'Capitan America: un nuevo mundo', 2025, 'Accion', 'imagenes/capitan america.jpg', NULL, 'sala2', 10),
('mision imposible', 'Mision imposible: la sentencia final', 2025, 'Accion', 'imagenes/mision imposible.jpg', NULL, 'sala3', 11),
('f1 la pelicula', 'F1 la pelicula', 2025, 'deporte/Accion', 'imagenes/f1 la pelicula.jpg', NULL, 'sala1', 12),
('lilo y stich', 'lilo & stitch (live action)', 2025, 'comedia', 'imagenes/lilo y stich.jpg', NULL, 'sala2', 13),
('como entrenar a tu dragon', 'Como entrenar a tu dragon', 2025, 'Aventura', 'imagenes/como entrenar a tu dragon.jpg', NULL, 'sala3', 14),
('zootopia 2', 'Zootopia 2', 2025, 'comedia', 'imagenes/zootopia 2.jpg', NULL, 'sala1', 15),
('conjuro 4', 'El conjuro 4: Los ultimos ritos', 2025, 'terror', 'imagenes/conjuro 4.jpg', NULL, 'sala2', 16),
('28 años despues', 'Exterminio: el templo de huesos', 2025, 'terror', 'imagenes/28 años despues.jpg', NULL, 'sala3', 17),
('el telefono negro 2', 'Telefono negro 2', 2025, 'terror', 'imagenes/el telefono negro 2.jpg', NULL, 'sala1', 18);

-- Horarios de función
INSERT INTO `pelicula_horarios` (`pelicula_id`,`hora`) VALUES
('matrix', '15:00:00'),
('matrix', '18:00:00'),
('matrix', '21:00:00'),
('coco', '13:00:00'),
('coco', '15:00:00'),
('coco', '16:30:00'),
('duna', '18:00:00'),
('duna', '21:00:00'),
('interestelar', '19:30:00'),
('avengers-endgame', '16:30:00'),
('avengers-endgame', '19:30:00'),
('avengers-endgame', '22:30:00'),
('black-panther', '15:00:00'),
('black-panther', '19:30:00'),
('la-odisea', '18:00:00'),
('la-odisea', '21:00:00'),
('spider-man-brand-new-day', '15:00:00'),
('spider-man-brand-new-day', '18:00:00'),
('spider-man-brand-new-day', '21:00:00'),
('spider-man-brand-new-day', '22:30:00'),
('toy-story-5', '13:00:00'),
('toy-story-5', '15:00:00'),
('toy-story-5', '16:30:00'),
('Capitan America', '13:00:00'),
('Capitan America', '15:00:00'),
('Capitan America', '16:30:00'),
('mision imposible', '13:00:00'),
('mision imposible', '15:00:00'),
('mision imposible', '16:30:00'),
('f1 la pelicula', '13:00:00'),
('f1 la pelicula', '15:00:00'),
('f1 la pelicula', '16:30:00'),
('lilo y stich', '13:00:00'),
('lilo y stich', '15:00:00'),
('lilo y stich', '16:30:00'),
('como entrenar a tu dragon', '13:00:00'),
('como entrenar a tu dragon', '15:00:00'),
('como entrenar a tu dragon', '16:30:00'),
('zootopia 2', '13:00:00'),
('zootopia 2', '15:00:00'),
('zootopia 2', '16:30:00'),
('conjuro 4', '13:00:00'),
('conjuro 4', '15:00:00'),
('conjuro 4', '16:30:00'),
('conjuro 4', '19:30:00'),
('conjuro 4', '21:00:00'),
('conjuro 4', '22:00:00'),
('28 años despues', '13:00:00'),
('28 años despues', '15:00:00'),
('28 años despues', '16:30:00'),
('28 años despues', '19:30:00'),
('28 años despues', '21:00:00'),
('28 años despues', '22:00:00'),
('el telefono negro 2', '13:00:00'),
('el telefono negro 2', '15:00:00'),
('el telefono negro 2', '16:30:00'),
('el telefono negro 2', '19:30:00'),
('el telefono negro 2', '21:00:00'),
('el telefono negro 2', '22:00:00');

-- Días en cartelera (0 = domingo ... 6 = sábado)
INSERT INTO `pelicula_dias` (`pelicula_id`,`dia`) VALUES
('matrix', 0),
('matrix', 1),
('matrix', 2),
('matrix', 3),
('matrix', 4),
('matrix', 5),
('matrix', 6),
('coco', 0),
('coco', 1),
('coco', 2),
('coco', 3),
('coco', 4),
('coco', 5),
('coco', 6),
('duna', 0),
('duna', 1),
('duna', 2),
('duna', 3),
('duna', 4),
('duna', 5),
('duna', 6),
('interestelar', 4),
('interestelar', 5),
('interestelar', 6),
('interestelar', 0),
('avengers-endgame', 0),
('avengers-endgame', 1),
('avengers-endgame', 2),
('avengers-endgame', 3),
('avengers-endgame', 4),
('avengers-endgame', 5),
('avengers-endgame', 6),
('black-panther', 0),
('black-panther', 1),
('black-panther', 2),
('black-panther', 3),
('black-panther', 4),
('black-panther', 5),
('black-panther', 6),
('la-odisea', 1),
('la-odisea', 2),
('la-odisea', 3),
('la-odisea', 4),
('la-odisea', 5),
('la-odisea', 6),
('la-odisea', 0),
('spider-man-brand-new-day', 1),
('spider-man-brand-new-day', 2),
('spider-man-brand-new-day', 3),
('spider-man-brand-new-day', 4),
('spider-man-brand-new-day', 5),
('spider-man-brand-new-day', 6),
('spider-man-brand-new-day', 0),
('toy-story-5', 1),
('toy-story-5', 2),
('toy-story-5', 3),
('toy-story-5', 4),
('toy-story-5', 5),
('toy-story-5', 6),
('toy-story-5', 0),
('Capitan America', 1),
('Capitan America', 2),
('Capitan America', 3),
('Capitan America', 4),
('Capitan America', 5),
('Capitan America', 6),
('Capitan America', 0),
('mision imposible', 1),
('mision imposible', 2),
('mision imposible', 3),
('mision imposible', 4),
('mision imposible', 5),
('mision imposible', 6),
('mision imposible', 0),
('f1 la pelicula', 1),
('f1 la pelicula', 2),
('f1 la pelicula', 3),
('f1 la pelicula', 4),
('f1 la pelicula', 5),
('f1 la pelicula', 6),
('f1 la pelicula', 0),
('lilo y stich', 1),
('lilo y stich', 2),
('lilo y stich', 3),
('lilo y stich', 4),
('lilo y stich', 5),
('lilo y stich', 6),
('lilo y stich', 0),
('como entrenar a tu dragon', 1),
('como entrenar a tu dragon', 2),
('como entrenar a tu dragon', 3),
('como entrenar a tu dragon', 4),
('como entrenar a tu dragon', 5),
('como entrenar a tu dragon', 6),
('como entrenar a tu dragon', 0),
('zootopia 2', 1),
('zootopia 2', 2),
('zootopia 2', 3),
('zootopia 2', 4),
('zootopia 2', 5),
('zootopia 2', 6),
('zootopia 2', 0),
('conjuro 4', 1),
('conjuro 4', 2),
('conjuro 4', 3),
('conjuro 4', 4),
('conjuro 4', 5),
('conjuro 4', 6),
('conjuro 4', 0),
('28 años despues', 1),
('28 años despues', 2),
('28 años despues', 3),
('28 años despues', 4),
('28 años despues', 5),
('28 años despues', 6),
('28 años despues', 0),
('el telefono negro 2', 1),
('el telefono negro 2', 2),
('el telefono negro 2', 3),
('el telefono negro 2', 4),
('el telefono negro 2', 5),
('el telefono negro 2', 6),
('el telefono negro 2', 0);

-- Entradas ya vendidas (de entradas.json)
INSERT INTO `entradas` (`codigo`,`usuario_id`,`pelicula_id`,`titulo`,`sala_id`,`fecha`,`hora`,`precio_entrada`,`subtotal`,`codigo_descuento`,`descuento`,`total`,`estado`,`comprado`,`cancelada_en`) VALUES
('B89DC791', 3, 'spider-man-brand-new-day', 'Spider-Man: Brand New Day', 'sala2', '2026-10-15', '15:00:00', 10000, 20000, 'ESTUDIANTE', 4000, 16000, 'activa', '2026-10-10 00:42:59', NULL);
INSERT INTO `entrada_asientos` (`entrada_codigo`,`pelicula_id`,`fecha`,`hora`,`asiento`,`activo`) VALUES ('B89DC791', 'spider-man-brand-new-day', '2026-10-15', '15:00:00', 'B4', 1);
INSERT INTO `entrada_asientos` (`entrada_codigo`,`pelicula_id`,`fecha`,`hora`,`asiento`,`activo`) VALUES ('B89DC791', 'spider-man-brand-new-day', '2026-10-15', '15:00:00', 'D8', 1);
