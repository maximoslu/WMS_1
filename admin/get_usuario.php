<?php
/**
 * WMS_1 - Endpoint AJAX para obtener datos de usuario
 */
require_once '../config/setup.php';
require_once '../config/db.php';

header('Content-Type: application/json');

$rol = strtolower($_SESSION['rol'] ?? '');
if (!in_array($rol, ['superadmin', 'administracion'])) {
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'ID no válido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, email, rol, cliente_id FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'Usuario no encontrado']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de base de datos']);
}
