<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$host     = '127.0.0.1'; 
$dbname   = 'crud'; 
$username = 'root'; 
$password = '';     

try {
    // 1. CONEXIÓN AL MOTOR MYSQL Y SELECCIÓN DE BASE DE DATOS
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // =========================================================================
    // 2. DETECCIÓN RÁPIDA DE PARTES FALTANTES (TABLAS, COLUMNAS Y RUTINAS)
    // =========================================================================
    $tablasRequeridas = [
        'cat_municipios', 'cat_parroquias', 'cat_dependencias', 'cat_cargos_nominales',
        'cat_bancos', 'cat_tipos_personal', 'cat_cargos_areas', 'cat_patologias',
        'cat_roles_sistema', 'cat_tipos_documentos', 'personal', 'personal_cargos',
        'personal_contactos', 'personal_datos_adicionales', 'personal_salud_patologias',
        'documentos', 'documentos_enlaces', 'asistencias', 'horarios_laborales',
        'cronograma_eventos', 'cronograma_participantes', 'organigrama_jerarquia',
        'usuarios_sistema', 'rebac_recursos', 'rebac_tipos_relacion',
        'rebac_politicas_permisos', 'rebac_tuplas_grafo', 'sesiones_usuarios',
        'historial_auditoria'
    ];

    $stmtCols = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?");
    $stmtCols->execute([$dbname]);
    $tablasExistentes = [];
    $colsExistentes = [];
    foreach ($stmtCols->fetchAll() as $row) {
        $tablasExistentes[$row['TABLE_NAME']] = true;
        $colsExistentes[$row['TABLE_NAME'] . '.' . $row['COLUMN_NAME']] = true;
    }

    $columnasClaveRequeridas = [
        'personal_datos_adicionales.banco_id',
        'personal_datos_adicionales.dependencia_id',
        'personal_datos_adicionales.cargo_nominal_id',
        'personal_datos_adicionales.parroquia_id',
        'asistencias.cierre_automatico',
        'cronograma_participantes.id',
        'cronograma_participantes.rol_participacion',
        'organigrama_jerarquia.id',
        'organigrama_jerarquia.fecha_asignacion',
        'organigrama_jerarquia.estado_jerarquia',
        'usuarios_sistema.rol_id',
        'usuarios_sistema.ultimo_login'
    ];

    $faltanPartes = false;
    foreach ($tablasRequeridas as $tReq) {
        if (!isset($tablasExistentes[$tReq])) { $faltanPartes = true; break; }
    }
    if (!$faltanPartes) {
        foreach ($columnasClaveRequeridas as $cReq) {
            if (!isset($colsExistentes[$cReq])) { $faltanPartes = true; break; }
        }
    }
    if (!$faltanPartes) {
        $stmtProc = $pdo->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = 'sp_cierre_diario_asistencias'");
        $stmtProc->execute([$dbname]);
        if ($stmtProc->fetchColumn() == 0) { $faltanPartes = true; }
    }

    // =========================================================================
    // 3. GENERACIÓN Y PARCHEO AUTOMÁTICO SI FALTA CUALQUIER PARTE
    // =========================================================================
    if ($faltanPartes) {

        // A. CATÁLOGOS GEOGRÁFICOS, MINISTERIALES, BANCARIOS Y MÉDICOS (3FN)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_municipios` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(80) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_parroquias` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `municipio_id` INT NOT NULL,
            `nombre` VARCHAR(80) NOT NULL,
            UNIQUE KEY `uk_municipio_parroquia` (`municipio_id`, `nombre`),
            CONSTRAINT `fk_parroquia_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `cat_municipios` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_dependencias` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `codigo` VARCHAR(50) NOT NULL UNIQUE,
            `nombre` VARCHAR(120) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_cargos_nominales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `codigo` VARCHAR(50) NOT NULL UNIQUE,
            `denominacion` VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_bancos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `codigo` CHAR(4) NOT NULL UNIQUE,
            `nombre` VARCHAR(60) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_tipos_personal` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `nombre` VARCHAR(50) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_cargos_areas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `tipo_personal_id` INT NOT NULL, 
            `nombre` VARCHAR(100) NOT NULL,
            `descripcion` VARCHAR(255) DEFAULT 'Cátedra / Función institucional.',
            FOREIGN KEY (`tipo_personal_id`) REFERENCES `cat_tipos_personal`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_patologias` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tipo` ENUM('Alergia','Enfermedad Crónica','Condición Especial','Otra') NOT NULL,
            `nombre` VARCHAR(100) NOT NULL,
            UNIQUE KEY `uk_tipo_nombre` (`tipo`, `nombre`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_roles_sistema` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `nombre` VARCHAR(50) NOT NULL UNIQUE, 
            `nivel_acceso` INT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_tipos_documentos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `clave` CHAR(3) NOT NULL UNIQUE,
            `nombre` VARCHAR(60) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // B. NÚCLEO DEL PERSONAL Y TABLAS SATÉLITE
        $pdo->exec("CREATE TABLE IF NOT EXISTS `personal` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `cedula` VARCHAR(20) NOT NULL UNIQUE, 
            `nombres` VARCHAR(80) NOT NULL, 
            `apellidos` VARCHAR(80) NOT NULL, 
            `fecha_nacimiento` DATE DEFAULT NULL, 
            `fecha_ingreso` DATE NOT NULL, 
            `estado_laboral` ENUM('Activo', 'Permiso', 'Reposo', 'Inactivo', 'Jubilado') DEFAULT 'Activo', 
            `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `personal_cargos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `personal_id` INT NOT NULL, 
            `cargo_area_id` INT NOT NULL, 
            `fecha_asignacion` DATE NOT NULL, 
            `estado_cargo` ENUM('Vigente', 'Finalizado') DEFAULT 'Vigente', 
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE, 
            FOREIGN KEY (`cargo_area_id`) REFERENCES `cat_cargos_areas`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `personal_contactos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `personal_id` INT NOT NULL, 
            `tipo_contacto` ENUM('Teléfono Personal', 'Teléfono Emergencia', 'Email', 'Dirección', 'Dirección Habitación') NOT NULL, 
            `valor` VARCHAR(255) NOT NULL, 
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `personal_datos_adicionales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `personal_id` INT NOT NULL UNIQUE, 
            `estado_civil` ENUM('Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Concubinato') DEFAULT 'Soltero/a', 
            `cantidad_hijos` INT DEFAULT 0, 
            `nivel_academico` VARCHAR(100) DEFAULT NULL, 
            `tipo_sangre` VARCHAR(5) DEFAULT NULL, 
            `talla_camisa` VARCHAR(10) DEFAULT NULL, 
            `talla_pantalon` VARCHAR(10) DEFAULT NULL, 
            `talla_calzado` VARCHAR(10) DEFAULT NULL, 
            `banco_id` INT DEFAULT NULL,
            `cuenta_bancaria` VARCHAR(30) DEFAULT NULL, 
            `etnia` VARCHAR(50) DEFAULT NULL,
            `fecha_ingreso_mppe` DATE DEFAULT NULL,
            `dependencia_id` INT DEFAULT NULL,
            `turno` ENUM('Mañana', 'Tarde', 'Completo', 'Integral', 'Nocturno') DEFAULT 'Mañana',
            `cargo_nominal_id` INT DEFAULT NULL,
            `horas_laborales` INT DEFAULT 0,
            `parroquia_id` INT DEFAULT NULL,
            `centro_votacion` VARCHAR(150) DEFAULT NULL,
            /* Columnas de compatibilidad para los formularios actuales */
            `alergias_medicas` TEXT DEFAULT NULL, 
            `condiciones_especiales` TEXT DEFAULT NULL, 
            `banco_nomina` VARCHAR(50) DEFAULT NULL, 
            `dependencia` VARCHAR(100) DEFAULT NULL,
            `codigo_dependencia` VARCHAR(50) DEFAULT NULL,
            `cargo_nominal` VARCHAR(100) DEFAULT NULL,
            `codigo_cargo` VARCHAR(50) DEFAULT NULL,
            `correo_electronico` VARCHAR(120) DEFAULT NULL,
            `direccion_habitacion` TEXT DEFAULT NULL,
            `municipio` VARCHAR(80) DEFAULT NULL,
            `parroquia` VARCHAR(80) DEFAULT NULL,
            `patologias` TEXT DEFAULT NULL,
            `medicamentos_permanentes` TEXT DEFAULT NULL,
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pda_banco` FOREIGN KEY (`banco_id`) REFERENCES `cat_bancos`(`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_pda_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `cat_dependencias`(`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_pda_cargo_nom` FOREIGN KEY (`cargo_nominal_id`) REFERENCES `cat_cargos_nominales`(`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_pda_parroquia` FOREIGN KEY (`parroquia_id`) REFERENCES `cat_parroquias`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Parcheo seguro si personal_datos_adicionales ya existía en versión antigua
        $pdo->exec("ALTER TABLE `personal_datos_adicionales` ADD COLUMN IF NOT EXISTS `banco_id` INT DEFAULT NULL AFTER `talla_calzado`");
        $pdo->exec("ALTER TABLE `personal_datos_adicionales` ADD COLUMN IF NOT EXISTS `dependencia_id` INT DEFAULT NULL AFTER `fecha_ingreso_mppe`");
        $pdo->exec("ALTER TABLE `personal_datos_adicionales` ADD COLUMN IF NOT EXISTS `cargo_nominal_id` INT DEFAULT NULL AFTER `turno`");
        $pdo->exec("ALTER TABLE `personal_datos_adicionales` ADD COLUMN IF NOT EXISTS `parroquia_id` INT DEFAULT NULL AFTER `horas_laborales`");

        $agregarFK = function($tabla, $nombreFK, $definicion) use ($pdo, $dbname) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
            $chk->execute([$dbname, $tabla, $nombreFK]);
            if ($chk->fetchColumn() == 0) {
                try { $pdo->exec("ALTER TABLE `$tabla` ADD CONSTRAINT `$nombreFK` $definicion"); } catch (PDOException $e) {}
            }
        };
        $agregarFK('personal_datos_adicionales', 'fk_pda_banco', 'FOREIGN KEY (`banco_id`) REFERENCES `cat_bancos`(`id`) ON DELETE SET NULL');
        $agregarFK('personal_datos_adicionales', 'fk_pda_dependencia', 'FOREIGN KEY (`dependencia_id`) REFERENCES `cat_dependencias`(`id`) ON DELETE SET NULL');
        $agregarFK('personal_datos_adicionales', 'fk_pda_cargo_nom', 'FOREIGN KEY (`cargo_nominal_id`) REFERENCES `cat_cargos_nominales`(`id`) ON DELETE SET NULL');
        $agregarFK('personal_datos_adicionales', 'fk_pda_parroquia', 'FOREIGN KEY (`parroquia_id`) REFERENCES `cat_parroquias`(`id`) ON DELETE SET NULL');

        $pdo->exec("CREATE TABLE IF NOT EXISTS `personal_salud_patologias` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `personal_id` INT NOT NULL,
            `patologia_id` INT NOT NULL,
            `medicamento_permanente` VARCHAR(150) DEFAULT NULL,
            `observaciones` TEXT DEFAULT NULL,
            UNIQUE KEY `uk_personal_patologia` (`personal_id`, `patologia_id`),
            CONSTRAINT `fk_psp_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_psp_patologia` FOREIGN KEY (`patologia_id`) REFERENCES `cat_patologias`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // C. SISTEMA GENERALIZADO DE DOCUMENTOS DIGITALES
        $pdo->exec("CREATE TABLE IF NOT EXISTS `documentos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tipo_documento_id` INT NOT NULL,
            `codigo_archivo` VARCHAR(100) NOT NULL UNIQUE,
            `nombre_original` VARCHAR(200) NOT NULL,
            `ruta_archivo` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `tamano_bytes` BIGINT DEFAULT 0,
            `hash_verificacion` VARCHAR(64) DEFAULT NULL,
            `fecha_subida` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_doc_tipo` FOREIGN KEY (`tipo_documento_id`) REFERENCES `cat_tipos_documentos`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `documentos_enlaces` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `documento_id` INT NOT NULL,
            `tabla_destino` ENUM('personal','asistencias','cronograma_eventos','institucional') NOT NULL,
            `registro_id` INT NOT NULL,
            `notas` VARCHAR(255) DEFAULT NULL,
            KEY `idx_busqueda_enlace` (`tabla_destino`, `registro_id`),
            CONSTRAINT `fk_enlace_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // D. MÓDULOS OPERATIVOS CON IDs SUBROGADOS Y CIERRE DE JORNADA
        $pdo->exec("CREATE TABLE IF NOT EXISTS `asistencias` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `personal_id` INT NOT NULL, 
            `fecha` DATE NOT NULL, 
            `hora_entrada` TIME DEFAULT NULL, 
            `hora_salida` TIME DEFAULT NULL, 
            `estado_asistencia` ENUM('Presente', 'Ausente', 'Retardo', 'Falta Justificada') NOT NULL, 
            `cierre_automatico` TINYINT(1) NOT NULL DEFAULT 0,
            `observaciones` VARCHAR(255) DEFAULT NULL, 
            UNIQUE KEY `asistencia_unica` (`personal_id`, `fecha`), 
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $pdo->exec("ALTER TABLE `asistencias` ADD COLUMN IF NOT EXISTS `cierre_automatico` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado_asistencia`");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `horarios_laborales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `personal_id` INT NOT NULL, 
            `dia_semana` ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL, 
            `hora_inicio` TIME NOT NULL, 
            `hora_fin` TIME NOT NULL, 
            `actividad` VARCHAR(150) NOT NULL, 
            `color_fondo` VARCHAR(50) DEFAULT 'bg-primary',
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cronograma_eventos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `fecha` DATE NOT NULL, 
            `hora_inicio` TIME NOT NULL, 
            `hora_fin` TIME NOT NULL, 
            `titulo` VARCHAR(150) NOT NULL, 
            `descripcion` TEXT DEFAULT NULL, 
            `creador_id` INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cronograma_participantes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `evento_id` INT NOT NULL, 
            `personal_id` INT NOT NULL, 
            `rol_participacion` ENUM('Organizador','Ponente','Asistente','Apoyo') DEFAULT 'Asistente',
            UNIQUE KEY `uk_evento_participante` (`evento_id`, `personal_id`), 
            FOREIGN KEY (`evento_id`) REFERENCES `cronograma_eventos`(`id`) ON DELETE CASCADE, 
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        if (!isset($colsExistentes['cronograma_participantes.id'])) {
            try { $pdo->exec("ALTER TABLE `cronograma_participantes` ADD UNIQUE KEY `uk_evento_participante` (`evento_id`, `personal_id`)"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE `cronograma_participantes` DROP PRIMARY KEY, ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST"); } catch (Exception $e) {}
        }
        $pdo->exec("ALTER TABLE `cronograma_participantes` ADD COLUMN IF NOT EXISTS `rol_participacion` ENUM('Organizador','Ponente','Asistente','Apoyo') DEFAULT 'Asistente'");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `organigrama_jerarquia` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `personal_id` INT NOT NULL,
            `jefe_id` INT DEFAULT NULL,
            `fecha_asignacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `estado_jerarquia` ENUM('Vigente','Reasignado') DEFAULT 'Vigente',
            UNIQUE KEY `uk_personal_nodo` (`personal_id`),
            KEY `jefe_id` (`jefe_id`),
            CONSTRAINT `organigrama_fk_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE,
            CONSTRAINT `organigrama_fk_jefe` FOREIGN KEY (`jefe_id`) REFERENCES `personal`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        if (!isset($colsExistentes['organigrama_jerarquia.id'])) {
            try { $pdo->exec("ALTER TABLE `organigrama_jerarquia` ADD UNIQUE KEY `uk_personal_nodo` (`personal_id`)"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE `organigrama_jerarquia` DROP PRIMARY KEY, ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST"); } catch (Exception $e) {}
        }
        $pdo->exec("ALTER TABLE `organigrama_jerarquia` ADD COLUMN IF NOT EXISTS `fecha_asignacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        $pdo->exec("ALTER TABLE `organigrama_jerarquia` ADD COLUMN IF NOT EXISTS `estado_jerarquia` ENUM('Vigente','Reasignado') DEFAULT 'Vigente'");

        // E. SEGURIDAD HÍBRIDA (RBAC + ReBAC), SESIONES Y AUDITORÍA
        $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios_sistema` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `personal_id` INT NULL, 
            `rol_id` INT NOT NULL DEFAULT 1, 
            `username` VARCHAR(50) NOT NULL UNIQUE, 
            `password_hash` VARCHAR(255) NOT NULL, 
            `estado` ENUM('Activo', 'Bloqueado') DEFAULT 'Activo',
            `ultimo_login` TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE SET NULL, 
            FOREIGN KEY (`rol_id`) REFERENCES `cat_roles_sistema`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $pdo->exec("ALTER TABLE `usuarios_sistema` ADD COLUMN IF NOT EXISTS `rol_id` INT NOT NULL DEFAULT 1 AFTER `personal_id`");
        $pdo->exec("ALTER TABLE `usuarios_sistema` ADD COLUMN IF NOT EXISTS `ultimo_login` TIMESTAMP NULL DEFAULT NULL AFTER `estado`");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `rebac_recursos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre_recurso` VARCHAR(60) NOT NULL UNIQUE,
            `descripcion` VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `rebac_tipos_relacion` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `clave_relacion` VARCHAR(40) NOT NULL UNIQUE,
            `descripcion` VARCHAR(150) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `rebac_politicas_permisos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `relacion_id` INT NOT NULL,
            `recurso_id` INT NOT NULL,
            `puede_crear` TINYINT(1) NOT NULL DEFAULT 0,
            `puede_leer` TINYINT(1) NOT NULL DEFAULT 0,
            `puede_editar` TINYINT(1) NOT NULL DEFAULT 0,
            `puede_eliminar` TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `uk_politica` (`relacion_id`, `recurso_id`),
            FOREIGN KEY (`relacion_id`) REFERENCES `rebac_tipos_relacion`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`recurso_id`) REFERENCES `rebac_recursos`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `rebac_tuplas_grafo` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `usuario_id` INT NOT NULL,
            `relacion_id` INT NOT NULL,
            `recurso_id` INT NOT NULL,
            `objeto_id` INT NOT NULL DEFAULT 0,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_tupla_grafo` (`usuario_id`, `relacion_id`, `recurso_id`, `objeto_id`),
            FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`relacion_id`) REFERENCES `rebac_tipos_relacion`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`recurso_id`) REFERENCES `rebac_recursos`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `sesiones_usuarios` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `usuario_id` INT NOT NULL, 
            `token` VARCHAR(255) NULL, 
            `ip_direccion` VARCHAR(45) NULL, 
            `dispositivo_agente` TEXT NULL, 
            `fecha_inicio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
            `fecha_expiracion` TIMESTAMP NULL, 
            FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `historial_auditoria` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `usuario_id` INT NULL, 
            `tabla_afectada` VARCHAR(50) NOT NULL, 
            `registro_id` INT NULL, 
            `accion` ENUM('CREAR', 'ACTUALIZAR', 'ELIMINAR', 'LOGIN', 'ACCESO_CRITICO', 'EXPORTAR') NOT NULL, 
            `descripcion_detallada` TEXT NOT NULL, 
            `ip_origen` VARCHAR(45) DEFAULT NULL, 
            `fecha_evento` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
            FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // F. DATOS SEMILLA DE CATÁLOGOS Y SEGURIDAD
        if ($pdo->query("SELECT COUNT(*) FROM cat_municipios")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_municipios (id, nombre) VALUES (1, 'Maracaibo'), (2, 'San Francisco')");
            $pdo->exec("INSERT IGNORE INTO cat_parroquias (id, municipio_id, nombre) VALUES (1, 1, 'Chiquinquirá'), (2, 1, 'Olegario Villalobos'), (3, 2, 'San Francisco')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_dependencias")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_dependencias (id, codigo, nombre) VALUES (1, 'DEP-00241', 'Zona Educativa Zulia'), (2, 'DEP-00892', 'Plantel U.E.N Luis Urdaneta')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_cargos_nominales")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_cargos_nominales (id, codigo, denominacion) VALUES (1, 'DOC-IV', 'Docente IV'), (2, 'ADM-II', 'Asistente Administrativo II'), (3, 'OBR-I', 'Obrero de Mantenimiento')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_bancos")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_bancos (id, codigo, nombre) VALUES (1, '0102', 'Banco de Venezuela'), (2, '0105', 'Banco Mercantil'), (3, '0108', 'Banco Provincial')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_patologias")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_patologias (id, tipo, nombre) VALUES (1, 'Alergia', 'Penicilina'), (2, 'Enfermedad Crónica', 'Hipertensión Arterial'), (3, 'Enfermedad Crónica', 'Diabetes Tipo II'), (4, 'Alergia', 'Polvo / Ácaros')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_personal")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_tipos_personal (id, nombre) VALUES (1, 'Docente'), (2, 'Administrativo'), (3, 'Obrero')");
            $pdo->exec("INSERT IGNORE INTO cat_cargos_areas (tipo_personal_id, nombre, descripcion) VALUES 
                (1, 'Biología', 'Cátedra de Ciencias Naturales'), 
                (1, 'Matemáticas', 'Cátedra de Ciencias Exactas'), 
                (2, 'Director/a', 'Dirección e Inspección del Plantel'), 
                (2, 'Secretaria', 'Atención y Control de Expedientes'), 
                (3, 'Mantenimiento General', 'Mantenimiento de Planta Física')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_documentos")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_tipos_documentos (id, clave, nombre) VALUES
                (1, 'CED', 'Cédula de Identidad'), (2, 'FOT', 'Fotografía Tamaño Carnet'),
                (3, 'TIT', 'Título Universitario o Certificado'), (4, 'MED', 'Informe o Certificado Médico'),
                (5, 'SOP', 'Soporte de Justificación / Reposo'), (6, 'MEM', 'Memorándum o Acta Institucional'),
                (7, 'OTR', 'Otro Soporte Digital')");
        }

        // Rol, Usuario 'admin' (admin123) y Grafo ReBAC Completo
        $pdo->exec("INSERT IGNORE INTO cat_roles_sistema (id, nombre, nivel_acceso) VALUES (1, 'SuperAdministrador', 10)");

        if ($pdo->query("SELECT COUNT(*) FROM usuarios_sistema WHERE username = 'admin'")->fetchColumn() == 0) {
            $hash = password_hash('admin123', PASSWORD_BCRYPT);
            $pdo->exec("INSERT INTO usuarios_sistema (id, rol_id, username, password_hash, estado) VALUES (1, 1, 'admin', '$hash', 'Activo')");
        } else {
            $pdo->exec("UPDATE usuarios_sistema SET rol_id = 1, estado = 'Activo' WHERE username = 'admin'");
        }

        $pdo->exec("INSERT IGNORE INTO rebac_recursos (id, nombre_recurso, descripcion) VALUES
            (1, 'expediente_personal', 'Expediente digital y datos del trabajador'),
            (2, 'asistencias', 'Control de asistencias y novedades'),
            (3, 'organigrama', 'Árbol jerárquico y relaciones de mando'),
            (4, 'documentos', 'Archivos y soportes institucionales'),
            (5, 'cronograma', 'Eventos y agenda institucional'),
            (6, 'configuracion', 'Catálogos, apariencia, respaldo e importación'),
            (7, 'horarios', 'Calendario laboral de turnos'),
            (8, 'auditoria', 'Historial y bitácora del sistema')");

        $pdo->exec("INSERT IGNORE INTO rebac_tipos_relacion (id, clave_relacion, descripcion) VALUES
            (1, 'superadministrador', 'Control total sin restricciones de objeto'),
            (2, 'supervisor_directo', 'Jefe jerárquico según el organigrama'),
            (3, 'propietario', 'El propio usuario es dueño del recurso/ficha'),
            (4, 'coordinador_area', 'Gestor del departamento o especialidad'),
            (5, 'lector', 'Permiso únicamente de consulta')");

        $pdo->exec("INSERT INTO rebac_politicas_permisos (relacion_id, recurso_id, puede_crear, puede_leer, puede_editar, puede_eliminar) VALUES
            (1,1,1,1,1,1), (1,2,1,1,1,1), (1,3,1,1,1,1), (1,4,1,1,1,1),
            (1,5,1,1,1,1), (1,6,1,1,1,1), (1,7,1,1,1,1), (1,8,1,1,1,1)
            ON DUPLICATE KEY UPDATE puede_crear=1, puede_leer=1, puede_editar=1, puede_eliminar=1");

        $adminIdBD = $pdo->query("SELECT id FROM usuarios_sistema WHERE username = 'admin' LIMIT 1")->fetchColumn();
        if ($adminIdBD) {
            for ($recId = 1; $recId <= 8; $recId++) {
                $pdo->prepare("INSERT IGNORE INTO rebac_tuplas_grafo (usuario_id, relacion_id, recurso_id, objeto_id) VALUES (?, 1, ?, 0)")
                    ->execute([$adminIdBD, $recId]);
            }
        }

        // G. PROCEDIMIENTO ALMACENADO Y EVENTO DE CIERRE AUTOMÁTICO DE JORNADA
        $chkProc = $pdo->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = 'sp_cierre_diario_asistencias'");
        $chkProc->execute([$dbname]);
        if ($chkProc->fetchColumn() == 0) {
            $pdo->exec("CREATE PROCEDURE `sp_cierre_diario_asistencias`(IN p_fecha DATE)
            BEGIN
                UPDATE `asistencias`
                SET `hora_salida` = '18:00:00',
                    `cierre_automatico` = 1,
                    `observaciones` = TRIM(CONCAT(IFNULL(`observaciones`, ''), ' [Salida cerrada automáticamente]'))
                WHERE `fecha` = p_fecha AND `hora_entrada` IS NOT NULL AND `hora_salida` IS NULL;

                IF DAYOFWEEK(p_fecha) BETWEEN 2 AND 6 THEN
                    INSERT INTO `asistencias` (`personal_id`, `fecha`, `estado_asistencia`, `cierre_automatico`, `observaciones`)
                    SELECT p.id, p_fecha, 'Ausente', 1, '[Inasistencia automática al finalizar el día]'
                    FROM `personal` p
                    WHERE p.estado_laboral = 'Activo'
                      AND p.fecha_ingreso <= p_fecha
                      AND NOT EXISTS (SELECT 1 FROM `asistencias` a WHERE a.personal_id = p.id AND a.fecha = p_fecha);
                END IF;
            END");
        }

        $chkEv = $pdo->prepare("SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ? AND EVENT_NAME = 'ev_cierre_automatico_diario'");
        $chkEv->execute([$dbname]);
        if ($chkEv->fetchColumn() == 0) {
            try { $pdo->exec("SET GLOBAL event_scheduler = ON"); } catch (Exception $e) {}
            $pdo->exec("CREATE EVENT `ev_cierre_automatico_diario`
                ON SCHEDULE EVERY 1 DAY
                STARTS (TIMESTAMP(CURDATE()) + INTERVAL 23 HOUR + INTERVAL 59 MINUTE)
                DO CALL sp_cierre_diario_asistencias(CURDATE())");
        }
    }

} catch (PDOException $e) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; border-radius:8px; font-family:sans-serif;'>
            <h3>⚠️ Error Crítico de Base de Datos:</h3>
            <p>" . $e->getMessage() . "</p>
            <small>Verifique que el servicio de MySQL en XAMPP esté activo.</small>
         </div>");
}

// =========================================================================
// 4. FUNCIONES AUXILIARES GLOBALES Y MOTOR ReBAC
// =========================================================================
function registrarHistorial($pdo, $tabla, $accion_str, $descripcion, $registro_id = null) {
    $usuario_id = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $accion_enum = 'ACTUALIZAR';
    $str = strtoupper($accion_str);
    
    if (strpos($str, 'CREA') !== false) $accion_enum = 'CREAR';
    elseif (strpos($str, 'ELIM') !== false) $accion_enum = 'ELIMINAR';
    elseif (strpos($str, 'LOGIN') !== false) $accion_enum = 'LOGIN';
    elseif (strpos($str, 'CRITI') !== false) $accion_enum = 'ACCESO_CRITICO';
    elseif (strpos($str, 'EXPOR') !== false) $accion_enum = 'EXPORTAR';

    $stmt = $pdo->prepare("INSERT INTO historial_auditoria (usuario_id, tabla_afectada, registro_id, accion, descripcion_detallada, ip_origen) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $tabla, $registro_id, $accion_enum, $descripcion, $ip]);
}

function formatearTelefono($telefono) {
    $tel = trim($telefono);
    if (empty($tel)) return '<span class="text-muted small">No registrado</span>';
    
    $bandera = '📱'; 
    if (strpos($tel, '+58') === 0) $bandera = '🇻🇪'; 
    elseif (strpos($tel, '+57') === 0) $bandera = '🇨🇴'; 
    
    $numeroLimpio = preg_replace('/[^0-9]/', '', $tel);
    return '<a href="https://wa.me/' . $numeroLimpio . '" target="_blank" class="text-decoration-none text-body fw-medium"><span class="me-1 fs-5">' . $bandera . '</span>' . htmlspecialchars($tel) . '</a>';
}

function tienePermisoReBAC($pdo, $usuario_id, $nombre_recurso, $accion = 'leer', $objeto_id = 0) {
    if (empty($usuario_id)) return false;

    $colMap = [
        'crear'    => 'puede_crear',
        'leer'     => 'puede_leer',
        'editar'   => 'puede_editar',
        'eliminar' => 'puede_eliminar'
    ];
    $columna = $colMap[strtolower($accion)] ?? 'puede_leer';

    try {
        // 1. Verificar tupla directa o comodín global (objeto_id = 0) en el Grafo ReBAC
        $sql = "
            SELECT MAX(p.$columna)
            FROM rebac_tuplas_grafo g
            JOIN rebac_recursos r ON g.recurso_id = r.id
            JOIN rebac_politicas_permisos p ON g.relacion_id = p.relacion_id AND g.recurso_id = p.recurso_id
            WHERE g.usuario_id = ?
              AND r.nombre_recurso = ?
              AND (g.objeto_id = ? OR g.objeto_id = 0)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $nombre_recurso, $objeto_id]);
        if ((int)$stmt->fetchColumn() === 1) {
            return true;
        }

        // 2. Evaluación dinámica por grafo jerárquico (Organigrama)
        if ($objeto_id > 0 && in_array($nombre_recurso, ['asistencias', 'expediente_personal'])) {
            $sqlOrg = "
                SELECT p.$columna
                FROM usuarios_sistema u
                JOIN organigrama_jerarquia oj ON oj.jefe_id = u.personal_id
                JOIN rebac_tipos_relacion tr ON tr.clave_relacion = 'supervisor_directo'
                JOIN rebac_recursos rec ON rec.nombre_recurso = ?
                JOIN rebac_politicas_permisos p ON p.relacion_id = tr.id AND p.recurso_id = rec.id
                WHERE u.id = ? AND oj.personal_id = ?
            ";
            $stmtOrg = $pdo->prepare($sqlOrg);
            $stmtOrg->execute([$nombre_recurso, $usuario_id, $objeto_id]);
            if ((int)$stmtOrg->fetchColumn() === 1) {
                return true;
            }
        }
    } catch (PDOException $e) {
        return false;
    }

    return false;
}

function exigirPermisoReBAC($pdo, $nombre_recurso, $accion = 'leer', $objeto_id = 0) {
    $uid = $_SESSION['admin_id'] ?? 0;
    if (!tienePermisoReBAC($pdo, $uid, $nombre_recurso, $accion, $objeto_id)) {
        include '../Includes/header.php';
        include '../Includes/sidebar.php';
        echo '<div class="alert alert-danger shadow-sm p-4 text-center rounded-4 mt-5">
                <i class="bi bi-shield-lock-fill fs-1 d-block mb-2"></i>
                <h4>Acceso Denegado por ReBAC</h4>
                <p class="mb-0">No cuentas con relación autorizada para <strong>' . htmlspecialchars($accion) . '</strong> en el módulo <strong>' . htmlspecialchars($nombre_recurso) . '</strong>.</p>
              </div>';
        include '../Includes/footer.php';
        exit;
    }
}

function obtenerPerfilSeguridad($pdo, $usuario_id) {
    $stmt = $pdo->prepare("
        SELECT u.username, r.nombre as rol_nombre, r.nivel_acceso,
               GROUP_CONCAT(DISTINCT CONCAT(tr.clave_relacion, ' → ', rec.nombre_recurso) SEPARATOR ' | ') as relaciones_rebac
        FROM usuarios_sistema u
        LEFT JOIN cat_roles_sistema r ON u.rol_id = r.id
        LEFT JOIN rebac_tuplas_grafo g ON u.id = g.usuario_id
        LEFT JOIN rebac_tipos_relacion tr ON g.relacion_id = tr.id
        LEFT JOIN rebac_recursos rec ON g.recurso_id = rec.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>