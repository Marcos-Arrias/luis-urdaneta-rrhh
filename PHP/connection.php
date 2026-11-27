<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$host     = '127.0.0.1'; 
$dbname   = 'crud'; 
$username = 'root'; 
$password = '';     

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // =========================================================================
    // 1. DETECCIÓN RÁPIDA DE ESTRUCTURA Y PARTES FALTANTES
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
        'cronograma_eventos.usuario_id',
        'cronograma_participantes.id',
        'cronograma_participantes.rol_participacion',
        'organigrama_jerarquia.id',
        'organigrama_jerarquia.fecha_asignacion',
        'organigrama_jerarquia.estado_jerarquia',
        'usuarios_sistema.rol_id',
        'usuarios_sistema.ultimo_login'
    ];

    $hayColsLegacy = isset($colsExistentes['personal_datos_adicionales.correo_electronico'])
                  || isset($colsExistentes['personal_datos_adicionales.banco_nomina'])
                  || isset($colsExistentes['personal_datos_adicionales.dependencia'])
                  || isset($colsExistentes['personal_datos_adicionales.patologias'])
                  || isset($colsExistentes['cronograma_eventos.creador_id'])
                  || isset($tablasExistentes['personal_archivos_digitales']);

    $faltanPartes = $hayColsLegacy;
    if (!$faltanPartes) {
        foreach ($tablasRequeridas as $tReq) {
            if (!isset($tablasExistentes[$tReq])) { $faltanPartes = true; break; }
        }
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
    // 2. CREACIÓN Y MIGRACIÓN ESTRICTA A 3FN / BCNF
    // =========================================================================
    if ($faltanPartes) {

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_municipios` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(80) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cat_parroquias` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `municipio_id` INT NOT NULL,
            `nombre` VARCHAR(80) NOT NULL,
            UNIQUE KEY `uk_municipio_parroquia` (`municipio_id`, `nombre`),
            CONSTRAINT `fk_parroquia_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `cat_municipios` (`id`) ON DELETE RESTRICT
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

        // TABLA ESTRICTAMENTE EN 3FN (Sin redundancias ni dependencias transitivas)
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
            FOREIGN KEY (`personal_id`) REFERENCES `personal`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pda_banco` FOREIGN KEY (`banco_id`) REFERENCES `cat_bancos`(`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_pda_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `cat_dependencias`(`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_pda_cargo_nom` FOREIGN KEY (`cargo_nominal_id`) REFERENCES `cat_cargos_nominales`(`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_pda_parroquia` FOREIGN KEY (`parroquia_id`) REFERENCES `cat_parroquias`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
        $agregarFK('personal_datos_adicionales', 'fk_pda_banco', 'FOREIGN KEY (`banco_id`) REFERENCES `cat_bancos`(`id`) ON DELETE RESTRICT');
        $agregarFK('personal_datos_adicionales', 'fk_pda_dependencia', 'FOREIGN KEY (`dependencia_id`) REFERENCES `cat_dependencias`(`id`) ON DELETE RESTRICT');
        $agregarFK('personal_datos_adicionales', 'fk_pda_cargo_nom', 'FOREIGN KEY (`cargo_nominal_id`) REFERENCES `cat_cargos_nominales`(`id`) ON DELETE RESTRICT');
        $agregarFK('personal_datos_adicionales', 'fk_pda_parroquia', 'FOREIGN KEY (`parroquia_id`) REFERENCES `cat_parroquias`(`id`) ON DELETE RESTRICT');

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
            CONSTRAINT `fk_doc_tipo` FOREIGN KEY (`tipo_documento_id`) REFERENCES `cat_tipos_documentos`(`id`) ON DELETE RESTRICT
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cronograma_eventos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `fecha` DATE NOT NULL, 
            `hora_inicio` TIME NOT NULL, 
            `hora_fin` TIME NOT NULL, 
            `titulo` VARCHAR(150) NOT NULL, 
            `descripcion` TEXT DEFAULT NULL, 
            `usuario_id` INT NULL,
            CONSTRAINT `fk_cronograma_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Convertir creador_id antiguo en usuario_id con llave foránea real
        if (isset($colsExistentes['cronograma_eventos.creador_id']) && !isset($colsExistentes['cronograma_eventos.usuario_id'])) {
            try { $pdo->exec("ALTER TABLE `cronograma_eventos` CHANGE COLUMN `creador_id` `usuario_id` INT NULL DEFAULT NULL"); } catch (Exception $e) {}
        }
        $pdo->exec("ALTER TABLE `cronograma_eventos` ADD COLUMN IF NOT EXISTS `usuario_id` INT NULL DEFAULT NULL");
        $pdo->exec("UPDATE `cronograma_eventos` SET `usuario_id` = NULL WHERE `usuario_id` IS NOT NULL AND `usuario_id` NOT IN (SELECT `id` FROM `usuarios_sistema`)");
        $agregarFK('cronograma_eventos', 'fk_cronograma_usuario', 'FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_sistema`(`id`) ON DELETE SET NULL');

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

        // DATOS SEMILLA BASE
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
            $pdo->exec("INSERT IGNORE INTO cat_bancos (id, codigo, nombre) VALUES (1, '0102', 'Banco de Venezuela'), (2, '0105', 'Banco Mercantil'), (3, '0108', 'Banco Provincial'), (4, '0134', 'Banesco')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_patologias")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_patologias (id, tipo, nombre) VALUES (1, 'Alergia', 'Penicilina'), (2, 'Enfermedad Crónica', 'Hipertensión Arterial'), (3, 'Enfermedad Crónica', 'Diabetes Tipo II'), (4, 'Alergia', 'Polvo / Ácaros')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_personal")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_tipos_personal (id, nombre) VALUES (1, 'Docente'), (2, 'Administrativo'), (3, 'Obrero')");
            $pdo->exec("INSERT IGNORE INTO cat_cargos_areas (tipo_personal_id, nombre, descripcion) VALUES 
                (1, 'Biología', 'Cátedra de Ciencias Naturales'), (1, 'Matemáticas', 'Cátedra de Ciencias Exactas'), 
                (2, 'Director/a', 'Dirección e Inspección del Plantel'), (2, 'Secretaria', 'Atención y Control de Expedientes'), 
                (3, 'Mantenimiento General', 'Mantenimiento de Planta Física')");
        }
        if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_documentos")->fetchColumn() == 0) {
            $pdo->exec("INSERT IGNORE INTO cat_tipos_documentos (id, clave, nombre) VALUES
                (1, 'CED', 'Cédula de Identidad'), (2, 'FOT', 'Fotografía Tamaño Carnet'),
                (3, 'TIT', 'Título Universitario o Certificado'), (4, 'MED', 'Informe o Certificado Médico'),
                (5, 'SOP', 'Soporte de Justificación / Reposo'), (6, 'MEM', 'Memorándum o Acta Institucional'),
                (7, 'OTR', 'Otro Documento')");
        }

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

        // MIGRACIÓN AUTOMÁTICA SIN PÉRDIDA DE COLUMNAS LEGACY -> TABLAS 3FN
        $tieneColsViejasPda = isset($colsExistentes['personal_datos_adicionales.correo_electronico'])
                           || isset($colsExistentes['personal_datos_adicionales.banco_nomina'])
                           || isset($colsExistentes['personal_datos_adicionales.dependencia'])
                           || isset($colsExistentes['personal_datos_adicionales.patologias']);

        if ($tieneColsViejasPda) {
            $filasViejas = $pdo->query("SELECT * FROM personal_datos_adicionales")->fetchAll();
            foreach ($filasViejas as $fv) {
                $pid = (int)$fv['personal_id'];
                if (!empty(trim($fv['correo_electronico'] ?? ''))) {
                    $chk = $pdo->prepare("SELECT id FROM personal_contactos WHERE personal_id=? AND tipo_contacto='Email'");
                    $chk->execute([$pid]);
                    if (!$chk->fetchColumn()) {
                        $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Email', ?)")->execute([$pid, trim($fv['correo_electronico'])]);
                    }
                }
                if (!empty(trim($fv['direccion_habitacion'] ?? ''))) {
                    $chk = $pdo->prepare("SELECT id FROM personal_contactos WHERE personal_id=? AND tipo_contacto IN ('Dirección Habitación','Dirección')");
                    $chk->execute([$pid]);
                    if (!$chk->fetchColumn()) {
                        $pdo->prepare("INSERT INTO personal_contactos (personal_id, tipo_contacto, valor) VALUES (?, 'Dirección Habitación', ?)")->execute([$pid, trim($fv['direccion_habitacion'])]);
                    }
                }
                $bId = resolverBancoId($pdo, $fv['banco_nomina'] ?? '', $fv['cuenta_bancaria'] ?? '');
                $dId = resolverDependenciaId($pdo, $fv['dependencia'] ?? '', $fv['codigo_dependencia'] ?? '');
                $cId = resolverCargoNominalId($pdo, $fv['cargo_nominal'] ?? '', $fv['codigo_cargo'] ?? '');
                $pId = resolverParroquiaId($pdo, $fv['municipio'] ?? '', $fv['parroquia'] ?? '');
                $pdo->prepare("UPDATE personal_datos_adicionales SET banco_id=COALESCE(banco_id,?), dependencia_id=COALESCE(dependencia_id,?), cargo_nominal_id=COALESCE(cargo_nominal_id,?), parroquia_id=COALESCE(parroquia_id,?) WHERE id=?")
                    ->execute([$bId, $dId, $cId, $pId, $fv['id']]);
                sincronizarPatologiasPersonal($pdo, $pid, $fv['patologias'] ?? '', $fv['alergias_medicas'] ?? '', $fv['condiciones_especiales'] ?? '', $fv['medicamentos_permanentes'] ?? '');
            }
            foreach (['alergias_medicas','condiciones_especiales','banco_nomina','dependencia','codigo_dependencia','cargo_nominal','codigo_cargo','correo_electronico','direccion_habitacion','municipio','parroquia','patologias','medicamentos_permanentes'] as $colDrop) {
                try { $pdo->exec("ALTER TABLE `personal_datos_adicionales` DROP COLUMN IF EXISTS `$colDrop`"); } catch (Exception $e) {}
            }
        }

        // Migrar y eliminar tabla antigua personal_archivos_digitales si existía
        if (isset($tablasExistentes['personal_archivos_digitales'])) {
            $legacyDocs = $pdo->query("SELECT * FROM personal_archivos_digitales")->fetchAll();
            foreach ($legacyDocs as $ld) {
                $chk = $pdo->prepare("SELECT id FROM documentos WHERE codigo_archivo = ?");
                $chk->execute([$ld['codigo_archivo']]);
                $docExistId = $chk->fetchColumn();
                if (!$docExistId) {
                    $stT = $pdo->prepare("SELECT id FROM cat_tipos_documentos WHERE clave = ?");
                    $stT->execute([$ld['tipo_documento_clave']]);
                    $tipoDocId = $stT->fetchColumn() ?: 7;
                    $tam = file_exists($ld['ruta_archivo']) ? filesize($ld['ruta_archivo']) : 0;
                    $hash = file_exists($ld['ruta_archivo']) ? hash_file('sha256', $ld['ruta_archivo']) : null;
                    $mime = $ld['mime_type'] ?: 'application/octet-stream';
                    $pdo->prepare("INSERT INTO documentos (tipo_documento_id, codigo_archivo, nombre_original, ruta_archivo, mime_type, tamano_bytes, hash_verificacion, fecha_subida) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$tipoDocId, $ld['codigo_archivo'], $ld['nombre_original'], $ld['ruta_archivo'], $mime, $tam, $hash, $ld['fecha_subida']]);
                    $docExistId = $pdo->lastInsertId();
                }
                $chkEnl = $pdo->prepare("SELECT id FROM documentos_enlaces WHERE documento_id = ? AND tabla_destino = 'personal' AND registro_id = ?");
                $chkEnl->execute([$docExistId, $ld['personal_id']]);
                if (!$chkEnl->fetchColumn()) {
                    $pdo->prepare("INSERT INTO documentos_enlaces (documento_id, tabla_destino, registro_id, notas) VALUES (?, 'personal', ?, 'Migrado al archivo digital')")
                        ->execute([$docExistId, $ld['personal_id']]);
                }
            }
            try { $pdo->exec("DROP TABLE IF EXISTS `personal_archivos_digitales`"); } catch (Exception $e) {}
        }

        // PROCEDIMIENTO Y EVENTO DE CIERRE DIARIO
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
// 3. HELPERS DE NORMALIZACIÓN 3FN PARA CATÁLOGOS
// =========================================================================
function resolverBancoId($pdo, $nombreBanco, $cuenta = '') {
    $nombre = trim($nombreBanco ?? '');
    $digitos = preg_replace('/[^0-9]/', '', $cuenta ?? '');
    $cod = (strlen($digitos) >= 4) ? substr($digitos, 0, 4) : '';
    if ($nombre === '' && $cod === '') return null;

    if ($cod !== '') {
        $st = $pdo->prepare("SELECT id FROM cat_bancos WHERE codigo = ? LIMIT 1");
        $st->execute([$cod]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    if ($nombre !== '') {
        $st = $pdo->prepare("SELECT id FROM cat_bancos WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
        $st->execute([$nombre]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    $codFinal = $cod !== '' ? $cod : strtoupper(substr(md5($nombre), 0, 4));
    $nomFinal = $nombre !== '' ? $nombre : ('Banco ' . $codFinal);
    $pdo->prepare("INSERT IGNORE INTO cat_bancos (codigo, nombre) VALUES (?, ?)")->execute([$codFinal, $nomFinal]);
    $st = $pdo->prepare("SELECT id FROM cat_bancos WHERE codigo = ? LIMIT 1");
    $st->execute([$codFinal]);
    return (int)$st->fetchColumn() ?: null;
}

function resolverDependenciaId($pdo, $nombreDep, $codigoDep = '') {
    $nombre = trim($nombreDep ?? '');
    $codigo = trim($codigoDep ?? '');
    if ($nombre === '' && $codigo === '') return null;

    if ($codigo !== '') {
        $st = $pdo->prepare("SELECT id FROM cat_dependencias WHERE codigo = ? LIMIT 1");
        $st->execute([$codigo]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    if ($nombre !== '') {
        $st = $pdo->prepare("SELECT id FROM cat_dependencias WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
        $st->execute([$nombre]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    $codFinal = $codigo !== '' ? $codigo : ('DEP-' . strtoupper(substr(md5($nombre), 0, 6)));
    $nomFinal = $nombre !== '' ? $nombre : $codFinal;
    $pdo->prepare("INSERT IGNORE INTO cat_dependencias (codigo, nombre) VALUES (?, ?)")->execute([$codFinal, $nomFinal]);
    $st = $pdo->prepare("SELECT id FROM cat_dependencias WHERE codigo = ? LIMIT 1");
    $st->execute([$codFinal]);
    return (int)$st->fetchColumn() ?: null;
}

function resolverCargoNominalId($pdo, $denominacion, $codigoCargo = '') {
    $nombre = trim($denominacion ?? '');
    $codigo = trim($codigoCargo ?? '');
    if ($nombre === '' && $codigo === '') return null;

    if ($codigo !== '') {
        $st = $pdo->prepare("SELECT id FROM cat_cargos_nominales WHERE codigo = ? LIMIT 1");
        $st->execute([$codigo]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    if ($nombre !== '') {
        $st = $pdo->prepare("SELECT id FROM cat_cargos_nominales WHERE LOWER(denominacion) = LOWER(?) LIMIT 1");
        $st->execute([$nombre]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    $codFinal = $codigo !== '' ? $codigo : ('CN-' . strtoupper(substr(md5($nombre), 0, 6)));
    $nomFinal = $nombre !== '' ? $nombre : $codFinal;
    $pdo->prepare("INSERT IGNORE INTO cat_cargos_nominales (codigo, denominacion) VALUES (?, ?)")->execute([$codFinal, $nomFinal]);
    $st = $pdo->prepare("SELECT id FROM cat_cargos_nominales WHERE codigo = ? LIMIT 1");
    $st->execute([$codFinal]);
    return (int)$st->fetchColumn() ?: null;
}

function resolverParroquiaId($pdo, $municipio, $parroquia) {
    $mun = trim($municipio ?? '');
    $par = trim($parroquia ?? '');
    if ($mun === '' && $par === '') return null;
    if ($mun === '') $mun = 'No Especificado';
    if ($par === '') $par = 'General';

    $stM = $pdo->prepare("SELECT id FROM cat_municipios WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
    $stM->execute([$mun]);
    $munId = $stM->fetchColumn();
    if (!$munId) {
        $pdo->prepare("INSERT INTO cat_municipios (nombre) VALUES (?)")->execute([$mun]);
        $munId = $pdo->lastInsertId();
    }

    $stP = $pdo->prepare("SELECT id FROM cat_parroquias WHERE municipio_id = ? AND LOWER(nombre) = LOWER(?) LIMIT 1");
    $stP->execute([$munId, $par]);
    $parId = $stP->fetchColumn();
    if (!$parId) {
        $pdo->prepare("INSERT INTO cat_parroquias (municipio_id, nombre) VALUES (?, ?)")->execute([$munId, $par]);
        $parId = $pdo->lastInsertId();
    }
    return (int)$parId;
}

function sincronizarPatologiasPersonal($pdo, $personal_id, $patologiasStr, $alergiasStr, $condicionesStr, $medicamentosStr) {
    $pdo->prepare("DELETE FROM personal_salud_patologias WHERE personal_id = ?")->execute([$personal_id]);

    $grupos = [
        'Enfermedad Crónica' => $patologiasStr,
        'Alergia'            => $alergiasStr,
        'Condición Especial' => $condicionesStr
    ];
    $med = trim($medicamentosStr ?? '');
    $medAsignado = false;

    foreach ($grupos as $tipo => $cadena) {
        $items = array_filter(array_map('trim', preg_split('/[,;]+/', $cadena ?? '')));
        foreach ($items as $item) {
            if (in_array(mb_strtolower($item), ['ninguna', 'ninguno', 'no', 'n/a', '-', 's/r'])) continue;
            $stPat = $pdo->prepare("SELECT id FROM cat_patologias WHERE tipo = ? AND LOWER(nombre) = LOWER(?) LIMIT 1");
            $stPat->execute([$tipo, $item]);
            $patId = $stPat->fetchColumn();
            if (!$patId) {
                $pdo->prepare("INSERT INTO cat_patologias (tipo, nombre) VALUES (?, ?)")->execute([$tipo, $item]);
                $patId = $pdo->lastInsertId();
            }
            $medVal = (!$medAsignado && $med !== '' && !in_array(mb_strtolower($med), ['ninguno', 'no', 'n/a', '-'])) ? $med : null;
            if ($medVal !== null) $medAsignado = true;
            $pdo->prepare("INSERT IGNORE INTO personal_salud_patologias (personal_id, patologia_id, medicamento_permanente) VALUES (?, ?, ?)")
                ->execute([$personal_id, $patId, $medVal]);
        }
    }

    if (!$medAsignado && $med !== '' && !in_array(mb_strtolower($med), ['ninguno', 'ninguna', 'no', 'n/a', '-'])) {
        $stOtro = $pdo->prepare("SELECT id FROM cat_patologias WHERE tipo = 'Otra' AND nombre = 'Tratamiento Médico Permanente' LIMIT 1");
        $stOtro->execute();
        $otroId = $stOtro->fetchColumn();
        if (!$otroId) {
            $pdo->prepare("INSERT INTO cat_patologias (tipo, nombre) VALUES ('Otra', 'Tratamiento Médico Permanente')")->execute();
            $otroId = $pdo->lastInsertId();
        }
        $pdo->prepare("INSERT IGNORE INTO personal_salud_patologias (personal_id, patologia_id, medicamento_permanente) VALUES (?, ?, ?)")
            ->execute([$personal_id, $otroId, $med]);
    }
}

// =========================================================================
// 4. FUNCIONES GLOBALES, MINIATURAS Y CONTROL DE ACCESO
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
    return '<span class="text-body fw-medium"><i class="bi bi-telephone-fill text-success me-2"></i>' . htmlspecialchars($tel) . '</span>';
}

function obtenerRutaMiniatura($rutaOriginal) {
    if (empty($rutaOriginal) || !file_exists($rutaOriginal)) return $rutaOriginal;
    $ext = strtolower(pathinfo($rutaOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) return $rutaOriginal;
    if (!extension_loaded('gd')) return $rutaOriginal;

    $dirThumbs = dirname($rutaOriginal) . '/thumbs';
    if (!file_exists($dirThumbs)) @mkdir($dirThumbs, 0777, true);

    $base = pathinfo($rutaOriginal, PATHINFO_FILENAME);
    $rutaThumb = $dirThumbs . '/thumb_' . $base . '.jpg';
    if (file_exists($rutaThumb)) return $rutaThumb;

    $info = @getimagesize($rutaOriginal);
    if (!$info) return $rutaOriginal;
    [$w, $h, $tipo] = $info;
    if ($w <= 0 || $h <= 0) return $rutaOriginal;

    $src = null;
    if ($tipo === IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($rutaOriginal);
    elseif ($tipo === IMAGETYPE_PNG) $src = @imagecreatefrompng($rutaOriginal);
    elseif ($tipo === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($rutaOriginal);
    elseif ($tipo === IMAGETYPE_GIF) $src = @imagecreatefromgif($rutaOriginal);
    if (!$src) return $rutaOriginal;

    if ($tipo === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($rutaOriginal);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $src = imagerotate($src, 180, 0); break;
                case 6: $src = imagerotate($src, -90, 0); [$w, $h] = [$h, $w]; break;
                case 8: $src = imagerotate($src, 90, 0); [$w, $h] = [$h, $w]; break;
            }
        }
    }

    $maxDim = 150;
    $ratio = min($maxDim / $w, $maxDim / $h, 1);
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));

    $dst = imagecreatetruecolor($nw, $nh);
    $blanco = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $blanco);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    @imagejpeg($dst, $rutaThumb, 72);
    imagedestroy($src);
    imagedestroy($dst);

    return file_exists($rutaThumb) ? $rutaThumb : $rutaOriginal;
}

function tienePermisoReBAC($pdo, $usuario_id, $nombre_recurso, $accion = 'leer', $objeto_id = 0) {
    if (empty($usuario_id)) return false;

    $colMap = ['crear' => 'puede_crear', 'leer' => 'puede_leer', 'editar' => 'puede_editar', 'eliminar' => 'puede_eliminar'];
    $columna = $colMap[strtolower($accion)] ?? 'puede_leer';

    try {
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
        if ((int)$stmt->fetchColumn() === 1) return true;

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
            if ((int)$stmtOrg->fetchColumn() === 1) return true;
        }
    } catch (PDOException $e) {
        return false;
    }

    return false;
}

function exigirPermisoReBAC($pdo, $nombre_recurso, $accion = 'leer', $objeto_id = 0) {
    $uid = $_SESSION['admin_id'] ?? 0;
    if (!tienePermisoReBAC($pdo, $uid, $nombre_recurso, $accion, $objeto_id)) {
        http_response_code(403);
        include '../Includes/header.php';
        include '../Includes/sidebar.php';
        echo '<div class="alert alert-danger shadow-sm p-4 text-center rounded-4 mt-5">
                <i class="bi bi-shield-lock-fill fs-1 d-block mb-2"></i>
                <h4>Acceso Restringido</h4>
                <p class="mb-0">Tu cuenta no tiene permisos suficientes para ingresar a esta sección.</p>
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