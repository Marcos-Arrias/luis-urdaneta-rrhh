-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 19-10-2026 a las 23:57:40
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `crud`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `estado_asistencia` enum('Presente','Ausente','Retardo','Falta Justificada') NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_cargos_areas`
--

CREATE TABLE `cat_cargos_areas` (
  `id` int(11) NOT NULL,
  `tipo_personal_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT 'Cátedra / Función institucional.',
  `depende_de_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cat_cargos_areas`
--

INSERT INTO `cat_cargos_areas` (`id`, `tipo_personal_id`, `nombre`, `descripcion`, `depende_de_id`) VALUES
(1, 1, 'Biología', 'Cátedra de Ciencias Naturales', NULL),
(2, 1, 'Matemáticas', 'Cátedra de Ciencias Exactas', NULL),
(3, 2, 'Director/a', 'Dirección e Inspección del Plantel', NULL),
(4, 2, 'Secretaria', 'Atención y Control de Expedientes', NULL),
(5, 3, 'Mantenimiento General', 'Mantenimiento de Planta Física', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_roles_sistema`
--

CREATE TABLE `cat_roles_sistema` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `nivel_acceso` int(11) NOT NULL DEFAULT 1 COMMENT '1: Basico, 10: SuperAdmin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cat_roles_sistema`
--

INSERT INTO `cat_roles_sistema` (`id`, `nombre`, `nivel_acceso`) VALUES
(1, 'SuperAdministrador', 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipos_personal`
--

CREATE TABLE `cat_tipos_personal` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cat_tipos_personal`
--

INSERT INTO `cat_tipos_personal` (`id`, `nombre`) VALUES
(2, 'Administrativo'),
(1, 'Docente'),
(3, 'Obrero');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_organigrama`
--

CREATE TABLE `configuracion_organigrama` (
  `id` int(11) NOT NULL,
  `jerarquia_json` text NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cronograma_eventos`
--

CREATE TABLE `cronograma_eventos` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `creador_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cronograma_participantes`
--

CREATE TABLE `cronograma_participantes` (
  `evento_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_auditoria`
--

CREATE TABLE `historial_auditoria` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `accion` enum('CREAR','ACTUALIZAR','ELIMINAR','LOGIN','ACCESO_CRITICO') NOT NULL,
  `descripcion_detallada` text NOT NULL,
  `ip_origen` varchar(45) DEFAULT NULL,
  `fecha_evento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_laborales`
--

CREATE TABLE `horarios_laborales` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `dia_semana` enum('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `actividad` varchar(150) NOT NULL,
  `color_fondo` varchar(50) DEFAULT 'bg-primary'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `organigrama_institucional`
--

CREATE TABLE `organigrama_institucional` (
  `id` int(11) NOT NULL,
  `estructura_json` longtext NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal`
--

CREATE TABLE `personal` (
  `id` int(11) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `estado_laboral` enum('Activo','Permiso','Reposo','Inactivo','Jubilado') DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_archivos_digitales`
--

CREATE TABLE `personal_archivos_digitales` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `codigo_archivo` varchar(100) NOT NULL COMMENT 'Ej: V12345678_42_CED_01.jpg',
  `tipo_documento_clave` char(3) NOT NULL COMMENT 'FOT, CED, TIT, MED, SOP, OTR',
  `numero_secuencia` int(11) NOT NULL DEFAULT 1,
  `nombre_original` varchar(150) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_cargos`
--

CREATE TABLE `personal_cargos` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `cargo_area_id` int(11) NOT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado_cargo` enum('Vigente','Finalizado') DEFAULT 'Vigente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_contactos`
--

CREATE TABLE `personal_contactos` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `tipo_contacto` enum('Teléfono Personal','Teléfono Emergencia','Email','Dirección') NOT NULL,
  `valor` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_datos_adicionales`
--

CREATE TABLE `personal_datos_adicionales` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `estado_civil` enum('Soltero/a','Casado/a','Divorciado/a','Viudo/a','Concubinato') DEFAULT 'Soltero/a',
  `cantidad_hijos` int(11) DEFAULT 0,
  `nivel_academico` varchar(100) DEFAULT NULL COMMENT 'Ej: Bachiller, Licenciado, Magister',
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
  `medicamentos_permanentes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_documentos`
--

CREATE TABLE `personal_documentos` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `tipo_documento` enum('Cédula Copia','RIF','Título Académico','Certificado Médico','Soporte Reposo','Curriculum','Otro') NOT NULL,
  `nombre_archivo` varchar(150) NOT NULL COMMENT 'Ej: titulo_universitario.pdf',
  `ruta_archivo` varchar(255) NOT NULL COMMENT 'Ruta en el servidor (Ej: /uploads/docs/)',
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_permisos_reposos`
--

CREATE TABLE `personal_permisos_reposos` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `tipo_permiso` enum('Salud / Médico','Diligencia Personal','Maternidad/Paternidad','Duelo','Vacaciones','Otro') NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo_detallado` text NOT NULL,
  `estado_aprobacion` enum('Pendiente','Aprobado','Rechazado') DEFAULT 'Pendiente',
  `aprobado_por_usuario_id` int(11) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_usuarios`
--

CREATE TABLE `sesiones_usuarios` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `ip_direccion` varchar(45) DEFAULT NULL,
  `dispositivo_agente` text DEFAULT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_sistema`
--

CREATE TABLE `usuarios_sistema` (
  `id` int(11) NOT NULL,
  `personal_id` int(11) DEFAULT NULL COMMENT 'Puede ser NULL si es un admin externo',
  `rol_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('Activo','Bloqueado') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios_sistema`
--

INSERT INTO `usuarios_sistema` (`id`, `personal_id`, `rol_id`, `username`, `password_hash`, `estado`) VALUES
(1, NULL, 1, 'admin', '$2y$10$FOupjgLEe4PqHRhU0PTLTu9h6Bf4fgUR2aDPQUTPt0tmmFTeddfze', 'Activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asistencia_unica` (`personal_id`,`fecha`);

--
-- Indices de la tabla `cat_cargos_areas`
--
ALTER TABLE `cat_cargos_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_personal_id` (`tipo_personal_id`),
  ADD KEY `fk_dependencia` (`depende_de_id`);

--
-- Indices de la tabla `cat_roles_sistema`
--
ALTER TABLE `cat_roles_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `cat_tipos_personal`
--
ALTER TABLE `cat_tipos_personal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `configuracion_organigrama`
--
ALTER TABLE `configuracion_organigrama`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cronograma_eventos`
--
ALTER TABLE `cronograma_eventos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cronograma_participantes`
--
ALTER TABLE `cronograma_participantes`
  ADD PRIMARY KEY (`evento_id`,`personal_id`),
  ADD KEY `personal_id` (`personal_id`);

--
-- Indices de la tabla `historial_auditoria`
--
ALTER TABLE `historial_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `horarios_laborales`
--
ALTER TABLE `horarios_laborales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personal_id` (`personal_id`);

--
-- Indices de la tabla `organigrama_institucional`
--
ALTER TABLE `organigrama_institucional`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `personal`
--
ALTER TABLE `personal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `personal_archivos_digitales`
--
ALTER TABLE `personal_archivos_digitales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_archivo` (`codigo_archivo`),
  ADD KEY `personal_id` (`personal_id`);

--
-- Indices de la tabla `personal_cargos`
--
ALTER TABLE `personal_cargos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personal_id` (`personal_id`),
  ADD KEY `cargo_area_id` (`cargo_area_id`);

--
-- Indices de la tabla `personal_contactos`
--
ALTER TABLE `personal_contactos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personal_id` (`personal_id`);

--
-- Indices de la tabla `personal_datos_adicionales`
--
ALTER TABLE `personal_datos_adicionales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_id` (`personal_id`);

--
-- Indices de la tabla `personal_documentos`
--
ALTER TABLE `personal_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personal_id` (`personal_id`);

--
-- Indices de la tabla `personal_permisos_reposos`
--
ALTER TABLE `personal_permisos_reposos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personal_id` (`personal_id`),
  ADD KEY `aprobado_por_usuario_id` (`aprobado_por_usuario_id`);

--
-- Indices de la tabla `sesiones_usuarios`
--
ALTER TABLE `sesiones_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `personal_id` (`personal_id`),
  ADD KEY `rol_id` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cat_cargos_areas`
--
ALTER TABLE `cat_cargos_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cat_roles_sistema`
--
ALTER TABLE `cat_roles_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cat_tipos_personal`
--
ALTER TABLE `cat_tipos_personal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `configuracion_organigrama`
--
ALTER TABLE `configuracion_organigrama`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cronograma_eventos`
--
ALTER TABLE `cronograma_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_auditoria`
--
ALTER TABLE `historial_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios_laborales`
--
ALTER TABLE `horarios_laborales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `organigrama_institucional`
--
ALTER TABLE `organigrama_institucional`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_archivos_digitales`
--
ALTER TABLE `personal_archivos_digitales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_cargos`
--
ALTER TABLE `personal_cargos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_contactos`
--
ALTER TABLE `personal_contactos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_datos_adicionales`
--
ALTER TABLE `personal_datos_adicionales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_documentos`
--
ALTER TABLE `personal_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_permisos_reposos`
--
ALTER TABLE `personal_permisos_reposos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sesiones_usuarios`
--
ALTER TABLE `sesiones_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cat_cargos_areas`
--
ALTER TABLE `cat_cargos_areas`
  ADD CONSTRAINT `cat_cargos_areas_ibfk_1` FOREIGN KEY (`tipo_personal_id`) REFERENCES `cat_tipos_personal` (`id`),
  ADD CONSTRAINT `fk_dependencia` FOREIGN KEY (`depende_de_id`) REFERENCES `cat_cargos_areas` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cronograma_participantes`
--
ALTER TABLE `cronograma_participantes`
  ADD CONSTRAINT `cronograma_participantes_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `cronograma_eventos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cronograma_participantes_ibfk_2` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_auditoria`
--
ALTER TABLE `historial_auditoria`
  ADD CONSTRAINT `historial_auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `horarios_laborales`
--
ALTER TABLE `horarios_laborales`
  ADD CONSTRAINT `horarios_laborales_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personal_archivos_digitales`
--
ALTER TABLE `personal_archivos_digitales`
  ADD CONSTRAINT `personal_archivos_digitales_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personal_cargos`
--
ALTER TABLE `personal_cargos`
  ADD CONSTRAINT `personal_cargos_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personal_cargos_ibfk_2` FOREIGN KEY (`cargo_area_id`) REFERENCES `cat_cargos_areas` (`id`);

--
-- Filtros para la tabla `personal_contactos`
--
ALTER TABLE `personal_contactos`
  ADD CONSTRAINT `personal_contactos_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personal_datos_adicionales`
--
ALTER TABLE `personal_datos_adicionales`
  ADD CONSTRAINT `personal_datos_adicionales_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personal_documentos`
--
ALTER TABLE `personal_documentos`
  ADD CONSTRAINT `personal_documentos_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personal_permisos_reposos`
--
ALTER TABLE `personal_permisos_reposos`
  ADD CONSTRAINT `personal_permisos_reposos_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personal_permisos_reposos_ibfk_2` FOREIGN KEY (`aprobado_por_usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `sesiones_usuarios`
--
ALTER TABLE `sesiones_usuarios`
  ADD CONSTRAINT `sesiones_usuarios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  ADD CONSTRAINT `usuarios_sistema_ibfk_1` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `usuarios_sistema_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `cat_roles_sistema` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
