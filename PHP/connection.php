<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$host     = '127.0.0.1'; 
$dbname   = 'crud'; 
$username = 'root'; 
$password = '';     

try {
    // 1. CONEXIÓN AL MOTOR MYSQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 2. CREACIÓN Y SELECCIÓN DE BASE DE DATOS
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // =========================================================================
    // LIMPIEZA DE TABLAS OBSOLETAS
    // =========================================================================
    $pdo->exec("DROP TABLE IF EXISTS organigrama_institucional");
    $pdo->exec("DROP TABLE IF EXISTS personal_documentos");
    $pdo->exec("DROP TABLE IF EXISTS configuracion_organigrama");
    $pdo->exec("DROP TABLE IF EXISTS personal_permisos_reposos");

    // =========================================================================
    // 3. ESTRUCTURA EXACTA A CRUD.SQL (ORDEN ESTRICTO DE CLAVES FORÁNEAS)
    // =========================================================================
    
    // CATÁLOGOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS cat_tipos_personal (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        nombre VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cat_cargos_areas (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        tipo_personal_id INT NOT NULL, 
        nombre VARCHAR(100) NOT NULL,
        descripcion VARCHAR(255) DEFAULT 'Cátedra / Función institucional.',
        FOREIGN KEY (tipo_personal_id) REFERENCES cat_tipos_personal(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cat_roles_sistema (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        nombre VARCHAR(50) NOT NULL UNIQUE, 
        nivel_acceso INT NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // NÚCLEO PERSONAL
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        cedula VARCHAR(20) NOT NULL UNIQUE, 
        nombres VARCHAR(80) NOT NULL, 
        apellidos VARCHAR(80) NOT NULL, 
        fecha_nacimiento DATE DEFAULT NULL, 
        fecha_ingreso DATE NOT NULL, 
        estado_laboral ENUM('Activo', 'Permiso', 'Reposo', 'Inactivo', 'Jubilado') DEFAULT 'Activo', 
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_cargos (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NOT NULL, 
        cargo_area_id INT NOT NULL, 
        fecha_asignacion DATE NOT NULL, 
        estado_cargo ENUM('Vigente', 'Finalizado') DEFAULT 'Vigente', 
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE, 
        FOREIGN KEY (cargo_area_id) REFERENCES cat_cargos_areas(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_contactos (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NOT NULL, 
        tipo_contacto ENUM('Teléfono Personal', 'Teléfono Emergencia', 'Email', 'Dirección') NOT NULL, 
        valor VARCHAR(150) NOT NULL, 
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_datos_adicionales (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NOT NULL UNIQUE, 
        estado_civil ENUM('Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Concubinato') DEFAULT 'Soltero/a', 
        cantidad_hijos INT DEFAULT 0, 
        nivel_academico VARCHAR(100) DEFAULT NULL, 
        tipo_sangre VARCHAR(5) DEFAULT NULL, 
        alergias_medicas TEXT DEFAULT NULL, 
        condiciones_especiales TEXT DEFAULT NULL, 
        talla_camisa VARCHAR(10) DEFAULT NULL, 
        talla_pantalon VARCHAR(10) DEFAULT NULL, 
        talla_calzado VARCHAR(10) DEFAULT NULL, 
        banco_nomina VARCHAR(50) DEFAULT NULL, 
        cuenta_bancaria VARCHAR(30) DEFAULT NULL, 
        etnia VARCHAR(50) DEFAULT NULL,
        fecha_ingreso_mppe DATE DEFAULT NULL,
        dependencia VARCHAR(100) DEFAULT NULL,
        codigo_dependencia VARCHAR(50) DEFAULT NULL,
        turno ENUM('Mañana', 'Tarde', 'Completo', 'Integral', 'Nocturno') DEFAULT 'Mañana',
        cargo_nominal VARCHAR(100) DEFAULT NULL,
        codigo_cargo VARCHAR(50) DEFAULT NULL,
        horas_laborales INT DEFAULT 0,
        correo_electronico VARCHAR(120) DEFAULT NULL,
        direccion_habitacion TEXT DEFAULT NULL,
        municipio VARCHAR(80) DEFAULT NULL,
        parroquia VARCHAR(80) DEFAULT NULL,
        centro_votacion VARCHAR(150) DEFAULT NULL,
        patologias TEXT DEFAULT NULL,
        medicamentos_permanentes TEXT DEFAULT NULL,
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS personal_archivos_digitales (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NOT NULL, 
        codigo_archivo VARCHAR(100) NOT NULL UNIQUE,
        tipo_documento_clave CHAR(3) NOT NULL, 
        numero_secuencia INT NOT NULL DEFAULT 1, 
        nombre_original VARCHAR(150) NOT NULL, 
        ruta_archivo VARCHAR(255) NOT NULL, 
        mime_type VARCHAR(50) NULL, 
        fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // MÓDULOS DEL SISTEMA
    $pdo->exec("CREATE TABLE IF NOT EXISTS asistencias (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NOT NULL, 
        fecha DATE NOT NULL, 
        hora_entrada TIME DEFAULT NULL, 
        hora_salida TIME DEFAULT NULL, 
        estado_asistencia ENUM('Presente', 'Ausente', 'Retardo', 'Falta Justificada') NOT NULL, 
        observaciones VARCHAR(255) DEFAULT NULL, 
        UNIQUE KEY asistencia_unica (personal_id, fecha), 
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS horarios_laborales (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NOT NULL, 
        dia_semana ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL, 
        hora_inicio TIME NOT NULL, 
        hora_fin TIME NOT NULL, 
        actividad VARCHAR(150) NOT NULL, 
        color_fondo VARCHAR(50) DEFAULT 'bg-primary',
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cronograma_eventos (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        fecha DATE NOT NULL, 
        hora_inicio TIME NOT NULL, 
        hora_fin TIME NOT NULL, 
        titulo VARCHAR(150) NOT NULL, 
        descripcion TEXT DEFAULT NULL, 
        creador_id INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cronograma_participantes (
        evento_id INT NOT NULL, 
        personal_id INT NOT NULL, 
        PRIMARY KEY (evento_id, personal_id), 
        FOREIGN KEY (evento_id) REFERENCES cronograma_eventos(id) ON DELETE CASCADE, 
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS organigrama_jerarquia (
        personal_id INT NOT NULL PRIMARY KEY,
        jefe_id INT DEFAULT NULL,
        KEY jefe_id (jefe_id),
        CONSTRAINT organigrama_fk_personal FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE,
        CONSTRAINT organigrama_fk_jefe FOREIGN KEY (jefe_id) REFERENCES personal(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // SEGURIDAD Y ACCESOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios_sistema (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        personal_id INT NULL, 
        rol_id INT NOT NULL, 
        username VARCHAR(50) NOT NULL UNIQUE, 
        password_hash VARCHAR(255) NOT NULL, 
        estado ENUM('Activo', 'Bloqueado') DEFAULT 'Activo', 
        FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL, 
        FOREIGN KEY (rol_id) REFERENCES cat_roles_sistema(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sesiones_usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        usuario_id INT NOT NULL, 
        token VARCHAR(255) NULL, 
        ip_direccion VARCHAR(45) NULL, 
        dispositivo_agente TEXT NULL, 
        fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
        fecha_expiracion TIMESTAMP NULL, 
        FOREIGN KEY (usuario_id) REFERENCES usuarios_sistema(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS historial_auditoria (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        usuario_id INT NULL, 
        tabla_afectada VARCHAR(50) NOT NULL, 
        registro_id INT NULL, 
        accion ENUM('CREAR', 'ACTUALIZAR', 'ELIMINAR', 'LOGIN', 'ACCESO_CRITICO', 'EXPORTAR') NOT NULL, 
        descripcion_detallada TEXT NOT NULL, 
        ip_origen VARCHAR(45) DEFAULT NULL, 
        fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
        FOREIGN KEY (usuario_id) REFERENCES usuarios_sistema(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // DATOS SEMILLA BASE
    if ($pdo->query("SELECT COUNT(*) FROM cat_tipos_personal")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO cat_tipos_personal (id, nombre) VALUES (1, 'Docente'), (2, 'Administrativo'), (3, 'Obrero')");
        $pdo->exec("INSERT INTO cat_cargos_areas (tipo_personal_id, nombre, descripcion) VALUES 
            (1, 'Biología', 'Cátedra de Ciencias Naturales'), 
            (1, 'Matemáticas', 'Cátedra de Ciencias Exactas'), 
            (2, 'Director/a', 'Dirección e Inspección del Plantel'), 
            (2, 'Secretaria', 'Atención y Control de Expedientes'), 
            (3, 'Mantenimiento General', 'Mantenimiento de Planta Física')");
        $pdo->exec("INSERT INTO cat_roles_sistema (id, nombre, nivel_acceso) VALUES (1, 'SuperAdministrador', 10)");
        
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO usuarios_sistema (id, rol_id, username, password_hash, estado) VALUES (1, 1, 'admin', '$hash', 'Activo')");
    }

} catch (PDOException $e) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; border-radius:8px; font-family:sans-serif;'>
            <h3>⚠️ Error Crítico de Base de Datos:</h3>
            <p>" . $e->getMessage() . "</p>
            <small>Verifique que el servicio de MySQL en XAMPP esté activo.</small>
         </div>");
}

// FUNCIONES AUXILIARES GLOBALES
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

// =========================================================================
// MOTOR DE EVALUACIÓN ReBAC (RELATIONSHIP-BASED ACCESS CONTROL)
// =========================================================================
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

        // 2. Evaluación dinámica por grafo jerárquico (Si es jefe directo en el organigrama)
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

// Helper rápido para obtener el Rol y las Relaciones ReBAC del usuario actual
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