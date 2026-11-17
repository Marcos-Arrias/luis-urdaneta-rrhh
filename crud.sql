-- ========================================================
-- SISTEMA DE GESTIÓN DE RRHH - TESIS DE GRADO
-- Motor: MySQL 8.0+ / MariaDB 10.4+
-- Normalización: 3FN / BCNF Completa
-- Control de Acceso: ReBAC (Relationship-Based Access Control)
-- ========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Habilitar el programador de eventos de MySQL para el cierre de jornada
SET GLOBAL event_scheduler = ON;

-- --------------------------------------------------------
-- 1. CATÁLOGOS GEOGRÁFICOS Y MINISTERIALES (3FN)
-- --------------------------------------------------------

CREATE TABLE `cat_municipios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_municipios` (`id`, `nombre`) VALUES
(1, 'Maracaibo'), (2, 'San Francisco');

CREATE TABLE `cat_parroquias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `municipio_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_municipio_parroquia` (`municipio_id`, `nombre`),
  CONSTRAINT `fk_parroquia_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `cat_municipios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_parroquias` (`id`, `municipio_id`, `nombre`) VALUES
(1, 1, 'Chiquinquirá'), (2, 1, 'Olegario Villalobos'), (3, 2, 'San Francisco');

CREATE TABLE `cat_dependencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL UNIQUE,
  `nombre` varchar(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_dependencias` (`id`, `codigo`, `nombre`) VALUES
(1, 'DEP-00241', 'Zona Educativa Zulia'),
(2, 'DEP-00892', 'Plantel U.E.N Luis Urdaneta');

CREATE TABLE `cat_cargos_nominales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL UNIQUE,
  `denominacion` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_cargos_nominales` (`id`, `codigo`, `denominacion`) VALUES
(1, 'DOC-IV', 'Docente IV'),
(2, 'ADM-II', 'Asistente Administrativo II'),
(3, 'OBR-I', 'Obrero de Mantenimiento');

CREATE TABLE `cat_bancos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` char(4) NOT NULL UNIQUE,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_bancos` (`id`, `codigo`, `nombre`) VALUES
(1, '0102', 'Banco de Venezuela'),
(2, '0105', 'Banco Mercantil'),
(3, '0108', 'Banco Provincial');

-- --------------------------------------------------------
-- 2. CATÁLOGOS INSTITUCIONALES Y DE ROLES
-- --------------------------------------------------------

CREATE TABLE `cat_tipos_personal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_tipos_personal` (`id`, `nombre`) VALUES
(1, 'Docente'), (2, 'Administrativo'), (3, 'Obrero');

CREATE TABLE `cat_cargos_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_personal_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT 'Cátedra / Función asignada.',
  PRIMARY KEY (`id`),
  KEY `tipo_personal_id` (`tipo_personal_id`),
  CONSTRAINT `fk_cargos_tipo` FOREIGN KEY (`tipo_personal_id`) REFERENCES `cat_tipos_personal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_cargos_areas` (`id`, `tipo_personal_id`, `nombre`, `descripcion`) VALUES
(1, 1, 'Biología', 'Cátedra de Ciencias Naturales'),
(2, 1, 'Matemáticas', 'Cátedra de Ciencias Exactas'),
(3, 2, 'Director/a', 'Dirección del Plantel'),
(4, 2, 'Secretaria', 'Control de Expedientes'),
(5, 3, 'Mantenimiento General', 'Planta Física');

CREATE TABLE `cat_patologias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('Alergia','Enfermedad Crónica','Condición Especial','Otra') NOT NULL,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tipo_nombre` (`tipo`, `nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_patologias` (`id`, `tipo`, `nombre`) VALUES
(1, 'Alergia', 'Penicilina'),
(2, 'Enfermedad Crónica', 'Hipertensión Arterial'),
(3, 'Enfermedad Crónica', 'Diabetes Tipo II'),
(4, 'Alergia', 'Polvo / Ácaros');

-- --------------------------------------------------------
-- 3. NÚCLEO DE PERSONAL Y FICHA TÉCNICA (3FN)
-- --------------------------------------------------------

CREATE TABLE `personal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL UNIQUE,
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `estado_laboral` enum('Activo','Permiso','Reposo','Inactivo','Jubilado') DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
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
  CONSTRAINT `fk_pc_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_cargo` FOREIGN KEY (`cargo_area_id`) REFERENCES `cat_cargos_areas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personal_contactos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `tipo_contacto` enum('Teléfono Personal','Teléfono Emergencia','Email','Dirección Habitación') NOT NULL,
  `valor` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `personal_id` (`personal_id`),
  CONSTRAINT `fk_contactos_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `personal_datos_adicionales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL UNIQUE,
  `estado_civil` enum('Soltero/a','Casado/a','Divorciado/a','Viudo/a','Concubinato') DEFAULT 'Soltero/a',
  `cantidad_hijos` int(11) DEFAULT 0,
  `nivel_academico` varchar(100) DEFAULT NULL,
  `tipo_sangre` varchar(5) DEFAULT NULL,
  `talla_camisa` varchar(10) DEFAULT NULL,
  `talla_pantalon` varchar(10) DEFAULT NULL,
  `talla_calzado` varchar(10) DEFAULT NULL,
  `banco_id` int(11) DEFAULT NULL,
  `cuenta_bancaria` varchar(20) DEFAULT NULL,
  `etnia` varchar(50) DEFAULT NULL,
  `fecha_ingreso_mppe` date DEFAULT NULL,
  `dependencia_id` int(11) DEFAULT NULL,
  `turno` enum('Mañana','Tarde','Completo','Integral','Nocturno') DEFAULT 'Mañana',
  `cargo_nominal_id` int(11) DEFAULT NULL,
  `horas_laborales` int(11) DEFAULT 0,
  `parroquia_id` int(11) DEFAULT NULL,
  `centro_votacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pda_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pda_banco` FOREIGN KEY (`banco_id`) REFERENCES `cat_bancos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pda_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `cat_dependencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pda_cargo_nom` FOREIGN KEY (`cargo_nominal_id`) REFERENCES `cat_cargos_nominales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pda_parroquia` FOREIGN KEY (`parroquia_id`) REFERENCES `cat_parroquias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Normalización Médica (1FN): Eliminación de listas dentro de texto
CREATE TABLE `personal_salud_patologias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `patologia_id` int(11) NOT NULL,
  `medicamento_permanente` varchar(150) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_personal_patologia` (`personal_id`, `patologia_id`),
  CONSTRAINT `fk_psp_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psp_patologia` FOREIGN KEY (`patologia_id`) REFERENCES `cat_patologias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. SISTEMA GENERALIZADO DE DOCUMENTOS DIGITALES
-- --------------------------------------------------------

CREATE TABLE `cat_tipos_documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` char(3) NOT NULL UNIQUE,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cat_tipos_documentos` (`id`, `clave`, `nombre`) VALUES
(1, 'CED', 'Cédula de Identidad'),
(2, 'FOT', 'Fotografía Tamaño Carnet'),
(3, 'TIT', 'Título Universitario o Certificado'),
(4, 'MED', 'Informe o Certificado Médico'),
(5, 'SOP', 'Soporte de Justificación / Reposo'),
(6, 'MEM', 'Memorándum o Acta Institucional'),
(7, 'OTR', 'Otro Soporte Digital');

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento_id` int(11) NOT NULL,
  `codigo_archivo` varchar(100) NOT NULL UNIQUE,
  `nombre_original` varchar(200) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `tamano_bytes` bigint(20) DEFAULT 0,
  `hash_verificacion` varchar(64) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_doc_tipo` FOREIGN KEY (`tipo_documento_id`) REFERENCES `cat_tipos_documentos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla relacional polimórfica/vinculante para documentos
CREATE TABLE `documentos_enlaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `tabla_destino` enum('personal','asistencias','cronograma_eventos','institucional') NOT NULL,
  `registro_id` int(11) NOT NULL,
  `notas` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_busqueda_enlace` (`tabla_destino`, `registro_id`),
  CONSTRAINT `fk_enlace_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. MÓDULOS OPERATIVOS CON IDs SUBROGADOS
-- --------------------------------------------------------

-- Tabla de Asistencias con soporte de cierre automático
CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `estado_asistencia` enum('Presente','Ausente','Retardo','Falta Justificada') NOT NULL,
  `cierre_automatico` tinyint(1) NOT NULL DEFAULT 0,
  `observaciones` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asistencia_unica` (`personal_id`, `fecha`),
  CONSTRAINT `fk_asistencias_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
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
  CONSTRAINT `fk_horarios_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
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

-- Con ID independiente (Surrogate Key) solicitado
CREATE TABLE `cronograma_participantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evento_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `rol_participacion` enum('Organizador','Ponente','Asistente','Apoyo') DEFAULT 'Asistente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento_participante` (`evento_id`, `personal_id`),
  CONSTRAINT `fk_cp_evento` FOREIGN KEY (`evento_id`) REFERENCES `cronograma_eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organigrama con ID independiente y relación recursiva documentada
CREATE TABLE `organigrama_jerarquia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) NOT NULL,
  `jefe_id` int(11) DEFAULT NULL,
  `fecha_asignacion` date NOT NULL DEFAULT (CURRENT_DATE),
  `estado_jerarquia` enum('Vigente','Reasignado') DEFAULT 'Vigente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_personal_nodo` (`personal_id`),
  KEY `idx_jefe` (`jefe_id`),
  CONSTRAINT `fk_organigrama_subordinado` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_organigrama_supervisor` FOREIGN KEY (`jefe_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. SISTEMA DE SEGURIDAD Y CONTROL ReBAC (RELATIONSHIP-BASED ACCESS CONTROL)
-- --------------------------------------------------------

CREATE TABLE `usuarios_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('Activo','Bloqueado') DEFAULT 'Activo',
  `ultimo_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_usuarios_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios_sistema` (`id`, `personal_id`, `username`, `password_hash`, `estado`) VALUES
(1, NULL, 'admin', '$2y$10$FOupjgLEe4PqHRhU0PTLTu9h6Bf4fgUR2aDPQUTPt0tmmFTeddfze', 'Activo');

-- Tipos de Recursos protegidos en ReBAC
CREATE TABLE `rebac_recursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_recurso` varchar(60) NOT NULL UNIQUE,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `rebac_recursos` (`id`, `nombre_recurso`, `descripcion`) VALUES
(1, 'expediente_personal', 'Expediente digital y datos del trabajador'),
(2, 'asistencias', 'Control de asistencias y novedades'),
(3, 'organigrama', 'Árbol jerárquico y relaciones de mando'),
(4, 'documentos', 'Archivos y soportes institucionales');

-- Tipos de Relaciones que un Sujeto puede tener con un Objeto
CREATE TABLE `rebac_tipos_relacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave_relacion` varchar(40) NOT NULL UNIQUE,
  `descripcion` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `rebac_tipos_relacion` (`id`, `clave_relacion`, `descripcion`) VALUES
(1, 'superadministrador', 'Control total sin restricciones de objeto'),
(2, 'supervisor_directo', 'Jefe jerárquico según el organigrama'),
(3, 'propietario', 'El propio usuario es dueño del recurso/ficha'),
(4, 'coordinador_area', 'Gestor del departamento o especialidad'),
(5, 'lector', 'Permiso únicamente de consulta');

-- Políticas ReBAC: Permisos derivados de la relación
CREATE TABLE `rebac_politicas_permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `relacion_id` int(11) NOT NULL,
  `recurso_id` int(11) NOT NULL,
  `puede_crear` tinyint(1) NOT NULL DEFAULT 0,
  `puede_leer` tinyint(1) NOT NULL DEFAULT 0,
  `puede_editar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_eliminar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_politica` (`relacion_id`, `recurso_id`),
  CONSTRAINT `fk_pol_relacion` FOREIGN KEY (`relacion_id`) REFERENCES `rebac_tipos_relacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pol_recurso` FOREIGN KEY (`recurso_id`) REFERENCES `rebac_recursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `rebac_politicas_permisos` (`relacion_id`, `recurso_id`, `puede_crear`, `puede_leer`, `puede_editar`, `puede_eliminar`) VALUES
(1, 1, 1, 1, 1, 1), -- SuperAdmin sobre Expedientes
(1, 2, 1, 1, 1, 1), -- SuperAdmin sobre Asistencias
(2, 2, 1, 1, 1, 0), -- Supervisor puede registrar/editar asistencia de su equipo, pero no borrarla
(3, 1, 0, 1, 0, 0); -- Propietario (empleado) solo puede leer su propio expediente

-- Tuplas Zanzibar/ReBAC: (Sujeto, Relación, Recurso, ID_Instancia_Objeto)
CREATE TABLE `rebac_tuplas_grafo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `relacion_id` int(11) NOT NULL,
  `recurso_id` int(11) NOT NULL,
  `objeto_id` int(11) NOT NULL COMMENT 'ID del registro puntual en la tabla recurso',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tupla_grafo` (`usuario_id`, `relacion_id`, `recurso_id`, `objeto_id`),
  CONSTRAINT `fk_grafo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grafo_relacion` FOREIGN KEY (`relacion_id`) REFERENCES `rebac_tipos_relacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grafo_recurso` FOREIGN KEY (`recurso_id`) REFERENCES `rebac_recursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sesiones y Auditoría
CREATE TABLE `sesiones_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `ip_direccion` varchar(45) DEFAULT NULL,
  `dispositivo_agente` text DEFAULT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE CASCADE
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
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. PROCEDIMIENTO ALMACENADO Y EVENTO: CIERRE AUTOMÁTICO DE JORNADA
-- --------------------------------------------------------

DELIMITER $$

-- Procedimiento que busca entradas del día sin salida registrada y las cierra
CREATE PROCEDURE `sp_cerrar_jornadas_incompletas`()
BEGIN
    UPDATE `asistencias`
    SET 
        `hora_salida` = '18:00:00',
        `cierre_automatico` = 1,
        `observaciones` = CONCAT(IFNULL(`observaciones`, ''), ' [Jornada cerrada automáticamente por el sistema]')
    WHERE 
        `fecha` = CURDATE() 
        AND `hora_entrada` IS NOT NULL 
        AND `hora_salida` IS NULL;
END$$

DELIMITER ;

-- Evento programado: se ejecuta automáticamente todos los días a las 23:59:00
DROP EVENT IF EXISTS `ev_cierre_automatico_diario`;
CREATE EVENT `ev_cierre_automatico_diario`
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURDATE()) + INTERVAL 23 HOUR + INTERVAL 59 MINUTE)
DO
    CALL sp_cerrar_jornadas_incompletas();

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;