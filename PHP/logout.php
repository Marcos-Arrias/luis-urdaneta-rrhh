<?php
session_start();
require_once 'connection.php'; // Incluimos para poder registrar historial

if(isset($_SESSION['admin_id'])) {
    registrarHistorial($pdo, 'Autenticación', 'LOGIN', 'Cierre de sesión manual (Logout).');
    
    // Opcional: Eliminar token de sesiones_usuarios aquí
    $pdo->prepare("DELETE FROM sesiones_usuarios WHERE token = ?")->execute([$_SESSION['session_token'] ?? '']);
}

session_destroy();
header("Location: ../index.php");
exit;
?>