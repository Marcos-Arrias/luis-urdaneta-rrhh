<?php
session_start();
require_once 'connection.php';

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT id, username, password_hash, rol_id, estado FROM usuarios_sistema WHERE username = ?");
$stmt->execute([$usuario]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash']) && $user['estado'] === 'Activo') {
    
    // Limpiar sesiones expiradas (navegadores cerrados hace más de 15 seg)
    $pdo->prepare("DELETE FROM sesiones_usuarios WHERE usuario_id = ? AND (fecha_expiracion IS NOT NULL AND fecha_expiracion < NOW())")
        ->execute([$user['id']]);

    $esPC = esComputadoraPrincipal();

    // =========================================================================
    // PRIORIDAD ABSOLUTA PARA LA COMPUTADORA PRINCIPAL
    // =========================================================================
    if ($esPC) {
        // Desconectar cualquier teléfono o sesión previa y tomar el control inmediato
        $pdo->prepare("DELETE FROM sesiones_usuarios WHERE usuario_id = ?")->execute([$user['id']]);
        $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = 'completado' WHERE usuario_id = ? AND estado = 'pendiente'")->execute([$user['id']]);
        unset($_SESSION['pc_en_modo_portable'], $_SESSION['pc_monitor_user_id'], $_SESSION['portable_espera_token']);
    } 
    // =========================================================================
    // SI ES UN SMARTPHONE / DISPOSITIVO REMOTO: VERIFICAR SI LA PC ESTÁ ACTIVA
    // =========================================================================
    else {
        $stmtViva = $pdo->prepare("SELECT id, es_pc_maestra FROM sesiones_usuarios WHERE usuario_id = ? AND (fecha_expiracion >= NOW() OR (fecha_expiracion IS NULL AND fecha_inicio > DATE_SUB(NOW(), INTERVAL 12 HOUR))) LIMIT 1");
        $stmtViva->execute([$user['id']]);
        $sesionViva = $stmtViva->fetch();

        if ($sesionViva) {
            $tokenSolicitud = bin2hex(random_bytes(24));
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $agente = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $pdo->prepare("UPDATE solicitudes_modo_portable SET estado = 'rechazado' WHERE usuario_id = ? AND estado = 'pendiente'")
                ->execute([$user['id']]);

            $pdo->prepare("INSERT INTO solicitudes_modo_portable (usuario_id, token_solicitud, ip_solicitante, dispositivo_solicitante, estado, fecha_expiracion) VALUES (?, ?, ?, ?, 'pendiente', DATE_ADD(NOW(), INTERVAL 90 SECOND))")
                ->execute([$user['id'], $tokenSolicitud, $ip, $agente]);

            $_SESSION['portable_espera_token'] = $tokenSolicitud;
            header("Location: ../index.php?espera_portable=1");
            exit;
        }
    }

    // INICIAR SESIÓN (PC con es_pc_maestra=1 | Teléfono libre con es_pc_maestra=0)
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_user'] = $user['username'];
    $_SESSION['rol_id'] = $user['rol_id'];
    
    $token = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agente = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $pdo->prepare("INSERT INTO sesiones_usuarios (usuario_id, token, ip_direccion, dispositivo_agente, es_pc_maestra, fecha_expiracion) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 20 SECOND))")
        ->execute([$user['id'], $token, $ip, $agente, $esPC ? 1 : 0]);
    $_SESSION['session_token'] = $token;

    $pdo->prepare("UPDATE usuarios_sistema SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);
    registrarHistorial($pdo, 'Autenticación', 'LOGIN', 'Inicio de sesión (' . ($esPC ? 'Computadora Principal' : 'Smartphone') . ').');

    header("Location: ../Pages/dashboard.php");
    exit;
} else {
    header("Location: ../index.php?error=1");
    exit;
}
?>