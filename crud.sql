-- phpMyAdmin SQL Dump optimizado
-- Base de datos: `crud`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. TABLAS DE CATÁLOGOS GLOBALES
-- --------------------------------------------------------

CREATE TABLE `cat_tipos_personal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_tipos_personal` (`id`, `nombre`) VALUES
(2, 'Administrativo'),
(1, 'Docente'),
(3, 'Obrero');

CREATE TABLE `cat_cargos_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_personal_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT 'Cátedra / Función institucional.',
  PRIMARY KEY (`id`),
  KEY `tipo_personal_id` (`tipo_personal_id`),
  CONSTRAINT `cat_cargos_areas_ibfk_1` FOREIGN KEY (`tipo_personal_id`) REFERENCES `cat_tipos_personal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_cargos_areas` (`id`, `tipo_personal_id`, `nombre`, `descripcion`) VALUES
(1, 1, 'Biología', 'Cátedra de Ciencias Naturales'),
(2, 1, 'Matemáticas', 'Cátedra de Ciencias Exactas'),
(3, 2, 'Director/a', 'Dirección e Inspección del Plantel'),
(4, 2, 'Secretaria', 'Atención y Control de Expedientes'),
(5, 3, 'Mantenimiento General', 'Mantenimiento de Planta Física');

CREATE TABLE `cat_roles_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `nivel_acceso` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_roles_sistema` (`id`, `nombre`, `nivel_acceso`) VALUES
(1, 'SuperAdministrador', 10);

-- --------------------------------------------------------
-- 2. TABLAS DEL PERSONAL (NORMALIZADAS)
-- --------------------------------------------------------

CREATE TABLE `personal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `estado_laboral` enum('Activo','Permiso','Reposo','Inactivo','Jubilado') DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula` (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personal_cargos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `cargo_area_id` int(11) NOT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado_cargo` enum('Vigente','Finalizado') DEFAULT 'Vigente',
  PRIMARY KEY (`id`),
  KEY `personal_id` (`personal_id`),
  KEY `cargo_area_id` (`cargo_area_id`),
  CONSTRAINT `personal_cargos_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personal_cargos_ibfk_2` FOREIGN KEY (`cargo_area_id`) REFERENCES `cat_cargos_areas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personal_contactos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `tipo_contacto` enum('Teléfono Personal','Teléfono Emergencia','Email','Dirección') NOT NULL,
  `valor` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `personal_id` (`personal_id`),
  CONSTRAINT `personal_contactos_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personal_datos_adicionales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `estado_civil` enum('Soltero/a','Casado/a','Divorciado/a','Viudo/a','Concubinato') DEFAULT 'Soltero/a',
  `cantidad_hijos` int(11) DEFAULT 0,
  `nivel_academico` varchar(100) DEFAULT NULL,
  `tipo_sangre` varchar(5) DEFAULT NULL,
  `alergias_medicas` text DEFAULT NULL,
  `condiciones_especiales` text DEFAULT NULL,
  `talla_camisa` varchar(10) DEFAULT NULL,
  `talla_pantalon` varchar(10) DEFAULT NULL,
  `talla_calzado` varchar(10) DEFAULT NULL,
  `banco_nomina` varchar(50) DEFAULT NULL,
  `cuenta_bancaria` varchar(30) DEFAULT NULL,
  `etnia` varchar(50) DEFAULT NULL,
  `fecha_ingreso_mppe` date DEFAULT NULL,
  `dependencia` varchar(100) DEFAULT NULL,
  `codigo_dependencia` varchar(50) DEFAULT NULL,
  `turno` enum('Mañana','Tarde','Completo','Integral','Nocturno') DEFAULT 'Mañana',
  `cargo_nominal` varchar(100) DEFAULT NULL,
  `codigo_cargo` varchar(50) DEFAULT NULL,
  `horas_laborales` int(11) DEFAULT 0,
  `correo_electronico` varchar(120) DEFAULT NULL,
  `direccion_habitacion` text DEFAULT NULL,
  `municipio` varchar(80) DEFAULT NULL,
  `parroquia` varchar(80) DEFAULT NULL,
  `centro_votacion` varchar(150) DEFAULT NULL,
  `patologias` text DEFAULT NULL,
  `medicamentos_permanentes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_id` (`personal_id`),
  CONSTRAINT `personal_datos_adicionales_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personal_archivos_digitales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `codigo_archivo` varchar(100) NOT NULL,
  `tipo_documento_clave` char(3) NOT NULL,
  `numero_secuencia` int(11) NOT NULL DEFAULT 1,
  `nombre_original` varchar(150) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_archivo` (`codigo_archivo`),
  KEY `personal_id` (`personal_id`),
  CONSTRAINT `personal_archivos_digitales_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. MÓDULOS DEL SISTEMA (Asistencias, Cronograma, Organigrama)
-- --------------------------------------------------------

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `estado_asistencia` enum('Presente','Ausente','Retardo','Falta Justificada') NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asistencia_unica` (`personal_id`,`fecha`),
  CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `horarios_laborales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `dia_semana` enum('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `actividad` varchar(150) NOT NULL,
  `color_fondo` varchar(50) DEFAULT 'bg-primary',
  PRIMARY KEY (`id`),
  KEY `personal_id` (`personal_id`),
  CONSTRAINT `horarios_laborales_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cronograma_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `creador_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cronograma_participantes` (
  `evento_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  PRIMARY KEY (`evento_id`,`personal_id`),
  KEY `personal_id` (`personal_id`),
  CONSTRAINT `cronograma_participantes_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `cronograma_eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cronograma_participantes_ibfk_2` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `organigrama_institucional` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estructura_json` longtext NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. SEGURIDAD Y ACCESOS
-- --------------------------------------------------------

CREATE TABLE `usuarios_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) DEFAULT NULL,
  `rol_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('Activo','Bloqueado') DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `personal_id` (`personal_id`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `usuarios_sistema_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_sistema_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `cat_roles_sistema` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios_sistema` (`id`, `personal_id`, `rol_id`, `username`, `password_hash`, `estado`) VALUES
(1, NULL, 1, 'admin', '$2y$10$FOupjgLEe4PqHRhU0PTLTu9h6Bf4fgUR2aDPQUTPt0tmmFTeddfze', 'Activo');

CREATE TABLE `sesiones_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `ip_direccion` varchar(45) DEFAULT NULL,
  `dispositivo_agente` text DEFAULT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `sesiones_usuarios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `historial_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `accion` enum('CREAR','ACTUALIZAR','ELIMINAR','LOGIN','ACCESO_CRITICO','EXPORTAR') NOT NULL,
  `descripcion_detallada` text NOT NULL,
  `ip_origen` varchar(45) DEFAULT NULL,
  `fecha_evento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `historial_auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;