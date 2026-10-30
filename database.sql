-- Base de datos inicial de Mantix.
-- Importar este archivo desde phpMyAdmin. Las tablas usan InnoDB para
-- garantizar las relaciones y UTF-8 completo para nombres y observaciones.
CREATE DATABASE IF NOT EXISTS mantix
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mantix;

-- Roles asignables a las cuentas de usuario.
CREATE TABLE IF NOT EXISTS roles (
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (codigo),
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Roles que determinan los permisos de acceso a Mantix';

-- Cada usuario tiene un rol. El hash guarda el resultado de password_hash(),
-- nunca la contraseña original. El nombre se conserva en mantenimientos
-- aunque posteriormente se elimine o desactive la cuenta responsable.
CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol_codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  correo VARCHAR(254) NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  estado ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY idx_usuarios_rol (rol_codigo),
  CONSTRAINT fk_usuarios_rol
    FOREIGN KEY (rol_codigo) REFERENCES roles (codigo)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Cuentas de usuario; contrasena_hash contiene solo hashes seguros';

-- Catálogo de estados usados por los equipos en la interfaz de Mantix.
CREATE TABLE IF NOT EXISTS equipos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  categoria VARCHAR(100) NOT NULL,
  marca VARCHAR(100) NOT NULL,
  modelo VARCHAR(100) NOT NULL,
  ubicacion VARCHAR(180) NOT NULL,
  estado ENUM('Operativo', 'En mantenimiento', 'Fuera de servicio')
    NOT NULL DEFAULT 'Operativo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipos_codigo (codigo),
  KEY idx_equipos_nombre (nombre),
  KEY idx_equipos_estado (estado)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Inventario de activos y equipos mantenidos';

-- Catálogo extensible de tipos de mantenimiento.
CREATE TABLE IF NOT EXISTS tipos_mantenimiento (
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (codigo),
  UNIQUE KEY uq_tipos_mantenimiento_nombre (nombre)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Tipos de mantenimiento, incluidos preventivo y correctivo';

-- Un mantenimiento puede conservarse aunque se elimine su equipo o usuario.
-- Las claves foráneas quedan en NULL al borrar esas entidades y las columnas
-- de snapshot mantienen sus nombres/códigos para consulta del historial.
CREATE TABLE IF NOT EXISTS mantenimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  equipo_id BIGINT UNSIGNED DEFAULT NULL,
  tipo_codigo VARCHAR(40) NOT NULL,
  responsable_usuario_id BIGINT UNSIGNED DEFAULT NULL,
  equipo_codigo_snapshot VARCHAR(50) NOT NULL,
  equipo_nombre_snapshot VARCHAR(150) NOT NULL,
  responsable_nombre_snapshot VARCHAR(150) NOT NULL,
  fecha_programada DATE NOT NULL,
  fecha_completada DATE DEFAULT NULL,
  descripcion TEXT NOT NULL,
  observaciones TEXT DEFAULT NULL,
  estado ENUM('Pendiente', 'Completado', 'Vencido')
    NOT NULL DEFAULT 'Pendiente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mantenimientos_equipo_fecha (equipo_id, fecha_programada),
  KEY idx_mantenimientos_responsable (responsable_usuario_id),
  KEY idx_mantenimientos_tipo_fecha (tipo_codigo, fecha_programada),
  KEY idx_mantenimientos_estado_fecha (estado, fecha_programada),
  KEY idx_mantenimientos_fecha_completada (fecha_completada),
  CONSTRAINT fk_mantenimientos_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_mantenimientos_tipo
    FOREIGN KEY (tipo_codigo) REFERENCES tipos_mantenimiento (codigo)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_mantenimientos_responsable
    FOREIGN KEY (responsable_usuario_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Mantenimientos programados y realizados; los snapshots protegen el historial';

-- Datos iniciales idempotentes: pueden volver a importarse sin duplicar
-- registros ni reemplazar los nombres/descripciones si ya fueron editados.
INSERT INTO roles (codigo, nombre, descripcion) VALUES
  ('administrador', 'Administrador', 'Administra usuarios, equipos y mantenimientos'),
  ('responsable_mantenimiento', 'Responsable de mantenimiento', 'Gestiona y ejecuta mantenimientos')
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

INSERT INTO tipos_mantenimiento (codigo, nombre, descripcion) VALUES
  ('preventivo', 'Preventivo', 'Actividad planificada para prevenir fallas'),
  ('correctivo', 'Correctivo', 'Actividad para corregir una falla detectada')
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);
