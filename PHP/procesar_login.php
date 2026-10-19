<?php
session_start();
require_once 'connection.php';

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

// Apuntando a la nueva tabla y nuevos nombres de columnas
$stmt = $pdo->prepare("SELECT id, username, password_hash, rol_id, estado FROM usuarios_sistema WHERE username = ?");
$stmt->execute([$usuario]);
$user = $stmt->fetch();

// Verificamos password y que el usuario no esté bloqueado
if ($user && password_verify($password, $user['password_hash']) && $user['estado'] === 'Activo') {
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_user'] = $user['username'];
    $_SESSION['rol_id'] = $user['rol_id'];
    
    // Registrar la sesión
    $token = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'];
    $agente = $_SERVER['HTTP_USER_AGENT'];
    
    $pdo->prepare("INSERT INTO sesiones_usuarios (usuario_id, token, ip_direccion, dispositivo_agente) VALUES (?, ?, ?, ?)")->execute([$user['id'], $token, $ip, $agente]);
    $_SESSION['session_token'] = $token;

    // Registrar en auditoría
    registrarHistorial($pdo, 'Autenticación', 'LOGIN', 'Inicio de sesión exitoso en el sistema.');

    header("Location: ../Pages/dashboard.php");
    exit;
} else {
    // Redirige al index fusionado
    header("Location: ../index.php?error=1");
    exit;
}
?>