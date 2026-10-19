<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $user_id = $_SESSION['admin_id'] ?? 0;

    if (empty($password) || $user_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM usuarios_sistema WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash_bd = $stmt->fetchColumn();

        if ($hash_bd && password_verify($password, $hash_bd)) {
            registrarHistorial($pdo, 'Base de Datos', 'ACCESO_CRITICO', 'El usuario autorizó acceso a phpMyAdmin.');
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta. Acceso denegado.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión.']);
    }
}
?>