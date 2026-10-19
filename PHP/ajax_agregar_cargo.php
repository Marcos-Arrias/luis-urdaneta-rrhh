<?php
session_start();
require_once 'connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo_personal_id = intval($_POST['tipo_personal_id'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? ''); // NUEVO CAMPO

    if (empty($nombre) || $tipo_personal_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO cat_cargos_areas (tipo_personal_id, nombre, descripcion) VALUES (?, ?, ?)");
        $stmt->execute([$tipo_personal_id, $nombre, $descripcion]);
        $id = $pdo->lastInsertId();
        
        registrarHistorial($pdo, 'Catálogos', 'CREAR', "Se agregó rápido un nuevo cargo: $nombre");
        
        echo json_encode(['success' => true, 'id' => $id, 'nombre' => $nombre]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>