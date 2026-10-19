<?php
// Evitar error de sesión si ya está iniciada
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$host = '127.0.0.1'; 
$dbname = 'crud'; 
$username = 'root'; 
$password = '';     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // =========================================================================
    // CREACIÓN AUTOMÁTICA DE LA NUEVA BASE DE DATOS NORMALIZADA (3FN)
    // =========================================================================
    
    // FASE 1: CATÁLOGOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS cat_tipos_personal (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL UNIQUE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cat_cargos_areas (id INT AUTO_INCREMENT PRIMARY KEY, tipo_personal_id INT NOT NULL, nombre VARCHAR(100) NOT NULL, FOREIGN KEY (tipo_personal_id) REFERENCES cat_tipos_personal(id) ON DELETE RESTRICT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cat_roles_sistema (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL UNIQUE, nivel_acceso INT NOT NULL DEFAULT 1)");

    // FASE 2: TABLA PRINCIPAL
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal (id INT AUTO_INCREMENT PRIMARY KEY, cedula VARCHAR(20) NOT NULL UNIQUE, nombres VARCHAR(80) NOT NULL, apellidos VARCHAR(80) NOT NULL, fecha_nacimiento DATE DEFAULT NULL, fecha_ingreso DATE NOT NULL, estado_laboral ENUM('Activo', 'Permiso', 'Reposo', 'Inactivo', 'Jubilado') DEFAULT 'Activo', fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    // FASE 3: DATOS RELACIONALES DEL PERSONAL
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_cargos (id INT AUTO_INCREMENT PRIMARY KEY, personal_id INT NOT NULL, cargo_area_id INT NOT NULL, fecha_asignacion DATE NOT NULL, estado_cargo ENUM('Vigente', 'Finalizado') DEFAULT 'Vigente', FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE, FOREIGN KEY (cargo_area_id) REFERENCES cat_cargos_areas(id) ON DELETE RESTRICT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_contactos (id INT AUTO_INCREMENT PRIMARY KEY, personal_id INT NOT NULL, tipo_contacto ENUM('Teléfono Personal', 'Teléfono Emergencia', 'Email', 'Dirección') NOT NULL, valor VARCHAR(150) NOT NULL, FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE)");
    
    // FASE 4: EXPEDIENTE Y MISCELÁNEOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_datos_adicionales (id INT AUTO_INCREMENT PRIMARY KEY, personal_id INT NOT NULL UNIQUE, estado_civil ENUM('Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Concubinato') DEFAULT 'Soltero/a', cantidad_hijos INT DEFAULT 0, nivel_academico VARCHAR(100) DEFAULT NULL, tipo_sangre VARCHAR(5) DEFAULT NULL, alergias_medicas TEXT DEFAULT NULL, condiciones_especiales TEXT DEFAULT NULL, talla_camisa VARCHAR(10) DEFAULT NULL, talla_pantalon VARCHAR(10) DEFAULT NULL, talla_calzado VARCHAR(10) DEFAULT NULL, banco_nomina VARCHAR(50) DEFAULT NULL, cuenta_bancaria VARCHAR(30) DEFAULT NULL, FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_documentos (id INT AUTO_INCREMENT PRIMARY KEY, personal_id INT NOT NULL, tipo_documento ENUM('Cédula Copia', 'RIF', 'Título Académico', 'Certificado Médico', 'Soporte Reposo', 'Curriculum', 'Otro') NOT NULL, nombre_archivo VARCHAR(150) NOT NULL, ruta_archivo VARCHAR(255) NOT NULL, fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE)");

    // FASE 5: SEGURIDAD Y USUARIOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios_sistema (id INT AUTO_INCREMENT PRIMARY KEY, personal_id INT NULL, rol_id INT NOT NULL, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, estado ENUM('Activo', 'Bloqueado') DEFAULT 'Activo', FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL, FOREIGN KEY (rol_id) REFERENCES cat_roles_sistema(id) ON DELETE RESTRICT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sesiones_usuarios (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, token VARCHAR(255) NULL, ip_direccion VARCHAR(45) NULL, dispositivo_agente TEXT NULL, fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP, fecha_expiracion TIMESTAMP NULL, FOREIGN KEY (usuario_id) REFERENCES usuarios_sistema(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS historial_auditoria (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NULL, tabla_afectada VARCHAR(50) NOT NULL, registro_id INT NULL, accion ENUM('CREAR', 'ACTUALIZAR', 'ELIMINAR', 'LOGIN', 'ACCESO_CRITICO', 'EXPORTAR') NOT NULL, descripcion_detallada TEXT NOT NULL, ip_origen VARCHAR(45) DEFAULT NULL, fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (usuario_id) REFERENCES usuarios_sistema(id) ON DELETE SET NULL)");

    // =========================================================================
    // DATOS SEMILLA BÁSICOS
    // =========================================================================
    if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_personal")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO cat_tipos_personal (id, nombre) VALUES (1, 'Docente'), (2, 'Administrativo'), (3, 'Obrero')");
        $pdo->exec("INSERT INTO cat_cargos_areas (tipo_personal_id, nombre) VALUES (1, 'Biología'), (1, 'Matemáticas'), (2, 'Director/a'), (2, 'Secretaria'), (3, 'Mantenimiento')");
        $pdo->exec("INSERT INTO cat_roles_sistema (id, nombre, nivel_acceso) VALUES (1, 'SuperAdministrador', 10)");
        
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO usuarios_sistema (rol_id, username, password_hash) VALUES (1, 'admin', '$hash')");
    }

} catch (PDOException $e) {
    die("Error BD Crítico: " . $e->getMessage());
}

// =========================================================================
// NUEVA FUNCIÓN DE HISTORIAL AUDITORÍA
// =========================================================================
function registrarHistorial($pdo, $tabla, $accion_str, $descripcion, $registro_id = null) {
    $usuario_id = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    // Mapear strings viejos a los nuevos ENUM de la base de datos
    $accion_enum = 'ACTUALIZAR';
    $str = strtoupper($accion_str);
    if(strpos($str, 'CREA') !== false) $accion_enum = 'CREAR';
    elseif(strpos($str, 'ELIM') !== false) $accion_enum = 'ELIMINAR';
    elseif(strpos($str, 'LOGIN') !== false) $accion_enum = 'LOGIN';
    elseif(strpos($str, 'CRITI') !== false) $accion_enum = 'ACCESO_CRITICO';
    elseif(strpos($str, 'EXPOR') !== false) $accion_enum = 'EXPORTAR';

    $stmt = $pdo->prepare("INSERT INTO historial_auditoria (usuario_id, tabla_afectada, registro_id, accion, descripcion_detallada, ip_origen) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $tabla, $registro_id, $accion_enum, $descripcion, $ip]);
}

// FUNCIÓN PARA FORMATEAR TELÉFONO (Se mantiene igual)
function formatearTelefono($telefono) {
    $tel = trim($telefono);
    if (empty($tel)) return '<span class="text-muted small">No registrado</span>';
    $bandera = '📱'; 
    if (strpos($tel, '+58') === 0) { $bandera = '🇻🇪'; }
    elseif (strpos($tel, '+57') === 0) { $bandera = '🇨🇴'; }
    $numeroLimpio = preg_replace('/[^0-9]/', '', $tel);
    
    // AHORA USA text-body (Adaptable a modo oscuro):
    return '<a href="https://wa.me/' . $numeroLimpio . '" target="_blank" class="text-decoration-none text-body fw-medium"><span class="me-1 fs-5">' . $bandera . '</span>' . htmlspecialchars($tel) . '</a>';
}
?>