<?php
session_start();
require_once 'connection.php';
header('Content-Type: application/json; charset=utf-8');

$accion = $_REQUEST['accion'] ?? '';
$esPC = esComputadoraPrincipal();

function describirAgenteCorto($ua) {
    if (stripos($ua, 'Android') !== false) return 'Smartphone Android';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iPhone / iPad';
    if (stripos($ua, 'Mobile') !== false) return 'Dispositivo Móvil';
    if (stripos($ua, 'Windows') !== false) return 'Computadora Windows';
    return 'Otro Dispositivo';
}

// 1. LATIDO DE SESIÓN ACTIVA
if ($accion === 'latido_y_pendientes') {
    if (!isset($_SESSION['admin_id'], $_SESSION['session_token'])) {
        echo json_encode(['sesion_valida' => false]);
        exit;
    }

    $uid = $_SESSION['admin_id'];
    $tok = $_SESSION['session_token'];

    $stmtCheck = $pdo->prepare("SELECT id FROM sesiones_usuarios WHERE usuario_id = ? AND token = ?");
    $stmtCheck->execute([$uid, $tok]);
    if (!$stmtCheck->fetch()) {
        session_destroy();
        echo json_encode(['sesion_valida' => false, 'desconectado_por_pc' => !$esPC]);
        exit;
    }

    // Renovar latido activo
    $pdo->prepare("UPDATE sesiones_usuarios SET fecha_expiracion = DATE_ADD(NOW(), INTERVAL 15 SECOND) WHERE usuario_id = ? AND token = ?")
        ->execute([$uid, $tok]);

    // SOLO LA COMPUTADORA PRINCIPAL RECIBE SOLICITUDES DE AUTORIZACIÓN
    $pendiente = null;
    if ($esPC) {
        $stmtPend = $pdo->prepare("
            SELECT id, token_solicitud, ip_solicitante, dispositivo_solicitante, fecha_solicitud
            FROM solicitudes_modo_portable
            WHERE usuario_id = ? AND estado = 'pendiente' AND fecha_expiracion > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmtPend->execute([$uid]);
        $pendiente = $stmtPend->fetch();
        if ($pendiente) {
            $pendiente['dispositivo_corto'] = describirAgenteCorto($pendiente['dispositivo_solicitante'] ?? '');
        }
    }

    echo json_encode(['sesion_valida' => true, 'es_pc' => $esPC, 'pendiente' => $pendiente ?: null]);
    exit;
}

// 2. RESPUESTA DE LA COMPUTADORA (ADMITIR O RECHAZAR AL TELÉFONO)
elseif ($accion === 'responder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$esPC || !isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false]);
        exit;
    }

    $uid = $_SESSION['admin_id'];
    $solId = intval($_POST['solicitud_id'] ?? 0);
    $decision = ($_POST['decision'] ?? '') === 'aprobado' ? 'aprobado' : 'rechazado';

    $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = ? WHERE id = ? AND usuario_id = ? AND estado = 'pendiente'")
        ->execute([$decision, $solId, $uid]);

    if ($decision === 'aprobado') {
        registrarHistorial($pdo, 'Autenticación', 'ACCESO_CRITICO', 'La computadora principal admitió el Modo Portable al teléfono.');
        
        // Pasar la PC a modo Monitor de Control (sin perder el poder de desconectar al teléfono)
        $_SESSION['pc_en_modo_portable'] = true;
        $_SESSION['pc_monitor_user_id'] = $uid;
        $_SESSION['pc_monitor_username'] = $_SESSION['admin_user'] ?? 'admin';
        $_SESSION['pc_monitor_rol_id'] = $_SESSION['rol_id'] ?? 1;
        $_SESSION['pc_portable_sol_id'] = $solId;

        unset($_SESSION['admin_id'], $_SESSION['session_token']);
        $pdo->prepare("DELETE FROM sesiones_usuarios WHERE usuario_id = ? AND es_pc_maestra = 1")->execute([$uid]);

        echo json_encode(['success' => true, 'decision' => 'aprobado']);
    } else {
        registrarHistorial($pdo, 'Autenticación', 'ACCESO_CRITICO', 'La computadora principal rechazó la solicitud de Modo Portable.');
        echo json_encode(['success' => true, 'decision' => 'rechazado']);
    }
    exit;
}

// 3. ESTADO DEL MONITOR EN LA COMPUTADORA (MIENTRAS EL TELÉFONO ESTÁ CONECTADO)
elseif ($accion === 'estado_monitor_pc') {
    if (!$esPC || empty($_SESSION['pc_en_modo_portable']) || empty($_SESSION['pc_monitor_user_id'])) {
        echo json_encode(['activo' => false]);
        exit;
    }

    $uid = $_SESSION['pc_monitor_user_id'];
    $stmtTel = $pdo->prepare("SELECT ip_direccion, dispositivo_agente, fecha_inicio FROM sesiones_usuarios WHERE usuario_id = ? AND es_pc_maestra = 0 AND fecha_expiracion >= NOW() ORDER BY id DESC LIMIT 1");
    $stmtTel->execute([$uid]);
    $tel = $stmtTel->fetch();

    echo json_encode([
        'activo' => true,
        'telefono_conectado' => (bool)$tel,
        'ip' => $tel['ip_direccion'] ?? 'Conectado',
        'dispositivo' => $tel ? describirAgenteCorto($tel['dispositivo_agente'] ?? '') : 'Desconectado',
        'inicio' => $tel ? date('h:i:s A', strtotime($tel['fecha_inicio'])) : ''
    ]);
    exit;
}

// 4. DESCONECTAR TELÉFONO DESDE LA COMPUTADORA Y RETOMAR CONTROL INMEDIATO
elseif ($accion === 'desconectar_telefono' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$esPC || empty($_SESSION['pc_monitor_user_id'])) {
        echo json_encode(['success' => false]);
        exit;
    }

    $uid = $_SESSION['pc_monitor_user_id'];

    // Eliminar sesiones del teléfono y marcar solicitudes
    $pdo->prepare("DELETE FROM sesiones_usuarios WHERE usuario_id = ?")->execute([$uid]);
    $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = 'completado' WHERE usuario_id = ? AND estado IN ('pendiente','aprobado')")->execute([$uid]);

    // Restaurar sesión inmediatamente en la computadora sin pedir clave
    $_SESSION['admin_id'] = $uid;
    $_SESSION['admin_user'] = $_SESSION['pc_monitor_username'] ?? 'admin';
    $_SESSION['rol_id'] = $_SESSION['pc_monitor_rol_id'] ?? 1;
    unset($_SESSION['pc_en_modo_portable'], $_SESSION['pc_monitor_user_id'], $_SESSION['pc_monitor_username'], $_SESSION['pc_monitor_rol_id'], $_SESSION['pc_portable_sol_id']);

    $nuevoToken = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agente = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $pdo->prepare("INSERT INTO sesiones_usuarios (usuario_id, token, ip_direccion, dispositivo_agente, es_pc_maestra, fecha_expiracion) VALUES (?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL 20 SECOND))")
        ->execute([$uid, $nuevoToken, $ip, $agente]);
    $_SESSION['session_token'] = $nuevoToken;

    registrarHistorial($pdo, 'Autenticación', 'ACCESO_CRITICO', 'Computadora principal desconectó al smartphone y retomó el control.');
    echo json_encode(['success' => true]);
    exit;
}

// 5. CONSULTA DE ESPERA DESDE EL SMARTPHONE
elseif ($accion === 'consultar_espera') {
    $tokEspera = $_SESSION['portable_espera_token'] ?? '';
    if (empty($tokEspera)) {
        echo json_encode(['estado' => 'invalido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT s.*, u.username, u.rol_id FROM solicitudes_modo_portable s JOIN usuarios_sistema u ON s.usuario_id = u.id WHERE s.token_solicitud = ?");
    $stmt->execute([$tokEspera]);
    $sol = $stmt->fetch();

    if (!$sol) {
        echo json_encode(['estado' => 'invalido']);
        exit;
    }

    if ($sol['estado'] === 'aprobado') {
        $_SESSION['admin_id'] = $sol['usuario_id'];
        $_SESSION['admin_user'] = $sol['username'];
        $_SESSION['rol_id'] = $sol['rol_id'];
        unset($_SESSION['portable_espera_token']);

        $nuevoToken = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $agente = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $pdo->prepare("DELETE FROM sesiones_usuarios WHERE usuario_id = ?")->execute([$sol['usuario_id']]);
        $pdo->prepare("INSERT INTO sesiones_usuarios (usuario_id, token, ip_direccion, dispositivo_agente, es_pc_maestra, fecha_expiracion) VALUES (?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL 15 SECOND))")
            ->execute([$sol['usuario_id'], $nuevoToken, $ip, $agente]);
        $_SESSION['session_token'] = $nuevoToken;

        $pdo->prepare("UPDATE usuarios_sistema SET ultimo_login = NOW() WHERE id = ?")->execute([$sol['usuario_id']]);
        $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = 'completado' WHERE id = ?")->execute([$sol['id']]);
        registrarHistorial($pdo, 'Autenticación', 'LOGIN', 'Smartphone autorizado en Modo Portable.');

        echo json_encode(['estado' => 'aprobado']);
        exit;
    } elseif ($sol['estado'] === 'rechazado') {
        unset($_SESSION['portable_espera_token']);
        echo json_encode(['estado' => 'rechazado']);
        exit;
    } elseif (strtotime($sol['fecha_expiracion']) < time()) {
        $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = 'rechazado' WHERE id = ?")->execute([$sol['id']]);
        unset($_SESSION['portable_espera_token']);
        echo json_encode(['estado' => 'expirado']);
        exit;
    }

    echo json_encode(['estado' => 'pendiente']);
    exit;
}

// 6. CANCELAR ESPERA
elseif ($accion === 'cancelar_espera') {
    $tokEspera = $_SESSION['portable_espera_token'] ?? '';
    if (!empty($tokEspera)) {
        $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = 'rechazado' WHERE token_solicitud = ? AND estado = 'pendiente'")
            ->execute([$tokEspera]);
        unset($_SESSION['portable_espera_token']);
    }
    echo json_encode(['success' => true]);
    exit;
}
?>